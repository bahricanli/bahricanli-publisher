<?php
/**
 * Plugin Name: BahriCanli Publisher
 * Plugin URI:  https://content-manager.tr
 * Description: Connects your WordPress site to content-manager.tr — publish, update and delete posts via a secure token-based API. Supports featured image sideloading, Gutenberg blocks, categories and tags. Built and maintained by Bahri Meriç Canlı.
 * Version:     1.3.1
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

define('BCP_TOKEN_OPTION', 'bahricanli_publisher_token');

// ─── REST API Endpoint ────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    register_rest_route('bahricanli-publisher/v1', '/posts', [
        'methods'             => 'POST',
        'callback'            => 'bcp_create_post',
        'permission_callback' => 'bcp_check_token',
    ]);
    register_rest_route('bahricanli-publisher/v1', '/posts/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'callback'            => 'bcp_update_post',
        'permission_callback' => 'bcp_check_token',
    ]);
});

function bcp_check_token(WP_REST_Request $request): bool
{
    $token = get_option(BCP_TOKEN_OPTION, '');
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

function bcp_create_post(WP_REST_Request $request): WP_REST_Response
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
        'post_author'   => 1,
        'post_category' => $category_ids ?: [1],
        'tags_input'    => $tag_ids,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }

    $featured_image_url = sanitize_url($params['featured_image'] ?? '');
    if ($featured_image_url) {
        $attachment_id = bcp_sideload_image($featured_image_url, $post_id, $title);
        if ($attachment_id && ! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    return new WP_REST_Response([
        'post_id'  => $post_id,
        'post_url' => get_permalink($post_id),
        'status'   => $status,
    ], 201);
}

function bcp_update_post(WP_REST_Request $request): WP_REST_Response
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

    if (! empty($params['featured_image'])) {
        $title         = get_the_title($post_id);
        $attachment_id = bcp_sideload_image(sanitize_url($params['featured_image']), $post_id, $title);
        if ($attachment_id && ! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    return new WP_REST_Response([
        'post_id'  => $post_id,
        'post_url' => get_permalink($post_id),
        'updated'  => true,
    ], 200);
}

// Grants upload_files capability temporarily for token-authenticated requests.
function bcp_grant_upload_cap(array $allcaps, array $caps): array
{
    if (in_array('upload_files', $caps, true)) {
        $allcaps['upload_files'] = true;
    }
    return $allcaps;
}

function bcp_sideload_image(string $url, int $post_id, string $desc): int|WP_Error
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filter_added = false;
    if (! current_user_can('upload_files')) {
        add_filter('user_has_cap', 'bcp_grant_upload_cap', 10, 2);
        $filter_added = true;
    }

    $tmp = bcp_download_image($url);
    if (is_wp_error($tmp)) {
        if ($filter_added) {
            remove_filter('user_has_cap', 'bcp_grant_upload_cap', 10);
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
        remove_filter('user_has_cap', 'bcp_grant_upload_cap', 10);
    }

    if (! is_wp_error($id)) {
        bcp_resize_attachment((int) $id, 1600);
    }

    return $id;
}

function bcp_resize_attachment(int $id, int $maxW): void
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

function bcp_download_image(string $url): string|WP_Error
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WordPress)',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (! $data || $code !== 200) {
        return new WP_Error('download_failed', "Görsel indirilemedi: HTTP {$code}");
    }

    $tmp = tempnam(sys_get_temp_dir(), 'bcp_img_') . '.jpg';
    file_put_contents($tmp, $data);
    return $tmp;
}

// ─── AJAX Endpoint (REST API engellendiğinde fallback) ───────────────────────

add_action('wp_ajax_nopriv_bcp_post', 'bcp_ajax_handler');
add_action('wp_ajax_bcp_post',        'bcp_ajax_handler');

/**
 * JSON yanıtını istemciye hemen gönderir; mümkünse (PHP-FPM) bağlantıyı kapatıp
 * script'in arka planda çalışmaya devam etmesini sağlar.
 *
 * Neden gerekli: wp_insert_post()/wp_update_post() hızlı tamamlanıyor ama ardından
 * çalışan bcp_sideload_image() (görsel indirme + boyutlandırma) yavaş olabiliyor.
 * Yanıt görsel işlemi bitene kadar gecikirse content-manager'ın 45sn HTTP timeout'u
 * dolup post'u "failed" (wp_post_id=null) işaretliyordu; bu da aynı yazının bir
 * sonraki cron çalışmasında tekrar gönderilip WordPress'te mükerrer post oluşmasına
 * yol açıyordu. Artık post_id, görsel adımı beklenmeden garanti şekilde dönüyor.
 */
function bcp_respond_now(array $data, int $status = 200): void
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

function bcp_ajax_handler(): void
{
    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $token    = get_option(BCP_TOKEN_OPTION, '');
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

        // Güncelleme tamamlandı — yanıtı hemen gönder, görsel işlemi arkadan devam etsin.
        bcp_respond_now(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'updated' => true]);

        if (! empty($_POST['featured_image'])) {
            $att = bcp_sideload_image(sanitize_url(wp_unslash($_POST['featured_image'])), $post_id, get_the_title($post_id));
            if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
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
        'post_author'   => 1,
        'post_category' => $category_ids ?: [1],
        'tags_input'    => $tag_ids,
    ], true);

    if (is_wp_error($post_id)) {
        wp_send_json(['error' => $post_id->get_error_message()], 500);
    }

    // Yazı oluşturuldu — post_id burada kesinleşti, yanıtı hemen gönder. Görsel indirme/
    // boyutlandırma adımı yavaş olabildiğinden önce burada dönülmezse content-manager
    // zaman aşımına düşüp aynı yazıyı tekrar gönderiyor, WordPress'te mükerrer post
    // oluşuyordu.
    bcp_respond_now(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'status' => $status], 201);

    $featured = sanitize_url(wp_unslash($_POST['featured_image'] ?? ''));
    if ($featured) {
        $att = bcp_sideload_image($featured, $post_id, $title);
        if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
    }

    exit;
}

// ─── Silme AJAX ──────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_bcp_delete', 'bcp_delete_handler');
add_action('wp_ajax_bcp_delete',        'bcp_delete_handler');

function bcp_delete_handler(): void
{
    $token    = get_option(BCP_TOKEN_OPTION, '');
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

add_action('wp_ajax_nopriv_bcp_fix_images', 'bcp_fix_images_handler');
add_action('wp_ajax_bcp_fix_images',        'bcp_fix_images_handler');

function bcp_fix_images_handler(): void
{
    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $token    = get_option(BCP_TOKEN_OPTION, '');
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

    $featured_url = sanitize_url(wp_unslash($_POST['featured_image'] ?? ''));
    if ($featured_url && ! bcp_is_local_url($featured_url, $site_host)) {
        $att_id = bcp_sideload_image($featured_url, $post_id, $post->post_title);
        if ($att_id && ! is_wp_error($att_id)) {
            set_post_thumbnail($post_id, $att_id);
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
            if (bcp_is_local_url($url, $site_host)) return $m[0];

            $att_id = bcp_sideload_image($url, $post_id, '');
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
        'post_id' => $post_id,
        'fixed'   => $fixed,
        'errors'  => $errors,
    ]);
}

function bcp_is_local_url(string $url, string $site_host): bool
{
    if (str_starts_with($url, '/')) return true;
    $host = parse_url($url, PHP_URL_HOST);
    return $host === $site_host || str_ends_with($host, '.' . $site_host);
}

// ─── Version Info ─────────────────────────────────────────────────────────────

add_action('wp_ajax_nopriv_bcp_version', 'bcp_version_handler');
add_action('wp_ajax_bcp_version',        'bcp_version_handler');

function bcp_version_handler(): void
{
    global $wp_version;

    $token    = get_option(BCP_TOKEN_OPTION, '');
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
        'bcp_settings_page'
    );
});

function bcp_settings_page(): void
{
    if (isset($_POST['bcp_token'])) {
        check_admin_referer('bcp_save_token');
        update_option(BCP_TOKEN_OPTION, sanitize_text_field(wp_unslash($_POST['bcp_token'])));
        echo '<div class="notice notice-success"><p>Token kaydedildi.</p></div>';
    }

    $token = get_option(BCP_TOKEN_OPTION, '');
    ?>
    <div class="wrap">
        <h1>BahriCanli Publisher Ayarları</h1>
        <p>Bu token, <strong>content-manager.tr</strong> servisindeki <code>WORDPRESS_PLUGIN_TOKEN</code> değeriyle eşleşmelidir.</p>

        <form method="post">
            <?php wp_nonce_field('bcp_save_token'); ?>
            <table class="form-table">
                <tr>
                    <th>API Token</th>
                    <td>
                        <input type="text" name="bcp_token"
                               value="<?php echo esc_attr($token); ?>"
                               class="regular-text" />
                        <button type="button" onclick="
                            const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                            let t=''; for(let i=0;i<48;i++) t+=chars[Math.floor(Math.random()*chars.length)];
                            document.querySelector('[name=bcp_token]').value=t;
                        " class="button">Yeni Token Oluştur</button>
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
