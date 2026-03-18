<?php
declare(strict_types=1);

/*
 * Optional local overrides for the Network Map module.
 *
 * Copy this file to config.local.php and adjust the values you need.
 */

return [
    /*
     * Writable cache directory for the web server user.
    * Default if omitted: sys_get_temp_dir() . '/network-map'
     */
    'cache_dir' => '/var/lib/zabbix/network-map-cache',

    /* Frontend cache lifetime for the built map payload. */
    'cache_ttl_seconds' => 1800,

    /* History window used when building the graph. */
    'history_window_hours' => 24,

    /*
     * Maximum number of history rows read per matching item.
     * Raise only if you know the item volume requires it.
     */
    'history_limit_per_item' => 100000,

    /*
     * Which Zabbix host field should be shown on the map for monitored nodes.
     * Supported values:
     *   - 'visible'   -> host.name (default)
     *   - 'technical' -> host.host
     */
    'host_label_source' => 'visible',

    /* Append the host primary IP to monitored host labels. */
    'append_primary_ip_to_host_labels' => true,

    /*
     * Item names used for the connection JSON payloads.
     * Keep these aligned with your existing collectors/templates.
     */
    'zabbix_item_names' => [
        'linux-network-connections',
        'windows-network-connections',
    ],

    /*
     * Colors used by the graph renderer.
     * monitored_host = resolved Zabbix host
     * private_ip     = private/internal IP that is not resolved to a Zabbix host
     * external       = public IP endpoint
     */
    'node_color_map' => [
        'monitored_host' => '#007bff',
        'private_ip' => '#6c757d',
        'external' => '#ff3366',
    ],
];
