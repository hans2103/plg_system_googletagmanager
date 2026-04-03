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
	 * Default GTM base URL
	 *
	 * @since  26.14.02
	 */
	private const string GTM_DEFAULT_BASE_URL = 'https://www.googletagmanager.com';

	/**
	 * Cached GTM Container ID
	 *
	 * @var    string|null
	 * @since  26.04.00
	 */
	private ?string $gtmId = null;

	/**
	 * Cached server-side GTM domain
	 *
	 * @var    string|null
	 * @since  26.14.01
	 */
	private ?string $serverSideDomain = null;

	/**
	 * Whether the server-side domain has been resolved
	 *
	 * @var    bool
	 * @since  26.14.02
	 */
	private bool $serverSideDomainResolved = false;

	/**
	 * Cached server-side GTM path prefix (e.g. '/custom-path')
	 *
	 * @var    string
	 * @since  26.14.03
	 */
	private string $serverSidePath = '';

	/**
	 * Whether the server-side path has been resolved
	 *
	 * @var    bool
	 * @since  26.14.03
	 */
	private bool $serverSidePathResolved = false;

	/**
	 * Cached script loader URL (overrides the sGTM domain for the head script only)
	 *
	 * @var    string|null
	 * @since  26.14.06
	 */
	private ?string $scriptLoaderUrl = null;

	/**
	 * Whether the script loader URL has been resolved
	 *
	 * @var    bool
	 * @since  26.14.06
	 */
	private bool $scriptLoaderUrlResolved = false;

	/**
	 * Cached custom loader configuration (filename + obfuscated params)
	 *
	 * @var    array{filename: string, params: string}|null
	 * @since  26.14.07
	 */
	private ?array $customLoader = null;

	/**
	 * Whether the custom loader has been resolved
	 *
	 * @var    bool
	 * @since  26.14.07
	 */
	private bool $customLoaderResolved = false;

	/**
	 * Character set used when generating obfuscated loader strings
	 *
	 * @since  26.14.08
	 */
	private const string LOADER_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   26.03.00
	 */
	#[\Override]
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
	 * Get the server-side GTM domain, enforcing https://
	 *
	 * Returns the custom sGTM domain (e.g. https://sst.example.com) when configured,
	 * or null to fall back to the standard Google CDN.
	 *
	 * @return  string|null  The server-side domain or null if not configured
	 *
	 * @since   26.14.01
	 */
	private function getServerSideDomain(): ?string
	{
		if (!$this->serverSideDomainResolved)
		{
			$this->serverSideDomainResolved = true;

			if ((bool) $this->params->get('server_side_tagging', 0))
			{
				$domain = rtrim((string) $this->params->get('server_side_domain', ''), '/');

				if ($domain !== '')
				{
					// Enforce https://
					if (!str_starts_with($domain, 'https://'))
					{
						$domain = 'https://' . ltrim($domain, '/');
					}

					$this->serverSideDomain = $domain;
				}
			}
		}

		return $this->serverSideDomain;
	}

	/**
	 * Get the server-side GTM path prefix
	 *
	 * Returns a slash-prefixed path (e.g. '/custom') when configured, or an empty
	 * string when scripts are served from the root of the sGTM domain.
	 * Only applies when server-side tagging is enabled.
	 *
	 * @return  string  The path prefix or empty string
	 *
	 * @since   26.14.03
	 */
	private function getServerSidePath(): string
	{
		if (!$this->serverSidePathResolved)
		{
			$this->serverSidePathResolved = true;

			if ((bool) $this->params->get('server_side_tagging', 0))
			{
				$path = trim((string) $this->params->get('server_side_path', ''), '/');
				$this->serverSidePath = $path !== '' ? '/' . $path : '';
			}
		}

		return $this->serverSidePath;
	}

	/**
	 * Get the effective GTM base URL (domain + optional path prefix)
	 *
	 * @return  string  The GTM base URL to load scripts from
	 *
	 * @since   26.14.03
	 */
	private function getGtmBaseUrl(): string
	{
		return ($this->getServerSideDomain() ?? self::GTM_DEFAULT_BASE_URL) . $this->getServerSidePath();
	}

	/**
	 * Get the base URL used to load the GTM head script
	 *
	 * When a dedicated script loader URL is configured (e.g. via Stape's Custom Loader
	 * power-up, where gtm.js is proxied through the site's own domain), that URL is
	 * returned. Otherwise falls back to the sGTM base URL or the Google CDN default.
	 *
	 * @return  string  The base URL from which gtm.js is served
	 *
	 * @since   26.14.06
	 */
	private function getScriptLoaderUrl(): string
	{
		if (!$this->scriptLoaderUrlResolved)
		{
			$this->scriptLoaderUrlResolved = true;

			if ((bool) $this->params->get('server_side_tagging', 0))
			{
				$url = rtrim((string) $this->params->get('script_loader_url', ''), '/');

				if ($url !== '')
				{
					if (!str_starts_with($url, 'https://'))
					{
						$url = 'https://' . ltrim($url, '/');
					}

					$this->scriptLoaderUrl = $url;
				}
			}
		}

		// Append server_side_path when a custom loader URL is set, just as getGtmBaseUrl() does
		// for the default sGTM domain. Falls back to getGtmBaseUrl() (which already includes
		// the path) when no custom loader URL is configured.
		if ($this->scriptLoaderUrl !== null)
		{
			return $this->scriptLoaderUrl . $this->getServerSidePath();
		}

		return $this->getGtmBaseUrl();
	}

	/**
	 * Get custom loader configuration for enhanced ad blocker protection
	 *
	 * Priority order:
	 * 1. Manual fields (custom_script_filename + custom_script_params) — explicit override
	 * 2. Container identifier — auto-generates filename and params using the same
	 *    algorithm as Stape's Custom Loader power-up (seeded deterministic RNG so
	 *    values are stable across requests without needing a cache)
	 * 3. null — fall back to the standard gtm.js loader
	 *
	 * @return  array{filename: string, params: string}|null
	 *
	 * @since   26.14.07
	 */
	private function getCustomLoader(): ?array
	{
		if (!$this->customLoaderResolved)
		{
			$this->customLoaderResolved = true;

			if ((bool) $this->params->get('server_side_tagging', 0))
			{
				// Priority 1: manual filename + params (explicit override)
				$filename = trim((string) $this->params->get('custom_script_filename', ''));
				$params   = trim((string) $this->params->get('custom_script_params', ''));

				if ($filename !== '' && $params !== '')
				{
					$this->customLoader = ['filename' => $filename, 'params' => $params];
				}
				else
				{
					// Priority 2: auto-generate from container identifier
					$identifier = trim((string) $this->params->get('container_identifier', ''));
					$gtmId      = $this->getGTMId();

					if ($identifier !== '' && $gtmId !== null)
					{
						$this->customLoader = $this->generateLoaderFromIdentifier($identifier, $gtmId);
					}
				}
			}
		}

		return $this->customLoader;
	}

	/**
	 * Generate an obfuscated custom loader from a container identifier and GTM container ID
	 *
	 * Replicates the algorithm used by Stape's Custom Loader power-up:
	 * - Filename: random 1–5 char prefix (not ending in 'kp'/'gt') + identifier + '.js'
	 * - Params:   random 1–8 char name + '=' + urlencode(base64('id=GTM-ID')) + '&' + random suffix
	 *
	 * Values are stable for a given identifier + GTM ID combination because the RNG is seeded
	 * deterministically — no caching or database storage required.
	 *
	 * @param   string  $identifier  The container identifier from the sGTM provider
	 * @param   string  $gtmId       The GTM container ID (e.g. GTM-XXXXXXX)
	 *
	 * @return  array{filename: string, params: string}
	 *
	 * @since   26.14.08
	 */
	private function generateLoaderFromIdentifier(string $identifier, string $gtmId): array
	{
		$chars = self::LOADER_CHARS;
		$len   = strlen($chars);

		// Seed the RNG deterministically so the same inputs always produce the same output
		$rng = new \Random\Randomizer(new \Random\Engine\Mt19937(abs(crc32($identifier . $gtmId))));

		// --- Filename prefix: 1–5 chars, must not end in 'kp' or 'gt' ---
		$prefix = '';

		for ($i = 0, $prefixLen = $rng->getInt(1, 5); $i < $prefixLen; $i++)
		{
			$prefix .= $chars[$rng->getInt(0, $len - 1)];
		}

		while (preg_match('/(kp|gt)$/i', $prefix))
		{
			$prefix .= $chars[$rng->getInt(0, $len - 1)];
		}

		$filename = $prefix . $identifier . '.js';

		// --- Obfuscated params ---
		$paramName = '';

		for ($i = 0, $paramLen = $rng->getInt(1, 8); $i < $paramLen; $i++)
		{
			$paramName .= $chars[$rng->getInt(0, $len - 1)];
		}

		$encodedId = urlencode(base64_encode('id=' . $gtmId));

		$suffixes = [
			'cgm=nmB',
			'asq=2',
			'tl=dr',
			'type=' . substr(md5($identifier), 0, 8),
			'sort=asc',
			'sort=desc',
		];

		$suffix = $suffixes[$rng->getInt(0, count($suffixes) - 1)];
		$params = $paramName . '=' . $encodedId . '&' . $suffix;

		return ['filename' => $filename, 'params' => $params];
	}

	/**
	 * Get additional GTM environment query parameters for gtm_auth / gtm_preview
	 *
	 * Returns an array with 'gtm_auth', 'gtm_preview', and 'gtm_cookies_win' keys
	 * when both tokens are configured, or an empty array otherwise.
	 *
	 * @return  array<string, string>
	 *
	 * @since   26.14.03
	 */
	private function getGtmEnvironmentParams(): array
	{
		$auth    = trim((string) $this->params->get('gtm_auth', ''));
		$preview = trim((string) $this->params->get('gtm_preview', ''));

		if ($auth !== '' && $preview !== '')
		{
			return [
				'gtm_auth'        => $auth,
				'gtm_preview'     => $preview,
				'gtm_cookies_win' => 'x',
			];
		}

		return [];
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
		'analytics_storage': 'denied',
		'personalization_storage': 'denied',
		'functionality_storage': 'granted',
		'security_storage': 'granted',
	});
} else {
	gtag('consent', 'default', JSON.parse(localStorage.getItem('consentMode')));
}

dataLayer.push({'event': 'gtm_consent_update'});
JS;

		$document->getWebAssetManager()->addInlineScript($consentScript);

		$jsEscapedBaseUrl = addcslashes($this->getScriptLoaderUrl(), "\\'");
		$customLoader     = $this->getCustomLoader();

		if ($customLoader !== null)
		{
			// Enhanced ad blocker protection: obfuscated filename and pre-built query string
			// (e.g. Stape Custom Loader with enhanced protection enabled).
			// The container ID is already embedded inside the obfuscated params string.
			$jsEscapedFilename = addcslashes($customLoader['filename'], "\\'");
			$jsEscapedParams   = addcslashes($customLoader['params'], "\\'");

			$headScript = <<<JS
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='{$jsEscapedBaseUrl}/{$jsEscapedFilename}?'+i;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$jsEscapedParams}');
JS;
		}
		else
		{
			// Standard loader: build URL from container ID
			$jsEscapedGtmId = addcslashes($gtmId, "\\'");
			$envParams      = $this->getGtmEnvironmentParams();
			$jsEnvParamsStr = $envParams !== [] ? '&' . http_build_query($envParams) : '';

			$headScript = <<<JS
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='{$jsEscapedBaseUrl}/gtm.js?id='+i+dl+'{$jsEnvParamsStr}';f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$jsEscapedGtmId}');
JS;
		}

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

		// Build noscript iframe src with all query parameters, then HTML-escape the full URL.
		// The noscript uses the sGTM domain only — server_side_path is for the head script loader, not ns.html.
		$noscriptBase = $this->getServerSideDomain() ?? self::GTM_DEFAULT_BASE_URL;
		$queryParams  = array_merge(['id' => $gtmId], $this->getGtmEnvironmentParams());
		$noscriptSrc  = $noscriptBase . '/ns.html?' . http_build_query($queryParams);
		$htmlSrc     = htmlspecialchars($noscriptSrc, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Google Tag Manager noscript fallback
		$bodyScript = <<<HTML
<noscript><iframe src="{$htmlSrc}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

HTML;

		$buffer = $application->getBody();
		$buffer = (string) preg_replace('/<body(\s[^>]*)?>/i', "$0\n{$bodyScript}", $buffer, 1);

		$application->setBody($buffer);
	}
}
