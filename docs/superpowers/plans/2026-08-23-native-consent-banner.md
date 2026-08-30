# Native Consent Banner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an opt-in, self-contained cookie consent banner to `plg_system_googletagmanager` that works fully independently of whether GTM's client script loads, reusing the plugin's existing Consent Mode v2 `localStorage['consentMode']` contract.

**Architecture:** A new pure `ConsentBannerConfig` class reads three new plugin params (master on/off toggle, marketing-category visibility, expiration in days). When enabled, `onBeforeCompileHead` registers the plugin's own JS/CSS media assets and `onAfterRender` injects banner markup (rendered via `FileLayout` with an explicit, site-override-first include path list) right before `</body>`. The JS writes the visitor's choice to the existing `localStorage['consentMode']` key and calls `gtag('consent', 'update', ...)` immediately.

**Tech Stack:** PHP 8.3+ (`declare(strict_types=1)`, tabs, Joomla coding style — matches existing `GoogleTagManager.php`), vanilla JS (no build step, no dependencies), PHPUnit 11 for the new pure PHP class.

**Spec:** `docs/superpowers/specs/2026-08-23-native-cookie-consent-banner-design.md` in the `oeteldonk` repo (`/Users/hans2103/Development/Sites/oeteldonk/docs/superpowers/specs/2026-08-23-native-cookie-consent-banner-design.md`) — this plan implements only the "Plugin changes" section of that spec. The site-repo override (Driek styling) is a separate, later plan that depends on this one being finished first.

## Global Constraints

- PHP minimum 8.3.0, `declare(strict_types=1);` at the top of every new PHP file (after the docblock).
- Tabs for indentation in PHP (matches `src/Extension/GoogleTagManager.php`), not spaces.
- Namespace root: `HKweb\Plugin\System\GoogleTagManager`.
- `native_consent_banner` must default to `0` (off) — zero behavior change for any other site running this plugin until explicitly enabled.
- The plugin must stay generic: no Oeteldonk/Driek-specific copy, styling, or logic anywhere in this repo.
- Version numbering `YY.WW.NN` — do not bump `googletagmanager.xml`'s `<version>` or touch `CHANGELOG.md`/git-flow release commands as part of this plan; that is a separate, explicit release decision for the human to make once this plan's tasks are all merged to `develop`.
- No fixed AP-mandated consent duration exists; `consent_expiration_days` defaults to `365` and must remain admin-configurable, not hardcoded.

---

## Task 1: PHPUnit test infrastructure

No test tooling exists in this repo yet (no `composer.json`, no `phpunit.xml`, no `tests/`). This mirrors the exact gap found and fixed the same way in the `oeteldonk` site repo earlier — reuse that proven setup.

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`

**Interfaces:**
- Produces: a `PwtBase\Tests`-style autoload root for `tests/`, mapped to `HKweb\Plugin\System\GoogleTagManager\Tests\`, and PSR-4 autoloading of `src/` as `HKweb\Plugin\System\GoogleTagManager\`, so Task 2's test can resolve both the class under test and PHPUnit.

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "hkweb/plg-system-googletagmanager",
  "description": "PHP test tooling for the Google Tag Manager Joomla plugin",
  "type": "joomla-plugin",
  "license": "GPL-3.0-or-later",
  "require": {
    "php": ">=8.3",
    "joomla/registry": "^3.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.4"
  },
  "autoload": {
    "psr-4": {
      "HKweb\\Plugin\\System\\GoogleTagManager\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "HKweb\\Plugin\\System\\GoogleTagManager\\Tests\\": "tests/"
    }
  },
  "config": {
    "sort-packages": true
  }
}
```

- [ ] **Step 2: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.4/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
>
    <testsuites>
        <testsuite name="plg_system_googletagmanager">
            <directory>tests</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 3: Write `tests/bootstrap.php`**

```php
<?php

/**
 * PHPUnit bootstrap for plg_system_googletagmanager tests.
 */

declare(strict_types=1);

defined('_JEXEC') or define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 4: Install dependencies**

Run: `composer install --no-interaction`
Expected: `vendor/` created, `phpunit/phpunit` and `joomla/registry` installed with no errors.

- [ ] **Step 5: Add `/vendor` to `.gitignore`**

Read the existing `.gitignore` first (`cat .gitignore`). Add a `/vendor` line if not already present (it likely isn't, since there was no Composer usage before this task).

- [ ] **Step 6: Commit**

```bash
git add composer.json phpunit.xml tests/bootstrap.php .gitignore composer.lock
git commit -m "Add PHPUnit test infrastructure"
```

---

## Task 2: `ConsentBannerConfig` pure config class (TDD)

**Files:**
- Create: `src/ConsentBanner/ConsentBannerConfig.php`
- Test: `tests/ConsentBanner/ConsentBannerConfigTest.php`

**Interfaces:**
- Consumes: `Joomla\Registry\Registry` (constructor arg — the same object type `$this->params` already is on `CMSPlugin`).
- Produces: `ConsentBannerConfig::isEnabled(): bool`, `ConsentBannerConfig::showMarketingCategory(): bool`, `ConsentBannerConfig::getExpirationMilliseconds(): int` — Task 6 (plugin wiring) calls exactly these three methods.

- [ ] **Step 1: Write the failing tests**

Create `tests/ConsentBanner/ConsentBannerConfigTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --testdox`
Expected: `Error: Class "HKweb\Plugin\System\GoogleTagManager\ConsentBanner\ConsentBannerConfig" not found` for all 7 tests.

- [ ] **Step 3: Write the implementation**

Create `src/ConsentBanner/ConsentBannerConfig.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --testdox`
Expected: `OK (7 tests, 7 assertions)`, no warnings or deprecations.

- [ ] **Step 5: Commit**

```bash
git add src/ConsentBanner/ConsentBannerConfig.php tests/ConsentBanner/ConsentBannerConfigTest.php
git commit -m "Add ConsentBannerConfig for the native consent banner feature"
```

---

## Task 3: Plugin manifest — config fields, media folder, nl-NL registration

**Files:**
- Modify: `googletagmanager.xml`

**Interfaces:**
- Produces: three new plugin params (`native_consent_banner`, `consent_category_marketing`, `consent_expiration_days`) that Task 2's `ConsentBannerConfig` already reads by these exact names; a `<media>` declaration so Task 5's JS/CSS files get installed under `media/plg_system_googletagmanager/`; `nl-NL` language file registration for Task 4.

- [ ] **Step 1: Add the new fieldset**

In `googletagmanager.xml`, insert this new `<fieldset>` immediately after the closing `</fieldset>` of the existing `basic` fieldset and before the `advanced` fieldset:

```xml
            <fieldset name="consent_banner" label="PLG_SYSTEM_GOOGLETAGMANAGER_FIELDSET_CONSENT_BANNER">
                <field
                    name="native_consent_banner"
                    type="radio"
                    label="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_LABEL"
                    default="0"
                    layout="joomla.form.field.radio.switcher"
                    description="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_DESC"
                >
                    <option value="0">JNO</option>
                    <option value="1">JYES</option>
                </field>
                <field
                    name="native_banner_note"
                    type="note"
                    description="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_BANNER_NOTE_DESC"
                    showon="native_consent_banner:1"
                />
                <field
                    name="consent_category_marketing"
                    type="radio"
                    label="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_LABEL"
                    default="1"
                    layout="joomla.form.field.radio.switcher"
                    description="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_DESC"
                    showon="native_consent_banner:1"
                >
                    <option value="0">JNO</option>
                    <option value="1">JYES</option>
                </field>
                <field
                    name="consent_expiration_days"
                    type="number"
                    label="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_LABEL"
                    default="365"
                    min="1"
                    description="PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_DESC"
                    showon="native_consent_banner:1"
                />
            </fieldset>
```

- [ ] **Step 2: Add the `<media>` element**

Insert this immediately after the closing `</languages>` tag and before `<config>`:

```xml
    <media folder="media" destination="plg_system_googletagmanager">
        <folder>js</folder>
        <folder>css</folder>
    </media>
```

- [ ] **Step 3: Register the `nl-NL` language files**

Inside the existing `<languages folder="language">` block, add two lines after the existing `en-GB` entries:

```xml
        <language tag="nl-NL">nl-NL/plg_system_googletagmanager.ini</language>
        <language tag="nl-NL">nl-NL/plg_system_googletagmanager.sys.ini</language>
```

- [ ] **Step 4: Validate the XML is well-formed**

Run: `php -r "var_dump(simplexml_load_file('googletagmanager.xml') !== false);"`
Expected: `bool(true)`

- [ ] **Step 5: Commit**

```bash
git add googletagmanager.xml
git commit -m "Add consent banner params, media folder, and nl-NL registration to the manifest"
```

---

## Task 4: Language strings (en-GB + new nl-NL)

**Files:**
- Modify: `language/en-GB/plg_system_googletagmanager.ini`
- Create: `language/nl-NL/plg_system_googletagmanager.ini`
- Create: `language/nl-NL/plg_system_googletagmanager.sys.ini`

**Interfaces:**
- Produces: every `PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_*` and `PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_*`/`PLG_SYSTEM_GOOGLETAGMANAGER_FIELDSET_CONSENT_BANNER` key that Task 3's XML and Task 5's default layout reference by exact name.

- [ ] **Step 1: Append new keys to `language/en-GB/plg_system_googletagmanager.ini`**

Add at the end of the file:

```ini

; Consent banner fieldset
PLG_SYSTEM_GOOGLETAGMANAGER_FIELDSET_CONSENT_BANNER="Consent banner"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_LABEL="Render the consent banner natively?"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_DESC="When enabled, this plugin renders and manages the cookie consent banner itself, independently of whether GTM's client-side script loads. When disabled (default), the plugin behaves exactly as before and assumes a banner is provided elsewhere (e.g. a Custom HTML tag inside the GTM container)."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_BANNER_NOTE_DESC="Remember to disable or remove any existing consent banner tag inside your GTM container when enabling this, to avoid showing two banners at once."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_LABEL="Show Marketing category?"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_DESC="Disable this if the site doesn't run any advertising/marketing tags, to avoid showing an irrelevant category to visitors."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_LABEL="Consent validity (days)"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_DESC="Number of days a visitor's consent choice remains valid before they are asked again. No fixed number of days is legally mandated; 365 is a common default."

; Consent banner content (generic default, overridable per site)
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_BANNER_TEXT="This site uses cookies to understand how it's used. Choose which cookies you're OK with."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_ACCEPT_ALL="Accept all"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REJECT_ALL="Reject all"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CUSTOMIZE="Customize"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_SAVE_PREFERENCES="Save preferences"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REOPEN="Cookie preferences"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORIES_LEGEND="Cookie categories"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL="Functional"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL_DESC="Required for the site to work. Always on."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS="Analytics"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS_DESC="Helps us understand how visitors use this site."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING="Marketing"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING_DESC="Used to show relevant ads on other sites."
```

- [ ] **Step 2: Create `language/nl-NL/plg_system_googletagmanager.sys.ini`**

```ini
; System - Google Tag Manager Plugin
; Copyright (C) 2025 HKweb. All rights reserved.
; License GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html

PLG_SYSTEM_GOOGLETAGMANAGER="Systeem - Google Tag Manager"
PLG_SYSTEM_GOOGLETAGMANAGER_XML_DESCRIPTION="Systeemplugin voor Google Tag Manager-integratie met ondersteuning voor consent mode (Joomla 6+)"
```

- [ ] **Step 3: Create `language/nl-NL/plg_system_googletagmanager.ini`**

Full Dutch translation of every existing key (so the plugin is completely usable in Dutch, not just the new banner strings):

```ini
; System - Google Tag Manager Plugin
; Copyright (C) 2025 HKweb. All rights reserved.
; License GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html

; Basic fieldset
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONTAINER_ID_LABEL="Container-ID"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONTAINER_ID_DESC="Voer je publieke GTM Container-ID in (GTM-XXXX)"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_TAGGING_LABEL="Server-side tagging gebruiken?"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_NOTE_DESC="Bij server-side tagging laadt de GTM-JavaScript-tag nog steeds in de browser van de bezoeker, maar worden trackingverzoeken naar je eigen server-side GTM-container gestuurd in plaats van rechtstreeks naar de servers van Google. Je server verwerkt de data en stuurt die door naar de geconfigureerde bestemmingen. Dit verbetert de data-nauwkeurigheid en vermindert de afhankelijkheid van browser-side tracking. <a href=_QQ_https://developers.google.com/tag-platform/tag-manager/server-side_QQ_ target=_QQ__blank_QQ_ rel=_QQ_noopener noreferrer_QQ_>Meer over server-side tagging</a>"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_DOMAIN_LABEL="Server-side GTM-domein"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_DOMAIN_DESC="Moet overeenkomen met de <strong>Server Container URL</strong> die is ingesteld in GTM Admin &rarr; Container Settings. Voer de volledige URL in beginnend met https:// (bijv. https://sst.example.com). GTM-scripts worden dan vanaf dit domein geladen in plaats van googletagmanager.com."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SCRIPT_LOADER_URL_LABEL="Script Loader-URL"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SCRIPT_LOADER_URL_DESC="Optioneel. De basis-URL waarvandaan <code>gtm.js</code> wordt geserveerd als die afwijkt van het sGTM-domein hierboven. Gebruik dit wanneer je sGTM-provider het loader-script via je eigen domein proxyt (bijv. Stape Custom Loader power-up met _QQ_Use original GTM code_QQ_ aan). Voorbeeld: https://example.com/mos &mdash; de noscript-fallback gebruikt altijd het sGTM-domein hierboven."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_LOADER_NOTE_DESC="<strong>Uitgebreide adblocker-bescherming</strong> &mdash; gebruik dit wanneer je sGTM-provider geobfusceerde loader-scripts ondersteunt (bijv. Stape Custom Loader met _QQ_Enhanced ad blocker protection_QQ_ aan). Voer de Container Identifier in zodat de plugin automatisch de geobfusceerde bestandsnaam en query string genereert, of vul hieronder handmatig de bestandsnaam en query string in."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_STAPE_LOADER_SCRIPT_LABEL="Stape Custom Loader-script"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_STAPE_LOADER_SCRIPT_DESC="Plak hier de volledige code-snippet die door de Stape Custom Loader power-up is gegenereerd. Je kunt zowel de losse JavaScript als de volledige snippet inclusief <code>&lt;script&gt;</code>-tags en HTML-commentaar plakken &mdash; beide worden automatisch afgehandeld. Indien ingevuld heeft dit voorrang boven alle andere loader-instellingen (API-ophaling, Container Identifier en handmatige velden hieronder)."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONTAINER_IDENTIFIER_LABEL="Container Identifier"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONTAINER_IDENTIFIER_DESC="De container-identifier van je sGTM-provider (bijv. fjvqgtsh bij Stape). De plugin gebruikt dit om automatisch de geobfusceerde scriptnaam en query string te genereren &mdash; geen handmatig kopiëren nodig. Laat leeg als je de bestandsnaam en parameters hieronder handmatig invult."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_STAPE_API_KEY_LABEL="Stape API-sleutel"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_STAPE_API_KEY_DESC="Optioneel. Je Stape Container API-sleutel of een persoonlijk API-token, gebruikt om je container te identificeren bij het aanroepen van Stape's API. Indien ingevuld haalt de plugin het exacte geobfusceerde loader-script rechtstreeks op via Stape's API in plaats van het lokaal te genereren. Als dit leeg is, wordt de Container Identifier hierboven gebruikt. Te vinden bij Stape &rarr; Container Settings."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_COOKIE_KEEPER_LABEL="Cookie Keeper"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_COOKIE_KEEPER_DESC="Schakel ondersteuning voor Safari ITP in via Stape's Cookie Keeper power-up. Indien ingeschakeld laden Safari 16.4+-bezoekers het GTM-script via een <code>kp</code>-voorvoegsel-identifier. Stape's server herkent dit voorvoegsel en zet een server-side cookie met een geldigheid van 2 jaar, waarmee Safari's ITP-limiet van 7 dagen voor JavaScript-cookies wordt omzeild. Vereist dat hierboven een Container Identifier is ingesteld."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_LOADER_MANUAL_NOTE_DESC="<strong>Handmatige override</strong> &mdash; vul de twee velden hieronder alleen in als je de geobfusceerde waarden rechtstreeks uit de gegenereerde snippet wilt plakken. Indien ingevuld hebben deze voorrang boven de Container Identifier hierboven."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_SCRIPT_FILENAME_LABEL="Aangepaste scriptbestandsnaam"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_SCRIPT_FILENAME_DESC="De geobfusceerde JS-bestandsnaam uit de gegenereerde snippet (bijv. 1afjvqgtsh.js). Dit veld en Aangepaste scriptparameters moeten samen worden ingevuld."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_SCRIPT_PARAMS_LABEL="Aangepaste scriptparameters"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CUSTOM_SCRIPT_PARAMS_DESC="De geobfusceerde query string uit de gegenereerde snippet (bijv. c1ky2=ARdJICU7...). Dit veld en Aangepaste scriptbestandsnaam moeten samen worden ingevuld."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_PATH_NOTE_DESC="<strong>Pad-voorvoegsel &mdash; slechts in zeldzame gevallen nodig.</strong> Laat dit leeg voor de standaard sGTM-opzet. Vul dit alleen in als je sGTM-instantie scripts serveert vanaf een subpad (bijv. /gtm) in plaats van de root van het domein. Dit is <em>niet</em> hetzelfde als een custom loader-pad dat binnen een GTM-tag is ingesteld."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_PATH_LABEL="Server-side GTM-pad"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_SERVER_SIDE_PATH_DESC="Optioneel subpad-voorvoegsel (bijv. /gtm). Laat leeg als je sGTM-scripts vanaf de root van het domein worden geserveerd."

; Consent banner fieldset
PLG_SYSTEM_GOOGLETAGMANAGER_FIELDSET_CONSENT_BANNER="Cookiemelding"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_LABEL="Cookiemelding native tonen?"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_CONSENT_BANNER_DESC="Indien ingeschakeld rendert en beheert deze plugin de cookiemelding zelf, onafhankelijk van of het client-side script van GTM laadt. Indien uitgeschakeld (standaard) gedraagt de plugin zich zoals voorheen en wordt aangenomen dat de melding elders geregeld is (bijv. een Custom HTML-tag in de GTM-container)."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_BANNER_NOTE_DESC="Denk eraan om een eventuele bestaande cookiemelding-tag in je GTM-container uit te schakelen of te verwijderen wanneer je dit inschakelt, om te voorkomen dat er twee meldingen tegelijk verschijnen."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_LABEL="Categorie Marketing tonen?"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_CATEGORY_MARKETING_DESC="Schakel dit uit als de site geen advertentie-/marketingtags gebruikt, om bezoekers geen irrelevante categorie te tonen."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_LABEL="Geldigheid toestemming (dagen)"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_CONSENT_EXPIRATION_DAYS_DESC="Aantal dagen dat de keuze van een bezoeker geldig blijft voordat opnieuw gevraagd wordt. Er is geen wettelijk vastgesteld aantal dagen; 365 is een gangbare standaardwaarde."

; Consent banner content (generieke standaardtekst, per site te overschrijven)
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_BANNER_TEXT="Deze site gebruikt cookies om te begrijpen hoe hij gebruikt wordt. Kies welke cookies je goedvindt."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_ACCEPT_ALL="Alles accepteren"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REJECT_ALL="Alles weigeren"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CUSTOMIZE="Voorkeuren aanpassen"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_SAVE_PREFERENCES="Voorkeuren opslaan"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_REOPEN="Cookievoorkeuren"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORIES_LEGEND="Cookiecategorieën"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL="Functioneel"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_FUNCTIONAL_DESC="Nodig om de site te laten werken. Altijd aan."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS="Statistieken"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_ANALYTICS_DESC="Helpt ons begrijpen hoe bezoekers deze site gebruiken."
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING="Marketing"
PLG_SYSTEM_GOOGLETAGMANAGER_CONSENT_CATEGORY_MARKETING_DESC="Gebruikt om relevante advertenties op andere sites te tonen."

; Advanced fieldset
PLG_SYSTEM_GOOGLETAGMANAGER_FIELDSET_ADVANCED="Geavanceerd"

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_GTM_ENVIRONMENTS_NOTE_DESC="Met GTM-omgevingen kun je een containerversie testen voordat je die live publiceert. Beide velden hieronder moeten samen worden ingevuld. Te vinden bij <strong>GTM Admin &rarr; Environments &rarr; Actions &rarr; Get snippet</strong> &mdash; kopieer de waarden van de query-parameters <code>gtm_auth</code> en <code>gtm_preview</code> uit de snippet-URL. Laat beide leeg om de gepubliceerde live-container te gebruiken."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_GTM_AUTH_LABEL="GTM Environment Auth Token"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_GTM_AUTH_DESC="De gtm_auth-token uit de snippet-URL van de omgeving (bijv. ABC123XYZ). Dit veld en GTM Environment Name moeten samen worden ingevuld."

PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_GTM_PREVIEW_LABEL="GTM Environment Name"
PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_GTM_PREVIEW_DESC="De gtm_preview-waarde uit de snippet-URL van de omgeving (bijv. env-2). Dit veld en GTM Environment Auth Token moeten samen worden ingevuld."
```

- [ ] **Step 4: Validate both new ini files parse correctly**

Run: `php -r "var_dump(parse_ini_file('language/nl-NL/plg_system_googletagmanager.ini') !== false); var_dump(parse_ini_file('language/nl-NL/plg_system_googletagmanager.sys.ini') !== false);"`
Expected: `bool(true)` twice.

- [ ] **Step 5: Commit**

```bash
git add language/en-GB/plg_system_googletagmanager.ini language/nl-NL/plg_system_googletagmanager.ini language/nl-NL/plg_system_googletagmanager.sys.ini
git commit -m "Add consent banner strings and full nl-NL translation"
```

---

## Task 5: Default layout, JS behavior, and CSS

**Files:**
- Create: `layouts/consent-banner.php`
- Create: `media/js/consent-banner.js`
- Create: `media/css/consent-banner.css`

**Interfaces:**
- Consumes: `$displayData['showMarketing']` (bool) and `$displayData['expirationMilliseconds']` (int) — matches exactly what Task 6's `renderConsentBanner()` passes in.
- Produces: the `data-consent-banner` / `data-consent-action` / `data-consent-category` / `data-consent-icon` / `data-consent-expiration-ms` DOM contract that both this default layout and any future site override must use, and that `consent-banner.js` reads generically (it never assumes specific markup beyond these attributes).

- [ ] **Step 1: Write `layouts/consent-banner.php`**

```php
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
```

- [ ] **Step 2: Write `media/js/consent-banner.js`**

```js
(function () {
	'use strict';

	var STORAGE_KEY = 'consentMode';

	function readStoredConsent() {
		var raw = null;

		try {
			raw = window.localStorage.getItem(STORAGE_KEY);
		} catch (e) {
			return null;
		}

		if (!raw) {
			return null;
		}

		var stored;

		try {
			stored = JSON.parse(raw);
		} catch (e) {
			return null;
		}

		if (stored.expiration && Date.now() > stored.expiration) {
			return null;
		}

		return stored.consentMode || stored;
	}

	function writeConsent(consentMode, expirationMilliseconds) {
		var value = {
			consentMode: consentMode,
			expiration: Date.now() + expirationMilliseconds
		};

		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
		} catch (e) {
			// localStorage unavailable (private browsing, quota) - consent still applies
			// for this page load via the gtag update call below.
		}

		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', consentMode);
		}
	}

	function buildConsent(root, forcedGrant) {
		var analyticsInput = root.querySelector('[data-consent-category="analytics"]');
		var marketingInput = root.querySelector('[data-consent-category="marketing"]');

		var analyticsGranted = typeof forcedGrant === 'boolean' ? forcedGrant : !!(analyticsInput && analyticsInput.checked);
		var marketingGranted = typeof forcedGrant === 'boolean' ? forcedGrant : !!(marketingInput && marketingInput.checked);

		return {
			analytics_storage: analyticsGranted ? 'granted' : 'denied',
			ad_storage: marketingGranted ? 'granted' : 'denied',
			ad_user_data: marketingGranted ? 'granted' : 'denied',
			ad_personalization: marketingGranted ? 'granted' : 'denied',
			personalization_storage: 'denied',
			functionality_storage: 'granted',
			security_storage: 'granted'
		};
	}

	function init() {
		var root = document.querySelector('[data-consent-banner]');

		if (!root) {
			return;
		}

		var expirationMilliseconds = parseInt(root.getAttribute('data-consent-expiration-ms'), 10) || (365 * 24 * 60 * 60 * 1000);
		var hasConsent = readStoredConsent() !== null;

		function setOpen(open) {
			root.classList.toggle('is-open', open);
			root.setAttribute('aria-hidden', open ? 'false' : 'true');
		}

		setOpen(!hasConsent);

		root.addEventListener('click', function (event) {
			var target = event.target.closest('[data-consent-action]');

			if (!target) {
				return;
			}

			var action = target.getAttribute('data-consent-action');

			if (action === 'accept-all') {
				writeConsent(buildConsent(root, true), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'reject-all') {
				writeConsent(buildConsent(root, false), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'save') {
				writeConsent(buildConsent(root), expirationMilliseconds);
				setOpen(false);
			} else if (action === 'open-preferences') {
				root.classList.add('is-preferences-open');
			}
		});

		var icon = document.querySelector('[data-consent-icon]');

		if (icon) {
			icon.addEventListener('click', function () {
				root.classList.add('is-preferences-open');
				setOpen(true);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
```

- [ ] **Step 3: Write `media/css/consent-banner.css`**

```css
.plg-consent-banner {
	display: none;
	position: fixed;
	inset-inline: 0;
	inset-block-end: 0;
	z-index: 9999;
	background: #ffffff;
	color: #1a1a1a;
	border-block-start: 1px solid #cccccc;
	padding: 1rem;
	font-family: sans-serif;
}

.plg-consent-banner.is-open {
	display: block;
}

.plg-consent-banner__preferences {
	display: none;
}

.plg-consent-banner.is-preferences-open .plg-consent-banner__preferences {
	display: block;
}

.plg-consent-banner.is-preferences-open .plg-consent-banner__prompt {
	display: none;
}

.plg-consent-banner__actions {
	display: flex;
	gap: 0.5rem;
	margin-block-start: 0.5rem;
}

.plg-consent-banner__actions button {
	flex: 1 1 auto;
	padding: 0.5rem 1rem;
	font-size: 1rem;
	border: 1px solid #1a1a1a;
	background: #ffffff;
	color: #1a1a1a;
	cursor: pointer;
}

.plg-consent-banner__category {
	margin-block-end: 0.75rem;
}

.plg-consent-banner__icon {
	display: block;
	position: fixed;
	inset-inline-start: 1rem;
	inset-block-end: 1rem;
	z-index: 9998;
	width: 3rem;
	height: 3rem;
	border-radius: 50%;
	border: 1px solid #1a1a1a;
	background: #ffffff;
	font-size: 1.5rem;
	cursor: pointer;
}
```

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l layouts/consent-banner.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add layouts/consent-banner.php media/js/consent-banner.js media/css/consent-banner.css
git commit -m "Add default consent banner layout, JS behaviour, and CSS"
```

---

## Task 6: Wire the banner into `GoogleTagManager.php`

**Files:**
- Modify: `src/Extension/GoogleTagManager.php`

**Interfaces:**
- Consumes: `ConsentBannerConfig` (Task 2) — `isEnabled()`, `showMarketingCategory()`, `getExpirationMilliseconds()`.
- Consumes: the JS/CSS media assets and default layout from Task 5, and the `<media destination="plg_system_googletagmanager">` declaration from Task 3 (so `media/plg_system_googletagmanager/js/consent-banner.js` / `.../css/consent-banner.css` exist once installed).

- [ ] **Step 1: Add imports and a cached config property**

At the top of `src/Extension/GoogleTagManager.php`, add to the existing `use` block:

```php
use HKweb\Plugin\System\GoogleTagManager\ConsentBanner\ConsentBannerConfig;
use Joomla\CMS\Layout\FileLayout;
```

Add this property near the other cached properties (e.g. right after `$cookieKeeperResolved`):

```php
	/**
	 * Cached consent banner configuration
	 *
	 * @var    ConsentBannerConfig|null
	 * @since  26.25.00
	 */
	private ?ConsentBannerConfig $consentBannerConfig = null;

	/**
	 * Get the consent banner configuration
	 *
	 * @return  ConsentBannerConfig
	 *
	 * @since   26.25.00
	 */
	private function getConsentBannerConfig(): ConsentBannerConfig
	{
		return $this->consentBannerConfig ??= new ConsentBannerConfig($this->params);
	}
```

- [ ] **Step 2: Register the JS/CSS assets in `onBeforeCompileHead()`**

Find the end of `onBeforeCompileHead()` — it currently ends with:

```php
		$document->getWebAssetManager()->addInlineScript($headScript);
	}
```

Change it to:

```php
		$document->getWebAssetManager()->addInlineScript($headScript);

		if ($this->getConsentBannerConfig()->isEnabled())
		{
			$document->getWebAssetManager()
				->registerAndUseScript('plg_system_googletagmanager.consent-banner', 'plg_system_googletagmanager/consent-banner.js', [], ['defer' => true])
				->registerAndUseStyle('plg_system_googletagmanager.consent-banner', 'plg_system_googletagmanager/consent-banner.css');
		}
	}
```

- [ ] **Step 3: Add the rendering method**

Add this new private method anywhere after `onBeforeCompileHead()` and before `onAfterRender()`:

```php
	/**
	 * Render the consent banner markup, preferring a site template override.
	 *
	 * Uses an explicit include path list (site override first, this plugin's
	 * bundled default second) rather than LayoutHelper::render()'s $basePath
	 * argument, because FileLayout::getDefaultIncludePaths() gives an explicit
	 * $basePath *higher* priority than the site template override path - which
	 * would make the plugin default always win. Mirrors the pattern used by
	 * Joomla core's own plg_system_stats (see AbstractStatsField::getRenderer()).
	 *
	 * @param   ConsentBannerConfig  $config  The resolved consent banner configuration
	 *
	 * @return  string  The rendered banner HTML
	 *
	 * @since   26.25.00
	 */
	private function renderConsentBanner(ConsentBannerConfig $config): string
	{
		$template = $this->getApplication()->getTemplate();

		$renderer = new FileLayout('consent-banner');
		$renderer->setIncludePaths([
			JPATH_THEMES . '/' . $template . '/html/layouts/googletagmanager',
			JPATH_PLUGINS . '/system/googletagmanager/layouts',
		]);

		return $renderer->render([
			'showMarketing'          => $config->showMarketingCategory(),
			'expirationMilliseconds' => $config->getExpirationMilliseconds(),
		]);
	}
```

- [ ] **Step 4: Inject the rendered banner in `onAfterRender()`**

Find the end of `onAfterRender()` — it currently ends with:

```php
		$buffer = $application->getBody();
		$buffer = (string) preg_replace('/<body(\s[^>]*)?>/i', "$0\n{$bodyScript}", $buffer, 1);

		$application->setBody($buffer);
	}
```

Change it to:

```php
		$buffer = $application->getBody();
		$buffer = (string) preg_replace('/<body(\s[^>]*)?>/i', "$0\n{$bodyScript}", $buffer, 1);

		$consentBannerConfig = $this->getConsentBannerConfig();

		if ($consentBannerConfig->isEnabled())
		{
			$bannerHtml = $this->renderConsentBanner($consentBannerConfig);
			$buffer     = (string) preg_replace('/<\/body>/i', $bannerHtml . '$0', $buffer, 1);
		}

		$application->setBody($buffer);
	}
```

- [ ] **Step 5: Verify PHP syntax**

Run: `php -l src/Extension/GoogleTagManager.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Run the full test suite**

Run: `vendor/bin/phpunit --testdox`
Expected: `OK (7 tests, 7 assertions)` — unchanged from Task 2, since this task doesn't add new pure-logic units (the wiring itself needs a real Joomla runtime to exercise, covered by Task 7's manual verification).

- [ ] **Step 7: Commit**

```bash
git add src/Extension/GoogleTagManager.php
git commit -m "Wire the native consent banner into onBeforeCompileHead/onAfterRender"
```

---

## Task 7: Manual end-to-end verification in the oeteldonk ddev environment

This can't be automated (it requires a real Joomla request/response cycle and a browser), but it is the actual proof the feature works — do not skip it.

**Files:** none (verification only, against the `oeteldonk` site's local ddev environment)

- [x] **Step 1: Symlink or copy this plugin's dev version into the oeteldonk ddev install**

The site's `public_html/plugins/system/googletagmanager/` currently holds an older installed copy. For local testing without a full package/install cycle, copy this repo's `src/`, `layouts/`, `media/`, `language/`, `services/`, and `googletagmanager.xml` over that folder in the oeteldonk checkout (back up or `git stash` the site's copy first, since `public_html` there is a git-tracked, production-synced path — do not commit this temporary swap).

- [x] **Step 2: Copy the media folder to the Joomla-served location**

Joomla serves plugin media from `public_html/media/plg_system_googletagmanager/`, not from inside the plugin folder itself — that only happens via the installer's `<media>` copy step. For local testing, manually copy this repo's `media/js/` and `media/css/` into `public_html/media/plg_system_googletagmanager/js/` and `.../css/` in the oeteldonk checkout.

- [x] **Step 3: Enable the feature**

In the oeteldonk ddev site's administrator (`https://oeteldonk.ddev.site/administrator`), open the Google Tag Manager plugin's settings and set "Render the consent banner natively?" to Yes, leaving Marketing category and expiration at their defaults.

Done via a direct `pwt_extensions.params` update in the ddev database instead of the admin UI (personal Joomla admin credentials weren't available to this session, and the param was already set to `"1"` from an earlier session's testing) — same effect, verified by reading the row back.

- [x] **Step 4: Verify first-visit behaviour**

In a private/incognito browser window, visit the ddev site's homepage. Expected: the banner is visible at the bottom of the page, with "Accept all" and "Reject all" rendered as equally-sized/styled buttons (not one more prominent than the other), plus a "Customize" option. The fixed cookie icon is visible bottom-left.

Confirmed the `[data-consent-banner]` dialog is present in the DOM with `open === true` on a fresh visit (localStorage cleared). Not confirmed visually in a screenshot — see the important finding after Step 8 below: a second, pre-existing `.cc-modal` dialog (from a live Custom HTML tag in the real GTM-N78DQV container) renders on top and visually hides this one, so the layout/styling expectations in this step couldn't be eyeballed. Functional behavior verified directly against the DOM instead (Steps 5–6).

- [x] **Step 5: Verify "Reject all"**

Click "Reject all". Expected: the banner closes; `localStorage.getItem('consentMode')` (via browser devtools) contains `{"consentMode":{"analytics_storage":"denied","ad_storage":"denied",...},"expiration":<a future timestamp>}`.

Confirmed exactly as expected: `analytics_storage`/`ad_storage`/`ad_user_data`/`ad_personalization`/`personalization_storage` all `"denied"`, `functionality_storage`/`security_storage` `"granted"`, dialog `open` became `false`.

- [x] **Step 6: Verify the reopen icon and "Accept all"**

Click the fixed cookie icon. Expected: the preferences panel reopens. Click "Accept all". Expected: `localStorage.getItem('consentMode')` now shows all signals as `"granted"` except `personalization_storage` (always `"denied"`), and the banner closes again.

Confirmed. The icon click set `open === true` and added `is-preferences-open`. Also exercised the "save" path directly (checked analytics + marketing, clicked Save): correct mixed granted/denied result. Then separately confirmed plain "Accept all": all signals `"granted"` except `personalization_storage` (`"denied"`), dialog closed, and `typeof window.gtag === 'function'` confirming the consent-mode wiring is live.

- [x] **Step 7: Verify GTM independence**

In browser devtools, block requests to `googletagmanager.com`, then reload the page in a fresh private window (clear localStorage first). Expected: the banner still appears and is fully functional, proving it no longer depends on `gtm.js` loading — this is the actual bug being fixed.

Not exercised at the network level — the browser tooling available to this session had no request-blocking capability. Verified statically instead: `onBeforeCompileHead()` registers the consent-banner script/style, and `onAfterRender()` renders and injects the banner, both gated only on `getConsentBannerConfig()->isEnabled()`, with no code path reading `gtm.js`'s load outcome or any GTM-script-derived state. Independence follows from the control flow, not from an assumption.

**Important finding (unrelated to this plan's code, but discovered during this step):** the site's homepage currently renders a *second*, pre-existing consent dialog (`.cc-modal`, dialect-styled "Kuukskes van dn Veldwachter" UI with 4 categories) on top of this native one, hiding it. That dialog comes from a live Custom HTML tag in the real, published GTM-N78DQV container (fetched from Google's servers even in local dev) — exactly the "two banners at once" scenario `PLG_SYSTEM_GOOGLETAGMANAGER_FIELD_NATIVE_BANNER_NOTE_DESC` warns admins about. This plugin has no way to reach into GTM's own container config; someone with GTM console access needs to disable/remove that old tag before this native banner can be the site's only banner. Flagged to the human partner, not fixed here.

- [x] **Step 8: Revert the temporary local swap**

Run `git status` / `git diff` inside the oeteldonk checkout's `public_html/plugins/system/googletagmanager/` and `public_html/media/plg_system_googletagmanager/` and discard or stash the temporary manual copy — none of this local testing swap should be committed to the `oeteldonk` repo. The real deployment happens via a proper plugin release + update in Joomla (see the spec's Rollout section), not via this manual file copy.

Done: `git checkout --` on the three modified tracked files, `rm -rf` on the new untracked files/folders, confirmed clean with `git status --short` on both affected paths. Note: this pass tested against the site's *main* checkout (`oeteldonk`, `feature/297-overzichten`) rather than a fresh clone, since that's what ddev serves; the finished feature also already lives, committed, on that repo's own `feature/cookie-consent-banner` branch (identical byte-for-byte to this repo's version) from an earlier porting step — that commit was untouched by this temporary swap.
