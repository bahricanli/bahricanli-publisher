<?php
/**
 * Plugin Name: BahriCanli Publisher
 * Plugin URI:  https://content-manager.tr
 * Description: Connects your WordPress site to content-manager.tr — publish, update and delete posts via a secure token-based API. Supports featured image sideloading, Gutenberg blocks, categories, tags and author selection. Built and maintained by Bahri Meriç Canlı.
 * Version:     1.7.0
 * Author:      Bahri Meriç Canlı
 * Author URI:  https://www.bahricanli.tr
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bahricanli-publisher
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if (! defined('ABSPATH')) {
    exit;
}

define('BAHRPU_TOKEN_OPTION', 'bahricanli_publisher_token');
define('BAHRPU_AUTHOR_OPTION', 'bahricanli_publisher_default_author');

// ─── REST API Endpoint ────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    register_rest_route('bahricanli-publisher/v1', '/posts', [
        'methods'             => 'POST',
        'callback'            => 'bahrpu_create_post',
        'permission_callback' => 'bahrpu_check_token',
    ]);
    register_rest_route('bahricanli-publisher/v1', '/posts/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'callback'            => 'bahrpu_update_post',
        'permission_callback' => 'bahrpu_check_token',
    ]);
});

/**
 * Yazı yazarı olarak atanabilecek kullanıcıları döndürür (edit_posts yetkisi olanlar).
 * Ayarlar sayfasındaki "Varsayılan Yazar" açılır listesini doldurmak için kullanılır.
 *
 * @return array<int, array{id:int,name:string}>
 */
function bahrpu_get_authorable_users(): array
{
    $users = get_users([
        'capability' => ['edit_posts'],
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'fields'     => ['ID', 'display_name'],
    ]);

    $result = [];
    foreach ($users as $user) {
        $result[] = [
            'id'   => (int) $user->ID,
            'name' => $user->display_name,
        ];
    }

    return $result;
}

/**
 * Yeni yazının hangi WordPress kullanıcısına atanacağını belirler.
 * Ayarlar sayfasında bir "Varsayılan Yazar" seçilmişse ve o kullanıcı hâlâ
 * yazı yazabiliyorsa onu kullanır; aksi halde WordPress'in ilk kullanıcısına (1) düşer.
 */
function bahrpu_resolve_author_id(): int
{
    $author_id = (int) get_option(BAHRPU_AUTHOR_OPTION, 0);
    if ($author_id > 0 && user_can($author_id, 'edit_posts')) {
        return $author_id;
    }
    return 1;
}

/**
 * Yoast SEO'nun meta description alanını (`_yoast_wpseo_metadesc`) doldurur.
 *
 * Neden gerekli: content-manager her zaman `excerpt` gönderiyor ve bu WP'nin
 * kendi `post_excerpt` alanına yazılıyor, ama Yoast SEO arama sonucu meta
 * description'ı (`<meta name="description">`) İÇİN ayrı bir postmeta alanı
 * kullanıyor. Bu alan hiç set edilmediğinde ve sitenin Yoast şablonu da boşsa,
 * Yoast description etiketini tamamen atlıyor (og:description ise kendi
 * excerpt fallback'i sayesinde dolu geliyordu — asıl SEO description eksikti).
 */
function bahrpu_set_seo_meta_description(int $post_id, string $excerpt): void
{
    $excerpt = trim(wp_strip_all_tags($excerpt));
    if ($excerpt === '') {
        return;
    }

    if (mb_strlen($excerpt) > 155) {
        $excerpt = mb_substr($excerpt, 0, 155);
        $excerpt = trim(mb_substr($excerpt, 0, mb_strrpos($excerpt, ' ') ?: 155)) . '…';
    }

    update_post_meta($post_id, '_yoast_wpseo_metadesc', $excerpt);
}

function bahrpu_check_token(WP_REST_Request $request): bool
{
    $token = get_option(BAHRPU_TOKEN_OPTION, '');
    if (empty($token)) {
        return false;
    }

    $incoming = $request->get_header('X-Content-Manager-Token');

    if (empty($incoming)) {
        $incoming = $request->get_param('_cm_token');
    }

    if (empty($incoming)) {
        $incoming = $request->get_json_params()['_cm_token'] ?? '';
    }

    return hash_equals($token, (string) $incoming);
}

function bahrpu_create_post(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params();

    $title   = sanitize_text_field($params['title'] ?? '');
    $content = wp_kses_post($params['content'] ?? '');
    $excerpt = sanitize_textarea_field($params['excerpt'] ?? '');
    $slug    = sanitize_title($params['slug'] ?? $title);
    $status  = in_array($params['status'] ?? 'draft', ['publish', 'draft', 'pending'], true)
               ? $params['status']
               : 'draft';

    if (empty($title) || empty($content)) {
        return new WP_REST_Response(['error' => 'title ve content zorunlu'], 400);
    }

    $category_ids = [];
    foreach ((array) ($params['categories'] ?? []) as $cat_name) {
        $cat_name = sanitize_text_field($cat_name);
        $term     = term_exists($cat_name, 'category');
        if (! $term) {
            $term = wp_insert_term($cat_name, 'category');
        }
        if (! is_wp_error($term)) {
            $category_ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
        }
    }

    $tag_ids = [];
    foreach ((array) ($params['tags'] ?? []) as $tag_name) {
        $tag_name = sanitize_text_field($tag_name);
        $term     = term_exists($tag_name, 'post_tag');
        if (! $term) {
            $term = wp_insert_term($tag_name, 'post_tag');
        }
        if (! is_wp_error($term)) {
            $tag_ids[] = (int) (is_array($term) ? $term['term_id'] : $term);
        }
    }

    $post_id = wp_insert_post([
        'post_title'    => $title,
        'post_content'  => $content,
        'post_excerpt'  => $excerpt,
        'post_name'     => $slug,
        'post_status'   => $status,
        'post_author'   => bahrpu_resolve_author_id(),
        'post_category' => $category_ids ?: [1],
        'tags_input'    => $tag_ids,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }

    bahrpu_set_seo_meta_description($post_id, $excerpt);

    $featured_image_url = sanitize_url($params['featured_image'] ?? '');
    if ($featured_image_url) {
        $attachment_id = bahrpu_sideload_image($featured_image_url, $post_id, $title);
        if ($attachment_id && ! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    // og:image için küçük JPEG — AVIF featured image'dan ayrı, botlar AVIF desteklemiyor
    $original_image_url = sanitize_url($params['original_image_url'] ?? '');
    if ($original_image_url) {
        $social_url = bahrpu_social_image_url($original_image_url);
        $social_id  = bahrpu_sideload_image_as_jpeg($social_url, $post_id, $title);
        if ($social_id && ! is_wp_error($social_id)) {
            update_post_meta($post_id, '_cm_social_image_url', wp_get_attachment_url($social_id));
        }
    }

    return new WP_REST_Response([
        'post_id'  => $post_id,
        'post_url' => get_permalink($post_id),
        'status'   => $status,
    ], 201);
}

function bahrpu_update_post(WP_REST_Request $request): WP_REST_Response
{
    $post_id = (int) $request->get_param('id');
    $params  = $request->get_json_params();

    if (! get_post($post_id)) {
        return new WP_REST_Response(['error' => 'Post bulunamadı: ' . $post_id], 404);
    }

    $update_data = ['ID' => $post_id];

    if (! empty($params['title'])) {
        $update_data['post_title'] = sanitize_text_field($params['title']);
    }
    if (! empty($params['content'])) {
        $update_data['post_content'] = wp_kses_post($params['content']);
    }
    if (! empty($params['excerpt'])) {
        $update_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);
    }

    if (count($update_data) > 1) {
        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
    }

    if (! empty($params['excerpt'])) {
        bahrpu_set_seo_meta_description($post_id, sanitize_textarea_field($params['excerpt']));
    }

    $title = get_the_title($post_id);

    if (! empty($params['featured_image'])) {
        $attachment_id = bahrpu_sideload_image(sanitize_url($params['featured_image']), $post_id, $title);
        if ($attachment_id && ! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    // og:image için küçük JPEG güncelle
    $original_image_url = sanitize_url($params['original_image_url'] ?? '');
    if ($original_image_url) {
        $social_url = bahrpu_social_image_url($original_image_url);
        $social_id  = bahrpu_sideload_image_as_jpeg($social_url, $post_id, $title);
        if ($social_id && ! is_wp_error($social_id)) {
            update_post_meta($post_id, '_cm_social_image_url', wp_get_attachment_url($social_id));
        }
    }

    return new WP_REST_Response([
        'post_id'  => $post_id,
        'post_url' => get_permalink($post_id),
        'updated'  => true,
    ], 200);
}

// Grants upload_files capability temporarily for token-authenticated requests.
function bahrpu_grant_upload_cap(array $allcaps, array $caps): array
{
    if (in_array('upload_files', $caps, true)) {
        $allcaps['upload_files'] = true;
    }
    return $allcaps;
}

// AVIF dönüşümünü engelleyen filtreler
function bahrpu_no_avif_output( array $formats ): array {
    return array_filter( $formats, fn( $v ) => $v !== 'image/avif' );
}
function bahrpu_no_avif_mime( string $mime ): string {
    return $mime === 'image/avif' ? 'image/jpeg' : $mime;
}

// Sideload yapar; attachment AVIF olursa GD ile JPEG'e dönüştürür ve attachment günceller
function bahrpu_sideload_image_as_jpeg(string $url, int $post_id, string $desc): int|WP_Error
{
    add_filter( 'image_editor_output_format', 'bahrpu_no_avif_output', 999 );
    add_filter( 'wp_get_attachment_image_src',  '__return_false', 0 ); // önleyici değil, gerek yok
    $id = bahrpu_sideload_image( $url, $post_id, $desc );
    remove_filter( 'image_editor_output_format', 'bahrpu_no_avif_output', 999 );

    if ( is_wp_error( $id ) ) {
        return $id;
    }

    // Yine de AVIF olduysa (WP zorla çevirdiyse) GD ile JPEG'e dönüştür
    $path = get_attached_file( $id );
    if ( $path && preg_match( '/\.avif$/i', $path ) && function_exists( 'imagecreatefromavif' ) ) {
        $img      = @imagecreatefromavif( $path );
        $jpg_path = preg_replace( '/\.avif$/i', '.jpg', $path );
        if ( $img && imagejpeg( $img, $jpg_path, 90 ) ) {
            imagedestroy( $img );
            // Eski AVIF dosyasını sil, yeni JPEG'i kaydet
            @unlink( $path );
            update_attached_file( $id, $jpg_path );
            wp_update_post( [ 'ID' => $id, 'post_mime_type' => 'image/jpeg' ] );
            update_post_meta( $id, '_wp_attachment_metadata',
                array_merge(
                    (array) wp_get_attachment_metadata( $id ),
                    [ 'file' => _wp_relative_upload_path( $jpg_path ) ]
                )
            );
        }
    }

    return $id;
}

// og:image için Pexels/Unsplash URL'ini küçük JPEG'e çevirir (1200px, fm=jpg)
function bahrpu_social_image_url(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST) ?? '';
    if (str_contains($host, 'pexels.com')) {
        return add_query_arg(['fm' => 'jpg', 'w' => '1200'], $url);
    }
    if (str_contains($host, 'unsplash.com')) {
        return add_query_arg(['fm' => 'jpg', 'w' => '1200', 'auto' => 'format'], $url);
    }
    return $url;
}

function bahrpu_sideload_image(string $url, int $post_id, string $desc): int|WP_Error
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filter_added = false;
    if (! current_user_can('upload_files')) {
        add_filter('user_has_cap', 'bahrpu_grant_upload_cap', 10, 2);
        $filter_added = true;
    }

    $tmp = bahrpu_download_image($url);
    if (is_wp_error($tmp)) {
        if ($filter_added) {
            remove_filter('user_has_cap', 'bahrpu_grant_upload_cap', 10);
        }
        return media_sideload_image($url, $post_id, $desc, 'id');
    }

    $basename = basename(parse_url($url, PHP_URL_PATH)) ?: 'image';
    // Uzantı yoksa MIME'den belirle (Unsplash gibi uzantısız URL'ler için)
    if (! pathinfo($basename, PATHINFO_EXTENSION)) {
        $mime_type = mime_content_type($tmp);
        $ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $basename .= '.' . ($ext_map[$mime_type] ?? 'jpg');
    }
    $file_array = [
        'name'     => $basename,
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id, $desc);
    @unlink($tmp);

    if ($filter_added) {
        remove_filter('user_has_cap', 'bahrpu_grant_upload_cap', 10);
    }

    if (! is_wp_error($id)) {
        bahrpu_resize_attachment((int) $id, 1600);
    }

    return $id;
}

function bahrpu_resize_attachment(int $id, int $maxW): void
{
    $file = get_attached_file($id);
    if (! $file || ! file_exists($file)) {
        return;
    }

    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return;
    }

    $size = $editor->get_size();
    if (empty($size['width']) || $size['width'] <= $maxW) {
        return;
    }

    $editor->resize($maxW, 9999, false);
    $saved = $editor->save($file);

    if (! is_wp_error($saved)) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    }
}

/**
 * Görseli WordPress'in HTTP API'si (wp_remote_get) ile indirir.
 * Not: Daha önce doğrudan curl_* fonksiyonları kullanılıyordu; WordPress.org
 * eklenti incelemesi gereği yerel curl çağrıları kaldırılıp çekirdek HTTP API'ye
 * geçildi (bkz. https://developer.wordpress.org/plugins/http-api/).
 */
function bahrpu_download_image(string $url): string|WP_Error
{
    $response = wp_remote_get($url, [
        'timeout'     => 30,
        'redirection' => 5,
        'sslverify'   => true,
        'user-agent'  => 'Mozilla/5.0 (compatible; WordPress)',
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $data = wp_remote_retrieve_body($response);

    if (! $data || $code !== 200) {
        return new WP_Error('download_failed', "Görsel indirilemedi: HTTP {$code}");
    }

    $tmp = tempnam(sys_get_temp_dir(), 'bahrpu_img_') . '.jpg';
    file_put_contents($tmp, $data);
    return $tmp;
}

// ─── AJAX Endpoint (REST API engellendiğinde fallback) ───────────────────────

add_action('wp_ajax_nopriv_bahrpu_post', 'bahrpu_ajax_handler');
add_action('wp_ajax_bahrpu_post',        'bahrpu_ajax_handler');

/**
 * JSON yanıtını istemciye hemen gönderir; mümkünse (PHP-FPM) bağlantıyı kapatıp
 * script'in arka planda çalışmaya devam etmesini sağlar.
 *
 * Neden gerekli: wp_insert_post()/wp_update_post() hızlı tamamlanıyor ama ardından
 * çalışan bahrpu_sideload_image() (görsel indirme + boyutlandırma) yavaş olabiliyor.
 * Yanıt görsel işlemi bitene kadar gecikirse content-manager'ın 45sn HTTP timeout'u
 * dolup post'u "failed" (wp_post_id=null) işaretliyordu; bu da aynı yazının bir
 * sonraki cron çalışmasında tekrar gönderilip WordPress'te mükerrer post oluşmasına
 * yol açıyordu. Artık post_id, görsel adımı beklenmeden garanti şekilde dönüyor.
 */
function bahrpu_respond_now(array $data, int $status = 200): void
{
    if (! headers_sent()) {
        status_header($status);
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        $body = wp_json_encode($data);
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
    } else {
        echo wp_json_encode($data);
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    // İstemci bağlantıyı kapatsa/timeout olsa bile arka plandaki görsel işlemi tamamlansın.
    ignore_user_abort(true);
    @set_time_limit(60);
}

function bahrpu_ajax_handler(): void
{
    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $token    = get_option(BAHRPU_TOKEN_OPTION, '');
    $incoming = $_POST['_cm_token'] ?? $_SERVER['HTTP_X_CONTENT_MANAGER_TOKEN'] ?? '';

    if (empty($token) || ! hash_equals($token, (string) $incoming)) {
        wp_send_json(['error' => 'Unauthorized'], 403);
    }

    $action = sanitize_key($_POST['cm_action'] ?? 'create');

    if ($action === 'update' && ! empty($_POST['post_id'])) {
        $post_id     = (int) $_POST['post_id'];
        $update_data = ['ID' => $post_id];
        if (! empty($_POST['title']))   $update_data['post_title']   = sanitize_text_field($_POST['title']);
        if (! empty($_POST['content'])) $update_data['post_content'] = wp_kses_post(wp_unslash($_POST['content']));
        if (! empty($_POST['excerpt'])) $update_data['post_excerpt'] = sanitize_textarea_field($_POST['excerpt']);

        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
            wp_send_json(['error' => $result->get_error_message()], 500);
        }

        if (! empty($_POST['excerpt'])) {
            bahrpu_set_seo_meta_description($post_id, sanitize_textarea_field($_POST['excerpt']));
        }

        // $_POST'u bahrpu_respond_now öncesinde oku — fastcgi_finish_request sonrası kaybolabilir
        $featured_image_url = sanitize_url(wp_unslash($_POST['featured_image'] ?? ''));
        $orig_url           = sanitize_url(wp_unslash($_POST['original_image_url'] ?? ''));
        $post_title         = get_the_title($post_id);

        // Güncelleme tamamlandı — yanıtı hemen gönder, görsel işlemi arkadan devam etsin.
        bahrpu_respond_now(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'updated' => true]);

        if ($featured_image_url) {
            $att = bahrpu_sideload_image($featured_image_url, $post_id, $post_title);
            if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
        }

        // og:image için küçük JPEG — AVIF dönüşümü kapalı
        if ($orig_url) {
            $social_url = bahrpu_social_image_url($orig_url);
            $social_id  = bahrpu_sideload_image_as_jpeg($social_url, $post_id, $post_title);
            if ($social_id && ! is_wp_error($social_id)) {
                update_post_meta($post_id, '_cm_social_image_url', wp_get_attachment_url($social_id));
            }
        }

        exit;
    }

    $title   = sanitize_text_field($_POST['title']   ?? '');
    $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
    $excerpt = sanitize_textarea_field($_POST['excerpt'] ?? '');
    $slug    = sanitize_title($_POST['slug'] ?? $title);
    $status  = in_array($_POST['status'] ?? 'draft', ['publish', 'draft', 'pending'], true)
               ? $_POST['status'] : 'draft';

    if (empty($title) || empty($content)) {
        wp_send_json(['error' => 'title ve content zorunlu'], 400);
    }

    $category_ids = [];
    foreach (json_decode(wp_unslash($_POST['categories'] ?? '[]'), true) ?: [] as $cat_name) {
        $cat_name = sanitize_text_field($cat_name);
        $term     = term_exists($cat_name, 'category') ?: wp_insert_term($cat_name, 'category');
        if (! is_wp_error($term)) $category_ids[] = (int)(is_array($term) ? $term['term_id'] : $term);
    }

    $tag_ids = [];
    foreach (json_decode(wp_unslash($_POST['tags'] ?? '[]'), true) ?: [] as $tag_name) {
        $tag_name = sanitize_text_field($tag_name);
        $term     = term_exists($tag_name, 'post_tag') ?: wp_insert_term($tag_name, 'post_tag');
        if (! is_wp_error($term)) $tag_ids[] = (int)(is_array($term) ? $term['term_id'] : $term);
    }

    $post_id = wp_insert_post([
        'post_title'    => $title,
        'post_content'  => $content,
        'post_excerpt'  => $excerpt,
        'post_name'     => $slug,
        'post_status'   => $status,
        'post_author'   => bahrpu_resolve_author_id(),
        'post_category' => $category_ids ?: [1],
        'tags_input'    => $tag_ids,
    ], true);

    if (is_wp_error($post_id)) {
        wp_send_json(['error' => $post_id->get_error_message()], 500);
    }

    bahrpu_set_seo_meta_description($post_id, $excerpt);

    // Yazı oluşturuldu — post_id burada kesinleşti, yanıtı hemen gönder. Görsel indirme/
    // boyutlandırma adımı yavaş olabildiğinden önce burada dönülmezse content-manager
    // zaman aşımına düşüp aynı yazıyı tekrar gönderiyor, WordPress'te mükerrer post
    // oluşuyordu.
    bahrpu_respond_now(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'status' => $status], 201);

    $featured = sanitize_url(wp_unslash($_POST['featured_image'] ?? ''));
    if ($featured) {
        $att = bahrpu_sideload_image($featured, $post_id, $title);
        if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
    }

    exit;
}

// ─── Silme AJAX ──────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_bahrpu_delete', 'bahrpu_delete_handler');
add_action('wp_ajax_bahrpu_delete',        'bahrpu_delete_handler');

function bahrpu_delete_handler(): void
{
    $token    = get_option(BAHRPU_TOKEN_OPTION, '');
    $incoming = $_POST['_cm_token'] ?? $_SERVER['HTTP_X_CONTENT_MANAGER_TOKEN'] ?? '';
    if (empty($token) || ! hash_equals($token, (string) $incoming)) {
        wp_send_json(['error' => 'Unauthorized'], 403);
    }

    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (! $post_id || ! get_post($post_id)) {
        wp_send_json(['error' => "Post bulunamadı: {$post_id}"], 404);
    }

    $result = wp_delete_post($post_id, true);
    if ($result) {
        wp_send_json(['deleted' => true, 'post_id' => $post_id]);
    } else {
        wp_send_json(['error' => 'Silinemedi'], 500);
    }
}

// ─── Görsel Düzeltme AJAX ────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_bahrpu_fix_images', 'bahrpu_fix_images_handler');
add_action('wp_ajax_bahrpu_fix_images',        'bahrpu_fix_images_handler');

function bahrpu_fix_images_handler(): void
{
    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $token    = get_option(BAHRPU_TOKEN_OPTION, '');
    $incoming = $_POST['_cm_token'] ?? $_SERVER['HTTP_X_CONTENT_MANAGER_TOKEN'] ?? '';
    if (empty($token) || ! hash_equals($token, (string) $incoming)) {
        wp_send_json(['error' => 'Unauthorized'], 403);
    }

    $post_id = (int) ($_POST['post_id'] ?? 0);
    $post    = get_post($post_id);
    if (! $post) {
        wp_send_json(['error' => "Post bulunamadı: {$post_id}"], 404);
    }

    $site_host = parse_url(get_site_url(), PHP_URL_HOST);
    $fixed     = 0;
    $errors    = [];

    $new_featured_url = null;
    $featured_url = sanitize_url(wp_unslash($_POST['featured_image'] ?? ''));
    if ($featured_url && ! bahrpu_is_local_url($featured_url, $site_host)) {
        $att_id = bahrpu_sideload_image($featured_url, $post_id, $post->post_title);
        if ($att_id && ! is_wp_error($att_id)) {
            set_post_thumbnail($post_id, $att_id);
            $new_featured_url = wp_get_attachment_url($att_id);
            $fixed++;
        } else {
            $errors[] = "featured: " . (is_wp_error($att_id) ? $att_id->get_error_message() : 'hata');
        }
    }

    $content = $post->post_content;
    $updated_content = preg_replace_callback(
        '/<img([^>]*)\ssrc=["\']([^"\']+)["\']([^>]*)>/i',
        function ($m) use ($post_id, $site_host, &$fixed, &$errors) {
            $url = $m[2];
            if (bahrpu_is_local_url($url, $site_host)) return $m[0];

            $att_id = bahrpu_sideload_image($url, $post_id, '');
            if ($att_id && ! is_wp_error($att_id)) {
                $local_url = wp_get_attachment_url($att_id);
                $fixed++;
                return "<img{$m[1]} src=\"{$local_url}\"{$m[3]}>";
            }
            $errors[] = "content img: " . (is_wp_error($att_id) ? $att_id->get_error_message() : 'hata');
            return $m[0];
        },
        $content
    );

    if ($updated_content !== $content) {
        wp_update_post(['ID' => $post_id, 'post_content' => $updated_content]);
    }

    wp_send_json([
        'post_id'            => $post_id,
        'fixed'              => $fixed,
        'errors'             => $errors,
        'featured_image_url' => $new_featured_url,
    ]);
}

function bahrpu_is_local_url(string $url, string $site_host): bool
{
    if (str_starts_with($url, '/')) return true;
    $host = parse_url($url, PHP_URL_HOST);
    return $host === $site_host || str_ends_with($host, '.' . $site_host);
}

// ─── Version Info ─────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_bahrpu_version', 'bahrpu_version_handler');
add_action('wp_ajax_bahrpu_version',        'bahrpu_version_handler');

function bahrpu_version_handler(): void
{
    global $wp_version;

    $token    = get_option(BAHRPU_TOKEN_OPTION, '');
    $incoming = $_POST['_cm_token'] ?? $_GET['_cm_token'] ?? $_SERVER['HTTP_X_CONTENT_MANAGER_TOKEN'] ?? '';
    if (empty($token) || ! hash_equals($token, (string) $incoming)) {
        wp_send_json(['error' => 'Unauthorized'], 403);
    }

    // En son WP versiyonunu api.wordpress.org'dan çek (bkz. readme.txt — External Services)
    $latest   = null;
    $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/', ['timeout' => 10]);
    if (! is_wp_error($response)) {
        $data   = json_decode(wp_remote_retrieve_body($response), true);
        $latest = $data['offers'][0]['version'] ?? null;
    }

    wp_send_json([
        'installed'  => $wp_version,
        'latest'     => $latest,
        'has_update' => $latest && version_compare($wp_version, $latest, '<'),
    ]);
}

// ─── Ayarlar Sayfası ─────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        'BahriCanli Publisher',
        'BahriCanli Publisher',
        'manage_options',
        'bahricanli-publisher',
        'bahrpu_settings_page'
    );
});

function bahrpu_settings_page(): void
{
    if (isset($_POST['bahrpu_token'])) {
        check_admin_referer('bahrpu_save_token');
        update_option(BAHRPU_TOKEN_OPTION, sanitize_text_field(wp_unslash($_POST['bahrpu_token'])));
        update_option(BAHRPU_AUTHOR_OPTION, (int) ($_POST['bahrpu_author'] ?? 0));
        echo '<div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div>';
    }

    $token       = get_option(BAHRPU_TOKEN_OPTION, '');
    $author_id   = (int) get_option(BAHRPU_AUTHOR_OPTION, 0);
    $authorUsers = bahrpu_get_authorable_users();
    ?>
    <div class="wrap">
        <h1>BahriCanli Publisher Ayarları</h1>
        <p>Bu token, <strong>content-manager.tr</strong> servisindeki <code>WORDPRESS_PLUGIN_TOKEN</code> değeriyle eşleşmelidir.</p>

        <form method="post">
            <?php wp_nonce_field('bahrpu_save_token'); ?>
            <table class="form-table">
                <tr>
                    <th>API Token</th>
                    <td>
                        <input type="text" name="bahrpu_token"
                               value="<?php echo esc_attr($token); ?>"
                               class="regular-text" />
                        <button type="button" onclick="
                            const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                            let t=''; for(let i=0;i<48;i++) t+=chars[Math.floor(Math.random()*chars.length)];
                            document.querySelector('[name=bahrpu_token]').value=t;
                        " class="button">Yeni Token Oluştur</button>
                    </td>
                </tr>
                <tr>
                    <th>Varsayılan Yazar</th>
                    <td>
                        <select name="bahrpu_author">
                            <option value="0" <?php selected($author_id, 0); ?>>— WordPress varsayılanı (ID: 1) —</option>
                            <?php foreach ($authorUsers as $u) : ?>
                                <option value="<?php echo (int) $u['id']; ?>" <?php selected($author_id, $u['id']); ?>>
                                    <?php echo esc_html($u['name']); ?> (ID: <?php echo (int) $u['id']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">content-manager.tr üzerinden gönderilen yazılar bu kullanıcıya atanır.</p>
                    </td>
                </tr>
                <tr>
                    <th>Endpoint URL</th>
                    <td>
                        <code><?php echo esc_url(rest_url('bahricanli-publisher/v1/posts')); ?></code>
                        <p class="description">Bu URL'yi content-manager.tr ayarlarına girin.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Kaydet'); ?>
        </form>
    </div>
    <?php
}
