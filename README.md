# BahriCanli Publisher

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

The official WordPress companion plugin for [content-manager.tr](https://content-manager.tr) — an AI-powered multi-site content management system built and maintained by Bahri Meriç Canlı.

content-manager.tr manages content for multiple WordPress sites simultaneously, generating and scheduling posts with AI assistance. This plugin acts as the bridge: it exposes a secure, token-authenticated endpoint that content-manager.tr uses to create, update and delete posts on your site.

📦 **WordPress.org listing:** https://wordpress.org/plugins/bahricanli-publisher/

## Features

- **Publish** new posts with title, content (Gutenberg blocks supported), excerpt, slug, categories, tags, author and featured image
- **Update** existing posts, including replacing the featured image
- **Delete** posts
- **Sideload featured images** from external URLs (Unsplash, Pexels, Pixabay, etc.) directly into the WordPress media library
- **Auto-resize** uploaded images to a maximum width of 1600 px
- Token-based authentication — no OAuth or WordPress login required
- REST API endpoints, with an AJAX fallback for hosts that block the REST API
- Translation-ready — bundled Turkish (`tr_TR`) translation, defaults to English

## Installation

1. Install from the [WordPress.org plugin directory](https://wordpress.org/plugins/bahricanli-publisher/), or upload the `bahricanli-publisher` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → BahriCanli Publisher**.
4. Generate or enter a token and copy it to your content-manager.tr app's `.env` file as `WORDPRESS_PLUGIN_TOKEN`.

## Development

This repository is the source of truth. Releases are published to the [WordPress.org SVN repository](https://plugins.svn.wordpress.org/bahricanli-publisher/) automatically by GitHub Actions whenever a version tag (e.g. `1.6.5`) is pushed — see [`.github/workflows/deploy-wordpress-org.yml`](.github/workflows/deploy-wordpress-org.yml).

To cut a release:

1. Bump `Version:` in `bahricanli-publisher.php` and `Stable tag:` in `readme.txt` to match.
2. Add a changelog entry to `readme.txt`.
3. `git tag X.Y.Z && git push origin X.Y.Z`

`readme.txt` (not this file) is what WordPress.org parses for the plugin directory page — see the [WordPress readme standard](https://wordpress.org/plugins/readme.txt) for its format.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
