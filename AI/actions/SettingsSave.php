<?php declare(strict_types = 0);

namespace Modules\AI\Actions;

require_once dirname(__DIR__).'/lib/bootstrap.php';

use CController,
    CControllerResponseRedirect,
    CMessageHelper,
    CUrl,
    Modules\AI\Lib\Config,
    Modules\AI\Lib\Util;

class SettingsSave extends CController {

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $redirect = new CControllerResponseRedirect(
            (new CUrl('zabbix.php'))
                ->setArgument('action', 'ai.settings')
                ->getUrl()
        );

        try {
            $current = Config::get();
            $new_config = Config::buildFromPost($this->getInputAll(), $current);
            Config::save($new_config);

            CMessageHelper::setSuccessTitle(_('AI settings updated.'));
        }
        catch (\Throwable $e) {
            CMessageHelper::setErrorTitle(_('Cannot update AI settings.'));
            CMessageHelper::addError(Util::truncate($e->getMessage(), 1000));
        }

        $this->setResponse($redirect);
    }
}
