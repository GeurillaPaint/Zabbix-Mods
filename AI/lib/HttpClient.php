<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

use RuntimeException;

class HttpClient {

    public static function request(string $method, string $url, array $options = []): array {
        $headers = self::normalizeHeaders($options['headers'] ?? []);
        $timeout = (int) ($options['timeout'] ?? 30);
        $verify_peer = array_key_exists('verify_peer', $options) ? (bool) $options['verify_peer'] : true;
        $body = null;

        if (array_key_exists('json', $options)) {
            $body = json_encode($options['json'], JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        }
        elseif (array_key_exists('body', $options)) {
            $body = (string) $options['body'];
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $header_lines = [];

        foreach ($headers as $name => $value) {
            $header_lines[] = $name.': '.$value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header_lines,
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => min(max(1, $timeout), 15),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $verify_peer,
            CURLOPT_SSL_VERIFYHOST => $verify_peer ? 2 : 0,
            CURLOPT_USERAGENT => 'Zabbix-AI-Module/1.0'
        ]);

        if ($body !== null && strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response_body = curl_exec($ch);

        if ($response_body === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException('HTTP request failed: '.$error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $content_type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $json = null;

        if ($response_body !== '' && (stripos($content_type, 'json') !== false || self::looksLikeJson($response_body))) {
            $decoded = json_decode($response_body, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        return [
            'status' => $status,
            'body' => $response_body,
            'json' => $json,
            'content_type' => $content_type
        ];
    }

    public static function expectSuccess(string $method, string $url, array $options = []): array {
        $response = self::request($method, $url, $options);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(
                'HTTP '.$response['status'].' from '.$url.': '.Util::truncate((string) $response['body'], 1200)
            );
        }

        return $response;
    }

    private static function normalizeHeaders(array $headers): array {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $parts = explode(':', (string) $value, 2);

                if (count($parts) === 2) {
                    $normalized[trim($parts[0])] = ltrim($parts[1]);
                }

                continue;
            }

            $normalized[trim((string) $name)] = (string) $value;
        }

        return $normalized;
    }

    private static function looksLikeJson(string $body): bool {
        $body = ltrim($body);

        return $body !== '' && ($body[0] === '{' || $body[0] === '[');
    }
}
