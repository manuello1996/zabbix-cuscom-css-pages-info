<?php declare(strict_types = 0);
/*
** Copyright (C) 2025
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
*/

namespace Modules\CSSandInfos\Actions;

use CWebUser;
use CCsrfTokenHelper;
use CController as Action;

abstract class BaseAction extends Action
{
    /** @property \Modules\CSSandInfos\Module $module */
    public $module;

    public function init()
    {
        if (strtolower($_SERVER['REQUEST_METHOD']) === 'get') {
            $this->disableSIDvalidation();
        }
    }

    protected function checkPermissions()
    {
        return true;
    }

    protected function getActionCsrfToken(string $action): string
    {
        if (version_compare(ZABBIX_VERSION, '6.4.13', '<')) {
            $action = 'pageinfo';
        }

        if (version_compare(ZABBIX_VERSION, '7.0.0alpha1', '>') && version_compare(ZABBIX_VERSION, '7.0.0beta2', '<')) {
            $action = 'pageinfo';
        }

        return CCsrfTokenHelper::get($action);
    }

    public function disableSIDvalidation()
    {
        if (version_compare(ZABBIX_VERSION, '6.4.0', '<')) {
            return parent::disableSIDvalidation();
        }

        return parent::disableCsrfValidation();
    }
}
