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
use CControllerResponseData;

class PageInfoList extends BaseAction
{
    private const MACRO_PREFIX = '{$PAGEINFO:';
    private const MACRO_SUFFIX = '}';

    public function init()
    {
        $this->disableCsrfValidation();
    }

    protected function checkInput()
    {
        $this->validateInput([
            'globalmacroid' => 'id',
            'pages' => 'string',
            'message' => 'string'
        ]);

        return true;
    }

    protected function doAction()
    {
        $macros = API::UserMacro()->get([
            'output' => ['globalmacroid', 'macro', 'value', 'type'],
            'globalmacro' => true
        ]);

        $entries = [];
        foreach ($macros as $macro) {
            $pages = $this->extractPages($macro['macro']);
            if ($pages === null) {
                continue;
            }

            $entries[] = [
                'globalmacroid' => $macro['globalmacroid'],
                'pages' => $pages,
                'message' => $macro['value']
            ];
        }

        usort($entries, static function ($a, $b) {
            return strcmp($a['pages'], $b['pages']);
        });

        $edit = [
            'globalmacroid' => $this->getInput('globalmacroid', 0),
            'pages' => $this->getInput('pages', ''),
            'message' => $this->getInput('message', '')
        ];

        if ($edit['globalmacroid'] && $edit['pages'] === '' && $edit['message'] === '') {
            foreach ($entries as $entry) {
                if ((int) $entry['globalmacroid'] === (int) $edit['globalmacroid']) {
                    $edit['pages'] = $entry['pages'];
                    $edit['message'] = $entry['message'];
                    break;
                }
            }
        }

        $data = [
            'entries' => $entries,
            'edit' => $edit,
            'csrf_token' => [
                'module.pageinfo.save' => $this->getActionCsrfToken('module.pageinfo.save'),
                'module.pageinfo.delete' => $this->getActionCsrfToken('module.pageinfo.delete')
            ]
        ];

        $response = new CControllerResponseData($data);
        $response->setTitle(_('Page informations'));
        $this->setResponse($response);
    }

    private function extractPages(string $macro): ?string
    {
        if (strpos($macro, self::MACRO_PREFIX) !== 0 || substr($macro, -1) !== self::MACRO_SUFFIX) {
            return null;
        }

        $pages = substr($macro, strlen(self::MACRO_PREFIX), -strlen(self::MACRO_SUFFIX));
        $pages = trim($pages);

        return $pages !== '' ? $pages : null;
    }
}
