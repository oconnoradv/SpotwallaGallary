# Gallery for SpotWalla

![Gallery for SpotWalla — an O'ConnorADV project](.wordpress-org/banner-772x250.jpg)

A WordPress plugin for public SpotWalla tracks, trips, retrospectives, and gallery groups. Requires WordPress 6.2+ and PHP 7.4+. Licensed under the GNU General Public License v2 or later (see [LICENSE](LICENSE)).

> **Disclaimer:** Gallery for SpotWalla is an independent project. It is **not** affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used only to describe compatibility. Report plugin problems to this repository, not to SpotWalla.

## Installation

1. Download `gallery-for-spotwalla-X.Y.Z.zip` from the release marked **Latest** on [GitHub Releases](https://github.com/oconnoradv/SpotwallaGallary/releases) and upload it through **Plugins > Add New > Upload Plugin**, or copy this repository into `wp-content/plugins/gallery-for-spotwalla/`. Use the latest stable release for installation.
2. Activate **Gallery for SpotWalla** in WordPress **Plugins**.
3. Open **Spotwalla Gallery** in the administrator menu (shown with a purple map icon). The official plugin name stays "Gallery for SpotWalla", as WordPress.org's trademark guideline requires.

The plugin supports individual WordPress sites. On multisite, activate and manage it separately on each site; network activation is not supported.

## Managing content

The settings page opens on the **Maps** tab. Use it to add a title, plain-text description, and public HTTPS SpotWalla link for each track, trip, or retrospective. Use the public/embed link provided by SpotWalla, and ensure the remote content is publicly accessible. Accepted hosts are `spotwalla.com`, `www.spotwalla.com`, and `new.spotwalla.com`. No API credentials are needed.

To group maps, first create a gallery on the **Galleries** tab, then select one or more galleries in a map's **Galleries** field on the **Maps** tab. A map can belong to multiple galleries; galleries cannot be nested. IDs are shown in each tab's table. Maps and galleries can be edited or deleted; deleting a gallery retains its maps. Types cannot be changed after creation.

If saving fails, the form keeps your entries and shows specific error messages next to highlighted invalid fields. Database failures show a retry message without blaming a field. Recovery data is private to your administrator login session, expires after ten minutes, and is removed when the form is restored. Correct the highlighted fields and save again.

Use **Preview** beside the map form to render your current entries without saving. Each saved map also has a **Preview** action in the list. The preview uses the same renderer as standalone shortcodes, including density, visibility, and custom dimensions. Gallery overrides are not applied. Site-theme styling can differ from the administrator page. JavaScript is required; previews are available only to authorized administrators and never change records or gallery memberships. Previewing loads SpotWalla in your browser, just like a published map.

The admin header uses a local, animated map-and-route logo. It makes no external requests, stops animation when reduced motion is requested, and remains recognizable when animation is unsupported. WordPress.org icons and banners use a matching static map illustration. Regenerate the static artwork on Windows with `powershell -File .github\scripts\build_brand_assets.ps1`.

Each map has **Show title** and **Show description** settings, both enabled by default. Displaying a map by its own ID always uses these settings. Within a gallery, the gallery's **Map titles** and **Map descriptions** options can use each map's setting (the default), or show or hide that element for all its maps. Hiding a title also hides its link to SpotWalla; the iframe retains the title for accessibility. Gallery titles and descriptions are always shown.

### Map density

Each map has a **Density/Fill percentage** setting matching the SpotWalla trip viewer's **Trip Adjustments > Density/Fill Percentage** values: None, 0.1, 0.3, 0.5, 1, 3, 5, 10–90%, or All (100%). It controls how many locations SpotWalla draws. The default, **SpotWalla trip setting**, leaves the embed link unchanged so the trip's own configuration applies. Otherwise, the plugin adds SpotWalla's `fillFactor` parameter to the embedded map's URL.

A gallery's **Map density in this gallery** option uses each map's setting by default, or applies one value to every trip map displayed in that gallery. Displaying a map by its own ID always uses the map's setting.

Density applies to **trips** only. SpotWalla's retrospective viewer ignores it, and it is not applied to tracks or retrospectives.

### Map layer and picture icons

SpotWalla does not currently support setting the initial map layer (Streets, Terrain, Satellite, Satellite/Streets) or picture-icon visibility through the embed link; its viewer always starts on Streets with picture icons shown. Configure these in SpotWalla instead: set the trip's initial map type on the **Options** tab of the trip in SpotWalla's Trip Manager. Visitors can still change the layer and show/hide picture icons from the embedded map's controls. Because the map is a cross-origin iframe, the plugin cannot operate those controls.

### Description appearance and map dimensions

**Inherit site theme** is enabled by default: headings, text, links, and backgrounds use the site's styles, with a responsive full-width map at 450px height. Uncheck it to set each map's **Description background color**, **Description text and link color**, and **Custom map dimensions** (width and map height, 200–2400px). Custom widths shrink to fit the available space. Galleries can set container colors and width; maps retain their individual styling. These settings style the plugin's title and description cards, not the contents of SpotWalla's cross-origin maps.

The **About** tab shows the installed version, the disclaimer that this project is not affiliated with or approved by SpotWalla, and links to this repository, the README, and the license, with instructions for reporting issues.

### Recommended plugin

The **About** tab's **Recommended Plugins** section links to [RallyQuestEngine: A Competitive Motorcycle Rally Scoring Application](https://github.com/oconnoradv/RallyQuestEngine). This is a separate, optional O'ConnorADV project for competitive motorcycle rally scoring; it is not required to use Gallery for SpotWalla.

## Reporting issues

Search [existing issues](https://github.com/oconnoradv/SpotwallaGallary/issues) first, then [open a new issue](https://github.com/oconnoradv/SpotwallaGallary/issues/new) with a descriptive title, the expected and actual behavior, steps to reproduce, the map type, any error messages or screenshots, and your plugin, WordPress, and PHP versions (shown on the **About** tab). Do not include passwords, private SpotWalla links, or other personal data. Report security vulnerabilities privately to the repository owner rather than in a public issue.

## Embedding in pages or posts

Use a WordPress **Shortcode** block (or a classic editor shortcode):

```text
[gallery_for_spotwalla id="123"]
```

Replace `123` with a map or gallery ID. A map renders its visible title/link, visible description, and lazy-loaded, sandboxed iframe; a gallery renders its maps in creation order, applying its visibility overrides. The saved frame URL is restricted to allowlisted SpotWalla hosts. The iframe permits scripts and preserves SpotWalla's origin (`allow-scripts allow-same-origin`) so its module-based controls and origin-dependent map requests can work. Browser same-origin rules isolate the cross-origin SpotWalla frame from WordPress. Forms, popups, downloads, and top-level navigation remain disabled. The plugin cannot control redirects or resources loaded by SpotWalla. If a remote page redirects to your own site's origin, the script and same-origin permissions no longer provide that cross-origin isolation; use trusted public viewer links only. Missing or invalid IDs render nothing. The same shortcode can be used more than once on a page.

SpotWalla controls whether a URL can be embedded. If the remote page is unavailable, private, or blocks framing, visitors can still use the title link when it is shown. The plugin validates the saved URL's HTTPS scheme and exact SpotWalla host when saving and again when rendering; it does not fetch or execute the URL on the WordPress server. Cross-origin browser isolation prevents the SpotWalla frame from accessing the WordPress page, but the plugin does not verify redirected content, scan remote content for malware, or control resources that SpotWalla itself loads (such as map tiles). Loading maps sends requests from visitors' browsers to SpotWalla (their IP address, browser information, and the map link; no referrer is sent), and SpotWalla may set cookies and use analytics. See SpotWalla's [Terms of Service and Privacy Policy](https://spotwalla.com/tos), and account for this in your site's privacy policy and consent configuration.

### Troubleshooting maps and controls

Compare the saved URL opened directly with the iframe. A blank map can be caused by an unavailable host, private content, framing restrictions, blocked scripts or tiles, or consent/ad-blocking rules. The iframe preserves SpotWalla's origin so its trip-data requests, map tiles, and controls can work. Forms, popups, downloads, and top-level navigation remain disabled.

If the saved host returns an SSL or availability error, verify an alternate public link supplied by SpotWalla and edit the map URL. The plugin does not silently rewrite saved hosts or fix SpotWalla's server certificates. Because the viewer is cross-origin, the plugin cannot inspect its errors or restyle its internal menu buttons; Preview readiness does not prove that remote tiles finished loading. Maps use live iframes, not cached daily images.

## Storage and deactivation

Activation creates three custom tables using the site's WordPress database prefix: `{prefix}SpotGal_items`, `{prefix}SpotGal_settings`, and `{prefix}SpotGal_gallery_items`. All plugin configuration and content are stored there, not in WordPress posts or options.

By default, deactivation retains all tables for reactivation. In **Data retention**, check and save **Permanently delete all plugin tables, settings, maps, and galleries on deactivation** to opt into irreversible deletion. Back up first. Reactivation after deletion creates an empty gallery. Deleting plugin files after a retaining deactivation leaves the data in the database.

## Translations

All admin text uses the `gallery-for-spotwalla` text domain. The translation template is `languages/gallery-for-spotwalla.pot`. Once the plugin is on WordPress.org, translations can be contributed at [translate.wordpress.org](https://translate.wordpress.org/) and WordPress downloads them automatically. For a local translation, create `gallery-for-spotwalla-{locale}.po`/`.mo` files from the template (for example with Poedit) and place them in `wp-content/languages/plugins/`. After changing any text, regenerate the template with `wp i18n make-pot . languages/gallery-for-spotwalla.pot --include=gallery-for-spotwalla.php,includes`.

## Development architecture

The bootstrap `gallery-for-spotwalla.php` composes the runtime dependencies and exposes the public `Gallery_For_SpotWalla` callbacks and constants. Components in `includes/` have separate responsibilities:

| Component | Responsibility |
| --- | --- |
| `GFSW_Lifecycle` | Activation, schema management, and opt-in deactivation cleanup |
| `GFSW_Wpdb_Store` | Prepared database queries and content/settings persistence |
| `GFSW_Validator` | Content validation, normalization, URL allowlisting, and density policy |
| `GFSW_Form_Recovery` | Session-scoped, expiring failed-form drafts |
| `GFSW_Admin` | Authorized, nonce-protected save/delete/settings requests and read-only map previews |
| `GFSW_Admin_Page` | Admin page presentation and field feedback |
| `GFSW_Renderer` | Shared shortcode/preview output, visibility overrides, styles, and sandboxed maps |
| `GFSW_Config` | Shared identity and supported option values |

Admin operations depend on the injected `GFSW_Store` contract, not SQL or a global database connection. The renderer accepts only the smaller `GFSW_Item_Reader` contract; it cannot write content. Alternative adapters can implement these contracts without changing consumers, and tests can supply in-memory readers. Keep replacement adapters consistent with the documented return values, ordering, and failure semantics. Schema management is intentionally WordPress-specific and uses an injected `wpdb` connection. No dependency container, runtime framework, or new third-party library is required.

## Validation

The **Security checks and ZIP release** GitHub Actions workflow runs on pull requests, pushes to `main`, `v*` tags, weekly, and manually. It checks PHP syntax on PHP 7.4 and 8.5, scans PHP source with both Semgrep's `p/php` and `p/security-audit` rulesets, and uses Trivy for known CVEs, secrets in the working tree, and configuration problems. Gitleaks additionally scans the full Git history for committed secrets; its binary is version-pinned and checksum-verified, and findings are redacted in the retained report. The broader PHP security audit checks for common exploit patterns, including SQL injection, cross-site scripting (XSS), and command injection. Trivy uses an automatically updated advisory database, including NIST's National Vulnerability Database (NVD); vendor advisories and severities take precedence where available. No NVD API key is required. HIGH/CRITICAL Trivy findings (including unfixed vulnerabilities), Semgrep or Gitleaks findings, and scanner errors block packaging and publication. JSON reports are retained as workflow artifacts for 14 days, including on scan failures.

These checks support secure-development practices such as NIST SSDF; they do **not** certify NIST compliance or replace a manual review. There are currently no bundled third-party dependencies or lockfiles, so dependency CVE coverage is limited; the scan does not assess the site's WordPress installation, PHP runtime, or remote SpotWalla service. Commit lockfiles if dependencies are introduced. Semgrep downloads rules and scans locally with metrics disabled; no Semgrep account or source upload is needed.

Before a tag can publish, an additional **Dependabot and exact-commit CodeQL release gate** requires zero open Dependabot alerts (all severities) and a clean CodeQL default-setup analysis on `main` matching the exact tagged commit, for every required CodeQL language. It waits up to fifteen minutes for asynchronous CodeQL analysis; unrelated commits, partial analyses, findings, analysis errors, API failures, and missing credentials block publication. The gate refreshes Dependabot alerts after waiting. CodeQL currently covers GitHub Actions, not PHP (which CodeQL does not support); Semgrep continues to scan all runtime PHP. This uses the existing CodeQL default setup instead of running a conflicting advanced setup.

The required CodeQL categories are declared in `CODEQL_CATEGORIES` in `.github/scripts/check_release_security.py` (currently `/language:actions`). Update that set when enabling additional languages. The gate reads analysis results directly, without requiring administrator access to repository security settings.

**Required repository setup:** keep Dependabot alerts and CodeQL default setup enabled. Add a repository Actions secret named `RELEASE_SECURITY_TOKEN` containing a fine-grained token restricted to this repository with **Dependabot alerts: read** permission. The workflow's built-in token has **security-events: read** for CodeQL but cannot read Dependabot alerts. Do not place tokens in source or logs. Missing/expired tokens deliberately block releases; PR builds do not receive this secret or run the tag-only gate. Automatic Dependabot update PRs are separate from the alerts gate and are not enabled by this workflow.

Check PHP syntax locally with `php -l gallery-for-spotwalla.php`. Before submitting to WordPress.org, run the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin (`wp plugin check gallery-for-spotwalla`); it should report no errors. For integration verification in WordPress, activate the plugin, check all three tabs and the RallyQuestEngine recommendation, create and edit each map type and multiple galleries, assign a map to several galleries, embed the IDs, verify visibility settings and gallery overrides, verify map and gallery density settings in the embedded trip URLs, check theme/custom styling and narrow-screen widths, delete a gallery and a map, check that every admin string is translatable, and verify both data-retaining and data-deleting deactivations.

On a disposable WordPress test site with the plugin active, run `wp --user=<administrator> eval-file .github/tests/test_form_recovery.php` to check form recovery, field errors, output escaping, user isolation, one-use recovery tokens, corrected submissions, and database failure handling. This test creates and removes its own map and gallery records; do not run it on a production site.

Run `wp --user=<administrator> eval-file .github/tests/test_components.php` on the same disposable site to check rendering with a read-only in-memory adapter, URL and density policy, the database adapter contract, schema management, and retention/deletion. Its database fixtures use a unique temporary table prefix and are removed afterward. Packaging tests (`python -m unittest discover -s .github/tests -p "test_*.py"`) also verify that every runtime PHP file is explicitly shipped and loaded by the bootstrap.

Run `wp --user=<administrator> eval-file .github/tests/test_preview.php` on that disposable site to verify unsaved and saved previews, validation, permission and nonce checks, escaping, and read-only behavior. Check Preview with keyboard navigation, repeated requests, close/reopen, and narrow screens; check the logo with reduced motion enabled. New JavaScript and SVG assets are explicitly included in release packages.

## Building and publishing releases

Build locally with Python 3.9+ using `python .github/scripts/build_release.py`. The verified ZIP and SHA-256 checksum are written to `dist/`. Packaging uses an explicit allowlist: the plugin bootstrap and runtime PHP components in `includes/`, `readme.txt` (the WordPress.org readme), `README.md`, the license, the translation template (`languages/gallery-for-spotwalla.pot`), the static logo (`images/logo-256x256.jpg`), animated header logo (`images/logo-map.svg`), and preview script (`images/admin-preview.js`) inside a single `gallery-for-spotwalla/` directory. CI configuration, reports, and development files are excluded. Successful non-tag workflow runs also retain the package as an artifact for 14 days.

The `.wordpress-org/` folder holds the plugin directory listing images, in the sizes WordPress.org recommends: banners at 772×250 and 1544×500 pixels and icons at 128×128 and 256×256 pixels. They are not part of the plugin ZIP. After the plugin is approved, copy them into the `assets/` folder of the WordPress.org SVN repository.

To publish, update the plugin's `Version:` header and the `Stable tag` in `readme.txt`, merge the changes, and push a matching stable tag in `vX.Y.Z` format. Tags that do not exactly match the header fail the build. Only tag pushes publish a GitHub Release with the installable ZIP and `.zip.sha256` checksum, and only after every check succeeds. Manual and scheduled runs do not publish. The release job alone receives `contents: write`; all other jobs have read-only repository permissions. GitHub Actions must be enabled with permission to create releases.

The workflow publishes tagged builds as stable releases and marks them **Latest**.

The action revisions and scanner versions are pinned in the workflow; update them periodically. If adding runtime assets, update the packaging allowlist in `.github/scripts/build_release.py`.