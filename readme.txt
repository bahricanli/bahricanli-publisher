=== BahriCanli Publisher ===
Contributors:      bmericc, bahricanli
Tags:              api, content, publishing, rest-api, automation
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        1.3.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connects your WordPress site to content-manager.tr — publish, update and delete posts via a secure token-based API.

== Description ==

**BahriCanli Publisher** is the official WordPress companion plugin for [content-manager.tr](https://content-manager.tr) — an AI-powered multi-site content management system built and maintained by Bahri Meriç Canlı.

content-manager.tr manages content for multiple WordPress sites simultaneously, generating and scheduling posts with AI assistance. This plugin acts as the bridge: it exposes a secure endpoint that content-manager.tr uses to create, update and delete posts on your site.

Once installed and configured, the plugin allows content-manager.tr to:

* **Publish** new posts with title, content (Gutenberg blocks supported), excerpt, slug, categories, tags and featured image
* **Update** existing posts including replacing the featured image
* **Delete** posts
* **Sideload featured images** from external URLs (Unsplash, Pexels, Pixabay, etc.) directly into the WordPress media library
* **Auto-resize** uploaded images to a maximum width of 1600 px to keep storage lean

All requests are authenticated with a shared secret token — no OAuth or WordPress login required.

== Installation ==

1. Upload the `bahricanli-publisher` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → BahriCanli Publisher**.
4. Generate or enter a token and copy it to your content-manager.tr app's `.env` file as `WORDPRESS_PLUGIN_TOKEN`.

== Frequently Asked Questions ==

= Does this plugin work without the content-manager.tr app? =

The plugin exposes an API endpoint that can be called by any HTTP client with the correct token. However it is designed and maintained specifically for the content-manager.tr application.

= Is the token stored securely? =

The token is stored in the WordPress options table using `get_option` / `update_option`. It is never exposed in the front end.

= Can I use this with WordPress Multisite? =

Each site in a Multisite network needs the plugin activated individually with its own token.

= What image formats are supported for sideloading? =

JPEG, PNG, WebP and GIF. The plugin detects the format from the downloaded file's MIME type, so URLs without a file extension (common with Unsplash) are handled correctly.

== External Services ==

This plugin makes an outbound HTTP request to the WordPress.org API to check whether a WordPress core update is available. This request is triggered only when the `bcp_version` AJAX action is called by the content-manager.tr application.

* Service: WordPress.org Core Version Check API
* URL: `https://api.wordpress.org/core/version-check/1.7/`
* Purpose: Retrieve the latest available WordPress version number
* Data sent: No personal data is sent. The request contains only standard HTTP headers.
* Privacy policy: https://wordpress.org/about/privacy/

== Screenshots ==

1. Settings page — generate or enter your API token.

== Changelog ==

= 1.3.0 =
* Rename: Plugin renamed to BahriCanli Publisher (slug: bahricanli-publisher).
* Update: All function prefixes changed to `bcp_`, option key to `bahricanli_publisher_token`.
* Update: REST namespace changed to `bahricanli-publisher/v1`.

= 1.2.0 =
* Fix: Featured image sideloading now works for URLs without a file extension (e.g. Unsplash).
* Fix: Media upload permission granted via capability filter for token-authenticated requests.
* Fix: Sanitize all `$_POST` fields in fix_images handler.

= 1.1.0 =
* Add: Image resize on upload (max 1600 px width).
* Add: `fix_images` AJAX action to sideload external images already in post content.
* Add: `version` AJAX action to check installed vs latest WordPress version.
* Improve: Fallback from custom cURL downloader to `media_sideload_image` on failure.

= 1.0.0 =
* Initial release.
* REST API endpoints: POST `/bahricanli-publisher/v1/posts`, PUT `/bahricanli-publisher/v1/posts/{id}`.
* AJAX fallback endpoints for hosts that block the REST API.
* Token-based authentication with settings page.

== Upgrade Notice ==

= 1.3.0 =
Plugin renamed to BahriCanli Publisher. After upgrading, re-enter your token in Settings → BahriCanli Publisher. Update the AJAX action names in content-manager.tr if you use the AJAX fallback endpoints.
