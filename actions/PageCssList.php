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

class PageCssList extends BaseAction
{
    private const MACRO_PREFIX = '{$PAGECSS:';
    private const MACRO_SUFFIX = '}';

    public function init()
    {
        $this->disableCsrfValidation();
    }

    protected function checkInput()
    {
        $this->validateInput([
            'css' => 'string'
        ]);

        return true;
    }

    protected function doAction()
    {
        $macros = API::UserMacro()->get([
            'output' => ['globalmacroid', 'macro', 'value', 'type'],
            'globalmacro' => true
        ]);

        $chunks = [];
        foreach ($macros as $macro) {
            $order = $this->extractOrder($macro['macro']);
            if ($order === null) {
                continue;
            }

            $chunks[] = [
                'globalmacroid' => $macro['globalmacroid'],
                'order' => $order,
                'value' => $macro['value']
            ];
        }

        usort($chunks, static function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $css = '';
        foreach ($chunks as $chunk) {
            $css .= $chunk['value'];
        }
        $formatted_css = $this->formatCss($css);

        $data = [
            'css' => $this->getInput('css', $formatted_css),
            'has_chunks' => (bool) $chunks,
            'csrf_token' => [
                'module.pagecss.save' => $this->getActionCsrfToken('module.pagecss.save'),
                'module.pagecss.delete' => $this->getActionCsrfToken('module.pagecss.delete')
            ]
        ];

        $response = new CControllerResponseData($data);
        $response->setTitle(_('Custom CSS'));
        $this->setResponse($response);
    }

    private function extractOrder(string $macro): ?int
    {
        if (strpos($macro, self::MACRO_PREFIX) !== 0 || substr($macro, -1) !== self::MACRO_SUFFIX) {
            return null;
        }

        $order = substr($macro, strlen(self::MACRO_PREFIX), -strlen(self::MACRO_SUFFIX));
        $order = trim($order);

        if ($order === '' || !ctype_digit($order)) {
            return null;
        }

        return (int) $order;
    }

    private function formatCss(string $css): string
    {
        if ($css === '') {
            return '';
        }

        $out = '';
        $indent = 0;
        $in_string = false;
        $string_char = '';
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $ch = $css[$i];

            if ($in_string) {
                $out .= $ch;
                if ($ch === $string_char && ($i === 0 || $css[$i - 1] !== '\\')) {
                    $in_string = false;
                    $string_char = '';
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $in_string = true;
                $string_char = $ch;
                $out .= $ch;
                continue;
            }

            if ($ch === '{') {
                $out = rtrim($out);
                $out .= " {\n";
                $indent++;
                $out .= str_repeat('  ', $indent);
                continue;
            }

            if ($ch === '}') {
                $out = rtrim($out);
                $indent = max(0, $indent - 1);
                $out .= "\n".str_repeat('  ', $indent)."}\n";
                if ($i + 1 < $length) {
                    $out .= str_repeat('  ', $indent);
                }
                continue;
            }

            if ($ch === ';') {
                $out = rtrim($out);
                $out .= ";\n".str_repeat('  ', $indent);
                continue;
            }

            if (!ctype_space($ch)) {
                $out .= $ch;
            }
            else if ($out !== '' && substr($out, -1) !== ' ' && substr($out, -1) !== "\n") {
                $out .= ' ';
            }
        }

        return rtrim($out);
    }
}
