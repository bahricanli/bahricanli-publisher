=== Content Manager API ===
Contributors:      bahricanli
Tags:              api, content, publishing, rest-api, automation
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.2.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connects your WordPress site to the Content Manager app for automated post publishing, updating and deletion via a secure token-based API.

== Description ==

**Content Manager API** is the WordPress companion plugin for the [Content Manager](https://github.com/bahricanli/content-manager) application — an AI-powered multi-site content management system built with Laravel.

Once installed and configured, the plugin exposes a secure endpoint that Content Manager uses to:

* **Publish** new posts with title, content (Gutenberg blocks supported), excerpt, slug, categories, tags and featured image
* **Update** existing posts including replacing the featured image
* **Delete** posts
* **Sideload featured images** from external URLs (Unsplash, Pexels, Pixabay, etc.) directly into the WordPress media library
* **Auto-resize** uploaded images to a maximum width of 1600 px to keep storage lean

All requests are authenticated with a shared secret token — no OAuth or WordPress login required.

== Installation ==

1. Upload the `content-manager-api` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Content Manager API**.
4. Generate or enter a token and copy it to your Content Manager app's `.env` file as `WORDPRESS_PLUGIN_TOKEN`.

== Frequently Asked Questions ==

= Does this plugin work without the Content Manager app? =

The plugin exposes an API endpoint that can be called by any HTTP client with the correct token. However it is designed and maintained specifically for the Content Manager application.

= Is the token stored securely? =

The token is stored in the WordPress options table using `get_option` / `update_option`. It is never exposed in the front end.

= Can I use this with WordPress Multisite? =

Each site in a Multisite network needs the plugin activated individually with its own token.

= What image formats are supported for sideloading? =

JPEG, PNG, WebP and GIF. The plugin detects the format from the downloaded file's MIME type, so URLs without a file extension (common with Unsplash) are handled correctly.

== Screenshots ==

1. Settings page — generate or enter your API token.

== Changelog ==

= 1.2.0 =
* Fix: Featured image sideloading now works for URLs without a file extension (e.g. Unsplash).
* Fix: Media upload permission granted correctly for unauthenticated AJAX requests via token auth.

= 1.1.0 =
* Add: Image resize on upload (max 1600 px width).
* Add: `cm_fix_images` AJAX action to sideload external images already in post content.
* Add: `cm_version` AJAX action to check installed vs latest WordPress version.
* Improve: Fallback from custom cURL downloader to `media_sideload_image` on failure.

= 1.0.0 =
* Initial release.
* REST API endpoints: POST `/content-manager/v1/posts`, PUT `/content-manager/v1/posts/{id}`.
* AJAX fallback endpoints for hosts that block the REST API.
* Token-based authentication with settings page.

== Upgrade Notice ==

= 1.2.0 =
Fixes featured image not being set when the image URL has no file extension. Recommended update for all users.
