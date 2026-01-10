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

$form_list = (new CFormList('pageinfoFormList'))
	->addRow(_('Pages'), $pages_input)
	->addRow(_('Message (HTML allowed)'), $message_input)
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

	$table->addRow([
		$entry['pages'],
		new CDiv($entry['message']),
		new CDiv([$edit_link, $delete_form])
	]);
}

(new CHtmlPage())
	->setTitle(_('Page informations'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MACROS_EDIT))
	->addItem($form)
	->addItem($table)
	->show();
