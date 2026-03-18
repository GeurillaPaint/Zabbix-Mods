<?php
declare(strict_types=1);

namespace Modules\NetworkMap;

require_once __DIR__ . '/lib/bootstrap.php';

use APP;
use CMenuItem;
use Zabbix\Core\CModule;

final class Module extends CModule {
    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Monitoring'))
            ->getSubmenu()
            ->insertAfter(
                _('Discovery'),
                (new CMenuItem(_('Network map')))->setAction('networkmap.view')
            );
    }
}
