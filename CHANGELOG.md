# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to the versioning scheme `YY.WW.NN` (Year.Week.Increment).

## [26.14.08] - 2026-04-03

### Added
- `container_identifier` config field: enter the identifier from your sGTM provider (e.g. Stape) and the plugin auto-generates the obfuscated filename and query string
- `generateLoaderFromIdentifier()`: replicates Stape's Custom Loader obfuscation algorithm using a seeded `Random\Engine\Mt19937` RNG — values are stable across requests for a given identifier + GTM ID pair without needing a cache
- `LOADER_CHARS` class constant for the alphanumeric character set used in obfuscation

### Changed
- `getCustomLoader()` now checks manual fields first (explicit override), then falls back to identifier-based auto-generation
- Custom loader note and manual override note updated to explain both configuration paths

## [26.14.07] - 2026-04-03

### Added
- `custom_script_filename` and `custom_script_params` config fields for enhanced ad blocker protection (e.g. Stape Custom Loader with "Enhanced ad blocker protection" enabled)
- `getCustomLoader()` method returning the obfuscated filename + params pair when both are configured, or null to fall back to the standard loader
- When custom loader is active the plugin generates a stripped IIFE (no `dl` variable) matching the obfuscated snippet format exactly

## [26.14.06] - 2026-04-03

### Added
- `script_loader_url` config field: optional base URL from which `gtm.js` is served when it differs from the sGTM domain (e.g. Stape Custom Loader with "Use original GTM code" enabled)
- `getScriptLoaderUrl()` method: returns the script loader URL when configured, falls back to `getGtmBaseUrl()`

### Changed
- Head script now uses `getScriptLoaderUrl()` instead of `getGtmBaseUrl()`, allowing the GTM loader and the noscript iframe to be served from different domains

## [26.14.05] - 2026-04-03

### Fixed
- Release workflow now extracts the curated changelog section from CHANGELOG.md instead of overwriting it with auto-generated git log output

## [26.14.04] - 2026-04-03

### Added
- Language files (`language/en-GB/plg_system_googletagmanager.ini` and `.sys.ini`) for full i18n support
- Release workflow now includes `language/` folder in the installable ZIP

### Changed
- All hardcoded UI strings in `googletagmanager.xml` replaced with language constant keys
- `<name>` and `<description>` in the manifest now use translatable keys
- Yes/No radio options use built-in Joomla constants (`JYES`/`JNO`)
- `<languages>` section added to manifest so Joomla installs the language files on extension install

## [26.14.03] - 2026-04-03

### Added
- `server_side_path` config field to support sGTM instances served from a custom path prefix
- `gtm_auth` and `gtm_preview` config fields for targeting specific GTM environments
- `getGtmBaseUrl()` helper combining domain and path into a single base URL
- `getGtmEnvironmentParams()` helper returning environment query params (gtm_auth, gtm_preview, gtm_cookies_win)

### Changed
- `$gtmBaseUrl` and `$gtmId` are now properly escaped before JS/HTML interpolation (`addcslashes` for JS string literals, `htmlspecialchars` for HTML attributes)
- `getServerSideDomain()` now enforces `https://` on the configured sGTM domain
- Server-side tagging admin note corrected to accurately describe the sGTM data flow
- `server_side_domain` field description updated to explicitly require `https://`

## [26.14.02] - 2026-04-02

### Added
- Server-side tagging support with configurable custom sGTM domain
- Typed class constant `GTM_DEFAULT_BASE_URL` (PHP 8.3) replacing duplicated URL string

### Changed
- `#[\Override]` attribute added to `getSubscribedEvents()` for explicit interface implementation

### Fixed
- `getServerSideDomain()` caching now correctly avoids repeated param lookups when server-side tagging is disabled

## [26.04.04] - 2026-01-20

### Changed
- Service provider uses standard Joomla pattern with `Factory::getApplication()`
- Added clarifying comment that Factory usage is acceptable in service provider infrastructure
- Follows Joomla core plugin conventions for Application injection

### Removed
- Unused `CMSApplicationInterface` import from plugin class

## [26.04.03] - 2026-01-20

### Added
- GTM Container ID caching to improve performance and avoid repeated parameter lookups

### Changed
- Improved code formatting with cleaner heredoc syntax for inline JavaScript
- Enhanced regex pattern for body tag injection (uses `$0` and limit of 1)
- Added explicit type casting for preg_replace result
- Application object now cached in methods to reduce method calls
- Removed unnecessary HTML comments from inline scripts for cleaner output

### Fixed
- GitHub Actions workflow now correctly checks out main branch before committing update.xml

## [26.04.02] - 2026-01-20

### Changed
- **BREAKING**: Minimum Joomla version increased from 4.0 to 6.0
- **BREAKING**: Minimum PHP version increased from 8.1 to 8.3.0
- Plugin now targets Joomla 6.x only
- Service provider now uses DI for Application injection instead of Factory pattern
- Improved type safety with `HtmlDocument` type checks instead of string comparison
- Protocol-relative URL changed to explicit HTTPS for better security

### Removed
- Support for Joomla 4.x and 5.x
- Factory::getApplication() usage in favor of proper DI

### Fixed
- Noscript iframe now uses `https://` instead of protocol-relative `//` URL

## [26.04.01] - 2026-01-20

### Fixed
- Plugin installation error "Field 'element' doesn't have a default value"
- Added `plugin="googletagmanager"` attribute to services folder in manifest

## [26.04.00] - 2026-01-20

### Added
- Joomla update system integration via `update.xml`
- Update server configuration in plugin manifest
- CLAUDE.md documentation for development guidance

### Changed
- GitHub Actions workflow now automatically updates `update.xml` on release
- Improved release automation with automatic version updates

## [26.03.00] - 2026-01-20

### Added
- Initial release of Google Tag Manager plugin
- Google Tag Manager integration with consent mode support
- GDPR-compliant consent mode implementation
- Configurable GTM Container ID
- Automatic script injection in HTML head
- Noscript fallback support for users without JavaScript
- Frontend-only execution to prevent admin interference

### Technical Details
- Namespace: HKweb\Plugin\System\GoogleTagManager
- Minimum Joomla version: 4.0
- Minimum PHP version: 8.1
- Uses Joomla's event subscriber interface
- Implements onBeforeCompileHead and onAfterRender events

[26.14.02]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.14.02
[26.04.04]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.04
[26.04.03]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.03
[26.04.02]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.02
[26.04.01]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.01
[26.04.00]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.00
[26.03.00]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.03.00
