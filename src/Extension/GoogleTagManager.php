<?php

declare(strict_types=1);

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @link       https://hkweb.nl
 */

namespace HKweb\Plugin\System\GoogleTagManager\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin to add Google Tag Manager script to head and beginning of body
 *
 * Implements consent mode support for GDPR compliance and adds GTM tracking code
 * to both the HTML head and body sections.
 *
 * @since   26.03.00
 */
final class GoogleTagManager extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Cached GTM Container ID
	 *
	 * @var    string|null
	 * @since  26.04.00
	 */
	private ?string $gtmId = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   26.03.00
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeCompileHead' => 'onBeforeCompileHead',
			'onAfterRender'       => 'onAfterRender',
		];
	}

	/**
	 * Get the GTM Container ID
	 *
	 * @return  string|null  The GTM Container ID or null if not configured
	 *
	 * @since   26.03.00
	 */
	private function getGTMId(): ?string
	{
		if ($this->gtmId === null)
		{
			$id = $this->params->get('container_id', '');
			$this->gtmId = $id !== '' ? $id : null;
		}

		return $this->gtmId;
	}

	/**
	 * Add GTM script to head
	 *
	 * Adds consent mode initialization and GTM tracking script to the document head.
	 *
	 * @return  void
	 *
	 * @since   26.03.00
	 */
	public function onBeforeCompileHead(): void
	{
		$application = $this->getApplication();

		// Only for frontend
		if (!$application->isClient('site'))
		{
			return;
		}

		$document = $application->getDocument();

		// Type check for HTML document
		if (!$document instanceof HtmlDocument)
		{
			return;
		}

		$gtmId = $this->getGTMId();

		if ($gtmId === null)
		{
			return;
		}

		// Initialize consent mode and data layer
		$consentScript = <<<JS
window.dataLayer = window.dataLayer || [];

function gtag() {
	dataLayer.push(arguments);
}

if (localStorage.getItem('consentMode') === null) {
	gtag('consent', 'default', {
		'ad_storage': 'denied',
		'ad_user_data': 'denied',
		'ad_personalization': 'denied',
		'analytics_storage': 'granted',
		'personalization_storage': 'granted',
		'functionality_storage': 'granted',
		'security_storage': 'granted',
	});
} else {
	gtag('consent', 'default', JSON.parse(localStorage.getItem('consentMode')));
}

dataLayer.push({'event': 'gtm_consent_update'});
JS;

		$document->getWebAssetManager()->addInlineScript($consentScript);

		// Google Tag Manager main script
		$headScript = <<<JS
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtmId}');
JS;

		$document->getWebAssetManager()->addInlineScript($headScript);
	}

	/**
	 * Add GTM noscript directly after start body
	 *
	 * Injects GTM noscript fallback iframe immediately after the opening body tag.
	 *
	 * @return  void
	 *
	 * @since   26.03.00
	 */
	public function onAfterRender(): void
	{
		$application = $this->getApplication();

		// Only for frontend
		if (!$application->isClient('site'))
		{
			return;
		}

		$document = $application->getDocument();

		// Type check for HTML document
		if (!$document instanceof HtmlDocument)
		{
			return;
		}

		$gtmId = $this->getGTMId();

		if ($gtmId === null)
		{
			return;
		}

		// Google Tag Manager noscript fallback
		$bodyScript = <<<HTML
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$gtmId}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

HTML;

		$buffer = $application->getBody();
		$buffer = (string) preg_replace('/<body(\s[^>]*)?>/i', "$0\n{$bodyScript}", $buffer, 1);

		$application->setBody($buffer);
	}
}
