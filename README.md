# Gallery for SpotWalla

![Gallery for SpotWalla — an O'ConnorADV project](.wordpress-org/banner-772x250.jpg)

A WordPress plugin for public SpotWalla tracks, trips, retrospectives, and gallery groups. Requires WordPress 6.2+ and PHP 7.4+. Licensed under the GNU General Public License v2 or later (see [LICENSE](LICENSE)).

Before version 1.0.2 this plugin was named **SpotWalla Gallery** (`spotwalla-gallery`). It was renamed to follow the WordPress.org rule that plugin names must not begin with another project's trademark.

> **Disclaimer:** Gallery for SpotWalla is an independent project. It is **not** affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used only to describe compatibility. Report plugin problems to this repository, not to SpotWalla.

## Installation

1. Download `gallery-for-spotwalla-X.Y.Z.zip` from the release marked **Latest** on [GitHub Releases](https://github.com/oconnoradv/SpotwallaGallary/releases) and upload it through **Plugins > Add New > Upload Plugin**, or copy this repository into `wp-content/plugins/gallery-for-spotwalla/`. Releases marked **Pre-release** with "(DEV)" in the title, including v1.0.0 through v1.0.4, are development builds kept for reference; use them only for testing.
2. Activate **Gallery for SpotWalla** in WordPress **Plugins**.
3. Open **Spotwalla Gallery** in the administrator menu (shown with a purple map icon). The official plugin name stays "Gallery for SpotWalla", as WordPress.org's trademark guideline requires.

**Upgrading from SpotWalla Gallery 1.0.0 or 1.0.1:** because the plugin folder changed, WordPress installs Gallery for SpotWalla as a separate plugin. Install and activate **Gallery for SpotWalla** first; it copies your maps and galleries (keeping their IDs, so existing shortcodes keep working) into its own tables. Then deactivate and delete **SpotWalla Gallery**. A warning is shown while the old plugin is still active. Back up before upgrading.

The plugin supports individual WordPress sites. On multisite, activate and manage it separately on each site; network activation is not supported.

## Managing content

The settings page opens on the **Maps** tab. Use it to add a title, plain-text description, and public HTTPS SpotWalla link for each track, trip, or retrospective. Use the public/embed link provided by SpotWalla, and ensure the remote content is publicly accessible. Accepted hosts are `spotwalla.com`, `www.spotwalla.com`, and `new.spotwalla.com`. No API credentials are needed.

To group maps, first create a gallery on the **Galleries** tab, then select one or more galleries in a map's **Galleries** field on the **Maps** tab. A map can belong to multiple galleries; galleries cannot be nested. IDs are shown in each tab's table. Maps and galleries can be edited or deleted; deleting a gallery retains its maps. Types cannot be changed after creation.

If saving fails, the form keeps your entries and shows specific error messages next to highlighted invalid fields. Database failures show a retry message without blaming a field. Recovery data is private to your administrator login session, expires after ten minutes, and is removed when the form is restored. Correct the highlighted fields and save again.

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

## Reporting issues

Search [existing issues](https://github.com/oconnoradv/SpotwallaGallary/issues) first, then [open a new issue](https://github.com/oconnoradv/SpotwallaGallary/issues/new) with a descriptive title, the expected and actual behavior, steps to reproduce, the map type, any error messages or screenshots, and your plugin, WordPress, and PHP versions (shown on the **About** tab). Do not include passwords, private SpotWalla links, or other personal data. Report security vulnerabilities privately to the repository owner rather than in a public issue.

## Embedding in pages or posts

Use a WordPress **Shortcode** block (or a classic editor shortcode):

```text
[gallery_for_spotwalla id="123"]
```

Shortcodes from earlier versions, `[spotwalla_gallery id="123"]`, continue to work.

Replace `123` with a map or gallery ID. A map renders its visible title/link, visible description, and lazy-loaded, sandboxed iframe; a gallery renders its maps in creation order, applying its visibility overrides. The saved frame URL is restricted to allowlisted SpotWalla hosts. The iframe has a unique, opaque browser origin: map scripts needed for display can run inside it but cannot access the WordPress page. Forms, popups, downloads, and top-level navigation are disabled. The plugin cannot control redirects or resources loaded by SpotWalla, but any content remains inside the sandbox. Missing or invalid IDs render nothing. The same shortcode can be used more than once on a page.

SpotWalla controls whether a URL can be embedded. If the remote page is unavailable, private, or blocks framing, visitors can still use the title link when it is shown. The plugin validates the saved URL's HTTPS scheme and exact SpotWalla host when saving and again when rendering; it does not fetch or execute the URL on the WordPress server. Sandboxing prevents the remote frame from accessing the WordPress page, but does not verify redirected content, scan remote content for malware, or control resources that SpotWalla itself loads (such as map tiles). Loading maps sends requests from visitors' browsers to SpotWalla (their IP address, browser information, and the map link; no referrer is sent), and SpotWalla may set cookies and use analytics. See SpotWalla's [Terms of Service and Privacy Policy](https://spotwalla.com/tos), and account for this in your site's privacy policy and consent configuration.

## Storage and deactivation

Activation creates three custom tables using the site's WordPress database prefix: `{prefix}SpotGal_items`, `{prefix}SpotGal_settings`, and `{prefix}SpotGal_gallery_items`. All plugin configuration and content are stored there, not in WordPress posts or options.

Versions before 1.0.3 used `{prefix}SW_*` tables. When the plugin is updated or activated, it copies the maps, galleries, gallery memberships, and data-retention setting from those tables into the `SpotGal_*` tables, keeping the same IDs, and then drops the old tables. This runs only while the new items table is empty, so it never overwrites data. Data from 1.0.0 is upgraded at the same time (adding the newer settings and converting each map's single group into a gallery membership). Back up before updating.

By default, deactivation retains all tables for reactivation. In **Data retention**, check and save **Permanently delete all plugin tables, settings, maps, and galleries on deactivation** to opt into irreversible deletion. Back up first. Reactivation after deletion creates an empty gallery. Deleting plugin files after a retaining deactivation leaves the data in the database.

## Translations

All admin text uses the `gallery-for-spotwalla` text domain. The translation template is `languages/gallery-for-spotwalla.pot`. Once the plugin is on WordPress.org, translations can be contributed at [translate.wordpress.org](https://translate.wordpress.org/) and WordPress downloads them automatically. For a local translation, create `gallery-for-spotwalla-{locale}.po`/`.mo` files from the template (for example with Poedit) and place them in `wp-content/languages/plugins/`. After changing any text, regenerate the template with `wp i18n make-pot . languages/gallery-for-spotwalla.pot --include=gallery-for-spotwalla.php,includes`.

## Development architecture

The bootstrap `gallery-for-spotwalla.php` composes the runtime dependencies and retains the public `Gallery_For_SpotWalla` callbacks and constants for compatibility. Components in `includes/` have separate responsibilities:

| Component | Responsibility |
| --- | --- |
| `GFSW_Lifecycle` | Activation, schema upgrades, legacy migration, and opt-in deactivation cleanup |
| `GFSW_Wpdb_Store` | Prepared database queries and content/settings persistence |
| `GFSW_Validator` | Content validation, normalization, URL allowlisting, and density policy |
| `GFSW_Form_Recovery` | Session-scoped, expiring failed-form drafts |
| `GFSW_Admin` | Authorized, nonce-protected save/delete/settings requests |
| `GFSW_Admin_Page` | Admin page presentation and field feedback |
| `GFSW_Renderer` | Shortcode output, visibility overrides, styles, and sandboxed maps |
| `GFSW_Config` | Shared identity and supported option values |

Admin operations depend on the injected `GFSW_Store` contract, not SQL or a global database connection. The renderer accepts only the smaller `GFSW_Item_Reader` contract; it cannot write content. Alternative adapters can implement these contracts without changing consumers, and tests can supply in-memory readers. Keep replacement adapters consistent with the documented return values, ordering, and failure semantics. Schema management is intentionally WordPress-specific and uses an injected `wpdb` connection. No dependency container, runtime framework, or new third-party library is required.

## Validation

The **Security checks and ZIP release** GitHub Actions workflow runs on pull requests, pushes to `main`, `v*` tags, weekly, and manually. It checks PHP syntax on PHP 7.4 and 8.5, scans PHP source with both Semgrep's `p/php` and `p/security-audit` rulesets, and uses Trivy for known CVEs, secrets in the working tree, and configuration problems. Gitleaks additionally scans the full Git history for committed secrets; its binary is version-pinned and checksum-verified, and findings are redacted in the retained report. The broader PHP security audit checks for common exploit patterns, including SQL injection, cross-site scripting (XSS), and command injection. Trivy uses an automatically updated advisory database, including NIST's National Vulnerability Database (NVD); vendor advisories and severities take precedence where available. No NVD API key is required. HIGH/CRITICAL Trivy findings (including unfixed vulnerabilities), Semgrep or Gitleaks findings, and scanner errors block packaging and publication. JSON reports are retained as workflow artifacts for 14 days, including on scan failures.

These checks support secure-development practices such as NIST SSDF; they do **not** certify NIST compliance or replace a manual review. There are currently no bundled third-party dependencies or lockfiles, so dependency CVE coverage is limited; the scan does not assess the site's WordPress installation, PHP runtime, or remote SpotWalla service. Commit lockfiles if dependencies are introduced. Semgrep downloads rules and scans locally with metrics disabled; no Semgrep account or source upload is needed.

Before a tag can publish, an additional **Dependabot and exact-commit CodeQL release gate** requires zero open Dependabot alerts (all severities) and a clean CodeQL default-setup analysis on `main` matching the exact tagged commit, for every configured CodeQL language. It waits up to fifteen minutes for asynchronous CodeQL analysis; unrelated commits, partial analyses, findings, analysis errors, API failures, and missing credentials block publication. The gate refreshes Dependabot alerts after waiting. CodeQL currently covers GitHub Actions, not PHP (which CodeQL does not support); Semgrep continues to scan all runtime PHP. This uses the existing CodeQL default setup instead of running a conflicting advanced setup.

**Required repository setup:** keep Dependabot alerts and CodeQL default setup enabled. Add a repository Actions secret named `RELEASE_SECURITY_TOKEN` containing a fine-grained token restricted to this repository with **Dependabot alerts: read** permission. The workflow's built-in token has **security-events: read** for CodeQL but cannot read Dependabot alerts. Do not place tokens in source or logs. Missing/expired tokens deliberately block releases; PR builds do not receive this secret or run the tag-only gate. Automatic Dependabot update PRs are separate from the alerts gate and are not enabled by this workflow.

Check PHP syntax locally with `php -l gallery-for-spotwalla.php`. Before submitting to WordPress.org, run the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin (`wp plugin check gallery-for-spotwalla`); it should report no errors. For integration verification in WordPress, activate the plugin, check all three tabs, create and edit each map type and multiple galleries, assign a map to several galleries, embed the IDs, verify visibility settings and gallery overrides, verify map and gallery density settings in the embedded trip URLs, check theme/custom styling and narrow-screen widths, delete a gallery and a map, verify upgrades from 1.0.0 and 1.0.2 (data moves from the `SW_*` to the `SpotGal_*` tables with the same IDs), check that every admin string is translatable, and verify both data-retaining and data-deleting deactivations.

On a disposable WordPress test site with the plugin active, run `wp --user=<administrator> eval-file .github/tests/test_form_recovery.php` to check form recovery, field errors, output escaping, user isolation, one-use recovery tokens, corrected submissions, and database failure handling. This test creates and removes its own map and gallery records; do not run it on a production site.

Run `wp --user=<administrator> eval-file .github/tests/test_components.php` on the same disposable site to check rendering with a read-only in-memory adapter, URL and density policy, the database adapter contract, activation, schema upgrades, legacy migration, and retention/deletion. Its database fixtures use a unique temporary table prefix and are removed afterward. Packaging tests (`python -m unittest discover -s .github/tests -p "test_*.py"`) also verify that every runtime PHP file is explicitly shipped and loaded by the bootstrap.

## Building and publishing releases

Build locally with Python 3.9+ using `python .github/scripts/build_release.py`. The verified ZIP and SHA-256 checksum are written to `dist/`. Packaging uses an explicit allowlist: the plugin bootstrap and runtime PHP components in `includes/`, `readme.txt` (the WordPress.org readme), `README.md`, the license, the translation template (`languages/gallery-for-spotwalla.pot`), and the admin header logo (`images/logo-256x256.jpg`) inside a single `gallery-for-spotwalla/` directory. CI configuration, reports, and development files are excluded. Successful non-tag workflow runs also retain the package as an artifact for 14 days.

The `.wordpress-org/` folder holds the plugin directory listing images, in the sizes WordPress.org recommends: banners at 772×250 and 1544×500 pixels and icons at 128×128 and 256×256 pixels. They are not part of the plugin ZIP. After the plugin is approved, copy them into the `assets/` folder of the WordPress.org SVN repository.

To publish, update the plugin's `Version:` header and the `Stable tag` in `readme.txt`, merge the changes, and push a matching stable tag, such as `v1.0.0`. Tags that do not exactly match the header fail the build. Only tag pushes publish a GitHub Release with the installable ZIP and `.zip.sha256` checksum, and only after every check succeeds. Manual and scheduled runs do not publish. The release job alone receives `contents: write`; all other jobs have read-only repository permissions. GitHub Actions must be enabled with permission to create releases.

The workflow publishes every tagged build as a stable release and marks it **Latest**. To mark an older or interim build as a development release, keep its tag and assets and run `gh release edit vX.Y.Z --prerelease --latest=false --title "Gallery for SpotWalla vX.Y.Z (DEV)"`. Then make sure the current stable release is still marked Latest (`gh release edit vA.B.C --latest`). Releases v1.0.0 through v1.0.4 are development releases; v1.0.5 is the first stable release.

The action revisions and scanner versions are pinned in the workflow; update them periodically. If adding runtime assets, update the packaging allowlist in `.github/scripts/build_release.py`.