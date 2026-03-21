<?php

namespace Modules\Rebrand;

use APP;
use CMenuItem;

class Module extends \Zabbix\Core\CModule {

	public function init(): void {
		APP::Component()->get('menu.main')
			->findOrAdd(_('Administration'))
				->getSubmenu()
					->add(
						(new CMenuItem(_('Branding')))->setAction('rebrand.config')
					);
	}
}
