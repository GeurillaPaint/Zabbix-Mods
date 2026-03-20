<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

use RuntimeException;

class ZabbixApiClient {

    private string $url;
    private string $token;
    private bool $verify_peer;
    private int $timeout;
    private string $auth_mode;

    public function __construct(string $url, string $token, bool $verify_peer = true, int $timeout = 15, string $auth_mode = 'auto') {
        $this->url = trim($url);
        $this->token = trim($token);
        $this->verify_peer = $verify_peer;
        $this->timeout = $timeout;
        $this->auth_mode = $auth_mode !== '' ? $auth_mode : 'auto';
    }

    public static function fromConfig(array $config): ?self {
        $config = Config::mergeWithDefaults($config);
        $token = Config::resolveSecret($config['zabbix_api']['token'] ?? '', $config['zabbix_api']['token_env'] ?? '');
        $url = trim((string) ($config['zabbix_api']['url'] ?? ''));

        if ($url === '') {
            $url = self::deriveApiUrl();
        }

        if ($token === '') {
            return null;
        }

        return new self(
            $url,
            $token,
            (bool) ($config['zabbix_api']['verify_peer'] ?? true),
            (int) ($config['zabbix_api']['timeout'] ?? 15),
            (string) ($config['zabbix_api']['auth_mode'] ?? 'auto')
        );
    }

    public static function deriveApiUrl(): string {
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '/zabbix.php';
        $base_path = rtrim(str_replace('\\', '/', dirname($script_name)), '/.');

        return $scheme.'://'.$host.$base_path.'/api_jsonrpc.php';
    }

    public function call(string $method, array $params = []): array {
        if ($this->auth_mode === 'bearer') {
            return $this->callWithBearer($method, $params);
        }

        if ($this->auth_mode === 'legacy_auth_field') {
            return $this->callWithLegacyAuthField($method, $params);
        }

        try {
            return $this->callWithBearer($method, $params);
        }
        catch (\Throwable $e) {
            return $this->callWithLegacyAuthField($method, $params);
        }
    }

    public function getHostIdByName(string $hostname): ?string {
        $result = $this->call('host.get', [
            'output' => ['hostid'],
            'filter' => [
                'host' => [$hostname]
            ]
        ]);

        return $result[0]['hostid'] ?? null;
    }

    public function getOsTypeByHostname(string $hostname): string {
        $host_id = $this->getHostIdByName($hostname);

        if ($host_id === null) {
            return 'Unknown';
        }

        $items = $this->call('item.get', [
            'hostids' => [$host_id],
            'search' => [
                'key_' => 'system.sw.os'
            ],
            'output' => ['lastvalue']
        ]);

        $lastvalue = strtolower(trim((string) ($items[0]['lastvalue'] ?? '')));

        if ($lastvalue === '') {
            return 'Unknown';
        }

        if (strpos($lastvalue, 'windows') !== false) {
            return 'Windows';
        }

        foreach (['linux', 'red hat', 'rhel', 'ubuntu', 'debian', 'suse', 'centos', 'rocky', 'fedora'] as $needle) {
            if (strpos($lastvalue, $needle) !== false) {
                return 'Linux';
            }
        }

        return 'Unknown';
    }

    public function addProblemComment(string $eventid, string $message, int $action = 4, int $chunk_size = 1900): array {
        $chunks = Util::chunkText($message, max(200, $chunk_size - 32));
        $count = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $prefix = ($count > 1)
                ? '[AI '.($index + 1).'/'.$count.'] '
                : '[AI] ';

            $this->call('event.acknowledge', [
                'eventids' => [$eventid],
                'action' => $action,
                'message' => $prefix.$chunk
            ]);
        }

        return $chunks;
    }

    private function callWithBearer(string $method, array $params): array {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1
        ];

        $response = HttpClient::expectSuccess('POST', $this->url, [
            'headers' => [
                'Content-Type' => 'application/json-rpc',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->token
            ],
            'json' => $payload,
            'timeout' => $this->timeout,
            'verify_peer' => $this->verify_peer
        ]);

        return $this->extractResult($response['json'], $method, 'Bearer');
    }

    private function callWithLegacyAuthField(string $method, array $params): array {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'auth' => $this->token,
            'id' => 1
        ];

        $response = HttpClient::expectSuccess('POST', $this->url, [
            'headers' => [
                'Content-Type' => 'application/json-rpc',
                'Accept' => 'application/json'
            ],
            'json' => $payload,
            'timeout' => $this->timeout,
            'verify_peer' => $this->verify_peer
        ]);

        return $this->extractResult($response['json'], $method, 'legacy auth field');
    }

    private function extractResult($json, string $method, string $auth_label): array {
        if (!is_array($json)) {
            throw new RuntimeException('Zabbix API returned a non-JSON response for '.$method.' using '.$auth_label.'.');
        }

        if (array_key_exists('error', $json)) {
            $message = $json['error']['message'] ?? 'Unknown Zabbix API error';
            $data = $json['error']['data'] ?? '';

            throw new RuntimeException($method.' failed via '.$auth_label.': '.$message.' '.Util::truncate((string) $data, 600));
        }

        $result = $json['result'] ?? [];

        return is_array($result) ? $result : [$result];
    }
}
