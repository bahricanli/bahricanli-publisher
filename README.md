# BahriCanli Publisher

[Türkçe](README.tr.md)

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/bahricanli-publisher)](https://wordpress.org/plugins/bahricanli-publisher/)
[![GitHub Release](https://img.shields.io/github/v/release/bahricanli/bahricanli-publisher)](https://github.com/bahricanli/bahricanli-publisher/releases/latest)
[![Tests](https://github.com/bahricanli/bahricanli-publisher/actions/workflows/tests.yml/badge.svg)](https://github.com/bahricanli/bahricanli-publisher/actions/workflows/tests.yml)
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/bahricanli/bahricanli-publisher/badge)](https://scorecard.dev/viewer/?uri=github.com/bahricanli/bahricanli-publisher)
[![License: GPL-3.0-or-later](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)
[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-ea4aaa?logo=githubsponsors)](https://github.com/sponsors/bmericc)

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
- Turkish-language settings screen

## Installation

1. Install from the [WordPress.org plugin directory](https://wordpress.org/plugins/bahricanli-publisher/), or upload the `bahricanli-publisher` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → BahriCanli Publisher**.
4. Generate or enter a token and copy it to your content-manager.tr app's `.env` file as `WORDPRESS_PLUGIN_TOKEN`.

## Development

GitHub Actions publishes releases to the WordPress.org SVN repository when a version tag such as `1.12.1` is pushed. Use the `X.Y.Z` format without a `v` prefix. [WordPress.org SVN](https://plugins.svn.wordpress.org/bahricanli-publisher/) · [Deploy workflow](.github/workflows/deploy-wordpress-org.yml)

The GitHub release workflow attaches an installable plugin ZIP and its signed Sigstore provenance. To verify a downloaded ZIP, run `gh attestation verify bahricanli-publisher.zip --repo bahricanli/bahricanli-publisher`. [GitHub releases](https://github.com/bahricanli/bahricanli-publisher/releases) · [Release workflow](.github/workflows/github-release.yml)

To cut a release:

1. Bump `Version:` in `bahricanli-publisher.php` and `Stable tag:` in `readme.txt` to match.
2. Add a changelog entry to `readme.txt`.
3. Once the release commit is on `main`, run `git tag X.Y.Z` and `git push origin X.Y.Z`.

`readme.txt` (not this file) is what WordPress.org parses for the plugin directory page — see the [WordPress readme standard](https://wordpress.org/plugins/readme.txt) for its format.

[Contributing](CONTRIBUTING.md) · [Changelog](CHANGELOG.md)

## License

Licensed under the GNU General Public License v3.0 or later (`GPL-3.0-or-later`) starting with version `1.12.1`. [License text](LICENSE)
