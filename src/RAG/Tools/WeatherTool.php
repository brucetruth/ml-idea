<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;

final class WeatherTool implements ToolInterface
{
    public function __construct(private readonly string $baseUrl = 'https://api.open-meteo.com/v1/forecast')
    {
    }

    public function name(): string
    {
        return 'weather';
    }

    public function description(): string
    {
        return 'Fetches current weather from Open-Meteo using latitude/longitude.';
    }

    public function invoke(array $input): string
    {
        $lat = isset($input['lat']) ? (float) $input['lat'] : 0.0;
        $lon = isset($input['lon']) ? (float) $input['lon'] : 0.0;

        $url = sprintf(
            '%s?latitude=%s&longitude=%s&current_weather=true',
            rtrim($this->baseUrl, '/'),
            rawurlencode((string) $lat),
            rawurlencode((string) $lon),
        );

        $ctx = stream_context_create(['http' => ['timeout' => 20]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return 'WeatherTool: failed to fetch weather data.';
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $current = isset($payload['current_weather']) && is_array($payload['current_weather'])
            ? $payload['current_weather']
            : [];

        return json_encode([
            'lat' => $lat,
            'lon' => $lon,
            'current_weather' => $current,
        ], JSON_THROW_ON_ERROR);
    }
}
