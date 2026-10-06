<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\GoogleTagManager\Tests\Html;

use HKweb\Plugin\System\GoogleTagManager\Html\HeadScript;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HeadScript.
 *
 * @since 26.41.02
 */
class HeadScriptTest extends TestCase
{
	/**
	 * @since 26.41.02
	 */
	public function testInsertsScriptRightAfterOpeningHeadTag(): void
	{
		$html = "<html><head>\n<title>t</title></head><body></body></html>";

		$this->assertSame(
			"<html><head>\n<script>var a = 1;</script>\n<title>t</title></head><body></body></html>",
			HeadScript::prepend($html, 'var a = 1;')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testKeepsHeadTagAttributes(): void
	{
		$html = '<head data-x="1"><title>t</title></head>';

		$this->assertSame(
			"<head data-x=\"1\">\n<script>x();</script><title>t</title></head>",
			HeadScript::prepend($html, 'x();')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testIgnoresHeaderElement(): void
	{
		$html = '<body><header>h</header></body>';

		$this->assertSame($html, HeadScript::prepend($html, 'x();'));
	}

	/**
	 * @since 26.41.02
	 */
	public function testOnlyFirstHeadTag(): void
	{
		$html = '<head></head><template><head></head></template>';

		$this->assertSame(
			"<head>\n<script>x();</script></head><template><head></head></template>",
			HeadScript::prepend($html, 'x();')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testAddsEscapedNonceWhenGiven(): void
	{
		$this->assertSame(
			"<head>\n<script nonce=\"a&quot;b\">x();</script>",
			HeadScript::prepend('<head>', 'x();', 'a"b')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testEmptyNonceIsOmitted(): void
	{
		$this->assertSame("<head>\n<script>x();</script>", HeadScript::prepend('<head>', 'x();', ''));
	}

	/**
	 * @since 26.41.02
	 */
	public function testDollarSignsInScriptAreKeptLiterally(): void
	{
		$this->assertSame(
			"<head>\n<script>var s = '$0 \\1 $1';</script>",
			HeadScript::prepend('<head>', "var s = '$0 \\1 $1';")
		);
	}

	/**
	 * The charset declaration must stay within the first 1024 bytes.
	 *
	 * @since 26.41.02
	 */
	public function testInsertsAfterMetaCharsetWhenPresent(): void
	{
		$html = "<head>\n\t<meta charset=\"utf-8\">\n\t<title>t</title></head>";

		$this->assertSame(
			"<head>\n\t<meta charset=\"utf-8\">\n<script>x();</script>\n\t<title>t</title></head>",
			HeadScript::prepend($html, 'x();')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testIgnoresMetaCharsetOutsideHead(): void
	{
		$html = '<head><title>t</title></head><body><meta charset="utf-8"></body>';

		$this->assertSame(
			"<head>\n<script>x();</script><title>t</title></head><body><meta charset=\"utf-8\"></body>",
			HeadScript::prepend($html, 'x();')
		);
	}

	/**
	 * @since 26.41.02
	 */
	public function testReturnsBufferUnchangedWithoutHeadTag(): void
	{
		$this->assertSame('<p>fragment</p>', HeadScript::prepend('<p>fragment</p>', 'x();'));
	}
}
