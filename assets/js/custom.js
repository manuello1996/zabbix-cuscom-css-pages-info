document.addEventListener('DOMContentLoaded', () => {
	const DEFAULT_PAGE_INFO = [
		{
			pages: [
				'mediatype.list',
				'usergroup.list', 'userrole.list', 'user.list', 'token.list',
				'authentication.edit', 'gui.edit', 'geomaps.edit',
				'proxygroup.list', 'proxy.list', 'userrole.edit'
			],
			message: 'These settings are managed by Ansible and may be overwritten by automated processes. Changes made here may not persist.'
		},
		{
			pages: ['dashboard.list'],
			message: 'For the creation of new Dashboards follow this naming structure <br><b>Example: </b>FB / Name<br><i>LXP / Linux servers</i><br><i>NES / Fortigate Firewall</i><br><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['hostgroup.list'],
			message: 'General Host groups are managed by LXP Team.<br>Team Are allowed to create sub-Host groups via "Host group as admin" menu entry.<br><br>Elements that do not follow the naming structure will be removed without notifying users.<br><br>Other settings are managed by Ansible and may be overwritten by automated processes. Changes made here may not persist.'
		},
		{
			pages: ['template.list', 'templategroup.list'],
			message: 'For the creation of new Template follow this naming structure <br><b>Example: </b>FB - Name<br><i>LXP - Linux by Zabbix Agent</i><br><i>IA - Basis Monitoring</i><br><b>Assigne the template to the Template Group of your FB</b><br><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['module.motd.list'],
			message: 'This module is managed by user with "Super admin role" role.'
		},
		{
			pages: ['action.list&eventsource=*'],
			message: 'For the creation of new Action follow this naming structure <br><b>Example: </b>Type - FB - Name<br><i>Trigger - LXP - production</i><br><i>Service - IA - SLA H24</i><br><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['service.list', 'sla.list'],
			message: 'For the creation of new Services or SLA follow this naming structure <br><b>Example: </b>FB - Service - Name | tag: FB<br><i>LXP - CyberArk - PSMP | tag: lxp</i><br><i>IA - SOS | tag: ia</i><br><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['discovery.list'],
			message: 'For the creation of new Discovery follow this naming structure <br><b>Example: </b>FB - DeviceType - Name<br><i>NES - Firewall - FortinetBE</i><br><i>NEB - Router - G1MO</i><br><b>MIN interval 1h</b><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['maintenance.list'],
			message: 'For the creation of new Maintenance follow this naming structure <br><b>Example: </b>FB - Action - User<br><i>NEB - Firmwareupgrade UPFE - prv-xyz</i><br><i>LXP - SLES 15 upgrade SP6 - prv-xyz</i>'
		},
		{
			pages: ['macros.edit'],
			message: 'For the creation of new Macro follow this naming structure <br><b>Example: </b>FB_APPLICATION_OR_DEVICE_DETAIL<br><i>LXP_EIP_SNMP_AUTH_PRIV_PASSPHRASE</i><br><br><b>Use TYPE "Secret text" for passwords</b><br><br>Elements that do not follow the naming structure will be removed without notifying users.'
		},
		{
			pages: ['userprofile.edit'],
			message: '<b>Auto-login</b> and <b>Auto-logout</b> settings are centrally managed and forced to all user. Changes made here may not persist.'
		}
	];

	const url = new URL(window.location.href);
	const params = url.searchParams;

	// --- pattern matcher ---
	function matchesPattern(pattern, params) {
	  if (!pattern || typeof pattern !== 'string') return false;

	  // Pattern format examples:
	  // "service.list"
	  // "action.list&eventsource=*"
	  // "service.list&serviceid=5"
	  const parts = pattern.split('&').filter(Boolean);

	  const actionSpec = parts[0]; // e.g., "service.list"
	  const actualAction = params.get('action');
	  if (actualAction !== actionSpec) return false;

	  // If only the action is specified in the pattern, match regardless of extra params.
	  if (parts.length === 1) {
		return true;
	  }

	  // otherwise, each remaining part is a key=value spec; value may be "*"
	  const expected = parts.slice(1).map(seg => {
		const i = seg.indexOf('=');
		const key = i >= 0 ? seg.slice(0, i) : seg;
		const val = i >= 0 ? seg.slice(i + 1) : '';
		return { key, val };
	  });

	  // every expected key must be present and (if not "*") equal
	  for (const { key, val } of expected) {
		if (!params.has(key)) return false;
		if (val !== '*' && params.get(key) !== val) return false;
	  }

	  return true;
	}

	function mergeEntries(defaultEntries, macroEntries) {
		if (!macroEntries.length) return defaultEntries;

		const macroPatterns = new Set();
		for (const entry of macroEntries) {
			for (const pattern of entry.pages || []) {
				macroPatterns.add(pattern);
			}
		}

		const defaultsMinusOverrides = defaultEntries
			.map(entry => ({
				...entry,
				pages: (entry.pages || []).filter(pattern => !macroPatterns.has(pattern))
			}))
			.filter(entry => entry.pages.length > 0);

		return [...macroEntries, ...defaultsMinusOverrides];
	}

	function parseMacroPages(macroName) {
		const prefix = '{$PAGEINFO:';
		const suffix = '}';

		if (!macroName.startsWith(prefix) || !macroName.endsWith(suffix)) {
			return [];
		}

		const context = macroName.slice(prefix.length, -suffix.length).trim();
		if (!context) return [];

		return context.split(',').map(p => p.trim()).filter(Boolean);
	}

	function parsePageCssOrder(macroName) {
		const prefix = '{$PAGECSS:';
		const suffix = '}';

		if (!macroName.startsWith(prefix) || !macroName.endsWith(suffix)) {
			return null;
		}

		const context = macroName.slice(prefix.length, -suffix.length).trim();
		if (!context || !/^\d+$/.test(context)) return null;

		return Number(context);
	}

	async function callApi(method, params) {
		if (typeof ApiCall === 'function') {
			return ApiCall(method, params);
		}

		const response = await fetch('api_jsonrpc.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			credentials: 'same-origin',
			body: JSON.stringify({
				jsonrpc: '2.0',
				method,
				params,
				id: 1
			})
		});

		return response.json();
	}

	async function loadMacroEntries() {
		const response = await callApi('usermacro.get', {
			globalmacro: true,
			output: ['macro', 'value'],
			search: { macro: '{$PAGEINFO:' },
			searchWildcardsEnabled: true
		});

		if (response && !response.error && Array.isArray(response.result) && response.result.length > 0) {
			return response.result
				.map(item => ({
					pages: parseMacroPages(item.macro),
					message: item.value || ''
				}))
				.filter(entry => entry.pages.length > 0 && entry.message !== '');
		}

		const fallbackResponse = await callApi('usermacro.get', {
			globalmacro: true,
			output: ['macro', 'value']
		});

		if (!fallbackResponse || fallbackResponse.error || !Array.isArray(fallbackResponse.result)) {
			return [];
		}

		return fallbackResponse.result
			.map(item => ({
				pages: parseMacroPages(item.macro),
				message: item.value || ''
			}))
			.filter(entry => entry.pages.length > 0 && entry.message !== '');
	}

	async function loadPageCss() {
		const response = await callApi('usermacro.get', {
			globalmacro: true,
			output: ['macro', 'value'],
			search: { macro: '{$PAGECSS:' },
			searchWildcardsEnabled: true
		});

		if (response && !response.error && Array.isArray(response.result) && response.result.length > 0) {
			return combinePageCss(response.result);
		}

		const fallbackResponse = await callApi('usermacro.get', {
			globalmacro: true,
			output: ['macro', 'value']
		});

		if (!fallbackResponse || fallbackResponse.error || !Array.isArray(fallbackResponse.result)) {
			return '';
		}

		return combinePageCss(fallbackResponse.result);
	}

	function combinePageCss(results) {
		const chunks = results
			.map(item => ({
				order: parsePageCssOrder(item.macro),
				value: item.value || ''
			}))
			.filter(item => item.order !== null && item.value !== '');

		if (!chunks.length) return '';

		chunks.sort((a, b) => a.order - b.order);

		return chunks.map(item => item.value).join('');
	}

	function applyPageCss(cssText) {
		if (!cssText) return;

		const styleId = 'pagecss-inline';
		let style = document.getElementById(styleId);
		if (!style) {
			style = document.createElement('style');
			style.id = styleId;
			document.head.appendChild(style);
		}

		style.textContent = cssText;
	}

	function findMatch(entries) {
		for (const entry of entries || []) {
			for (const pattern of entry.pages || []) {
				if (matchesPattern(pattern, params)) {
					return { entry, pattern };
				}
			}
		}
		return null;
	}

	(async () => {
		let macroEntries = [];
		let cssText = '';

		try {
			macroEntries = await loadMacroEntries();
		}
		catch (error) {
			macroEntries = [];
		}

		try {
			cssText = await loadPageCss();
		}
		catch (error) {
			cssText = '';
		}

		applyPageCss(cssText);

		const entries = mergeEntries(DEFAULT_PAGE_INFO, macroEntries);
		const matchEntry = findMatch(entries);
		if (!matchEntry) return;

		const headerTitle = document.querySelector('header.header-title, .header-title');
		if (!headerTitle) return;

		const existing = document.querySelector('.restricted-box');
		if (existing) return;

		const { entry, pattern } = matchEntry;

	// --- injection UI ---
	const output = document.createElement('output');
	output.setAttribute('role', 'contentinfo');
	output.classList.add('restricted-box');
	output.dataset.pageInfoPattern = pattern;

	const msgDetails = document.createElement('div');
	msgDetails.classList.add('msg-details');

	const ulList = document.createElement('ul');
	ulList.classList.add('list-dashed');

	const li = document.createElement('li');
	li.innerHTML = entry.message;

	ulList.appendChild(li);
	msgDetails.appendChild(ulList);

	output.appendChild(msgDetails);

	const closeButton = document.createElement('button');
	closeButton.classList.add('btn-overlay-close');
	closeButton.setAttribute('type', 'button');
	closeButton.setAttribute('title', 'Close');
	closeButton.addEventListener('click', () => output.remove());
	output.appendChild(closeButton);

	headerTitle.insertAdjacentElement('afterend', output);
	})();
});
