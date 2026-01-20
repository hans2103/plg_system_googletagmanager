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

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use HKweb\Plugin\System\GoogleTagManager\Extension\GoogleTagManager;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   26.03.00
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container): PluginInterface {
				$dispatcher = $container->get(DispatcherInterface::class);
				$config     = (array) PluginHelper::getPlugin('system', 'googletagmanager');

				$plugin = new GoogleTagManager($dispatcher, $config);

				// Standard Joomla pattern: Factory is acceptable in service provider infrastructure
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
