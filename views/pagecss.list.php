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

$save_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'module.pagecss.save')
	->getUrl();

$list_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'module.pagecss.list')
	->getUrl();

$css_input = (new CTextArea('css', $data['css']))
	->setRows(48)
	->setWidth(840);

$form_list = (new CFormList('pagecssFormList'))
	->addRow(_('Custom CSS'), $css_input)
	->addRow('', (new CDiv(_('CSS will be minified and stored in multiple macros if needed.')))->addClass('grey'));

$form = (new CForm())
	->setName('pagecssForm')
	->setAction($save_url)
	->addItem((new CVar(CSRF_TOKEN_NAME, $data['csrf_token']['module.pagecss.save']))->removeId())
	->addItem($form_list);

$save = (new CSubmit('save', _('Save')))->addClass(ZBX_STYLE_BTN);
$form->addItem((new CDiv($save))->addClass('form-buttons'));

$delete_form = null;
if ($data['has_chunks']) {
	$delete_form = (new CForm('post', 'zabbix.php'))
		->setName('pagecssDeleteForm')
		->addItem(new CVar('action', 'module.pagecss.delete'))
		->addItem(new CVar(CSRF_TOKEN_NAME, $data['csrf_token']['module.pagecss.delete']));

	$delete_button = (new CButton('delete', _('Delete')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->setAttribute('type', 'submit')
		->setAttribute('onclick', 'return confirm("Delete custom CSS?");')
		->addStyle('text-align: center;width: 100%;border: 0;');
	$delete_form->addItem($delete_button);
}

$page = (new CHtmlPage())
	->setTitle(_('Custom CSS'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MACROS_EDIT))
	->addItem($form);

if ($delete_form !== null) {
	$page->addItem($delete_form);
}

$page->show();
