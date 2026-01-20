# Joomla System Plugin - Google Tag Manager

A Joomla 6.x system plugin for Google Tag Manager integration with consent mode support for GDPR compliance.

## Features

- Google Tag Manager integration
- GDPR consent mode support
- Automatic GTM script injection in HTML head
- Noscript fallback support
- Configurable GTM Container ID
- Frontend-only execution

## Requirements

- Joomla 6.x
- PHP 8.3 or higher

## Installation

1. Download the latest release package from the [Releases](../../releases) page
2. In Joomla administrator, go to **Extensions** → **Manage** → **Install**
3. Upload and install the package
4. Go to **System** → **Plugins**
5. Find "System - Google Tag Manager" and enable it
6. Configure your GTM Container ID in the plugin settings

## Configuration

After installation and activation:

1. Navigate to **Extensions** → **Plugins**
2. Search for "Google Tag Manager"
3. Click on the plugin to open settings
4. Enter your GTM Container ID (e.g., GTM-XXXXXXX)
5. Save the settings

## Consent Mode

The plugin implements Google's consent mode for GDPR compliance:

- **Default state**: Analytics, personalization, functionality, and security storage are granted
- **Restricted by default**: Ad storage, ad user data, and ad personalization are denied
- **Customizable**: Users can update consent preferences via localStorage (`consentMode`)

## Development

This plugin is developed and maintained by [HKweb](https://hkweb.nl).

### Version Numbering

This project uses the format `YY.WW.NN`:
- `YY` - Last 2 digits of the year (e.g., 26 for 2026)
- `WW` - ISO week number (e.g., 03 for week 3)
- `NN` - Incremental number starting at 00 for each week

Example: `26.03.00`, `26.03.01`, `26.03.02`

### Git Workflow

This project uses git-flow branching model:
- `main` - Production-ready releases
- `develop` - Integration branch for features
- `feature/*` - New features (branch from develop)
- `release/*` - Release preparation (branch from develop)
- `hotfix/*` - Production fixes (branch from main)

### Building a Release

Releases are automatically created when a new tag is pushed to GitHub:

```bash
# Create a new release using git-flow
git flow release start 26.03.00

# Update version in googletagmanager.xml if needed
# Commit any final changes

# Finish the release (creates tag and merges to main and develop)
git flow release finish 26.03.00

# Push everything including tags
git push origin main develop --tags
```

The GitHub Actions workflow will automatically:
1. Create a release on GitHub
2. Build an installable ZIP package
3. Attach the package to the release
4. Generate a changelog from commits

## License

GNU General Public License v3.0 or later

## Author

**HKweb**
- Website: [hkweb.nl](https://hkweb.nl)
- Email: info@hkweb.nl

## Support

For issues, questions, or feature requests, please use the [GitHub Issues](../../issues) page.
