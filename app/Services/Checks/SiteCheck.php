<?php

namespace App\Services\Checks;

use App\Models\StoreCheck;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;

class SiteCheck implements CheckStrategy
{
    public function __construct(private readonly PublicHttpClient $http) {}

    public function run(StoreCheck $check): CheckResult
    {
        $c = $check->configuration;
        try {
            $r = $this->http->fetch($this->http->resolve($check->store->store_url, $c['url']), $c['method'] ?? 'GET', $c['timeout'] ?? 10, $c['follow_redirects'] ?? false);
        } catch (ConnectionException $e) {
            return new CheckResult($check->severity, 'Alvo inacessível ou timeout.', [], [], get_class($e));
        }
        $details = ['http_status' => $r['status'], 'url' => $r['url']];
        $metrics = ['response_ms' => $r['duration_ms']];
        if ($check->check_type === 'RESPONSE_TIME') {
            $s = $r['status'] >= 400 ? 'CRITICAL' : ($r['duration_ms'] > $c['critical_ms'] ? 'CRITICAL' : ($r['duration_ms'] > $c['warn_ms'] ? 'WARNING' : 'OK'));

            return new CheckResult($s, 'Resposta em '.$r['duration_ms'].' ms.', $details, $metrics);
        }
        $ok = match ($check->check_type) {
            'HTTP_STATUS' => $r['status'] === $c['expected_status'],
            'CONTENT' => $r['status'] === 200 && $this->content($r['body'], $c),
            'META_ROBOTS' => $r['status'] === 200 && $this->robotsMeta($r, $c),
            'ROBOTS_TXT' => $r['status'] === 200 && $this->robotsTxt($r['body'], $c),
            'SITEMAP_XML' => $r['status'] === 200 && $this->sitemap($r['body'], $c),
            'MAINTENANCE' => $r['status'] === 200 && ! $this->maintenance($r, $c), default => false
        };

        return new CheckResult($ok ? 'OK' : $check->severity, $ok ? 'Condição esperada confirmada.' : 'Condição esperada não atendida.', $details, $metrics);
    }

    private function content(string $body, array $c): bool
    {
        $found = str_contains($body, $c['text']);
        if ($c['regex'] ?? false) {
            $old = ini_set('pcre.backtrack_limit', '100000');
            try {
                $found = preg_match($c['text'], $body);
            } finally {
                ini_set('pcre.backtrack_limit', $old);
            }
            if ($found === false) {
                throw new \RuntimeException('Regex excedeu limites.');
            }
        }

        return $c['mode'] === 'present' ? (bool) $found : ! $found;
    }

    private function robotsMeta(array $r, array $c): bool
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($r['body'] ?: '<html></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $values = [];
        foreach ((new DOMXPath($dom))->query('//meta') as $meta) {
            if (in_array(strtolower($meta->getAttribute('name')), ['robots', 'googlebot'])) {
                $values[] = $meta->getAttribute('content');
            }
        }
        foreach ($r['headers'] as $name => $value) {
            if (strtolower($name) === 'x-robots-tag') {
                $values = array_merge($values, (array) $value);
            }
        }
        $tokens = preg_split('/[\s,;:]+/', strtolower(implode(',', $values)), -1, PREG_SPLIT_NO_EMPTY);
        if (in_array('none', $tokens)) {
            $tokens = array_merge($tokens, ['noindex', 'nofollow']);
        }
        if (in_array('all', $tokens)) {
            $tokens = array_merge($tokens, ['index', 'follow']);
        }
        if (! $values && ($c['require_explicit'] ?? true)) {
            return false;
        }
        foreach (explode(',', $c['expected']) as $expected) {
            $opposite = ['index' => 'noindex', 'follow' => 'nofollow', 'noindex' => 'index', 'nofollow' => 'follow'][$expected];
            if (in_array($opposite, $tokens)) {
                return false;
            }
            if (! in_array($expected, $tokens) && (($c['require_explicit'] ?? true) || str_starts_with($expected, 'no'))) {
                return false;
            }
        }

        return true;
    }

    private function robotsTxt(string $body, array $c): bool
    {
        $agents = [];
        $directives = false;
        $valid = false;
        $sitemap = false;
        $blocked = false;
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(user-agent|allow|disallow|sitemap|crawl-delay)\s*:\s*(.*)$/i', $line, $m)) {
                return false;
            }
            $key = strtolower($m[1]);
            $value = trim($m[2]);
            if ($key === 'user-agent') {
                if ($directives) {
                    $agents = [];
                    $directives = false;
                }
                $agents[] = strtolower($value);
                $valid = $valid || $value !== '';
            } elseif ($key === 'sitemap') {
                $sitemap = (bool) filter_var($value, FILTER_VALIDATE_URL) || $sitemap;
            } else {
                $directives = true;
                if ($key === 'disallow' && in_array('*', $agents) && in_array($value, ['/', '/*', '/*$'])) {
                    $blocked = true;
                }
            }
        }

        return $valid && (! $blocked || ($c['allow_disallow_all'] ?? false)) && (! ($c['require_sitemap'] ?? false) || $sitemap);
    }

    private function sitemap(string $body, array $c): bool
    {
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            return false;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($xml === false || ! in_array($xml->getName(), ['urlset', 'sitemapindex'])) {
            return false;
        }

        return count($xml->xpath('//*[local-name()="loc"]') ?: []) >= ($c['min_urls'] ?? 0);
    }

    private function maintenance(array $r, array $c): bool
    {
        return preg_match('/maintenance|manutenção|temporarily unavailable|503 Service/i', $r['body']) === 1
          || (! empty($c['text']) && str_contains(strtolower($r['body']), strtolower($c['text'])))
          || count(array_filter(array_keys($r['headers']), fn ($h) => strtolower($h) === 'retry-after')) > 0;
    }
}
