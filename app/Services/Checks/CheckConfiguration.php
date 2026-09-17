<?php

namespace App\Services\Checks;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckConfiguration
{
    public const TYPES = ['HTTP_STATUS', 'CONTENT', 'META_ROBOTS', 'ROBOTS_TXT', 'SITEMAP_XML', 'MAINTENANCE', 'MAGENTO_CRON', 'API_HEALTH', 'RESPONSE_TIME'];

    public const INTERVALS = [300, 600, 900, 1800, 3600, 10800, 21600, 43200, 86400];

    public function validate(string $type, array $configuration): array
    {
        $http = ['url' => 'required|string|max:2048', 'timeout' => 'sometimes|integer|min:1|max:30', 'follow_redirects' => 'sometimes|boolean'];
        $rules = match ($type) {
            'HTTP_STATUS' => $http + ['method' => 'required|in:GET,HEAD', 'expected_status' => 'required|integer|min:100|max:599'],
            'CONTENT' => $http + ['text' => 'required|string|max:1000', 'mode' => 'required|in:present,absent', 'regex' => 'sometimes|boolean'],
            'META_ROBOTS' => $http + ['expected' => ['required', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])], 'require_explicit' => 'sometimes|boolean'],
            'ROBOTS_TXT' => $http + ['allow_disallow_all' => 'sometimes|boolean', 'require_sitemap' => 'sometimes|boolean'],
            'SITEMAP_XML' => $http + ['min_urls' => 'sometimes|integer|min:0|max:1000000'],
            'MAINTENANCE' => $http + ['text' => 'sometimes|string|max:1000'],
            'MAGENTO_CRON' => ['stale_minutes' => 'required|integer|min:1|max:1440', 'running_minutes' => 'required|integer|min:1|max:1440', 'max_pending' => 'required|integer|min:0|max:100000', 'max_missed' => 'required|integer|min:0|max:100000', 'max_errors' => 'required|integer|min:0|max:100000'],
            'API_HEALTH' => [],
            'RESPONSE_TIME' => $http + ['warn_ms' => 'required|integer|min:1|max:30000', 'critical_ms' => 'required|integer|gt:warn_ms|max:30000'],
            default => throw ValidationException::withMessages(['check_type' => 'Tipo não suportado.'])
        };
        if (array_diff(array_keys($configuration), array_keys($rules))) {
            throw ValidationException::withMessages(['configuration' => 'Configuração contém campos não permitidos.']);
        }
        $valid = Validator::make($configuration, $rules)->validate();
        if (($valid['regex'] ?? false) && @preg_match($valid['text'], '') === false) {
            throw ValidationException::withMessages(['configuration.text' => 'Regex inválida. Use delimitadores.']);
        }

        return $valid;
    }

    public static function defaults(string $type): array
    {
        $http = ['url' => '/', 'timeout' => 10, 'follow_redirects' => false];

        return match ($type) {
            'HTTP_STATUS' => $http + ['method' => 'GET', 'expected_status' => 200],
            'CONTENT' => $http + ['text' => '', 'mode' => 'present', 'regex' => false],
            'META_ROBOTS' => $http + ['expected' => 'index,follow', 'require_explicit' => true],
            'ROBOTS_TXT' => array_replace($http, ['url' => '/robots.txt', 'allow_disallow_all' => false, 'require_sitemap' => false]),
            'SITEMAP_XML' => array_replace($http, ['url' => '/sitemap.xml', 'min_urls' => 1]),
            'MAINTENANCE' => $http + ['text' => 'maintenance'],
            'RESPONSE_TIME' => $http + ['warn_ms' => 2000, 'critical_ms' => 5000],
            'MAGENTO_CRON' => ['stale_minutes' => 15, 'running_minutes' => 30, 'max_pending' => 100, 'max_missed' => 0, 'max_errors' => 0], default => []
        };
    }
}
