<?php

namespace App\Services\PlatformApi;

use App\Services\Checks\PublicHttpClient;
use Illuminate\Validation\ValidationException;

class ProductImageFetcher
{
    public function __construct(private readonly PublicHttpClient $http) {}

    public function fetch(string $url, string $alt = '', string $label = ''): array
    {
        $response = $this->http->fetch($url, 'GET', 20, true, [], 5242880);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($response['body']);
        if ($response['status'] !== 200 || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['images' => 'A URL precisa retornar uma imagem JPG, PNG ou WebP válida.']);
        }

        $name = basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'imagem';
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

        return ['filename' => substr($name, 0, 255), 'name' => $label !== '' ? $label : substr($name, 0, 255), 'mime' => $mime, 'contents' => $response['body'], 'alt' => $alt];
    }
}
