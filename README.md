# Joomla System Plugin - Google Tag Manager

A Joomla 6.x system plugin for Google Tag Manager (GTM) integration with GDPR-compliant consent mode support and full server-side tagging (sGTM) capabilities.

## Features

- Google Tag Manager integration (standard and server-side)
- GDPR consent mode support (grants/denials wired to `localStorage`)
- Automatic GTM script injection in HTML `<head>`
- Noscript `<iframe>` fallback for users without JavaScript
- Configurable GTM Container ID
- Frontend-only execution
- GTM Environments support (test before publishing live)
- Server-side tagging via your own sGTM domain
- **Stape Custom Loader** support for enhanced ad blocker protection:
  - Paste the full snippet from the Stape dashboard
  - Or let the plugin call the Stape API to fetch it automatically
  - Or enter a Container Identifier for local deterministic obfuscation
  - Or enter the filename and params manually
- **Cookie Keeper** support for Safari ITP bypass (Stape power-up)

## Requirements

- Joomla 6.x
- PHP 8.3 or higher

## Installation

1. Download the latest release package from the [Releases](../../releases) page
2. In Joomla administrator, go to **Extensions** → **Manage** → **Install**
3. Upload and install the package
4. Go to **System** → **Plugins**
5. Find **System - Google Tag Manager** and enable it
6. Configure your GTM Container ID in the plugin settings

## Configuration

### Basic — Standard GTM

1. Navigate to **Extensions** → **Plugins**
2. Search for **Google Tag Manager** and open the plugin
3. Enter your **Container ID** (e.g. `GTM-XXXXXXX`)
4. Save and verify the GTM snippet appears in your page source

### Advanced — GTM Environments

Use this to target a specific GTM environment (e.g. a staging preview) instead of the published live container.

| Field | Description |
|---|---|
| **GTM Environment Auth Token** | The `gtm_auth` value from **GTM Admin → Environments → Actions → Get snippet** |
| **GTM Environment Name** | The `gtm_preview` value from the same snippet URL (e.g. `env-2`) |

Both fields must be filled in together. Leave both empty to use the live container.

---

## Server-Side Tagging

Enable **Use server-side tagging?** to route GTM through your own sGTM container. The GTM JavaScript tag still loads in the visitor's browser, but tracking requests go to your server instead of directly to Google. Your server then forwards the data to configured destinations.

[Learn more about server-side tagging](https://developers.google.com/tag-platform/tag-manager/server-side)

### Required

| Field | Description |
|---|---|
| **Server-side GTM Domain** | The full URL of your sGTM container (e.g. `https://sst.example.com`). Must match the **Server Container URL** in **GTM Admin → Container Settings**. |

### Optional

| Field | Description |
|---|---|
| **Script Loader URL** | Override the domain used to load `gtm.js` in the `<head>`. Use this when your sGTM provider proxies the loader through a different domain (e.g. Stape Custom Loader with **Use original GTM code** enabled, e.g. `https://example.com/mos`). The noscript fallback always uses the sGTM domain above. |
| **Server-side GTM Path** | Sub-path prefix if your sGTM scripts are not served from the domain root (e.g. `/gtm`). Leave empty in most setups. |

---

## Stape Custom Loader

The Stape [Custom Loader](https://stape.io/blog/custom-loader-for-google-tag-manager) power-up serves `gtm.js` through an obfuscated filename and query string to reduce ad blocker interference. The plugin supports several configuration methods, evaluated in priority order:

### Priority 1 — Paste snippet (recommended)

Copy the full code snippet from **Stape Dashboard → Container → Custom Loader** and paste it into the **Stape Custom Loader Script** textarea. The plugin strips `<script>` tags and HTML comments automatically. This method always produces the exact script Stape generates.

### Priority 2 — Stape API (automatic)

Fill in a **Container Identifier** and optionally a **Stape API Key**. The plugin calls Stape's API to fetch the obfuscated script and caches the result for 3 hours. If the API is unavailable the plugin falls back to local generation (Priority 3).

| Field | Where to find it |
|---|---|
| **Container Identifier** | Stape Dashboard → Container Settings (e.g. `kpxlywlp`) |
| **Stape API Key** | Stape Dashboard → Container Settings → Container API Key |

### Priority 3 — Local generation (automatic fallback)

When only a **Container Identifier** is set (and the API is unavailable or not configured), the plugin generates the obfuscated filename and query string locally using the same deterministic algorithm as Stape. The result is stable for a given identifier + GTM ID combination and requires no caching.

### Priority 4 — Manual override

Enter the exact values copied from the Stape-generated snippet:

| Field | Example |
|---|---|
| **Custom Script Filename** | `1afjvqgtsh.js` |
| **Custom Script Params** | `c1ky2=ARdJICU7...` |

Both fields must be filled in together.

### Cookie Keeper (Safari ITP support)

Enable **Cookie Keeper** to activate Stape's Cookie Keeper power-up. Safari 16.4+ visitors load the GTM script via a `kp`-prefixed identifier. Stape's server detects this prefix and responds with a server-side cookie carrying a 2-year expiry, bypassing Safari's ITP 7-day cap on JavaScript-set cookies.

Requires **Container Identifier** to be set.

---

## Consent Mode

The plugin injects a consent mode initialisation block before the GTM loader. Defaults are applied on first visit; on subsequent visits the stored consent from `localStorage` (`consentMode`) is used.

| Storage type | Default |
|---|---|
| `ad_storage` | denied |
| `ad_user_data` | denied |
| `ad_personalization` | denied |
| `analytics_storage` | denied |
| `personalization_storage` | denied |
| `functionality_storage` | granted |
| `security_storage` | granted |

A `gtm_consent_update` event is pushed to `dataLayer` after consent defaults are set, allowing GTM triggers to fire on consent state.

To update consent from your cookie banner, write a JSON object to `localStorage` under the key `consentMode` and reload the page (or push a `consent` update directly to `dataLayer`).

The stored value may be either a flat object of consent types (`{ "ad_storage": "granted", ... }`) or wrapped by the banner with extra metadata (`{ "consentMode": { "ad_storage": "granted", ... }, "expiration": ... }`). The plugin unwraps the inner `consentMode` object automatically when present, so both shapes work.

When the wrapped value carries an `expiration` timestamp (milliseconds since epoch) that has passed, the plugin discards the stored choice and falls back to the denied defaults, letting your banner re-prompt for fresh consent instead of replaying a stale choice.

---

## Google Tag Manager container

This plugin only injects the GTM loader and the consent-mode bootstrap; the tags, triggers and consent logic live inside your GTM container. A ready-to-import starter container is provided at [`docs/gtm-starter-container.json`](docs/gtm-starter-container.json).

**Import it:** GTM → *Admin* → *Import Container* → choose the file → import into a new or existing workspace (use *Merge* to keep your existing setup).

It contains:

| Item | Type | Purpose |
|---|---|---|
| `00.01 - Google Tag \| GA4` | Tag (Google tag) | Loads GA4; fires on the `gtm_consent_update` event |
| `98.00 - Consent Mode \| Cookie Wall` | Tag (Custom HTML) | Accessible cookie-wall modal (NL/EN) that writes the wrapped `consentMode` value and calls `gtag('consent', 'update', …)` |
| `Event - GTM Consent Update` | Trigger | Custom event matching `gtm_consent_update` (pushed by this plugin on every page load and by the banner after a choice) |
| `Consent State - *` | Variables | Read each consent type (`ad_storage`, `analytics_storage`, …) via the *GTM Consent State* community template |
| `Google Analytics \| GA4 \| Measurement ID` | Variable (Constant) | **Set this to your `G-XXXXXXXXXX` measurement ID after import** |
| `Consent Mode - privacy policy` | Variable (Constant) | Placeholder privacy-policy URL — point it at your own |

The plugin and this container are co-designed: the plugin sets the consent **defaults** and emits `gtm_consent_update`, which is the only trigger that fires GA4. The banner provides the UI and issues consent **updates**. The starter container is generic (no account, container or property IDs); GTM assigns fresh IDs on import.

---

## Development

### Version Numbering

This project uses the format `YY.WW.NN`:

- `YY` — Last 2 digits of the year (e.g. `26` for 2026)
- `WW` — ISO week number (e.g. `03` for week 3)
- `NN` — Incremental counter starting at `00` each week

Example: `26.03.00`, `26.03.01`, `26.04.00`

The version must be updated manually in `googletagmanager.xml`. The `update.xml` file is updated automatically by GitHub Actions on release.

### Git Workflow

This project follows the git-flow branching model:

| Branch | Purpose |
|---|---|
| `main` | Production-ready releases only |
| `develop` | Integration branch (default for development) |
| `feature/*` | New features (branch from `develop`) |
| `release/*` | Release preparation (branch from `develop`) |
| `hotfix/*` | Production fixes (branch from `main`) |

### Building a Release

```bash
# Start a new release
git flow release start 26.04.00

# Update version in googletagmanager.xml
# Update CHANGELOG.md

# Finish the release (creates tag, merges to main and develop)
git flow release finish 26.04.00

# Push everything including tags
git push origin main develop --tags
```

The GitHub Actions workflow automatically:

1. Creates a GitHub release
2. Builds an installable ZIP package (`plg_system_googletagmanager-{version}.zip`) containing:
   - `googletagmanager.xml`
   - `services/`
   - `src/`
   - `language/`
3. Attaches the package to the release
4. Extracts the changelog section from `CHANGELOG.md`
5. Updates `update.xml` with the new version and download URL

---

## License

GNU General Public License v3.0 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html)

## Author

**HKweb**
- Website: [hkweb.nl](https://hkweb.nl)
- Email: info@hkweb.nl

## Support

For issues, questions, or feature requests, please use the [GitHub Issues](../../issues) page.
