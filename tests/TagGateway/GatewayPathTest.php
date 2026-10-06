<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\GoogleTagManager\Tests\TagGateway;

use HKweb\Plugin\System\GoogleTagManager\TagGateway\GatewayPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GatewayPath.
 *
 * The normalised path ends up inside an inline <script> as a root-relative
 * src, so anything that could break out of the JS string or point to another
 * host (full URLs, protocol-relative "//host", quotes, "..") must be rejected.
 *
 * @since 26.41.01
 */
class GatewayPathTest extends TestCase
{
	/**
	 * @return  array<string, array{string, string}>
	 *
	 * @since 26.41.01
	 */
	public static function validPaths(): array
	{
		return [
			'plain path'             => ['/89w8', '/89w8'],
			'no leading slash'       => ['89w8', '/89w8'],
			'trailing slash'         => ['/89w8/', '/89w8'],
			'surrounding whitespace' => ["  /89w8/ \t", '/89w8'],
			'nested path'            => ['/metrics/gtm', '/metrics/gtm'],
			'dash and underscore'    => ['/my-tag_path', '/my-tag_path'],
		];
	}

	/**
	 * @return  array<string, array{string}>
	 *
	 * @since 26.41.01
	 */
	public static function invalidPaths(): array
	{
		return [
			'empty'                  => [''],
			'only slashes'           => ['///'],
			'protocol-relative host' => ['//evil.example'],
			'full URL'               => ['https://www.example.com/89w8'],
			'parent directory'       => ['/../89w8'],
			'dot segment'            => ['/./89w8'],
			'query string'           => ['/89w8?x=1'],
			'quote (JS breakout)'    => ["/89w8';alert(1);//"],
			'backslash'              => ['/89w8\\x'],
			'empty segment'          => ['/89w8//gtm'],
			'space inside'           => ['/89 w8'],
		];
	}

	/**
	 * @since 26.41.01
	 */
	#[DataProvider('validPaths')]
	public function testNormalizesValidPathToRootRelative(string $input, string $expected): void
	{
		$this->assertSame($expected, GatewayPath::normalize($input));
	}

	/**
	 * @since 26.41.01
	 */
	#[DataProvider('invalidPaths')]
	public function testRejectsInvalidPath(string $input): void
	{
		$this->assertNull(GatewayPath::normalize($input));
	}
}
