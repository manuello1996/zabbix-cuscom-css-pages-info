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

class PageInfoSave extends BaseAction
{
    private const MACRO_PREFIX = '{$PAGEINFO:';
    private const MACRO_SUFFIX = '}';

    protected function checkInput()
    {
        $rules = [
            'globalmacroid' => 'id',
            'pages' => 'required|not_empty|string',
            'message' => 'required|not_empty|string'
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
        $pages = trim($this->getInput('pages'));
        $message = $this->getInput('message');
        $globalmacroid = $this->getInput('globalmacroid', 0);

        if ($pages === '' || strpos($pages, '{') !== false || strpos($pages, '}') !== false) {
            CMessageHelper::setErrorTitle(_('Invalid page pattern.'));
            $response = new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message
            ]);
            $this->setResponse($response);
            return;
        }

        $macro = self::MACRO_PREFIX.$pages.self::MACRO_SUFFIX;

        $existing = API::UserMacro()->get([
            'output' => ['globalmacroid', 'macro'],
            'globalmacro' => true,
            'filter' => ['macro' => [$macro]]
        ]);

        if ($existing && (!$globalmacroid || (int) $existing[0]['globalmacroid'] !== (int) $globalmacroid)) {
            CMessageHelper::setErrorTitle(_('A page information entry for this pattern already exists.'));
            $response = new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message
            ]);
            $this->setResponse($response);
            return;
        }

        $result = false;

        if ($globalmacroid) {
            $result = (bool) API::UserMacro()->updateGlobal([[
                'globalmacroid' => $globalmacroid,
                'macro' => $macro,
                'value' => $message,
                'type' => ZBX_MACRO_TYPE_TEXT
            ]]);
        }
        else {
            $result = (bool) API::UserMacro()->createGlobal([[
                'macro' => $macro,
                'value' => $message,
                'type' => ZBX_MACRO_TYPE_TEXT
            ]]);
        }

        $response = new CControllerResponseRedirect(
            (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
        );

        if ($result) {
            CMessageHelper::setSuccessTitle($globalmacroid
                ? _('Page information updated.')
                : _('Page information created.')
            );
        }
        else {
            CMessageHelper::setErrorTitle($globalmacroid
                ? _('Cannot update page information.')
                : _('Cannot create page information.')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message
            ]);
        }

        $this->setResponse($response);
    }
}
