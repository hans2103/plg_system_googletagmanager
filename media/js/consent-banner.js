(function () {
	'use strict';

	/**
	 * Markup contract for the consent banner (both default and site-override layouts must satisfy):
	 *
	 * DOM attributes:
	 *   [data-consent-banner]                            — root element anchor
	 *   [data-consent-expiration-ms="<milliseconds>"]    — expiration duration (defaults to 365 days)
	 *   [data-consent-action="<action>"]                 — button actions: accept-all, reject-all, save, open-preferences
	 *   [data-consent-category="analytics"]              — checkbox control for analytics consent
	 *   [data-consent-category="marketing"]              — checkbox control for marketing consent
	 *   [data-consent-icon]                              — icon button to reopen preferences
	 *
	 * CSS class toggling (script toggles these on the root [data-consent-banner] element):
	 *   .is-open                 — root is visible (initial state if no consent found)
	 *   .is-preferences-open     — preferences view is shown (prompt view hidden)
	 *
	 * Element requirements:
	 *   [data-consent-category="..."] must be real checkbox-like controls with a .checked property
	 *     (native <input type="checkbox">, or a custom element exposing .checked).
	 *     They can be visually restyled via CSS (e.g. appearance: none) but must remain checkboxes under the hood.
	 */

	var STORAGE_KEY = 'consentMode';

	function readStoredConsent() {
		var raw = null;

		try {
			raw = window.localStorage.getItem(STORAGE_KEY);
		} catch (e) {
			return null;
		}

		if (!raw) {
			return null;
		}

		var stored;

		try {
			stored = JSON.parse(raw);
		} catch (e) {
			return null;
		}

		if (stored.expiration && Date.now() > stored.expiration) {
			return null;
		}

		return stored.consentMode || stored;
	}

	function writeConsent(consentMode, expirationMilliseconds) {
		var value = {
			consentMode: consentMode,
			expiration: Date.now() + expirationMilliseconds
		};

		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
		} catch (e) {
			// localStorage unavailable (private browsing, quota) - consent still applies
			// for this page load via the gtag update call below.
		}

		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', consentMode);
		}
	}

	function buildConsent(root, forcedGrant) {
		var analyticsInput = root.querySelector('[data-consent-category="analytics"]');
		var marketingInput = root.querySelector('[data-consent-category="marketing"]');

		var analyticsGranted = typeof forcedGrant === 'boolean' ? forcedGrant : !!(analyticsInput && analyticsInput.checked);
		var marketingGranted = typeof forcedGrant === 'boolean' ? forcedGrant : !!(marketingInput && marketingInput.checked);

		return {
			analytics_storage: analyticsGranted ? 'granted' : 'denied',
			ad_storage: marketingGranted ? 'granted' : 'denied',
			ad_user_data: marketingGranted ? 'granted' : 'denied',
			ad_personalization: marketingGranted ? 'granted' : 'denied',
			personalization_storage: 'denied',
			functionality_storage: 'granted',
			security_storage: 'granted'
		};
	}

	function init() {
		var root = document.querySelector('[data-consent-banner]');

		if (!root) {
			return;
		}

		var expirationMilliseconds = parseInt(root.getAttribute('data-consent-expiration-ms'), 10) || (365 * 24 * 60 * 60 * 1000);
		var hasConsent = readStoredConsent() !== null;

		function setOpen(open) {
			root.classList.toggle('is-open', open);
			root.setAttribute('aria-hidden', open ? 'false' : 'true');
		}

		setOpen(!hasConsent);

		root.addEventListener('click', function (event) {
			var target = event.target.closest('[data-consent-action]');

			if (!target) {
				return;
			}

			var action = target.getAttribute('data-consent-action');

			if (action === 'accept-all') {
				writeConsent(buildConsent(root, true), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'reject-all') {
				writeConsent(buildConsent(root, false), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'save') {
				writeConsent(buildConsent(root), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'open-preferences') {
				root.classList.add('is-preferences-open');
			}
		});

		var icon = document.querySelector('[data-consent-icon]');

		if (icon) {
			icon.addEventListener('click', function () {
				root.classList.add('is-preferences-open');
				setOpen(true);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
