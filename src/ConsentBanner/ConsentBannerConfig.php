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

use Joomla\Registry\Registry;

/**
 * Reads the native consent banner's plugin parameters.
 *
 * @since 26.25.00
 */
final class ConsentBannerConfig
{
	/**
	 * @param   Registry  $params  The plugin's parameters registry.
	 *
	 * @since 26.25.00
	 */
	public function __construct(private readonly Registry $params)
	{
	}

	/**
	 * Whether the plugin should render and manage the consent banner itself.
	 *
	 * @return boolean
	 *
	 * @since 26.25.00
	 */
	public function isEnabled(): bool
	{
		return (bool) $this->params->get('native_consent_banner', 0);
	}

	/**
	 * Whether the Marketing category should be shown in the banner.
	 *
	 * @return boolean
	 *
	 * @since 26.25.00
	 */
	public function showMarketingCategory(): bool
	{
		return (bool) $this->params->get('consent_category_marketing', 1);
	}

	/**
	 * How long a visitor's consent choice stays valid, in milliseconds.
	 *
	 * @return integer
	 *
	 * @since 26.25.00
	 */
	public function getExpirationMilliseconds(): int
	{
		$days = max((int) $this->params->get('consent_expiration_days', 365), 1);

		return $days * 24 * 60 * 60 * 1000;
	}
}
