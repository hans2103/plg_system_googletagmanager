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

namespace HKweb\Plugin\System\GoogleTagManager\Html;

defined('_JEXEC') or die;

/**
 * Places an inline script at the very top of <head>
 *
 * @since  26.41.02
 */
final class HeadScript
{
	/**
	 * Insert an inline script as the first script in <head>
	 *
	 * Used for the Consent Mode defaults, which must run before any Google tag
	 * loader and before blocking scripts the document head loads. The Web Asset
	 * Manager can't place a script that early, so the rendered buffer is edited
	 * instead.
	 *
	 * The script goes right after the head's <meta charset> when there is one,
	 * so the charset declaration stays within the first 1024 bytes; otherwise
	 * right after the opening <head> tag.
	 *
	 * @param   string       $buffer  The rendered HTML
	 * @param   string       $script  The JavaScript to inline (inserted as is)
	 * @param   string|null  $nonce   CSP nonce for the script tag; omitted when empty
	 *
	 * @return  string  The HTML with the script inserted, or unchanged without a <head> tag
	 *
	 * @since   26.41.02
	 */
	public static function prepend(string $buffer, string $script, ?string $nonce = null): string
	{
		if (!preg_match('/<head(\s[^>]*)?>/i', $buffer, $head, PREG_OFFSET_CAPTURE))
		{
			return $buffer;
		}

		$position = $head[0][1] + strlen($head[0][0]);

		// Limit the charset lookup to the head itself
		$headEnd     = stripos($buffer, '</head>', $position);
		$headContent = substr($buffer, $position, $headEnd === false ? null : $headEnd - $position);

		if (preg_match('/<meta\s[^>]*charset[^>]*>/i', $headContent, $charset, PREG_OFFSET_CAPTURE))
		{
			$position += $charset[0][1] + strlen($charset[0][0]);
		}

		$nonceAttribute = $nonce !== null && $nonce !== ''
			? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"'
			: '';

		return substr($buffer, 0, $position) . "\n<script{$nonceAttribute}>{$script}</script>" . substr($buffer, $position);
	}
}
