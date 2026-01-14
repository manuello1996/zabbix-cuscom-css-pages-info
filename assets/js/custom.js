document.addEventListener('DOMContentLoaded', () => {
	const DEFAULT_PAGE_INFO = [
		{
			pages: ['dashboard.list'],
			message: 'This is an example of Hard-Coded message for the page <b>"dashboard.list".</b><br>The text can be stiled with tag like <b>bold</b>, <i>italic</i>, and others basic HTML.<br><br>Tis messae can be founrd in the file: <code>assets/js/custom.js</code> inside the modules files.',
			color: '',
			icon: ''
		},
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

		let context = macroName.slice(prefix.length, -suffix.length).trim();
		if (!context) return [];

		const delimiterIndex = context.indexOf('|');
		if (delimiterIndex !== -1) {
			context = context.slice(0, delimiterIndex).trim();
		}

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

	function parseMacroValue(rawValue) {
		if (typeof rawValue !== 'string' || rawValue === '') {
			return { message: '', color: '', icon: '' };
		}

		try {
			const parsed = JSON.parse(rawValue);
			if (parsed && typeof parsed === 'object') {
				return {
					message: typeof parsed.message === 'string' ? parsed.message : rawValue,
					color: typeof parsed.color === 'string' ? parsed.color : '',
					icon: typeof parsed.icon === 'string' ? parsed.icon : ''
				};
			}
		}
		catch (error) {
			// Fallback to plain message string.
		}

		return { message: rawValue, color: '', icon: '' };
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
				.map(item => {
					const parsed = parseMacroValue(item.value || '');
					return {
						pages: parseMacroPages(item.macro),
						message: parsed.message || '',
						color: parsed.color || '',
						icon: parsed.icon || ''
					};
				})
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
			.map(item => {
				const parsed = parseMacroValue(item.value || '');
				return {
					pages: parseMacroPages(item.macro),
					message: parsed.message || '',
					color: parsed.color || '',
					icon: parsed.icon || ''
				};
			})
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

	function findMatches(entries) {
		const matches = [];
		for (const entry of entries || []) {
			for (const pattern of entry.pages || []) {
				if (matchesPattern(pattern, params)) {
					matches.push({ entry, pattern });
					break;
				}
			}
		}
		return matches;
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
		const matches = findMatches(entries);
		if (!matches.length) return;

		const headerTitle = document.querySelector('header.header-title, .header-title');
		if (!headerTitle) return;

		const fragment = document.createDocumentFragment();

		for (const match of matches) {
			const { entry, pattern } = match;

			// --- injection UI ---
			const output = document.createElement('output');
			output.setAttribute('role', 'contentinfo');
			output.classList.add('restricted-box');
			output.dataset.pageInfoPattern = pattern;

			if (entry.color) {
				const normalized = entry.color.startsWith('#') ? entry.color : `#${entry.color}`;
				output.style.setProperty('--motd-color-primary', normalized);
			}

			if (entry.icon) {
				const iconValue = entry.icon.replace(/^#/, '');
				if (iconValue) {
					output.style.setProperty('--motd-icon', `"\\${iconValue}"`);
				}
			}

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

			fragment.appendChild(output);
		}

		headerTitle.after(fragment);
	})();
});
