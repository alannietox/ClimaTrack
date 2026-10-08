<?php
declare(strict_types=1);

namespace ClimaTrack\Http;

use RuntimeException;

final class HttpClient
{
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 5,
        private readonly string $userAgent = 'ClimaTrack/1.0'
    ) {}

    /** @return array{status:int,body:string,headers:array<string,string>} */
    public function get(string $url, array $headers = [], ?int $timeout = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL.');
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $timeout ?? $this->timeout,
            CURLOPT_HTTPHEADER => array_merge(['Accept: */*', 'User-Agent: ' . $this->userAgent], $headers),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Error HTTP al consultar {$url}: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("HTTP {$status} al consultar {$url}.");
        }

        return ['status' => $status, 'body' => (string) $body, 'headers' => $responseHeaders];
    }

    public function getJson(string $url, array $headers = [], ?int $timeout = null): array
    {
        $response = $this->get($url, array_merge(['Accept: application/json'], $headers), $timeout);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException("Respuesta JSON inválida de {$url}.");
        }
        return $data;
    }

    public function getXml(string $url, array $headers = [], ?int $timeout = null): \SimpleXMLElement
    {
        $response = $this->get($url, array_merge(['Accept: application/xml,text/xml'], $headers), $timeout);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response['body']);
        if ($xml === false) {
            throw new RuntimeException("Respuesta XML inválida de {$url}.");
        }
        return $xml;
    }
}
