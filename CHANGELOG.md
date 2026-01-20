# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to the versioning scheme `YY.WW.NN` (Year.Week.Increment).

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

[26.03.00]: https://github.com/hans2103/plg_system_googletagmanager/releases/tag/26.03.00
