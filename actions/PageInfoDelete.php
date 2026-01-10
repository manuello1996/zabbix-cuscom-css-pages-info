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

use API;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;

class PageInfoDelete extends BaseAction
{
    protected function checkInput()
    {
        $rules = [
            'globalmacroid' => 'required|id'
        ];

        $valid = $this->validateInput($rules);

        if (!$valid) {
            $this->setResponse(new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            ));
        }

        return $valid;
    }

    protected function doAction()
    {
        $globalmacroid = $this->getInput('globalmacroid');

        $result = (bool) API::UserMacro()->deleteGlobal([$globalmacroid]);

        $response = new CControllerResponseRedirect(
            (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
        );

        if ($result) {
            CMessageHelper::setSuccessTitle(_('Page information deleted.'));
        }
        else {
            CMessageHelper::setErrorTitle(_('Cannot delete page information.'));
        }

        $this->setResponse($response);
    }
}
