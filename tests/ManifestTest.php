<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\GoogleTagManager\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the plugin manifest.
 *
 * The release ZIP contains every folder below, but Joomla's installer only
 * copies what the manifest declares. A folder missing from <files> is in the
 * package yet never reaches the site (26.42.00 shipped without layouts/, so
 * the native consent banner rendered empty).
 *
 * @since 26.44.00
 */
class ManifestTest extends TestCase
{
	/**
	 * @return  array<string, array{string}>
	 *
	 * @since 26.44.00
	 */
	public static function pluginFolders(): array
	{
		return [
			'services' => ['services'],
			'src'      => ['src'],
			'layouts'  => ['layouts'],
		];
	}

	/**
	 * @since 26.44.00
	 */
	#[DataProvider('pluginFolders')]
	public function testPluginFolderIsDeclared(string $folder): void
	{
		$manifest = simplexml_load_file(dirname(__DIR__) . '/googletagmanager.xml');
		$declared = array_map('strval', $manifest->xpath('/extension/files/folder'));

		$this->assertDirectoryExists(dirname(__DIR__) . '/' . $folder);
		$this->assertContains($folder, $declared);
	}
}
