<?php
declare(strict_types=1);

$root = (new CDiv(new CDiv(_('Loading network map…'))))
    ->setId('network-map-root')
    ->addClass('network-map-root')
    ->setAttribute('data-data-url', $data['data_url'])
    ->setAttribute('data-history-window-hours', (string) $data['history_window_hours'])
    ->setAttribute('data-cache-ttl-seconds', (string) $data['cache_ttl_seconds']);

(new CHtmlPage())
    ->setTitle($data['page_title'])
    ->addItem($root)
    ->show();
