=== Gallery for SpotWalla ===
Contributors: oconnoradv
Tags: maps, gps, tracking, travel, embed
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed public SpotWalla trips, tracks, and retrospectives in posts and pages, individually or grouped into galleries.

== Description ==

Gallery for SpotWalla lets administrators save public SpotWalla trip, track, and retrospective links and embed them with a shortcode, either as individual maps or as galleries of maps.

**Disclaimer:** Gallery for SpotWalla is an independent project. It is not affiliated with, endorsed by, sponsored by, or approved by SpotWalla or the SpotWalla team. SpotWalla is a trademark of its respective owner and is used only to describe compatibility.

Features:

* **Maps** tab: add trips, tracks, and retrospectives with a title, description, title/description visibility, trip density, and custom description colors and map dimensions.
* **Galleries** tab: group maps into galleries. A map can belong to any number of galleries, and a gallery can override its maps' title, description, and trip density settings.
* **About** tab: version, links, and how to report issues.
* Embed with `[gallery_for_spotwalla id="123"]`. The shortcode from earlier versions, `[spotwalla_gallery id="123"]`, still works.
* Data is kept when the plugin is deactivated, unless you choose to delete it.
* Translation-ready: all admin text can be translated.

SpotWalla does not allow the map layer (streets, terrain, satellite) or picture icons to be set from an embed link, so viewers choose those on the embedded map.

== External services ==

This plugin embeds maps from SpotWalla (https://spotwalla.com), an online personal location management service, to display the trips, tracks, and retrospectives you add.

* What is sent and when: when a visitor views a page containing the shortcode, their browser loads each saved public SpotWalla link (with the selected density setting for trips) in a sandboxed iframe directly from SpotWalla. SpotWalla receives the visitor's IP address, browser information, and the requested map link, and may set cookies and use analytics as described in its policy. The plugin sends no referrer and no other site or visitor data, and it makes no server-side requests to SpotWalla.
* Only HTTPS links on spotwalla.com, www.spotwalla.com, and new.spotwalla.com are accepted, and the host is checked again when the map is rendered. The sandbox allows scripts needed to display the map in an isolated browser origin, but does not allow the frame to access the WordPress page, submit forms, open popups, download files, or navigate the top-level page. The plugin cannot scan remote SpotWalla content for malware or control resources SpotWalla itself loads, such as map tiles.
* SpotWalla Terms of Service and Privacy Policy: https://spotwalla.com/tos

== Installation ==

1. Install and activate the plugin through **Plugins > Add New**, or upload the `gallery-for-spotwalla` folder to `/wp-content/plugins/`.
2. Open **Spotwalla Gallery** in the admin menu.
3. On the **Maps** tab, add the public HTTPS link of a SpotWalla trip, track, or retrospective.
4. Optionally create galleries on the **Galleries** tab and assign maps to them.
5. Add the shortcode shown in the list to any post or page.

**Upgrading from "SpotWalla Gallery" 1.0.0 or 1.0.1:** the plugin was renamed. Install and activate Gallery for SpotWalla first; it copies your maps and galleries, keeping their IDs so existing shortcodes keep working. Then deactivate and delete the old "SpotWalla Gallery" plugin. Back up before upgrading.

== Frequently Asked Questions ==

= Can I choose the map layer or hide picture icons? =

No. SpotWalla only lets you change these on the map itself, not through the embed link.

= Why does the density setting not change my track or retrospective? =

SpotWalla supports the density (fill percentage) setting only for trips.

= Is this an official SpotWalla plugin? =

No. It is an independent project and is not affiliated with or approved by SpotWalla.

= Where do I report a problem? =

Open an issue at https://github.com/oconnoradv/SpotwallaGallary/issues. Do not include passwords, private links, or other personal data.

== Changelog ==

Versions 1.0.0 through 1.0.4 were development releases. 1.0.5 was the first stable release.

= 1.0.8 =
* Separates lifecycle, persistence, validation, form recovery, admin requests, admin presentation, and shortcode rendering into focused components with injectable storage contracts.
* Retains existing shortcodes, settings, database tables, migrations, and form behavior.

= 1.0.7 =
* Failed map and gallery saves return to the form with the submitted entries, highlighted invalid fields, and specific error messages.

= 1.0.6 =
* Added an optional Recommended Plugins section on the About page linking to the Motorcycle Rally Scoring App project.
* The admin header shows the installed plugin version next to the project byline.
* Maps render in a sandboxed iframe isolated from the WordPress page.

= 1.0.5 =
* First stable release.
* The page header and browser title show the plugin name, "Gallery for SpotWalla". Only the admin menu reads "Spotwalla Gallery".

= 1.0.4 =
* The admin menu and page header now read "Spotwalla Gallery", and the page header shows the project logo and the tagline "an O'ConnorADV project".
* The admin menu icon is now purple.

= 1.0.3 =
* The plugin's database tables are now named `{prefix}SpotGal_*`. Data in the old `{prefix}SW_*` tables is moved automatically, keeping the same IDs.
* Translation-ready: all admin text uses the `gallery-for-spotwalla` text domain, and a translation template is included.

= 1.0.2 =
* Renamed to Gallery for SpotWalla, with the new `[gallery_for_spotwalla]` shortcode. `[spotwalla_gallery]` still works.
* Licensed under GPLv2 or later.
* Added per-map and per-gallery trip density settings.
* Added the About tab with a non-affiliation disclaimer and issue-reporting instructions.
* Renamed appearance and dimension labels.
* Requires WordPress 6.2 or later.

= 1.0.1 =
* Separate Maps and Galleries tabs, per-map title and description visibility with gallery overrides, and multi-gallery membership.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.8 =
Internal architecture refactor. Existing content, settings, and shortcodes are retained.

= 1.0.7 =
Improves form error recovery without changing saved map data.

= 1.0.6 =
Adds an optional related-plugin link to the About page and isolates embedded maps in a sandboxed iframe. No data changes.

= 1.0.5 =
First stable release. The page header shows the plugin name again. No data changes.

= 1.0.4 =
Updates the admin menu name, icon color, and page header. No data changes.

= 1.0.3 =
Moves your maps and galleries into renamed database tables (same IDs) and adds translation support. Back up before updating.

= 1.0.2 =
The plugin is now Gallery for SpotWalla. Turn off deletion on deactivation, delete the old "SpotWalla Gallery" plugin, then activate this one; your maps and galleries are kept.
