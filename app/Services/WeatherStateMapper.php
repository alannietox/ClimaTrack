<?php
declare(strict_types=1);

namespace ClimaTrack\Services;

final class WeatherStateMapper
{
    public static function map(?string $state): ?string
    {
        if (!$state) return null;
        $state = trim($state);
        $mapping = [
            'Soleado'=>'Despejado','Sunny'=>'Despejado','Cielo despejado'=>'Despejado',
            'Parcialmente nublado'=>'Poco nuboso','Partly Cloudy'=>'Poco nuboso','Partly cloudy'=>'Poco nuboso',
            'Nublado'=>'Nuboso','Cloudy'=>'Nuboso','Nubes dispersas'=>'Nuboso','Neblina'=>'Nuboso',
            'Niebla'=>'Nuboso','Mist'=>'Nuboso','Fog'=>'Nuboso','Muy nublado'=>'Cubierto',
            'Cielo cubierto'=>'Cubierto','Overcast'=>'Cubierto','Muy nuboso'=>'Muy nuboso',
            'Lluvia  moderada a intervalos'=>'Intervalos nubosos con lluvia','Patchy rain nearby'=>'Intervalos nubosos con lluvia',
            'Lluvia moderada'=>'Cubierto con lluvia','Lluvias fuertes o moderadas'=>'Cubierto con lluvia',
            'Periodos de lluvia moderada'=>'Cubierto con lluvia','Ligeras precipitaciones'=>'Nuboso con lluvia escasa',
            'Ligeras lluvias'=>'Nuboso con lluvia escasa','Llovizna'=>'Nuboso con lluvia escasa',
            'Light drizzle'=>'Nuboso con lluvia escasa','Fuertes nevadas'=>'Muy nuboso con nieve',
            'Heavy snow'=>'Muy nuboso con nieve','Nieve moderada'=>'Intervalos nubosos con nieve',
            'Nieve moderada a intervalos'=>'Intervalos nubosos con nieve','Ligeras ráfagas de nieve'=>'Intervalos nubosos con nieve',
            'Cielos tormentosos en las aproximaciones'=>'Tormenta','Thundery outbreaks nearby'=>'Tormenta',
        ];
        foreach ($mapping as $key => $value) {
            if (mb_stripos($state, $key) !== false) return $value;
        }
        if (mb_stripos($state, 'lluvia') !== false || mb_stripos($state, 'precipita') !== false) return 'Nuboso con lluvia';
        if (mb_stripos($state, 'nieve') !== false) return 'Muy nuboso con nieve';
        if (mb_stripos($state, 'nublado') !== false || mb_stripos($state, 'nubes') !== false) return 'Nuboso';
        return $state;
    }
}
