# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to the versioning scheme `YY.WW.NN` (Year.Week.Increment).

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

[26.04.02]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.02
[26.04.01]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.01
[26.04.00]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.04.00
[26.03.00]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.03.00
