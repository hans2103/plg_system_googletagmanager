(function () {
	'use strict';

	/**
	 * Markup contract for the consent banner (both default and site-override layouts must satisfy):
	 *
	 * DOM element:
	 *   [data-consent-banner]                            — must be a <dialog> element, shown via showModal()/close()
	 *
	 * DOM attributes:
	 *   [data-consent-expiration-ms="<milliseconds>"]    — expiration duration (defaults to 365 days)
	 *   [data-consent-action="<action>"]                 — button actions: accept-all, reject-all, save, open-preferences
	 *   [data-consent-category="analytics"]              — checkbox control for analytics consent
	 *   [data-consent-category="marketing"]              — checkbox control for marketing consent; leave it
	 *                                                      out and no action grants ad consent
	 *   [data-consent-icon]                              — icon button to reopen the dialog
	 *
	 * CSS class toggling (script toggles this on the root [data-consent-banner] element):
	 *   .is-preferences-open     — preferences view is shown (prompt view hidden)
	 *
	 * Visibility itself is handled natively via the <dialog> element's showModal()/close() methods,
	 * not via a CSS class — the browser's own top-layer/::backdrop handles showing, centering, and
	 * blocking interaction with the rest of the page.
	 *
	 * Backdrop clicks never close the dialog (see the `event.target === root` guard
	 * in the click handler below) — the dialog can only be dismissed via an explicit
	 * accept-all/reject-all/save action, by design.
	 *
	 * After every choice the script calls gtag('consent', 'update', …) and pushes
	 * {event: 'gtm_consent_update'} to the dataLayer. Reopening via [data-consent-icon]
	 * ticks the category checkboxes to match the stored choice.
	 *
	 * A refused category also removes Google's cookies for it (_ga* / _gid for
	 * analytics, _gcl_* for marketing) on this host and every parent domain, so a
	 * _ga that GA4 placed on the parent domain from another subdomain goes too.
	 *
	 * Element requirements:
	 *   [data-consent-category="..."] must be real checkbox-like controls with a .checked property
	 *     (native <input type="checkbox">, or a custom element exposing .checked).
	 *     They can be visually restyled via CSS (e.g. appearance: none) but must remain checkboxes under the hood.
	 */

	var STORAGE_KEY = 'consentMode';

	// Cookie (and localStorage) names Google writes per consent category.
	var ANALYTICS_NAMES = /^(_ga|_ga_.+|_gid|_gat.*)$/;
	var MARKETING_NAMES = /^_gcl_/;

	/**
	 * The host itself plus every parent domain, e.g. for sub.example.com:
	 * '' (host-only), '.sub.example.com' and '.example.com'. The browser
	 * ignores a domain it may not set, so the list needs no public-suffix check.
	 */
	function cookieDomains() {
		var parts = window.location.hostname.split('.');
		var domains = [''];

		for (var i = 0; i < parts.length - 1; i++) {
			domains.push('.' + parts.slice(i).join('.'));
		}

		return domains;
	}

	function removeTrackingData(pattern) {
		var domains = cookieDomains();

		document.cookie.split(';').forEach(function (cookie) {
			var name = cookie.split('=')[0].trim();

			if (!pattern.test(name)) {
				return;
			}

			domains.forEach(function (domain) {
				document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (domain ? '; domain=' + domain : '');
			});
		});

		try {
			Object.keys(window.localStorage).forEach(function (key) {
				if (pattern.test(key)) {
					window.localStorage.removeItem(key);
				}
			});
		} catch (e) {
			// localStorage unavailable - nothing stored there to remove.
		}
	}

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

		// After the update above, gtag no longer writes these, so they stay gone.
		if (consentMode.analytics_storage === 'denied') {
			removeTrackingData(ANALYTICS_NAMES);
		}

		if (consentMode.ad_storage === 'denied') {
			removeTrackingData(MARKETING_NAMES);
		}

		// Same event the head script pushes on page load: GTM tags that wait for
		// consent use it as their trigger, so they fire on this page view too
		// instead of only on the next one.
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({event: 'gtm_consent_update'});
	}

	function buildConsent(root, forcedGrant) {
		var analyticsInput = root.querySelector('[data-consent-category="analytics"]');
		var marketingInput = root.querySelector('[data-consent-category="marketing"]');

		var analyticsGranted = typeof forcedGrant === 'boolean' ? forcedGrant : !!(analyticsInput && analyticsInput.checked);
		// Visitors can only consent to a category the banner shows: without the
		// Marketing switch (turned off per site) even accept-all keeps ad
		// consent denied.
		var marketingGranted = !!marketingInput && (typeof forcedGrant === 'boolean' ? forcedGrant : marketingInput.checked);

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

		// No Marketing category on this site: the head script keeps ad consent
		// denied, so clear the _gcl_* data an earlier choice may have left behind.
		if (!root.querySelector('[data-consent-category="marketing"]')) {
			removeTrackingData(MARKETING_NAMES);
		}

		function setOpen(open) {
			if (open) {
				root.showModal();
			} else {
				root.close();
			}
		}

		root.addEventListener('click', function (event) {
			// Clicking the ::backdrop dispatches a click with the dialog itself as the
			// event target (there is no real element "outside" the dialog to click on).
			// Ignore it so the dialog can only ever be dismissed via an explicit
			// accept/reject/save choice, never by clicking outside it.
			if (event.target === root) {
				return;
			}

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

		// Tick the category checkboxes to match the stored choice, so reopening
		// the preferences shows what the visitor picked last time.
		function syncCheckboxes() {
			var stored = readStoredConsent();
			var categories = {
				analytics: 'analytics_storage',
				marketing: 'ad_storage'
			};

			Object.keys(categories).forEach(function (category) {
				var input = root.querySelector('[data-consent-category="' + category + '"]');

				if (input) {
					input.checked = !!stored && stored[categories[category]] === 'granted';
				}
			});
		}

		var icon = document.querySelector('[data-consent-icon]');

		if (icon) {
			icon.addEventListener('click', function () {
				syncCheckboxes();
				root.classList.add('is-preferences-open');
				setOpen(true);
			});
		}

		if (!hasConsent) {
			setOpen(true);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
