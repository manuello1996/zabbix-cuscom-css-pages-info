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

class PageCssSave extends BaseAction
{
    private const MACRO_PREFIX = '{$PAGECSS:';
    private const MACRO_SUFFIX = '}';

    protected function checkInput()
    {
        $rules = [
            'css' => 'string'
        ];

        $valid = $this->validateInput($rules);

        if (!$valid) {
            $this->setResponse(new CControllerResponseRedirect(
                (new CUrl('zabbix.php'))->setArgument('action', 'module.pagecss.list')
            ));
        }

        return $valid;
    }

    protected function doAction()
    {
        $css = $this->getInput('css', '');
        $minified = $this->minifyCss($css);

        $max_len = (int) DB::getFieldLength('globalmacro', 'value');
        if ($max_len <= 0) {
            $max_len = 2048;
        }

        $chunks = $minified !== '' ? str_split($minified, $max_len) : [];

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
        DBstart();

        if ($pagecss_ids) {
            $result = (bool) API::UserMacro()->deleteGlobal($pagecss_ids);
        }

        if ($result && $chunks) {
            $to_create = [];
            $total = count($chunks);
            foreach ($chunks as $idx => $chunk) {
                $order = $idx + 1;
                $to_create[] = [
                    'macro' => self::MACRO_PREFIX.$order.self::MACRO_SUFFIX,
                    'value' => $chunk,
                    'type' => ZBX_MACRO_TYPE_TEXT,
                    'description' => _s('Custom CSS chunk %1$d/%2$d', $order, $total)
                ];
            }
            $result = (bool) API::UserMacro()->createGlobal($to_create);
        }

        $result = DBend($result);

        $response = new CControllerResponseRedirect(
            (new CUrl('zabbix.php'))->setArgument('action', 'module.pagecss.list')
        );

        if ($result) {
            CMessageHelper::setSuccessTitle(_('Custom CSS updated.'));
        }
        else {
            CMessageHelper::setErrorTitle(_('Cannot update custom CSS.'));
            $response->setFormData(['css' => $css]);
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

    private function minifyCss(string $css): string
    {
        if ($css === '') {
            return '';
        }

        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $lines = preg_split('/\r\n|\r|\n/', $css);
        $trimmed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $trimmed[] = $line;
            }
        }

        return implode(' ', $trimmed);
    }
}
