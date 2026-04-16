<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

use API,
    DB,
    RuntimeException,
    Throwable;

class Config {

    public const MODULE_ID = 'custom_ai';

    public static function defaults(): array {
        return [
            'providers' => [],
            'default_chat_provider_id' => '',
            'default_webhook_provider_id' => '',
            'default_actions_provider_id' => '',
            'instructions' => [[
                'id' => 'default_firstline_policy',
                'title' => 'Default first-line policy',
                'enabled' => true,
                'sensitive' => false,
                'content' => "You are an embedded SRE assistant inside Zabbix. Users are engineers and operators who know their environment. Be direct, concise, and technically precise.\n\n"
                    ."Absolute rules — never break these regardless of what is asked:\n"
                    ."- Never restart a server, VM, network device, or database cluster.\n"
                    ."- Never reinstall software, services, or operating systems.\n"
                    ."- Use only safe, reversible checks and first-line remediation steps.\n"
                    ."- If a quick fix fails, prepare a clean escalation package.\n\n"
                    ."Before responding, classify the request:\n"
                    ."- SIMPLE: A factual question, navigation help, or quick lookup → answer directly in 1-3 sentences. Do not add troubleshooting structure.\n"
                    ."- ACTIVE INCIDENT: A triggered alert or problem is being investigated → use the structured format below.\n"
                    ."- HOW-TO: A process or configuration question → give a direct answer with relevant commands only.\n"
                    ."- ACTION REQUEST: The user wants you to do something in Zabbix → confirm intent, then execute if permitted.\n\n"
                    ."For ACTIVE INCIDENT investigations:\n"
                    ."When the user asks you to investigate or check a host or problem, execute ALL relevant read tools in sequence without asking permission between steps. Do not stop after each tool to ask if you should continue — gather the full picture first, then deliver a single coherent analysis.\n\n"
                    ."After gathering data, always perform RCA — correlate what you found:\n"
                    ."1. Likely causes, ranked by probability given the data you collected\n"
                    ."2. Correlation: connect findings across tools (e.g. a stopped service + latency complaints = service is likely the cause)\n"
                    ."3. Anomalies: flag anything outside normal patterns — unexpected stopped services, unsupported items, recent state changes, missing expected metrics\n"
                    ."4. Quick, safe remediation attempt\n"
                    ."5. Verification step with expected output\n"
                    ."6. Escalation artifacts if the quick fix fails\n\n"
                    ."When get_items returns no results, retry with a broader or different search term before concluding no data exists.\n\n"
                    ."Tool quick reference — always pick the correct tool for the intent:\n"
                    ."- User asks to find/list/search hosts by name or keyword → search_hosts (ALWAYS this, never get_items or get_host_info)\n"
                    ."- User has an exact hostname and wants details → get_host_info\n"
                    ."- User asks to find host groups → search_host_groups\n"
                    ."- User asks about active problems, alerts, incidents → get_problems (use host_pattern if hostname is partial)\n"
                    ."- User asks about triggers on a host or template → get_triggers (use host_pattern if hostname is partial)\n"
                    ."- User asks about metrics, items, or what Zabbix is collecting → get_items (use host_pattern if hostname is partial)\n"
                    ."- User asks about items that are failing or broken → get_unsupported_items\n"
                    ."- User asks how long a host has been up → get_host_uptime\n"
                    ."- User asks about OS or operating system → get_host_os\n"
                    ."- User asks to create maintenance → create_maintenance\n"
                    ."- User asks to acknowledge or close a problem → acknowledge_problem\n\n"
                    ."If Zabbix data is available in context (host, event, item history), use it. Do not speculate about things you can look up.\n\n"
                    ."Reply in Markdown. Commands go in fenced code blocks. Match response length to the complexity of what was asked."
            ]],
            'reference_links' => [],
            'zabbix_api' => [
                'url' => '',
                'token' => '',
                'token_env' => '',
                'verify_peer' => true,
                'timeout' => 15,
                'auth_mode' => 'auto'
            ],
            'netbox' => [
                'enabled' => false,
                'url' => '',
                'token' => '',
                'token_env' => '',
                'verify_peer' => true,
                'timeout' => 10
            ],
            'webhook' => [
                'enabled' => true,
                'shared_secret' => '',
                'shared_secret_env' => '',
                'add_problem_update' => true,
                'problem_update_action' => 4,
                'comment_chunk_size' => 1900,
                'skip_resolved' => true,
                'include_netbox' => true,
                'include_os_hint' => true
            ],
            'chat' => [
                'max_history_messages' => 12,
                'temperature' => 0.2,
                'item_history_period_hours' => 24,
                'item_history_max_rows' => 50
            ],
            'problem_inline' => [
                'auto_analyze' => true
            ],
            'security' => [
                'enabled' => true,
                'strict_mode' => true,
                'session_ttl_hours' => 12,
                'state_path' => '/tmp/zabbix-ai-module/state',
                'apply_to' => [
                    'chat' => true,
                    'webhook' => true,
                    'action_reads' => true,
                    'action_writes' => true,
                    'action_formatting' => true
                ],
                'categories' => [
                    'zabbix_inventory' => true,
                    'inventory_ttl_seconds' => 300,
                    'hostnames' => false,
                    'ipv4' => true,
                    'ipv6' => true,
                    'fqdns' => true,
                    'urls' => true,
                    'strip_url_query' => false,
                    'os_mode' => 'family_only'
                ],
                'custom_rules' => []
            ],
            'logging' => [
                'enabled' => false,
                'path' => '/tmp/zabbix-ai-module/logs',
                'archive_path' => '/tmp/zabbix-ai-module/archive',
                'archive_enabled' => true,
                'compress_archives' => true,
                'retention_days' => 30,
                'max_payload_chars' => 8000,
                'include_payloads' => true,
                'include_mapping_details' => false,
                'categories' => [
                    'chat' => true,
                    'webhook' => true,
                    'reads' => true,
                    'writes' => true,
                    'translations' => true,
                    'user_activity' => true,
                    'settings_changes' => true,
                    'errors' => true
                ]
            ],
            'zabbix_actions' => [
                'enabled' => true,
                'mode' => 'read',
                'write_permissions' => [
                    'maintenance' => false,
                    'items' => false,
                    'triggers' => false,
                    'users' => false,
                    'problems' => false,
                    'hostgroups' => false
                ],
                'require_super_admin_for_write' => true
            ]
        ];
    }

    public static function getModuleRecord(): ?array {
        $result = DBselect(
            'SELECT moduleid,id,relative_path,status,config'
            .' FROM module'
            .' WHERE id='.zbx_dbstr(self::MODULE_ID)
        );

        $row = DBfetch($result);

        if (!$row) {
            return null;
        }

        $row['config'] = self::mergeWithDefaults(self::decodeConfig($row['config'] ?? ''));

        return $row;
    }

    public static function get(): array {
        $record = self::getModuleRecord();

        return $record ? $record['config'] : self::defaults();
    }

    public static function save(array $config): void {
        $record = self::getModuleRecord();

        if (!$record) {
            throw new RuntimeException('AI module is not registered in the Zabbix module table.');
        }

        $config = self::mergeWithDefaults($config);

        try {
            API::Module()->update([[
                'moduleid' => $record['moduleid'],
                'config' => $config
            ]]);
        }
        catch (Throwable $e) {
            DB::update('module', [[
                'values' => [
                    'config' => json_encode($config, JSON_THROW_ON_ERROR)
                ],
                'where' => [
                    'moduleid' => $record['moduleid']
                ]
            ]]);
        }
    }

    public static function sanitizeForView(array $config): array {
        $config = self::mergeWithDefaults($config);

        foreach ($config['providers'] as &$provider) {
            $provider['api_key_present'] = trim((string) ($provider['api_key'] ?? '')) !== '';
            $provider['api_key'] = '';
        }
        unset($provider);

        $config['zabbix_api']['token_present'] = trim((string) ($config['zabbix_api']['token'] ?? '')) !== '';
        $config['zabbix_api']['token'] = '';

        $config['netbox']['token_present'] = trim((string) ($config['netbox']['token'] ?? '')) !== '';
        $config['netbox']['token'] = '';

        $config['webhook']['shared_secret_present'] = trim((string) ($config['webhook']['shared_secret'] ?? '')) !== '';
        $config['webhook']['shared_secret'] = '';

        foreach ($config['security']['custom_rules'] as &$rule) {
            $rule['id'] = Util::cleanId($rule['id'] ?? '', 'rule');
        }
        unset($rule);

        return $config;
    }

    public static function buildFromPost(array $post, array $current_config): array {
        $current_config = self::mergeWithDefaults($current_config);
        $new_config = self::defaults();

        $new_config['providers'] = [];
        $current_providers = self::indexById($current_config['providers']);

        foreach (($post['providers'] ?? []) as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $is_empty = trim((string) ($provider['name'] ?? '')) === ''
                && trim((string) ($provider['endpoint'] ?? '')) === ''
                && trim((string) ($provider['model'] ?? '')) === '';

            if ($is_empty) {
                continue;
            }

            $id = Util::cleanId($provider['id'] ?? '', 'provider');
            $existing = $current_providers[$id] ?? [];

            $clear_api_key = Util::truthy($provider['clear_api_key'] ?? false);
            $api_key_input = Util::cleanString($provider['api_key'] ?? '');
            $api_key = $clear_api_key
                ? ''
                : (($api_key_input !== '') ? $api_key_input : (string) ($existing['api_key'] ?? ''));

            $new_config['providers'][] = [
                'id' => $id,
                'name' => Util::cleanString($provider['name'] ?? '', 128),
                'type' => self::normalizeProviderType($provider['type'] ?? 'openai_compatible'),
                'endpoint' => Util::cleanUrl($provider['endpoint'] ?? ''),
                'model' => Util::cleanString($provider['model'] ?? '', 256),
                'api_key' => $api_key,
                'api_key_env' => Util::cleanString($provider['api_key_env'] ?? '', 128),
                'headers_json' => Util::cleanMultiline($provider['headers_json'] ?? '', 10000),
                'verify_peer' => Util::truthy($provider['verify_peer'] ?? false),
                'timeout' => Util::cleanInt($provider['timeout'] ?? 60, 60, 5, 300),
                'enabled' => Util::truthy($provider['enabled'] ?? false),
                'temperature' => Util::cleanFloat($provider['temperature'] ?? '', -1, 0, 2),
                'max_tokens' => Util::cleanInt($provider['max_tokens'] ?? 0, 0, 0, 128000)
            ];
        }

        $provider_ids = array_column($new_config['providers'], 'id');

        $default_chat_provider_id = Util::cleanString($post['default_chat_provider_id'] ?? '', 128);
        $default_webhook_provider_id = Util::cleanString($post['default_webhook_provider_id'] ?? '', 128);
        $default_actions_provider_id = Util::cleanString($post['default_actions_provider_id'] ?? '', 128);

        $new_config['default_chat_provider_id'] = in_array($default_chat_provider_id, $provider_ids, true)
            ? $default_chat_provider_id
            : '';
        $new_config['default_webhook_provider_id'] = in_array($default_webhook_provider_id, $provider_ids, true)
            ? $default_webhook_provider_id
            : '';
        $new_config['default_actions_provider_id'] = in_array($default_actions_provider_id, $provider_ids, true)
            ? $default_actions_provider_id
            : '';

        if ($new_config['default_chat_provider_id'] === '' && $provider_ids) {
            $new_config['default_chat_provider_id'] = $provider_ids[0];
        }
        if ($new_config['default_webhook_provider_id'] === '' && $provider_ids) {
            $new_config['default_webhook_provider_id'] = $provider_ids[0];
        }
        if ($new_config['default_actions_provider_id'] === '' && $provider_ids) {
            $new_config['default_actions_provider_id'] = $provider_ids[0];
        }

        $new_config['instructions'] = [];
        $current_instructions = self::indexById($current_config['instructions']);

        foreach (($post['instructions'] ?? []) as $instruction) {
            if (!is_array($instruction)) {
                continue;
            }

            $content = Util::cleanMultiline($instruction['content'] ?? '', 50000);
            if ($content === '') {
                continue;
            }

            $id = Util::cleanId($instruction['id'] ?? '', 'instruction');
            $existing = $current_instructions[$id] ?? [];

            $new_config['instructions'][] = [
                'id' => $id,
                'title' => Util::cleanString($instruction['title'] ?? ($existing['title'] ?? ''), 128),
                'enabled' => Util::truthy($instruction['enabled'] ?? false),
                'sensitive' => Util::truthy($instruction['sensitive'] ?? false),
                'content' => $content
            ];
        }

        $new_config['reference_links'] = [];
        $current_links = self::indexById($current_config['reference_links']);

        foreach (($post['reference_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }

            $url = Util::cleanUrl($link['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $id = Util::cleanId($link['id'] ?? '', 'link');
            $existing = $current_links[$id] ?? [];

            $new_config['reference_links'][] = [
                'id' => $id,
                'title' => Util::cleanString($link['title'] ?? ($existing['title'] ?? ''), 128),
                'url' => $url,
                'enabled' => Util::truthy($link['enabled'] ?? false)
            ];
        }

        $current_zabbix = $current_config['zabbix_api'];
        $clear_zabbix_token = Util::truthy($post['zabbix_api']['clear_token'] ?? false);
        $zabbix_token_input = Util::cleanString($post['zabbix_api']['token'] ?? '');
        $new_config['zabbix_api'] = [
            'url' => Util::cleanUrl($post['zabbix_api']['url'] ?? ''),
            'token' => $clear_zabbix_token
                ? ''
                : (($zabbix_token_input !== '') ? $zabbix_token_input : (string) ($current_zabbix['token'] ?? '')),
            'token_env' => Util::cleanString($post['zabbix_api']['token_env'] ?? '', 128),
            'verify_peer' => Util::truthy($post['zabbix_api']['verify_peer'] ?? false),
            'timeout' => Util::cleanInt($post['zabbix_api']['timeout'] ?? 15, 15, 3, 300),
            'auth_mode' => self::normalizeAuthMode($post['zabbix_api']['auth_mode'] ?? 'auto')
        ];

        $current_netbox = $current_config['netbox'];
        $clear_netbox_token = Util::truthy($post['netbox']['clear_token'] ?? false);
        $netbox_token_input = Util::cleanString($post['netbox']['token'] ?? '');
        $new_config['netbox'] = [
            'enabled' => Util::truthy($post['netbox']['enabled'] ?? false),
            'url' => Util::cleanUrl($post['netbox']['url'] ?? ''),
            'token' => $clear_netbox_token
                ? ''
                : (($netbox_token_input !== '') ? $netbox_token_input : (string) ($current_netbox['token'] ?? '')),
            'token_env' => Util::cleanString($post['netbox']['token_env'] ?? '', 128),
            'verify_peer' => Util::truthy($post['netbox']['verify_peer'] ?? false),
            'timeout' => Util::cleanInt($post['netbox']['timeout'] ?? 10, 10, 3, 300)
        ];

        $current_webhook = $current_config['webhook'];
        $clear_secret = Util::truthy($post['webhook']['clear_shared_secret'] ?? false);
        $secret_input = Util::cleanString($post['webhook']['shared_secret'] ?? '');
        $new_config['webhook'] = [
            'enabled' => Util::truthy($post['webhook']['enabled'] ?? false),
            'shared_secret' => $clear_secret
                ? ''
                : (($secret_input !== '') ? $secret_input : (string) ($current_webhook['shared_secret'] ?? '')),
            'shared_secret_env' => Util::cleanString($post['webhook']['shared_secret_env'] ?? '', 128),
            'add_problem_update' => Util::truthy($post['webhook']['add_problem_update'] ?? false),
            'problem_update_action' => Util::cleanInt($post['webhook']['problem_update_action'] ?? 4, 4, 1, 256),
            'comment_chunk_size' => Util::cleanInt($post['webhook']['comment_chunk_size'] ?? 1900, 1900, 200, 2000),
            'skip_resolved' => Util::truthy($post['webhook']['skip_resolved'] ?? false),
            'include_netbox' => Util::truthy($post['webhook']['include_netbox'] ?? false),
            'include_os_hint' => Util::truthy($post['webhook']['include_os_hint'] ?? false)
        ];

        $new_config['chat'] = [
            'max_history_messages' => Util::cleanInt($post['chat']['max_history_messages'] ?? 12, 12, 0, 50),
            'temperature' => Util::cleanFloat($post['chat']['temperature'] ?? 0.2, 0.2, 0, 2),
            'item_history_period_hours' => Util::cleanInt($post['chat']['item_history_period_hours'] ?? 24, 24, 1, 720),
            'item_history_max_rows' => Util::cleanInt($post['chat']['item_history_max_rows'] ?? 50, 50, 5, 500)
        ];

        $new_config['problem_inline'] = [
            'auto_analyze' => Util::truthy($post['problem_inline']['auto_analyze'] ?? true)
        ];

        $security = $post['security'] ?? [];
        $new_config['security'] = [
            'enabled' => Util::truthy($security['enabled'] ?? false),
            'strict_mode' => Util::truthy($security['strict_mode'] ?? false),
            'session_ttl_hours' => Util::cleanInt($security['session_ttl_hours'] ?? 12, 12, 1, 720),
            'state_path' => self::normalizePathOrDefault($security['state_path'] ?? '', '/tmp/zabbix-ai-module/state'),
            'apply_to' => [
                'chat' => Util::truthy($security['apply_to']['chat'] ?? false),
                'webhook' => Util::truthy($security['apply_to']['webhook'] ?? false),
                'action_reads' => Util::truthy($security['apply_to']['action_reads'] ?? false),
                'action_writes' => Util::truthy($security['apply_to']['action_writes'] ?? false),
                'action_formatting' => Util::truthy($security['apply_to']['action_formatting'] ?? false)
            ],
            'categories' => [
                'zabbix_inventory' => Util::truthy($security['categories']['zabbix_inventory'] ?? false),
                'inventory_ttl_seconds' => Util::cleanInt($security['categories']['inventory_ttl_seconds'] ?? 300, 300, 30, 86400),
                'hostnames' => Util::truthy($security['categories']['hostnames'] ?? false),
                'ipv4' => Util::truthy($security['categories']['ipv4'] ?? false),
                'ipv6' => Util::truthy($security['categories']['ipv6'] ?? false),
                'fqdns' => Util::truthy($security['categories']['fqdns'] ?? false),
                'urls' => Util::truthy($security['categories']['urls'] ?? false),
                'strip_url_query' => Util::truthy($security['categories']['strip_url_query'] ?? false),
                'os_mode' => Util::cleanEnum($security['categories']['os_mode'] ?? 'family_only', ['off', 'family_only', 'full_alias'], 'family_only')
            ],
            'custom_rules' => self::buildCustomRules($security['custom_rules'] ?? [])
        ];

        $logging = $post['logging'] ?? [];
        $new_config['logging'] = [
            'enabled' => Util::truthy($logging['enabled'] ?? false),
            'path' => self::normalizePathOrDefault($logging['path'] ?? '', '/tmp/zabbix-ai-module/logs'),
            'archive_path' => self::normalizePathOrDefault($logging['archive_path'] ?? '', '/tmp/zabbix-ai-module/archive'),
            'archive_enabled' => Util::truthy($logging['archive_enabled'] ?? false),
            'compress_archives' => Util::truthy($logging['compress_archives'] ?? false),
            'retention_days' => Util::cleanInt($logging['retention_days'] ?? 30, 30, 1, 3650),
            'max_payload_chars' => Util::cleanInt($logging['max_payload_chars'] ?? 8000, 8000, 200, 500000),
            'include_payloads' => Util::truthy($logging['include_payloads'] ?? false),
            'include_mapping_details' => Util::truthy($logging['include_mapping_details'] ?? false),
            'categories' => [
                'chat' => Util::truthy($logging['categories']['chat'] ?? false),
                'webhook' => Util::truthy($logging['categories']['webhook'] ?? false),
                'reads' => Util::truthy($logging['categories']['reads'] ?? false),
                'writes' => Util::truthy($logging['categories']['writes'] ?? false),
                'translations' => Util::truthy($logging['categories']['translations'] ?? false),
                'user_activity' => Util::truthy($logging['categories']['user_activity'] ?? false),
                'settings_changes' => Util::truthy($logging['categories']['settings_changes'] ?? false),
                'errors' => Util::truthy($logging['categories']['errors'] ?? false)
            ]
        ];

        $za = $post['zabbix_actions'] ?? [];
        $new_config['zabbix_actions'] = [
            'enabled' => Util::truthy($za['enabled'] ?? false),
            'mode' => in_array(($za['mode'] ?? 'read'), ['read', 'readwrite'], true)
                ? $za['mode']
                : 'read',
            'write_permissions' => [
                'maintenance' => Util::truthy($za['write_permissions']['maintenance'] ?? false),
                'items' => Util::truthy($za['write_permissions']['items'] ?? false),
                'triggers' => Util::truthy($za['write_permissions']['triggers'] ?? false),
                'users' => Util::truthy($za['write_permissions']['users'] ?? false),
                'problems' => Util::truthy($za['write_permissions']['problems'] ?? false),
                'hostgroups' => Util::truthy($za['write_permissions']['hostgroups'] ?? false)
            ],
            'require_super_admin_for_write' => Util::truthy($za['require_super_admin_for_write'] ?? true)
        ];

        return self::mergeWithDefaults($new_config);
    }

    public static function getProvider(array $config, string $provider_id = '', string $purpose = 'chat'): ?array {
        $config = self::mergeWithDefaults($config);

        if ($provider_id === '') {
            if ($purpose === 'webhook') {
                $provider_id = (string) $config['default_webhook_provider_id'];
            }
            elseif ($purpose === 'actions') {
                $provider_id = (string) ($config['default_actions_provider_id'] ?? '');
                if ($provider_id === '') {
                    $provider_id = (string) $config['default_chat_provider_id'];
                }
            }
            else {
                $provider_id = (string) $config['default_chat_provider_id'];
            }
        }

        foreach ($config['providers'] as $provider) {
            if (($provider['id'] ?? '') === $provider_id) {
                return $provider;
            }
        }

        foreach ($config['providers'] as $provider) {
            if (Util::truthy($provider['enabled'] ?? false)) {
                return $provider;
            }
        }

        return $config['providers'][0] ?? null;
    }

    public static function resolveSecret($plain_value, $env_name = ''): string {
        $plain_value = trim((string) $plain_value);
        $env_name = trim((string) $env_name);

        if ($env_name === '' && strncmp($plain_value, 'env:', 4) === 0) {
            $env_name = substr($plain_value, 4);
            $plain_value = '';
        }

        if ($env_name !== '') {
            $env_value = getenv($env_name);

            if ($env_value !== false && $env_value !== null) {
                return trim((string) $env_value);
            }
        }

        return $plain_value;
    }

    public static function mergeWithDefaults(array $config): array {
        $defaults = self::defaults();
        $merged = $defaults;

        foreach ($config as $key => $value) {
            if (in_array($key, ['providers', 'instructions', 'reference_links'], true)) {
                $merged[$key] = is_array($value) ? array_values($value) : [];
            }
            elseif (isset($defaults[$key]) && is_array($defaults[$key]) && is_array($value)) {
                $merged[$key] = array_replace_recursive($defaults[$key], $value);
            }
            else {
                $merged[$key] = $value;
            }
        }

        $merged['security']['custom_rules'] = array_values(is_array($merged['security']['custom_rules'] ?? null)
            ? $merged['security']['custom_rules']
            : []);

        return $merged;
    }

    private static function decodeConfig($config): array {
        if (is_array($config)) {
            return $config;
        }

        $config = trim((string) $config);

        if ($config === '') {
            return self::defaults();
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : self::defaults();
    }

    private static function buildCustomRules($rows): array {
        $rules = [];

        foreach ((array) $rows as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $match = Util::cleanMultiline($rule['match'] ?? '', 1000);
            $replace = Util::cleanMultiline($rule['replace'] ?? '', 1000);
            if ($match === '' || $replace === '') {
                continue;
            }

            $rules[] = [
                'id' => Util::cleanId($rule['id'] ?? '', 'rule'),
                'type' => Util::cleanEnum($rule['type'] ?? 'exact', ['exact', 'regex', 'domain_suffix'], 'exact'),
                'match' => $match,
                'replace' => $replace,
                'enabled' => Util::truthy($rule['enabled'] ?? false)
            ];
        }

        return $rules;
    }

    private static function indexById(array $rows): array {
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? '');

            if ($id !== '') {
                $indexed[$id] = $row;
            }
        }

        return $indexed;
    }

    private static function normalizeProviderType($value): string {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['openai_compatible', 'ollama', 'anthropic'], true)
            ? $value
            : 'openai_compatible';
    }

    private static function normalizeAuthMode($value): string {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['auto', 'bearer', 'legacy_auth_field'], true)
            ? $value
            : 'auto';
    }

    private static function normalizePathOrDefault($value, string $default): string {
        $value = Util::cleanPath($value);

        return $value !== '' ? $value : $default;
    }
}
