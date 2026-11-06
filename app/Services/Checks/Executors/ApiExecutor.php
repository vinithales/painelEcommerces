<?php

namespace App\Services\Checks\Executors;

use App\Models\Store;
use App\Services\Checks\PublicHttpClient;
use App\Support\Redactor;
use Illuminate\Support\Arr;
use RuntimeException;

class ApiExecutor
{
    public function __construct(private readonly PublicHttpClient $http) {}

    public static function requestHeaders(string $lines): array
    {
        $headers = ['accept' => 'application/json'];
        foreach (explode("\n", str_replace("\r\n", "\n", $lines)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (! preg_match('/^([a-z0-9-]+):[ \t]*([^\x00-\x1F\x7F]+)$/iD', $line, $parts)) {
                throw new \InvalidArgumentException('Use um cabeçalho por linha no formato Nome: valor.');
            }
            $name = strtolower($parts[1]);
            if (in_array($name, ['host', 'content-length', 'transfer-encoding', 'connection', 'expect', 'upgrade', 'te', 'trailer', 'forwarded'], true)
                || preg_match('/authorization|cookie|api.?key|secret|token|^proxy-|^x-forwarded-/', $name)) {
                throw new \InvalidArgumentException('Cabeçalho reservado. Para autenticação, use as credenciais da loja.');
            }
            $headers[$name] = trim($parts[2]);
        }

        return $headers;
    }

    public function execute(Store $store, array $configuration): array
    {
        $path = $configuration['path'] ?? '';
        if (! preg_match('#^/(?!/)(?!.*\\\\)[^\r\n]*$#D', $path)) {
            throw new RuntimeException('Informe um caminho de API relativo à loja.');
        }
        $url = $this->http->resolve($store->store_url, $path);
        $headers = self::requestHeaders($configuration['header_lines'] ?? '');
        if ($configuration['authenticate'] ?? false) {
            if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
                throw new RuntimeException('A autenticação da loja exige HTTPS.');
            }
            $credentials = $store->integrationCredentials();
            if ($store->isWooCommerce() && isset($credentials['consumer_key'], $credentials['consumer_secret'])) {
                $headers['Authorization'] = 'Basic '.base64_encode($credentials['consumer_key'].':'.$credentials['consumer_secret']);
            } elseif ($store->isMagento2() && isset($credentials['access_token'])) {
                $headers['Authorization'] = 'Bearer '.$credentials['access_token'];
            } else {
                throw new RuntimeException('Cadastre as credenciais de API da loja.');
            }
        }

        $body = $configuration['payload'] ?? null;
        if ($body !== null && $body !== '') {
            $headers['content-type'] ??= 'application/json';
        }
        $response = $this->http->fetch($url, $configuration['method'], $configuration['timeout'], false, $headers, null, $body);
        $number = null;
        $value = $response['body'];
        if (($field = $configuration['json_path'] ?? '') !== '') {
            $json = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($json) || ! Arr::has($json, $field)) {
                throw new RuntimeException('Campo ausente na resposta JSON: '.$field);
            }
            $value = Arr::get(Redactor::clean($json), $field);
            $number = is_int($value) || is_float($value) ? $value : null;
            $value = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $responseHeaders = [];
        foreach ($response['headers'] as $name => $values) {
            $responseHeaders[strtolower($name)] = implode(', ', (array) $values);
        }

        return [
            'json_number' => $number,
            'status_code' => $response['status'], 'response_time' => $response['duration_ms'],
            'url' => Redactor::clean($url), 'body' => Redactor::clean(mb_scrub($value, 'UTF-8')),
            'headers' => Redactor::clean($responseHeaders),
        ];
    }
}
