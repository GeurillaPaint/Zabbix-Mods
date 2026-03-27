<?php declare(strict_types = 1);

namespace Modules\VeeamBackupReport;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {

    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Reports'))
            ->getSubmenu()
            ->add(
                (new CMenuItem(_('Veeam Backup Report')))
                    ->setAction('veeambackup.report.view')
            );
    }
}
