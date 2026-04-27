/*!
 * EuroComply Cookie Consent — banner runtime.
 * MIT / GPL-2.0+.
 */
(function () {
	'use strict';

	var cfg = window.EuroComplyCC;
	if (!cfg) {
		return;
	}

	var root = document.querySelector('.eurocomply-cc-root');
	if (!root) {
		return;
	}

	var COOKIE = cfg.cookie || 'eurocomply_cc';
	var VERSION = String(cfg.version || '1');
	var DAYS = Math.max(1, parseInt(cfg.days, 10) || 180);

	function readCookie(name) {
		var pairs = document.cookie ? document.cookie.split('; ') : [];
		for (var i = 0; i < pairs.length; i++) {
			var eq = pairs[i].indexOf('=');
			if (eq === -1) continue;
			var k = decodeURIComponent(pairs[i].slice(0, eq));
			if (k === name) {
				try {
					return JSON.parse(decodeURIComponent(pairs[i].slice(eq + 1)));
				} catch (e) {
					return null;
				}
			}
		}
		return null;
	}

	function writeCookie(name, value) {
		var expires = new Date();
		expires.setTime(expires.getTime() + DAYS * 864e5);
		var encoded = encodeURIComponent(JSON.stringify(value));
		var attrs = [
			name + '=' + encoded,
			'path=/',
			'expires=' + expires.toUTCString(),
			'SameSite=Lax'
		];
		if (location.protocol === 'https:') {
			attrs.push('Secure');
		}
		document.cookie = attrs.join('; ');
	}

	function currentChoice() {
		var choice = readCookie(COOKIE);
		if (!choice || typeof choice !== 'object') return null;
		if (String(choice.v) !== VERSION) return null;
		return choice;
	}

	function gcmSignal(state) {
		var cats = cfg.categories || {};
		var signal = {};
		Object.keys(cats).forEach(function (slug) {
			var granted = slug === 'necessary' ? true : !!state[slug];
			(cats[slug].gcm || []).forEach(function (k) {
				signal[k] = granted ? 'granted' : 'denied';
			});
		});
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push(function () {
			// Function push so gtag runs in the dataLayer's own scope.
		});
		function gtag() { window.dataLayer.push(arguments); }
		gtag('consent', 'update', signal);
		return signal;
	}

	function enableBlockedScripts(state) {
		var nodes = document.querySelectorAll('script[type="text/plain"][data-eurocomply-cc]');
		nodes.forEach(function (node) {
			var cat = node.getAttribute('data-eurocomply-cc');
			if (cat === 'necessary' || state[cat]) {
				var replacement = document.createElement('script');
				for (var i = 0; i < node.attributes.length; i++) {
					var a = node.attributes[i];
					if (a.name === 'type' || a.name === 'data-eurocomply-cc') continue;
					replacement.setAttribute(a.name, a.value);
				}
				replacement.text = node.text;
				node.parentNode.replaceChild(replacement, node);
			}
		});
	}

	function persist(state) {
		writeCookie(COOKIE, { v: VERSION, s: state, t: Date.now() });
		try {
			localStorage.setItem(COOKIE, JSON.stringify({ v: VERSION, s: state, t: Date.now() }));
		} catch (e) { /* private-mode / disabled */ }
	}

	function logConsent(state) {
		if (!cfg.rest) return;
		try {
			fetch(cfg.rest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce
				},
				body: JSON.stringify({
					consent_id: generateId(),
					version: VERSION,
					language: cfg.language || '',
					state: state
				}),
				keepalive: true
			});
		} catch (e) { /* best-effort */ }
	}

	function generateId() {
		if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'cid-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
	}

	function applyChoice(state, options) {
		persist(state);
		gcmSignal(state);
		enableBlockedScripts(state);
		if (!options || options.log !== false) {
			logConsent(state);
		}
		hideBanner();
	}

	function showBanner() {
		root.hidden = false;
	}
	function hideBanner() {
		root.hidden = true;
		toggleInlinePrefs(false);
	}

	function toggleInlinePrefs(show) {
		var prefs = root.querySelector('.eurocomply-cc-prefs');
		var saveBtn = root.querySelector('.eurocomply-cc-save');
		if (prefs) prefs.hidden = !show;
		if (saveBtn) saveBtn.hidden = !show;
	}

	function buildState(allGranted) {
		var cats = cfg.categories || {};
		var state = {};
		Object.keys(cats).forEach(function (slug) {
			state[slug] = allGranted || !!cats[slug].locked;
		});
		return state;
	}

	function readPrefsUi() {
		var state = {};
		var checkboxes = root.querySelectorAll('[data-category]');
		checkboxes.forEach(function (input) {
			state[input.getAttribute('data-category')] = !!input.checked;
		});
		state.necessary = true;
		return state;
	}

	root.addEventListener('click', function (event) {
		var target = event.target;
		if (!(target instanceof Element)) return;
		var action = target.getAttribute('data-action');
		if (!action) return;

		if (action === 'accept') {
			applyChoice(buildState(true));
		} else if (action === 'reject') {
			applyChoice(buildState(false));
		} else if (action === 'customize') {
			toggleInlinePrefs(true);
		} else if (action === 'save') {
			applyChoice(readPrefsUi());
		}
	});

	// On first paint: decide whether to show the banner or silently replay the stored choice.
	var existing = currentChoice();
	if (existing && existing.s) {
		gcmSignal(existing.s);
		enableBlockedScripts(existing.s);
	} else {
		showBanner();
	}

	// Expose a minimal re-open API so themes / plugins can show the banner on demand.
	window.EuroComplyCC.open = showBanner;
	window.EuroComplyCC.reset = function () {
		writeCookie(COOKIE, { v: '__reset__' });
		try { localStorage.removeItem(COOKIE); } catch (e) {}
		showBanner();
	};
})();
