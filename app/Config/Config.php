<?php
declare(strict_types=1);

namespace ClimaTrack\Config;

final class Config
{
    public static function timezone(): string
    {
        return getenv('APP_TIMEZONE') ?: 'Europe/Madrid';
    }

    public static function aemetApiKey(): string
    {
        $key = getenv('AEMET_API_KEY') ?: '';
        if ($key === '') {
            throw new \RuntimeException('AEMET_API_KEY no está configurada.');
        }
        return $key;
    }

    public static function cacheDirectory(): string
    {
        return dirname(__DIR__, 2) . '/cache';
    }
}
