# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Joomla 6.x system plugin that integrates Google Tag Manager (GTM) with GDPR-compliant consent mode support. The plugin is frontend-only and injects GTM tracking code into the HTML head and body sections of Joomla sites.

**Key characteristics:**
- Namespace: `HKweb\Plugin\System\GoogleTagManager`
- Minimum Joomla: 6.0
- Minimum PHP: 8.3.0
- Uses strict typing throughout (`declare(strict_types=1);`)
- Implements Joomla's modern event subscriber interface

## Architecture

### Core Components

**services/provider.php**
- Service provider for Joomla's DI container
- Registers the plugin with the dependency injection system
- Handles plugin instantiation with proper dispatcher and configuration
- Uses `Factory::getApplication()` following standard Joomla plugin pattern (Factory is acceptable in service provider infrastructure)

**src/Extension/GoogleTagManager.php**
- Main plugin class implementing `SubscriberInterface`
- Subscribes to two events:
  - `onBeforeCompileHead`: Injects consent mode initialization and GTM script into `<head>`
  - `onAfterRender`: Injects noscript fallback iframe immediately after `<body>` tag
- Frontend-only execution (checks `$this->getApplication()->isClient('site')`)
- HTML document type validation before script injection

### GTM Integration Flow

1. **Consent Mode Initialization** (onBeforeCompileHead):
   - Creates global `dataLayer` array
   - Defines `gtag()` helper function
   - Reads consent preferences from localStorage (`consentMode`)
   - Sets default consent states (ad storage denied, analytics granted)
   - Pushes `gtm_consent_update` event

2. **GTM Script Loading** (onBeforeCompileHead):
   - Injects standard GTM loader script
   - Uses container ID from plugin configuration

3. **Noscript Fallback** (onAfterRender):
   - Uses regex to find `<body>` tag in rendered HTML
   - Injects iframe fallback immediately after opening tag
   - Provides tracking for users without JavaScript

## Version Numbering

Uses `YY.WW.NN` format:
- `YY`: Last 2 digits of year (e.g., 26 for 2026)
- `WW`: ISO week number
- `NN`: Incremental counter starting at 00 each week

Example: `26.03.00` → `26.03.01` → `26.04.00`

**Version must be updated manually in:**
- `googletagmanager.xml` (line 10)

**Automatically updated by GitHub Actions:**
- `update.xml` (version and downloadurl elements)

## Git Workflow

Follows git-flow branching model:
- `main`: Production releases only
- `develop`: Integration branch (default development branch)
- `feature/*`: New features (branch from develop)
- `release/*`: Release preparation (branch from develop)
- `hotfix/*`: Production fixes (branch from main)

## Joomla Update System

The plugin uses Joomla's built-in update system via `update.xml`:
- Update server URL is configured in `googletagmanager.xml` (`<updateservers>` section)
- Points to: `https://raw.githubusercontent.com/hans2103/plg_system_googletagmanager/main/update.xml`
- The `update.xml` file is automatically updated by GitHub Actions when a release is created
- Joomla checks this URL for available updates

The GitHub Actions workflow automatically updates `update.xml` with:
- New version number
- New download URL pointing to the GitHub release ZIP
- Note: Manual updates are only needed for compatibility changes (targetplatform, php_minimum)

## Release Process

Releases are fully automated via GitHub Actions:

```bash
# Start a new release
git flow release start 26.04.00

# Update version in:
# - googletagmanager.xml (line 10)
# - CHANGELOG.md with changes

# Finish the release (creates tag, merges to main and develop)
git flow release finish 26.04.00

# Push everything including tags
git push origin main develop --tags
```

The GitHub Actions workflow (.github/workflows/release.yml) automatically:
1. Creates a GitHub release
2. Builds ZIP package: `plg_system_googletagmanager-{version}.zip`
3. Includes only files needed for installation:
   - `googletagmanager.xml` (plugin manifest)
   - `services/` (DI container configuration)
   - `src/` (plugin source code)
4. Excludes documentation files: `CLAUDE.md`, `README.md`, `CHANGELOG.md`, `update.xml`, `.github/`
5. Generates changelog from git commits
6. Attaches package to release
7. Updates `update.xml` with new version and download URL
8. Commits and pushes `update.xml` to main branch

## Code Style Guidelines

- Use strict typing: `declare(strict_types=1);` at top of all PHP files
- Follow Joomla coding standards
- Use type hints for all parameters and return types
- Final classes where appropriate (main plugin class is `final`)
- Proper PHPDoc blocks with `@since` tags showing version number
- Defensive checks: validate application client, document type, and configuration before operations

## Plugin Configuration

The plugin reads a single configuration parameter:
- `container_id`: GTM Container ID (format: GTM-XXXXXXX)

Configuration is accessed via `$this->params->get('container_id', '')` and validated to ensure it's non-empty before use.

## Important Implementation Details

**Consent Mode Defaults:**
- Denied: `ad_storage`, `ad_user_data`, `ad_personalization`
- Granted: `analytics_storage`, `personalization_storage`, `functionality_storage`, `security_storage`

**Script Injection Methods:**
- Head scripts use: `$document->getWebAssetManager()->addInlineScript()`
- Body script uses: Buffer manipulation with `preg_replace()` on `<body>` tag

**Security Considerations:**
- Container ID is escaped when inserted into JavaScript
- Frontend-only execution prevents admin interference
- Uses Joomla's standard security check: `defined('_JEXEC') or die;`
