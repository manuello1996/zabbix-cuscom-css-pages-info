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
    private const DEFAULT_COLOR = 'ECD200';
    private const DEFAULT_ICON = 'EA30';
    private const MACRO_ID_DELIMITER = '|';

    protected function checkInput()
    {
        $rules = [
            'globalmacroid' => 'id',
            'pages' => 'required|not_empty|string',
            'message' => 'required|not_empty|string',
            'color' => 'string',
            'icon' => 'string'
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
        $color = strtoupper(ltrim(trim($this->getInput('color', '')), '#'));
        $icon = strtoupper(ltrim(trim($this->getInput('icon', '')), '#'));
        $globalmacroid = $this->getInput('globalmacroid', 0);

        if ($pages === '' || strpos($pages, '{') !== false || strpos($pages, '}') !== false) {
            CMessageHelper::setErrorTitle(_('Invalid page pattern.'));
            $response = new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message,
                'color' => $color,
                'icon' => $icon
            ]);
            $this->setResponse($response);
            return;
        }

        if ($color !== '' && !preg_match('/^[0-9A-F]{6}$/', $color)) {
            CMessageHelper::setErrorTitle(_('Invalid color value.'));
            $response = new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message,
                'color' => $color,
                'icon' => $icon
            ]);
            $this->setResponse($response);
            return;
        }

        if ($color === '') {
            $color = self::DEFAULT_COLOR;
        }

        if ($icon !== '' && !preg_match('/^[0-9A-F]{4,6}$/', $icon)) {
            CMessageHelper::setErrorTitle(_('Invalid icon value.'));
            $response = new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pageinfo.list')
            );
            $response->setFormData([
                'globalmacroid' => $globalmacroid,
                'pages' => $pages,
                'message' => $message,
                'color' => $color,
                'icon' => $icon
            ]);
            $this->setResponse($response);
            return;
        }

        if ($icon === '') {
            $icon = self::DEFAULT_ICON;
        }

        $stored_value = json_encode([
            'message' => $message,
            'color' => $color,
            'icon' => $icon
        ]);
        $macro = self::MACRO_PREFIX.$pages.$this->buildMacroSuffix((int) $globalmacroid).self::MACRO_SUFFIX;

        $result = false;

        if ($globalmacroid) {
            $result = (bool) API::UserMacro()->updateGlobal([[
                'globalmacroid' => $globalmacroid,
                'macro' => $macro,
                'value' => $stored_value,
                'type' => ZBX_MACRO_TYPE_TEXT
            ]]);
        }
        else {
            $result = (bool) API::UserMacro()->createGlobal([[
                'macro' => $macro,
                'value' => $stored_value,
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
                'message' => $message,
                'color' => $color,
                'icon' => $icon
            ]);
        }

        $this->setResponse($response);
    }

    private function buildMacroSuffix(int $globalmacroid): string
    {
        $suffix = '';

        if ($globalmacroid) {
            $existing = API::UserMacro()->get([
                'output' => ['macro'],
                'globalmacro' => true,
                'globalmacroids' => [$globalmacroid]
            ]);

            if ($existing) {
                $macro = $existing[0]['macro'];
                if (strpos($macro, self::MACRO_PREFIX) === 0 && substr($macro, -1) === self::MACRO_SUFFIX) {
                    $context = substr($macro, strlen(self::MACRO_PREFIX), -strlen(self::MACRO_SUFFIX));
                    $delimiter_pos = strpos($context, self::MACRO_ID_DELIMITER);
                    if ($delimiter_pos !== false) {
                        $suffix = substr($context, $delimiter_pos);
                    }
                }
            }

            if ($suffix === '') {
                $suffix = self::MACRO_ID_DELIMITER.dechex($globalmacroid);
            }
        }
        else {
            try {
                $suffix = self::MACRO_ID_DELIMITER.bin2hex(random_bytes(4));
            }
            catch (\Throwable $e) {
                $suffix = self::MACRO_ID_DELIMITER.uniqid('', false);
            }
        }

        return $suffix;
    }
}
