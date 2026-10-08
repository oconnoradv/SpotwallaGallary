# SpotWalla Gallery

A WordPress plugin for public SpotWalla tracks, trips, retrospectives, and gallery groups. Requires WordPress 6.0+ and PHP 7.4+.

## Installation

1. Download `spotwalla-gallery-X.Y.Z.zip` from [GitHub Releases](https://github.com/oconnoradv/SpotwallaGallary/releases) and upload it through **Plugins > Add New > Upload Plugin**, or copy this repository into `wp-content/plugins/spotwalla-gallery/`.
2. Activate **SpotWalla Gallery** in WordPress **Plugins**.
3. Open **SpotWalla Gallery** in the administrator menu.

The plugin supports individual WordPress sites. On multisite, activate and manage it separately on each site; network activation is not supported.

## Managing content

Add a title, plain-text description, and public HTTPS SpotWalla link for each track, trip, or retrospective. Use the public/embed link provided by SpotWalla, and ensure the remote content is publicly accessible. Accepted hosts are `spotwalla.com`, `www.spotwalla.com`, and `new.spotwalla.com`. No API credentials are needed.

To group entries, first create an entry with type **Gallery**, then select it in the **Gallery group** field of individual entries. Each entry can belong to one group; groups cannot be nested. IDs are shown in the management table. Entries can be edited or deleted; deleting a group retains its entries as ungrouped content. Entry types cannot be changed after creation.

**Inherit site theme** is enabled by default: headings, text, links, and backgrounds use the site's styles, with a responsive full-width map at 450px height. Uncheck it to set each entry's background/text/link colors, width, and map height (200–2400px). Custom widths shrink to fit the available space. Group styling applies to its container; members retain their individual settings. These settings style the plugin's cards, not the contents of SpotWalla's cross-origin maps.

## Embedding in pages or posts

Use a WordPress **Shortcode** block (or a classic editor shortcode):

```text
[spotwalla_gallery id="123"]
```

Replace `123` with an entry or gallery group ID. An entry renders its title, description, public link, and lazy-loaded map iframe; a group renders its members in creation order. Missing or invalid IDs render nothing. The same shortcode can be used more than once on a page.

SpotWalla controls whether a URL can be embedded. If the remote page is unavailable, private, or blocks framing, visitors can still use the title link. Loading maps sends requests to SpotWalla; account for this in your site's privacy policy and consent configuration.

## Storage and deactivation

Activation creates two custom tables using the site's WordPress database prefix: `{prefix}SW_items` and `{prefix}SW_settings`. All plugin configuration and content are stored there, not in WordPress posts or options.

By default, deactivation retains both tables for reactivation. In **Data retention**, check and save **Permanently delete all plugin tables, settings, and entries on deactivation** to opt into irreversible deletion. Back up first. Reactivation after deletion creates an empty gallery. Deleting plugin files after a retaining deactivation leaves the data in the database.

## Validation

The **Security checks and ZIP release** GitHub Actions workflow runs on pull requests, pushes to `main`, `v*` tags, weekly, and manually. It checks PHP syntax on PHP 7.4 and 8.5, scans PHP source with Semgrep's `p/php` rules, and uses Trivy for known CVEs, secrets, and configuration problems. Trivy uses an automatically updated advisory database, including NIST's National Vulnerability Database (NVD); vendor advisories and severities take precedence where available. No NVD API key is required. HIGH/CRITICAL Trivy findings (including unfixed vulnerabilities), Semgrep findings, and scanner errors block packaging and publication. JSON reports are retained as workflow artifacts for 14 days, including on scan failures.

These checks support secure-development practices such as NIST SSDF; they do **not** certify NIST compliance or replace a manual review. There are currently no bundled third-party dependencies or lockfiles, so dependency CVE coverage is limited; the scan does not assess the site's WordPress installation, PHP runtime, or remote SpotWalla service. Commit lockfiles if dependencies are introduced. Semgrep downloads rules and scans locally with metrics disabled; no Semgrep account or source upload is needed.

Check PHP syntax locally with `php -l spotwalla-gallery.php`. For integration verification in WordPress, activate the plugin, create and edit each entry type and a group, embed their IDs in a page, check theme/custom styling and narrow-screen widths, delete a group, and verify both data-retaining and data-deleting deactivations.

## Building and publishing releases

Build locally with Python 3.9+ using `python .github/scripts/build_release.py`. The verified ZIP and SHA-256 checksum are written to `dist/`. Packaging uses an explicit allowlist: the plugin PHP file, README, and license inside a single `spotwalla-gallery/` directory. CI configuration, reports, and development files are excluded. Successful non-tag workflow runs also retain the package as an artifact for 14 days.

To publish, update the plugin's `Version:` header, merge the changes, and push a matching stable tag, such as `v1.0.0`. Tags that do not exactly match the header fail the build. Only tag pushes publish a GitHub Release with the installable ZIP and `.zip.sha256` checksum, and only after every check succeeds. Manual and scheduled runs do not publish. The release job alone receives `contents: write`; all other jobs have read-only repository permissions. GitHub Actions must be enabled with permission to create releases.

The action revisions and scanner versions are pinned in the workflow; update them periodically. If adding runtime assets, update the packaging allowlist in `.github/scripts/build_release.py`.