<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

use RuntimeException;

class ProviderClient {

    public static function chat(array $provider, array $messages, float $temperature = 0.2): string {
        $type = strtolower(trim((string) ($provider['type'] ?? 'openai_compatible')));

        switch ($type) {
            case 'ollama':
                return self::chatOllama($provider, $messages, $temperature);

            case 'openai_compatible':
            default:
                return self::chatOpenAICompatible($provider, $messages, $temperature);
        }
    }

    private static function chatOllama(array $provider, array $messages, float $temperature): string {
        $endpoint = trim((string) ($provider['endpoint'] ?? ''));

        if ($endpoint === '') {
            $endpoint = 'http://localhost:11434/api/chat';
        }

        $payload = [
            'model' => trim((string) ($provider['model'] ?? '')),
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $temperature
            ]
        ];

        if ($payload['model'] === '') {
            throw new RuntimeException('The selected Ollama provider has no model configured.');
        }

        $headers = self::buildHeaders($provider);

        $response = HttpClient::expectSuccess('POST', $endpoint, [
            'headers' => $headers,
            'json' => $payload,
            'timeout' => (int) ($provider['timeout'] ?? 60),
            'verify_peer' => (bool) ($provider['verify_peer'] ?? false)
        ]);

        if (!is_array($response['json'])) {
            throw new RuntimeException('The Ollama response was not valid JSON.');
        }

        $content = trim((string) (($response['json']['message']['content'] ?? '')));

        if ($content === '') {
            throw new RuntimeException('The Ollama response did not contain message.content.');
        }

        return $content;
    }

    private static function chatOpenAICompatible(array $provider, array $messages, float $temperature): string {
        $endpoint = trim((string) ($provider['endpoint'] ?? ''));

        if ($endpoint === '') {
            throw new RuntimeException('The selected provider has no endpoint configured.');
        }

        if (!preg_match('#/chat/completions/?$#', $endpoint)) {
            $endpoint = rtrim($endpoint, '/').'/chat/completions';
        }

        $payload = [
            'model' => trim((string) ($provider['model'] ?? '')),
            'messages' => $messages,
            'temperature' => $temperature
        ];

        if ($payload['model'] === '') {
            throw new RuntimeException('The selected provider has no model configured.');
        }

        $headers = self::buildHeaders($provider, true);

        $response = HttpClient::expectSuccess('POST', $endpoint, [
            'headers' => $headers,
            'json' => $payload,
            'timeout' => (int) ($provider['timeout'] ?? 60),
            'verify_peer' => (bool) ($provider['verify_peer'] ?? true)
        ]);

        if (!is_array($response['json'])) {
            throw new RuntimeException('The provider response was not valid JSON.');
        }

        $message = $response['json']['choices'][0]['message']['content'] ?? null;
        $content = self::normalizeContent($message);

        if ($content === '') {
            throw new RuntimeException('The provider response did not contain choices[0].message.content.');
        }

        return $content;
    }

    private static function buildHeaders(array $provider, bool $default_json_accept = false): array {
        $headers = [];

        if ($default_json_accept) {
            $headers['Accept'] = 'application/json';
        }

        $api_key = Config::resolveSecret($provider['api_key'] ?? '', $provider['api_key_env'] ?? '');

        if ($api_key !== '') {
            $headers['Authorization'] = 'Bearer '.$api_key;
        }

        $extra_headers = Util::decodeJsonArray($provider['headers_json'] ?? '');

        if ($extra_headers) {
            foreach ($extra_headers as $name => $value) {
                if (is_string($name)) {
                    $headers[trim($name)] = (string) $value;
                }
            }
        }

        return $headers;
    }

    private static function normalizeContent($message): string {
        if (is_string($message)) {
            return trim($message);
        }

        if (is_array($message)) {
            $parts = [];

            foreach ($message as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                    continue;
                }

                if (!is_array($part)) {
                    continue;
                }

                if (isset($part['text']) && is_string($part['text'])) {
                    $parts[] = $part['text'];
                }
                elseif (isset($part['content']) && is_string($part['content'])) {
                    $parts[] = $part['content'];
                }
            }

            return trim(implode("\n", array_filter($parts, static function($value) {
                return trim((string) $value) !== '';
            })));
        }

        return '';
    }
}
