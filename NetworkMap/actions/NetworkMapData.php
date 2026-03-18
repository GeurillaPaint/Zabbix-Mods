<?php
declare(strict_types=1);

namespace Modules\NetworkMap\Actions;

require_once __DIR__ . '/../lib/bootstrap.php';

use CController;
use Modules\NetworkMap\Lib\ActionHelperTrait;
use Modules\NetworkMap\Lib\MapBuilder;

final class NetworkMapData extends CController {
    use ActionHelperTrait;

    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $force = $this->getInput('force', 0);

        return $force === 0 || $force === '0' || $force === 1 || $force === '1' || $force === null;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        try {
            $force_refresh = (string) $this->getInput('force', '0') === '1';
            $builder = new MapBuilder();
            $payload = $builder->getMap($force_refresh, $this->currentUserId());

            $this->respondJson($payload);
        }
        catch (\Throwable $e) {
            $this->respondJsonError($e->getMessage());
        }
    }
}
