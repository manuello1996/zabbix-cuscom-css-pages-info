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

/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('colorpicker.js');

$icon_css_path = APP::getRootDir().'/assets/styles/blue-theme.css';
$icon_list = [];
$icon_by_code = [];

if (is_readable($icon_css_path)) {
	$icon_css = file_get_contents($icon_css_path);
	if ($icon_css !== false) {
		if (preg_match_all('/([^{]+)\\{[^}]*content:\\s*"\\\\(e[0-9a-f]{3,4})"[^}]*\\}/i', $icon_css, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$selectors = $match[1];
				$code = strtoupper($match[2]);

				if (preg_match_all('/\\.zi-([a-z0-9-]+):before/i', $selectors, $selector_matches)) {
					foreach ($selector_matches[1] as $name) {
						if (str_ends_with($name, '-small') || str_ends_with($name, '-large') || str_contains($name, '--large')) {
							continue;
						}
						$class = 'zi-'.$name;
						$icon_list[$class] = $code;
						if (!isset($icon_by_code[$code])) {
							$icon_by_code[$code] = $class;
						}
					}
				}
			}
		}
	}
}

if (!$icon_list) {
	$icon_list = ['zi-circle-warning' => 'EA30'];
	$icon_by_code = ['EA30' => 'zi-circle-warning'];
}

ksort($icon_list);

$save_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'module.pageinfo.save')
	->getUrl();

$list_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'module.pageinfo.list')
	->getUrl();

$pages_input = (new CTextBox('pages', $data['edit']['pages']))
	->setWidth(480)
	->setAttribute('placeholder', 'discovery.view, action.list&eventsource=*');

$message_input = (new CTextArea('message', $data['edit']['message']))
	->setRows(6)
	->setWidth(480);

$color_input = new CColor('color', $data['edit']['color']);

$selected_icon_code = strtoupper($data['edit']['icon']);
$selected_icon_class = $icon_by_code[$selected_icon_code] ?? '';
$summary_label = $selected_icon_class !== '' ? $selected_icon_class : 'Custom';

$icon_summary_icon = (new CSpan())->addClass('pageinfo-icon-preview');
if ($selected_icon_class !== '') {
	$icon_summary_icon->addClass($selected_icon_class);
}

$icon_summary_text = (new CSpan($summary_label))
	->addClass('pageinfo-icon-label');

$icon_summary = (new CTag('summary', true, [$icon_summary_icon, $icon_summary_text]))
	->addClass('pageinfo-icon-summary');

$icon_grid = (new CDiv())->addClass('pageinfo-icon-grid');
foreach ($icon_list as $class => $code) {
	$input_id = 'pageinfo-icon-'.str_replace('-', '_', $class);
	$input = (new CTag('input', true))
		->setAttribute('type', 'radio')
		->setAttribute('name', 'icon')
		->setAttribute('value', $code)
		->setAttribute('data-icon-class', $class)
		->setAttribute('data-icon-code', $code)
		->setId($input_id);

	if ($code === $selected_icon_code) {
		$input->setAttribute('checked', 'checked');
	}

	$icon_swatch = (new CSpan())->addClass('pageinfo-icon-swatch '.$class);
	$icon_name = (new CSpan($class))->addClass('pageinfo-icon-name');

	$label = (new CLabel('', $input_id))->addClass('pageinfo-icon-option');
	$label->addItem($input);
	$label->addItem($icon_swatch);
	$label->addItem($icon_name);

	$icon_grid->addItem($label);
}

$icon_picker = (new CTag('details', true, [$icon_summary, $icon_grid]))
	->addClass('pageinfo-icon-picker');

$form_list = (new CFormList('pageinfoFormList'))
	->addRow(_('Pages'), $pages_input)
	->addRow(_('Message (HTML allowed)'), $message_input)
	->addRow(_('Box color'), $color_input)
	->addRow(_('Box icon'), $icon_picker)
	->addRow('', (new CDiv(_('Use comma-separated page patterns.')))->addClass('grey'))
	->addRow('', (new CDiv(_('Example: discovery.view, action.list&eventsource=*')))->addClass('grey'));


$form = (new CForm())
	->setName('pageinfoForm')
	->setAction($save_url)
	->addItem((new CVar(CSRF_TOKEN_NAME, $data['csrf_token']['module.pageinfo.save']))->removeId())
	->addItem($form_list);

if ($data['edit']['globalmacroid']) {
	$form->addItem(new CVar('globalmacroid', $data['edit']['globalmacroid']));
}

$submit_label = $data['edit']['globalmacroid'] ? _('Update') : _('Create');
$submit = (new CSubmit('save', $submit_label))->addClass(ZBX_STYLE_BTN);

if ($data['edit']['globalmacroid']) {
	$cancel = (new CButton('cancel', _('Cancel')))
		->addClass(ZBX_STYLE_BTN_ALT)
		->setAttribute('type', 'button')
		->setAttribute('onclick', 'window.location.href=\''.zbx_jsvalue($list_url, true).'\';');
	$form->addItem((new CDiv([$submit, $cancel]))->addClass('form-buttons'));
}
else {
	$form->addItem((new CDiv($submit))->addClass('form-buttons'));
}

$table = (new CTableInfo())
	->setHeader([
		_('Pages'),
		_('Message'),
		_('Color'),
		_('Icon'),
		_('Actions')
	]);

foreach ($data['entries'] as $entry) {
	$edit_link = (new CLink(_('Edit'),
		(new CUrl('zabbix.php'))
			->setArgument('action', 'module.pageinfo.list')
			->setArgument('globalmacroid', $entry['globalmacroid'])
	))->addClass(ZBX_STYLE_LINK_ACTION);

	$delete_form = (new CForm('post', 'zabbix.php'))
		->addItem(new CVar('action', 'module.pageinfo.delete'))
		->addItem(new CVar(CSRF_TOKEN_NAME, $data['csrf_token']['module.pageinfo.delete']))
		->addItem(new CVar('globalmacroid', $entry['globalmacroid']));

	$delete_button = (new CSubmit('delete', _('Delete')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->setAttribute('onclick', 'return confirm("Delete this page information?");');

	$delete_form->addItem($delete_button);

	$color_value = $entry['color'] ?? '';
	$color_label = $color_value !== '' ? '#'.$color_value : '-';

	$color_cell = $color_label;
	if ($color_value !== '') {
		$color_swatch = (new CDiv())
			->addStyle('display:inline-block;width:14px;height:14px;border:1px solid #777;background-color:#'.$color_value.';vertical-align:middle;margin-right:6px;');
		$color_cell = new CDiv([$color_swatch, $color_label]);
	}

	$icon_code = $entry['icon'] ?? '';
	$icon_class = $icon_by_code[$icon_code] ?? '';
	$icon_cell = '';
	if ($icon_class !== '') {
		$icon_preview = (new CSpan())->addClass('pageinfo-icon-swatch '.$icon_class);
		$icon_cell = $icon_preview;
	}

	$table->addRow([
		$entry['pages'],
		new CDiv($entry['message']),
		$color_cell,
		$icon_cell,
		new CDiv([$edit_link, $delete_form])
	]);
}

$style = <<<'CSS'
.pageinfo-icon-picker {
  border: 1px solid #c7c7c7;
  border-radius: 4px;
  padding: 6px 8px;
  max-width: 480px;
}
.pageinfo-icon-picker[open] {
  padding-bottom: 10px;
}
.pageinfo-icon-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  list-style: none;
}
.pageinfo-icon-summary::-webkit-details-marker {
  display: none;
}
.pageinfo-icon-preview,
.pageinfo-icon-swatch {
  width: 18px;
  text-align: center;
  display: inline-block;
}
.pageinfo-icon-label {
  color: #5f5f5f;
}
.pageinfo-icon-grid {
  margin-top: 8px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 4px 8px;
  max-height: 240px;
  overflow: auto;
  border: 1px solid #e1e1e1;
  padding: 6px;
  background: #ffffff;
}
.pageinfo-icon-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 6px;
  border-radius: 4px;
  cursor: pointer;
}
.pageinfo-icon-option:hover {
  background: #f5f5f5;
}
.pageinfo-icon-option input {
  margin: 0;
}
.pageinfo-icon-name {
  font-size: 12px;
  color: #5f5f5f;
}
CSS;

$script = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
  const picker = document.querySelector('.pageinfo-icon-picker');
  if (!picker) {
    return;
  }

  const summaryIcon = picker.querySelector('.pageinfo-icon-preview');
  const summaryLabel = picker.querySelector('.pageinfo-icon-label');

  const updateSummary = (input) => {
    if (!input) {
      return;
    }

    const iconClass = input.getAttribute('data-icon-class') || '';

    if (summaryIcon) {
      summaryIcon.className = `pageinfo-icon-preview ${iconClass}`.trim();
    }
    if (summaryLabel) {
      summaryLabel.textContent = iconClass;
    }
  };

  const inputs = picker.querySelectorAll('input[name="icon"]');
  inputs.forEach((input) => {
    input.addEventListener('change', () => updateSummary(input));
  });

  updateSummary(picker.querySelector('input[name="icon"]:checked'));

  const title = document.querySelector('header .header-title, header.header-title, .header-title, .page-title, h1');
  const messages = Array.from(document.querySelectorAll('output.msg-good, output.msg-bad, output.msg-warning'));

  if (messages.length) {
    const fragment = document.createDocumentFragment();
    messages.forEach((message) => {
<<<<<<< ours
      const text = (message.textContent || '').toLowerCase();
      if (text.includes('incorrect value for field') || text.includes('cannot be empty')) {
        message.classList.add('msg-bad');

        const closeButton = message.querySelector('.btn-overlay-close');
        if (closeButton) {
          closeButton.setAttribute('onclick', "jQuery(this).closest('output').remove();");
=======
      if (message.classList.contains('msg-good')) {
        const text = message.textContent || '';
        if (text.includes('Incorrect value for field')) {
          message.classList.remove('msg-good');
          message.classList.add('msg-bad');

          const closeButton = message.querySelector('.btn-overlay-close');
          if (closeButton) {
            closeButton.setAttribute('onclick', "jQuery(this).closest('output').remove();");
          }
>>>>>>> theirs
        }
      }
      fragment.appendChild(message);
    });

    if (title) {
      title.after(fragment);
    }
  }
});
JS;

$page = (new CHtmlPage())
	->setTitle(_('Page informations'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MACROS_EDIT))
	->addItem($form)
	->addItem($table)
	->addItem(new CTag('style', true, $style))
	->addItem(new CTag('script', true, $script));

$page->show();
