<?php
declare(strict_types=1);

namespace Modules\NetworkMap\Actions;

require_once __DIR__ . '/../lib/bootstrap.php';

use CController;
use CControllerResponseData;
use Modules\NetworkMap\Lib\Config;

final class NetworkMapView extends CController {
    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $data = [
            'page_title' => _('Network map'),
            'data_url' => 'zabbix.php?action=networkmap.data',
            'history_window_hours' => (int) Config::get('history_window_hours', 24),
            'cache_ttl_seconds' => (int) Config::get('cache_ttl_seconds', 1800)
        ];

        $this->setResponse(new CControllerResponseData($data));
    }
}
