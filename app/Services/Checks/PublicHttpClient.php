<?php

namespace App\Services\Checks;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PublicHttpClient
{
    public function resolve(string $base, string $url): string
    {
        return (string) UriResolver::resolve(new Uri(rtrim($base, '/').'/'), new Uri($url));
    }

    public function address(string $url): array
    {
        $p = parse_url($url);

        if (! $p || ! in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || ! in_array($p['port'] ?? 443, [80, 443], true)) {
            throw new RuntimeException('URL pública HTTP/HTTPS inválida.');
        }

        $host = trim($p['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(array_column(dns_get_record($host, DNS_A) ?: [], 'ip'), array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));

        if (! $addresses) {
            throw new RuntimeException('DNS indisponível.');
        }
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || str_starts_with(strtolower($ip), '::ffff:')) {
                throw new RuntimeException('Destino privado ou reservado não permitido.');
            }
        }

        return [$host, $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80), $addresses[0]];
    }

    public function fetch(string $url, string $method = 'GET', int $timeout = 10, bool $redirects = false, array $headers = [], ?int $maxBytes = null): array
    {
        $start = microtime(true);
        $max = $maxBytes ?? (int) config('operations.max_response_bytes', 1048576);
        for ($hop = 0; $hop < 6; $hop++) {
            [$host, $port, $ip] = $this->address($url);
            $remaining = $timeout - (microtime(true) - $start);
            if ($remaining <= 0) {
                throw new ConnectionException('Tempo limite excedido.');
            }
            $response = Http::withHeaders($headers)->timeout($remaining)->connectTimeout(min(5, $remaining))
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
                    'on_headers' => function ($r) use ($max) {
                        if ((int) $r->getHeaderLine('Content-Length') > $max) {
                            throw new RuntimeException('Resposta acima do limite.');
                        }
                    },
                    'progress' => function ($total, $downloaded) use ($max) {
                        if ($downloaded > $max) {
                            throw new RuntimeException('Resposta acima do limite.');
                        }
                    },
                ])->send($method, $url);
            if ($redirects && in_array($response->status(), [301, 302, 303, 307, 308], true) && $response->header('Location')) {
                $next = $this->resolve($url, $response->header('Location'));
                if (parse_url($next, PHP_URL_HOST) !== parse_url($url, PHP_URL_HOST)) {
                    $headers = [];
                }
                $url = $next;

                continue;
            }
            $body = $response->body();
            if (strlen($body) > $max) {
                throw new RuntimeException('Resposta acima do limite.');
            }

            return ['status' => $response->status(), 'headers' => $response->headers(), 'body' => $body, 'duration_ms' => (int) round((microtime(true) - $start) * 1000), 'url' => $url];
        }
        throw new RuntimeException('Limite de redirecionamentos excedido.');
    }
}
