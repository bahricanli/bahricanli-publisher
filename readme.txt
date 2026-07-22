=== BahriCanli Publisher ===
Contributors:      bmericc
Tags:              api, content, publishing, rest-api, automation
Requires at least: 6.0
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        1.9.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Connects your WordPress site to content-manager.tr — publish, update and delete posts via a secure token-based API.

== Description ==

**BahriCanli Publisher** is the official WordPress companion plugin for [content-manager.tr](https://content-manager.tr) — an AI-powered multi-site content management system built and maintained by Bahri Meriç Canlı.

content-manager.tr manages content for multiple WordPress sites simultaneously, generating and scheduling posts with AI assistance. This plugin acts as the bridge: it exposes a secure endpoint that content-manager.tr uses to create, update and delete posts on your site.

Once installed and configured, the plugin allows content-manager.tr to:

* **Publish** new posts with title, content (Gutenberg blocks supported), excerpt, slug, categories, tags, author and featured image
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

This plugin makes an outbound HTTP request to the WordPress.org API to check whether a WordPress core update is available. This request is triggered only when the `bahrpu_version` AJAX action is called by the content-manager.tr application.

* Service: WordPress.org Core Version Check API
* URL: `https://api.wordpress.org/core/version-check/1.7/`
* Purpose: Retrieve the latest available WordPress version number
* Data sent: No personal data is sent. The request contains only standard HTTP headers.
* Privacy policy: https://wordpress.org/about/privacy/

== Screenshots ==

1. Settings page — generate or enter your API token.

== Changelog ==

= 1.9.0 =
* Feat: Ayarlar sayfasına "Güncelleme Kontrolü" butonu eklendi — tıklanınca update_plugins önbelleği temizlenir ve WordPress.org'dan güncel sürüm bilgisi çekilir.

= 1.8.1 =
* Chore: Sideload edilen görselin dosya adı kaynak URL yerine yazı başlığı slug'ından oluşturuluyor.

= 1.8.0 =
* Feat: og:image için ayrı küçük JPEG sideload — `original_image_url` parametresi Pexels/Unsplash'tan `fm=jpg&w=1200` ile JPEG olarak indirilir, webp-uploads AVIF dönüşümü o yükleme için geçici devre dışı bırakılır, WP URL'i `_cm_social_image_url` post meta'ya kaydedilir.
* Fix: update handler'da `$_POST` değişkenleri `fastcgi_finish_request` öncesinde okunuyor.
* Chore: `.jpeg` uzantısı `.jpg`'ye normalize edildi.

= 1.7.0 =
* Feat: `bahrpu_fix_images` AJAX action now returns `featured_image_url` in the response, enabling the content manager to retrieve the sideloaded image URL directly without a separate REST API call.

= 1.6.5 =
* Chore: Added a GitHub-facing README.md alongside readme.txt (WordPress.org still reads readme.txt).
* Chore: Deploy action pinned to a fixed commit SHA per automated security review.

= 1.6.4 =
* Add: Full Turkish (tr_TR) translation bundled with the plugin. The settings page now automatically
  displays in Turkish when the WordPress site's language is set to Turkish, and in English otherwise.
* Improve: All settings-page strings are now wrapped for translation (`load_plugin_textdomain`),
  making the plugin translation-ready for additional languages in the future.

= 1.6.3 =
* Fix: readme.txt `Contributors` alanından geçersiz WordPress.org kullanıcı adı (`bahricanli`) kaldırıldı.

= 1.6.2 =
* Chore: WordPress.org eklenti sayfası için icon/banner görselleri eklendi (SVN assets/ dizini).
* Chore: Repoda kalan bayat build zip dosyası kaldırıldı. Fonksiyonel bir değişiklik yoktur.

= 1.6.1 =
* Chore: GitHub üzerinden WordPress.org SVN'ine otomatik dağıtım (GitHub Actions) kuruldu.
  Bu, yalnızca yayın altyapısıyla ilgili bir değişikliktir; eklenti işlevselliğinde bir
  değişiklik yoktur.

= 1.6.0 =
* Fix: Posts published or updated via content-manager.tr now also populate Yoast SEO's per-post
  meta description field (`_yoast_wpseo_metadesc`) from the post excerpt. Previously the excerpt
  was only saved to WordPress's native excerpt field, so Yoast's search-result meta description
  tag was left empty whenever the site's own Yoast templates had no fallback configured.

= 1.5.0 =
* Fix: All internal function names, defines and the AJAX action prefix renamed from the too-short
  `bcp_` to the unique `bahrpu_` prefix, per WordPress.org Plugin Review feedback (naming collisions).
* Fix: Removed direct `curl_*` usage for downloading remote images — now uses the WordPress HTTP API
  (`wp_remote_get`), per WordPress.org Plugin Review feedback.

= 1.4.0 =
* Add: "Varsayılan Yazar" (Default Author) setting on the settings page — choose which WordPress user
  new posts published via content-manager.tr are assigned to (defaults to user ID 1 if unset).

= 1.3.1 =
* Fix: AJAX post oluşturma/güncellemede yanıt (post_id) artık öne çıkan görsel indirilip
  boyutlandırılmadan HEMEN gönderiliyor (mümkünse `fastcgi_finish_request()` ile). Önceden
  yavaş/başarısız görsel indirme adımı content-manager'ın 45sn zaman aşımına neden olup
  post'u "failed" işaretliyor, bu da aynı yazının tekrar gönderilip WordPress'te mükerrer
  post oluşmasına yol açıyordu.

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
