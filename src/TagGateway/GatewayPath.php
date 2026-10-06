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

namespace HKweb\Plugin\System\GoogleTagManager\TagGateway;

defined('_JEXEC') or die;

/**
 * Google tag gateway measurement path handling
 *
 * @since  26.41.01
 */
final class GatewayPath
{
	/**
	 * Normalise a Google tag gateway measurement path
	 *
	 * Accepts the path as configured in Cloudflare or the Google tag console, with or
	 * without leading/trailing slashes, and returns it root-relative ('/89w8'). Returns
	 * null for anything that is not a plain path of [A-Za-z0-9_-] segments: the result
	 * is placed in an inline script as a same-origin src, so full URLs, protocol-relative
	 * hosts, dot segments, query strings and quotes are rejected.
	 *
	 * @param   string  $path  The configured measurement path
	 *
	 * @return  string|null  The root-relative path, or null when empty or invalid
	 *
	 * @since   26.41.01
	 */
	public static function normalize(string $path): ?string
	{
		$path = trim(trim($path), '/');

		if ($path === '' || !preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $path))
		{
			return null;
		}

		return '/' . $path;
	}
}
