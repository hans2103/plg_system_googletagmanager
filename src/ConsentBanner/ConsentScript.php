<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\GoogleTagManager\ConsentBanner;

defined('_JEXEC') or die;

/**
 * Builds the Consent Mode default script.
 *
 * @since 26.41.04
 */
final class ConsentScript
{
	/**
	 * Get the Consent Mode default script
	 *
	 * Initialises the data layer and replays the visitor's stored consent choice
	 * (or the denied defaults). Placed at the very top of <head> by onAfterRender()
	 * so it runs before any Google tag loader.
	 *
	 * @param   bool  $marketingOffered  Whether the banner offers the Marketing category.
	 *
	 * @return  string  The inline JavaScript
	 *
	 * @since   26.41.04
	 */
	public static function build(bool $marketingOffered): string
	{
		// Without the Marketing category a stored choice (made before it was switched
		// off) must not grant ad consent anymore.
		$denyAds = $marketingOffered ? '' : <<<JS


// This site does not offer the Marketing category: ad consent stays denied.
consent = Object.assign({}, consent, {
	'ad_storage': 'denied',
	'ad_user_data': 'denied',
	'ad_personalization': 'denied',
});
JS;

		return <<<JS
window.dataLayer = window.dataLayer || [];

function gtag() {
	dataLayer.push(arguments);
}

var defaultConsent = {
	'ad_storage': 'denied',
	'ad_user_data': 'denied',
	'ad_personalization': 'denied',
	'analytics_storage': 'denied',
	'personalization_storage': 'denied',
	'functionality_storage': 'granted',
	'security_storage': 'granted',
	'wait_for_update': 500,
};

var raw = localStorage.getItem('consentMode');
var consent = defaultConsent;

if (raw !== null) {
	try {
		// The cookie-wall banner persists { consentMode: {...}, expiration: ... },
		// so unwrap consentMode before replaying the stored choice. When the stored
		// choice has expired, fall back to the denied defaults so the banner can
		// re-prompt instead of replaying stale consent.
		var stored = JSON.parse(raw);

		if (!(stored.expiration && Date.now() > stored.expiration)) {
			consent = stored.consentMode || stored;
		}
	} catch (e) {
		// Malformed value — keep the denied defaults
	}
}{$denyAds}

gtag('consent', 'default', consent);

dataLayer.push({'event': 'gtm_consent_update'});
JS;
	}
}
