<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

use RuntimeException;

class NetBoxClient {

    private string $url;
    private string $token;
    private bool $verify_peer;
    private int $timeout;

    public function __construct(string $url, string $token, bool $verify_peer = true, int $timeout = 10) {
        $this->url = rtrim(trim($url), '/');
        $this->token = trim($token);
        $this->verify_peer = $verify_peer;
        $this->timeout = $timeout;
    }

    public static function fromConfig(array $config): ?self {
        $config = Config::mergeWithDefaults($config);

        if (!Util::truthy($config['netbox']['enabled'] ?? false)) {
            return null;
        }

        $url = trim((string) ($config['netbox']['url'] ?? ''));
        $token = Config::resolveSecret($config['netbox']['token'] ?? '', $config['netbox']['token_env'] ?? '');

        if ($url === '' || $token === '') {
            return null;
        }

        return new self(
            $url,
            $token,
            (bool) ($config['netbox']['verify_peer'] ?? true),
            (int) ($config['netbox']['timeout'] ?? 10)
        );
    }

    public function getContextForHostname(string $hostname): string {
        $hostname = trim($hostname);

        if ($hostname === '') {
            return '';
        }

        $vm = $this->findVirtualMachine($hostname);

        if ($vm) {
            $services = $this->getServices(['virtual_machine_id' => $vm['id'] ?? 0]);

            return $this->formatVirtualMachine($vm, $services);
        }

        $device = $this->findDevice($hostname);

        if ($device) {
            $services = $this->getServices(['device_id' => $device['id'] ?? 0]);

            return $this->formatDevice($device, $services);
        }

        return 'No NetBox VM or device match found.';
    }

    private function findVirtualMachine(string $hostname): ?array {
        $data = $this->get('/api/virtualization/virtual-machines/', [
            'limit' => 1000
        ]);

        $target = strtolower($hostname);

        foreach (($data['results'] ?? []) as $vm) {
            $name = strtolower((string) ($vm['name'] ?? ''));
            $display = strtolower((string) ($vm['display'] ?? ''));

            if ($target !== '' && (strpos($name, $target) !== false || strpos($display, $target) !== false)) {
                return $vm;
            }
        }

        return null;
    }

    private function findDevice(string $hostname): ?array {
        $data = $this->get('/api/dcim/devices/', [
            'limit' => 1000
        ]);

        $target = strtolower($hostname);

        foreach (($data['results'] ?? []) as $device) {
            $name = strtolower((string) ($device['name'] ?? ''));
            $display = strtolower((string) ($device['display'] ?? ''));

            if ($target !== '' && (strpos($name, $target) !== false || strpos($display, $target) !== false)) {
                return $device;
            }
        }

        return null;
    }

    private function getServices(array $params): array {
        $filtered = array_filter($params, static function($value) {
            return !empty($value);
        });

        if ($filtered === []) {
            return [];
        }

        $data = $this->get('/api/ipam/services/', $filtered);

        return $data['results'] ?? [];
    }

    private function get(string $endpoint, array $params = []): array {
        $headers = [
            'Authorization' => 'Token '.$this->token,
            'Accept' => 'application/json'
        ];

        $url = $this->url.$endpoint;

        if ($params) {
            $url .= '?'.http_build_query($params);
        }

        $response = HttpClient::expectSuccess('GET', $url, [
            'headers' => $headers,
            'timeout' => $this->timeout,
            'verify_peer' => $this->verify_peer
        ]);

        if (!is_array($response['json'])) {
            throw new RuntimeException('NetBox did not return valid JSON for '.$endpoint);
        }

        return $response['json'];
    }

    private function formatVirtualMachine(array $vm, array $services): string {
        $lines = [];
        $lines[] = 'NetBox object type: Virtual machine';
        $lines[] = 'Name: '.($vm['name'] ?? 'N/A');
        $lines[] = 'Status: '.($vm['status']['label'] ?? 'N/A');
        $lines[] = 'Site: '.($vm['site']['display'] ?? 'N/A');
        $lines[] = 'Cluster: '.($vm['cluster']['display'] ?? 'N/A');
        $lines[] = 'Role: '.($vm['role']['display'] ?? 'N/A');
        $lines[] = 'Tenant: '.($vm['tenant']['display'] ?? 'N/A');
        $lines[] = 'Platform: '.($vm['platform']['display'] ?? 'N/A');
        $lines[] = 'Primary IP: '.($vm['primary_ip4']['address'] ?? $vm['primary_ip']['address'] ?? 'N/A');
        $lines[] = 'Resources: vCPU '.($vm['vcpus'] ?? 'N/A').', RAM '.($vm['memory'] ?? 'N/A').' MB, Disk '.($vm['disk'] ?? 'N/A').' MB';

        $custom = is_array($vm['custom_fields'] ?? null) ? $vm['custom_fields'] : [];

        if (!empty($custom['operating_system'])) {
            $lines[] = 'Operating system: '.$custom['operating_system'];
        }

        if (!empty($custom['ha_with_server']) && is_array($custom['ha_with_server'])) {
            $ha = [];

            foreach ($custom['ha_with_server'] as $item) {
                if (is_array($item)) {
                    $ha[] = $item['display'] ?? $item['name'] ?? json_encode($item);
                }
                else {
                    $ha[] = (string) $item;
                }
            }

            if ($ha) {
                $lines[] = 'HA with server: '.implode(', ', $ha);
            }
        }

        if (!empty($custom['operations_services']) && is_array($custom['operations_services'])) {
            $lines[] = 'Operations services: '.implode(', ', $custom['operations_services']);
        }

        $this->appendServices($lines, $services);

        return implode("\n", $lines);
    }

    private function formatDevice(array $device, array $services): string {
        $lines = [];
        $lines[] = 'NetBox object type: Device';
        $lines[] = 'Name: '.($device['name'] ?? 'N/A');
        $lines[] = 'Status: '.($device['status']['label'] ?? 'N/A');
        $lines[] = 'Site: '.($device['site']['display'] ?? 'N/A');
        $lines[] = 'Rack: '.($device['rack']['display'] ?? 'N/A');
        $lines[] = 'Role: '.($device['role']['display'] ?? 'N/A');
        $lines[] = 'Device type: '.($device['device_type']['display'] ?? 'N/A');
        $lines[] = 'Tenant: '.($device['tenant']['display'] ?? 'N/A');
        $lines[] = 'Platform: '.($device['platform']['display'] ?? 'N/A');
        $lines[] = 'Primary IP: '.($device['primary_ip4']['address'] ?? $device['primary_ip']['address'] ?? 'N/A');
        $lines[] = 'Serial: '.($device['serial'] ?? 'N/A');
        $this->appendServices($lines, $services);

        return implode("\n", $lines);
    }

    private function appendServices(array &$lines, array $services): void {
        if (!$services) {
            return;
        }

        $lines[] = 'Services:';

        foreach ($services as $service) {
            $name = $service['name'] ?? 'N/A';
            $protocol = $service['protocol']['label'] ?? 'N/A';
            $ports = is_array($service['ports'] ?? null) ? implode(',', $service['ports']) : '';
            $description = trim((string) ($service['description'] ?? ''));
            $lines[] = '  - '.$name.' ('.$protocol.($ports !== '' ? '/'.$ports : '').')'.($description !== '' ? ' '.$description : '');
        }
    }
}
