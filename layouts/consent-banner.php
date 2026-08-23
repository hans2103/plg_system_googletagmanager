<?php

/**
 * @package    GoogleTagManager
 *
 * @author     HKweb <info@hkweb.nl>
 * @copyright  Copyright (C) 2025 HKweb. All rights reserved.
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var array $displayData */
extract($displayData);

/** @var bool $showMarketing */
/** @var int $expirationMilliseconds */
?>
<div class="plg-consent-banner" data-consent-banner data-consent-expiration-ms="<?php echo (int) $expirationMilliseconds; ?>" aria-hidden="true">
	<div class="plg-consent-banner__prompt">
		<p class="plg-consent-banner__text"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_BANNER_TEXT'); ?></p>
		<div class="plg-consent-banner__actions">
			<button type="button" data-consent-action="accept-all"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_ACCEPT_ALL'); ?></button>
			<button type="button" data-consent-action="reject-all"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REJECT_ALL'); ?></button>
			<button type="button" data-consent-action="open-preferences"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CUSTOMIZE'); ?></button>
		</div>
	</div>
	<div class="plg-consent-banner__preferences">
		<fieldset>
			<legend><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORIES_LEGEND'); ?></legend>
			<div class="plg-consent-banner__category">
				<label>
					<input type="checkbox" checked disabled>
					<?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL'); ?>
				</label>
				<p><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL_DESC'); ?></p>
			</div>
			<div class="plg-consent-banner__category">
				<label>
					<input type="checkbox" data-consent-category="analytics">
					<?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS'); ?>
				</label>
				<p><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS_DESC'); ?></p>
			</div>
			<?php if ($showMarketing) : ?>
			<div class="plg-consent-banner__category">
				<label>
					<input type="checkbox" data-consent-category="marketing">
					<?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING'); ?>
				</label>
				<p><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING_DESC'); ?></p>
			</div>
			<?php endif; ?>
		</fieldset>
		<div class="plg-consent-banner__actions">
			<button type="button" data-consent-action="accept-all"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_ACCEPT_ALL'); ?></button>
			<button type="button" data-consent-action="reject-all"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REJECT_ALL'); ?></button>
			<button type="button" data-consent-action="save"><?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_SAVE_PREFERENCES'); ?></button>
		</div>
	</div>
</div>
<button type="button" class="plg-consent-banner__icon" data-consent-icon aria-label="<?php echo Text::_('PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REOPEN'); ?>">🍪</button>
