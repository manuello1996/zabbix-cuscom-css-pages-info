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
    private const DEFAULT_COLOR = 'ECD200';
    private const DEFAULT_ICON = 'EA30';

    public function init()
    {
        $this->disableCsrfValidation();
    }

    protected function checkInput()
    {
        $this->validateInput([
            'globalmacroid' => 'id',
            'pages' => 'string',
            'message' => 'string',
            'color' => 'string'
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

            $parsed = $this->parseMacroValue($macro['value']);

            $entries[] = [
                'globalmacroid' => $macro['globalmacroid'],
                'pages' => $pages,
                'message' => $parsed['message'],
                'color' => $parsed['color'],
                'icon' => $parsed['icon']
            ];
        }

        usort($entries, static function ($a, $b) {
            return strcmp($a['pages'], $b['pages']);
        });

        $edit = [
            'globalmacroid' => $this->getInput('globalmacroid', 0),
            'pages' => $this->getInput('pages', ''),
            'message' => $this->getInput('message', ''),
            'color' => strtoupper(ltrim($this->getInput('color', ''), '#')),
            'icon' => strtoupper(ltrim($this->getInput('icon', ''), '#'))
        ];

        if ($edit['globalmacroid'] && $edit['pages'] === '' && $edit['message'] === '') {
            foreach ($entries as $entry) {
                if ((int) $entry['globalmacroid'] === (int) $edit['globalmacroid']) {
                    $edit['pages'] = $entry['pages'];
                    $edit['message'] = $entry['message'];
                    $edit['color'] = $entry['color'];
                    $edit['icon'] = $entry['icon'];
                    break;
                }
            }
        }

        if ($edit['color'] === '') {
            $edit['color'] = self::DEFAULT_COLOR;
        }

        if ($edit['icon'] === '') {
            $edit['icon'] = self::DEFAULT_ICON;
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

        $delimiter_pos = strpos($pages, '|');
        if ($delimiter_pos !== false) {
            $pages = trim(substr($pages, 0, $delimiter_pos));
        }

        return $pages !== '' ? $pages : null;
    }

    private function parseMacroValue(string $value): array
    {
        $decoded = json_decode($value, true);

        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            $message = isset($decoded['message']) && is_string($decoded['message']) ? $decoded['message'] : '';
            $color = isset($decoded['color']) && is_string($decoded['color']) ? strtoupper(ltrim($decoded['color'], '#')) : '';
            $icon = isset($decoded['icon']) && is_string($decoded['icon']) ? strtoupper(ltrim($decoded['icon'], '#')) : '';
            if ($color === '') {
                $color = self::DEFAULT_COLOR;
            }
            if ($icon === '') {
                $icon = self::DEFAULT_ICON;
            }

            return [
                'message' => $message !== '' ? $message : $value,
                'color' => $color,
                'icon' => $icon
            ];
        }

        return [
            'message' => $value,
            'color' => self::DEFAULT_COLOR,
            'icon' => self::DEFAULT_ICON
        ];
    }
}
