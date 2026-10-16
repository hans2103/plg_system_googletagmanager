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

use HKweb\Plugin\System\GoogleTagManager\ConsentBanner\ConsentScript;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConsentScript.
 *
 * The generated JavaScript is run in node with a stubbed localStorage, and the
 * consent it passes to gtag('consent', 'default', …) is checked.
 *
 * @since 26.41.04
 */
class ConsentScriptTest extends TestCase
{
	private const AD_DENIED = [
		'ad_storage'         => 'denied',
		'ad_user_data'       => 'denied',
		'ad_personalization' => 'denied',
	];

	/**
	 * @since 26.41.04
	 */
	protected function setUp(): void
	{
		if (trim((string) shell_exec('command -v node')) === '')
		{
			$this->markTestSkipped('node is needed to run the generated script');
		}
	}

	/**
	 * @since 26.41.04
	 */
	public function testReplaysAStoredGrantWhenMarketingIsOffered(): void
	{
		$consent = $this->defaultConsent(ConsentScript::build(true), $this->storedGrant());

		$this->assertSame('granted', $consent['ad_storage']);
		$this->assertSame('granted', $consent['ad_user_data']);
		$this->assertSame('granted', $consent['ad_personalization']);
		$this->assertSame('granted', $consent['analytics_storage']);
	}

	/**
	 * @since 26.41.04
	 */
	public function testKeepsAdConsentDeniedWhenMarketingIsNotOffered(): void
	{
		$consent = $this->defaultConsent(ConsentScript::build(false), $this->storedGrant());

		$this->assertSame(self::AD_DENIED, array_intersect_key($consent, self::AD_DENIED));
		$this->assertSame('granted', $consent['analytics_storage']);
	}

	/**
	 * @since 26.41.04
	 */
	public function testUsesTheDeniedDefaultsWithoutAStoredChoice(): void
	{
		foreach ([true, false] as $marketingOffered)
		{
			$consent = $this->defaultConsent(ConsentScript::build($marketingOffered), null);

			$this->assertSame(self::AD_DENIED, array_intersect_key($consent, self::AD_DENIED));
			$this->assertSame('denied', $consent['analytics_storage']);
		}
	}

	/**
	 * A browser that blocks storage throws on localStorage.getItem(); the
	 * denied defaults must still be set.
	 *
	 * @since 26.42.00
	 */
	public function testUsesTheDeniedDefaultsWhenStorageIsBlocked(): void
	{
		$consent = $this->defaultConsent(ConsentScript::build(true), null, true);

		$this->assertSame(self::AD_DENIED, array_intersect_key($consent, self::AD_DENIED));
		$this->assertSame('denied', $consent['analytics_storage']);
	}

	/**
	 * A consent choice as the banner stores it, accepting everything.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since 26.41.04
	 */
	private function storedGrant(): array
	{
		return [
			'consentMode' => [
				'ad_storage'         => 'granted',
				'ad_user_data'       => 'granted',
				'ad_personalization' => 'granted',
				'analytics_storage'  => 'granted',
			],
			'expiration'  => (time() + 3600) * 1000,
		];
	}

	/**
	 * Runs the consent script in node and returns the default consent it sets.
	 *
	 * @param   string                     $script  The generated script.
	 * @param   array<string, mixed>|null  $stored  The stored consent choice, if any.
	 * @param   bool                       $storageBlocked  Whether localStorage.getItem() throws.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since 26.41.04
	 */
	private function defaultConsent(string $script, ?array $stored, bool $storageBlocked = false): array
	{
		$storage = json_encode($stored === null ? null : json_encode($stored));
		$getItem = $storageBlocked
			? "throw new Error('SecurityError');"
			: "return {$storage};";
		$harness = <<<JS
globalThis.window = globalThis;
globalThis.localStorage = { getItem: function () { {$getItem} } };
{$script}
var call = window.dataLayer.find(function (args) { return args[0] === 'consent' && args[1] === 'default'; });
process.stdout.write(JSON.stringify(call[2]));
JS;

		$file = tempnam(sys_get_temp_dir(), 'consent') . '.js';
		file_put_contents($file, $harness);
		$output = (string) shell_exec('node ' . escapeshellarg($file) . ' 2>&1');
		unlink($file);

		$consent = json_decode($output, true);
		$this->assertIsArray($consent, $output);

		return $consent;
	}
}
