<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\GoogleTagManager\Tests\ConsentBanner;

use HKweb\Plugin\System\GoogleTagManager\ConsentBanner\ConsentBannerConfig;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConsentBannerConfig.
 *
 * @since 26.25.00
 */
class ConsentBannerConfigTest extends TestCase
{
	/**
	 * @since 26.25.00
	 */
	public function testIsDisabledByDefault(): void
	{
		$config = new ConsentBannerConfig(new Registry());

		$this->assertFalse($config->isEnabled());
	}

	/**
	 * @since 26.25.00
	 */
	public function testIsEnabledWhenParamIsSetToOne(): void
	{
		$config = new ConsentBannerConfig(new Registry(['native_consent_banner' => '1']));

		$this->assertTrue($config->isEnabled());
	}

	/**
	 * @since 26.25.00
	 */
	public function testShowsMarketingCategoryByDefault(): void
	{
		$config = new ConsentBannerConfig(new Registry());

		$this->assertTrue($config->showMarketingCategory());
	}

	/**
	 * @since 26.25.00
	 */
	public function testHidesMarketingCategoryWhenParamIsSetToZero(): void
	{
		$config = new ConsentBannerConfig(new Registry(['consent_category_marketing' => '0']));

		$this->assertFalse($config->showMarketingCategory());
	}

	/**
	 * @since 26.25.00
	 */
	public function testDefaultExpirationIsThreeHundredSixtyFiveDaysInMilliseconds(): void
	{
		$config = new ConsentBannerConfig(new Registry());

		$this->assertSame(365 * 24 * 60 * 60 * 1000, $config->getExpirationMilliseconds());
	}

	/**
	 * @since 26.25.00
	 */
	public function testConvertsConfiguredExpirationDaysToMilliseconds(): void
	{
		$config = new ConsentBannerConfig(new Registry(['consent_expiration_days' => '30']));

		$this->assertSame(30 * 24 * 60 * 60 * 1000, $config->getExpirationMilliseconds());
	}

	/**
	 * @since 26.25.00
	 */
	public function testTreatsZeroOrNegativeExpirationDaysAsOneDay(): void
	{
		$config = new ConsentBannerConfig(new Registry(['consent_expiration_days' => '0']));

		$this->assertSame(24 * 60 * 60 * 1000, $config->getExpirationMilliseconds());
	}
}
