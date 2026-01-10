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
use DB;

class PageCssDelete extends BaseAction
{
    private const MACRO_PREFIX = '{$PAGECSS:';
    private const MACRO_SUFFIX = '}';

    protected function checkInput()
    {
        return true;
    }

    protected function doAction()
    {
        $existing = API::UserMacro()->get([
            'output' => ['globalmacroid', 'macro'],
            'globalmacro' => true
        ]);

        $pagecss_ids = [];
        foreach ($existing as $macro) {
            if ($this->isPageCssMacro($macro['macro'])) {
                $pagecss_ids[] = $macro['globalmacroid'];
            }
        }

        $result = true;
        if ($pagecss_ids) {
            DBstart();
            $result = (bool) API::UserMacro()->deleteGlobal($pagecss_ids);
            $result = DBend($result);
        }

        $response = new CControllerResponseRedirect(
            (new CUrl('zabbix.php'))->setArgument('action', 'module.pagecss.list')
        );

        if ($result) {
            CMessageHelper::setSuccessTitle(_('Custom CSS deleted.'));
        }
        else {
            CMessageHelper::setErrorTitle(_('Cannot delete custom CSS.'));
        }

        $this->setResponse($response);
    }

    private function isPageCssMacro(string $macro): bool
    {
        if (strpos($macro, self::MACRO_PREFIX) !== 0 || substr($macro, -1) !== self::MACRO_SUFFIX) {
            return false;
        }

        $order = substr($macro, strlen(self::MACRO_PREFIX), -strlen(self::MACRO_SUFFIX));
        $order = trim($order);

        return $order !== '' && ctype_digit($order);
    }
}
