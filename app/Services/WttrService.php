<?php
declare(strict_types=1);

namespace ClimaTrack\Services;

use ClimaTrack\Http\HttpClient;

final class WttrService
{
    public function __construct(private readonly HttpClient $http = new HttpClient()) {}

    public function forecast(string $city): array
    {
        $name = str_replace('?', 'n', $city);
        return $this->http->getJson(
            'https://wttr.in/' . urlencode($name) . '?format=j1&lang=es',
            [],
            15
        );
    }
}
