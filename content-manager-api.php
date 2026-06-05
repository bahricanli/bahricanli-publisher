<?php
/**
 * Plugin Name: Content Manager API
 * Description: Content Manager servisinden WordPress'e yazı almak için REST API endpoint.
 * Version: 1.1.0
 * Author: Bahri Meriç Canlı
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CM_TOKEN_OPTION', 'content_manager_api_token');

// ─── REST API Endpoint ────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    register_rest_route('content-manager/v1', '/posts', [
        'methods'             => 'POST',
        'callback'            => 'cm_create_post',
        'permission_callback' => 'cm_check_token',
    ]);
    register_rest_route('content-manager/v1', '/posts/(?P<id>\d+)', [
        'methods'             => 'PUT',
        'callback'            => 'cm_update_post',
        'permission_callback' => 'cm_check_token',
    ]);
});

function cm_check_token(WP_REST_Request $request): bool
{
    $token = get_option(CM_TOKEN_OPTION, '');
    if (empty($token)) {
        return false;
    }

    // 1. Header'dan oku
    $incoming = $request->get_header('X-Content-Manager-Token');

    // 2. Header gelmemişse query param'dan dene
    if (empty($incoming)) {
        $incoming = $request->get_param('_cm_token');
    }

    // 3. JSON body'den dene
    if (empty($incoming)) {
        $incoming = $request->get_json_params()['_cm_token'] ?? '';
    }

    return hash_equals($token, (string) $incoming);
}

function cm_create_post(WP_REST_Request $request): WP_REST_Response
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

    // Kategorileri ID'ye çevir (yoksa oluştur)
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

    // Etiketleri ID'ye çevir (yoksa oluştur)
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

    $post_data = [
        'post_title'    => $title,
        'post_content'  => $content,
        'post_excerpt'  => $excerpt,
        'post_name'     => $slug,
        'post_status'   => $status,
        'post_author'   => 1,
        'post_category' => $category_ids ?: [1],
        'tags_input'    => $tag_ids,
    ];

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }

    // Öne çıkan görsel — URL'den indir ve medya kütüphanesine ekle
    $featured_image_url = sanitize_url($params['featured_image'] ?? '');
    if ($featured_image_url) {
        $attachment_id = cm_sideload_image($featured_image_url, $post_id, $title);
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

function cm_update_post(WP_REST_Request $request): WP_REST_Response
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

    // Öne çıkan görsel güncelle
    if (! empty($params['featured_image'])) {
        $title         = get_the_title($post_id);
        $attachment_id = cm_sideload_image($params['featured_image'], $post_id, $title);
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

function cm_sideload_image(string $url, int $post_id, string $desc): int|WP_Error
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    return media_sideload_image($url, $post_id, $desc, 'id');
}

// ─── AJAX Endpoint (REST API engellendiğinde fallback) ───────────────────────

add_action('wp_ajax_nopriv_cm_post', 'cm_ajax_handler');
add_action('wp_ajax_cm_post',        'cm_ajax_handler');

function cm_ajax_handler(): void
{
    // Medya fonksiyonları için gerekli admin include'ları yükle
    if (! function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $token    = get_option(CM_TOKEN_OPTION, '');
    $incoming = $_POST['_cm_token'] ?? $_SERVER['HTTP_X_CONTENT_MANAGER_TOKEN'] ?? '';

    if (empty($token) || ! hash_equals($token, (string) $incoming)) {
        wp_send_json(['error' => 'Unauthorized'], 403);
    }

    $action = $_POST['cm_action'] ?? 'create';

    if ($action === 'update' && ! empty($_POST['post_id'])) {
        // Güncelle
        $post_id     = (int) $_POST['post_id'];
        $update_data = ['ID' => $post_id];
        if (! empty($_POST['title']))   $update_data['post_title']   = sanitize_text_field($_POST['title']);
        if (! empty($_POST['content'])) $update_data['post_content'] = wp_kses_post(wp_unslash($_POST['content']));
        if (! empty($_POST['excerpt'])) $update_data['post_excerpt'] = sanitize_textarea_field($_POST['excerpt']);

        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
            wp_send_json(['error' => $result->get_error_message()], 500);
        }

        if (! empty($_POST['featured_image'])) {
            $att = cm_sideload_image(sanitize_url($_POST['featured_image']), $post_id, get_the_title($post_id));
            if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
        }

        wp_send_json(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'updated' => true]);
    }

    // Yeni yazı oluştur
    $title   = sanitize_text_field($_POST['title']   ?? '');
    $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
    $excerpt = sanitize_textarea_field($_POST['excerpt'] ?? '');
    $slug    = sanitize_title($_POST['slug'] ?? $title);
    $status  = in_array($_POST['status'] ?? 'draft', ['publish', 'draft', 'pending'], true)
               ? $_POST['status'] : 'draft';

    if (empty($title) || empty($content)) {
        wp_send_json(['error' => 'title ve content zorunlu'], 400);
    }

    // Kategoriler
    $category_ids = [];
    foreach (json_decode(wp_unslash($_POST['categories'] ?? '[]'), true) ?: [] as $cat_name) {
        $cat_name = sanitize_text_field($cat_name);
        $term     = term_exists($cat_name, 'category') ?: wp_insert_term($cat_name, 'category');
        if (! is_wp_error($term)) $category_ids[] = (int)(is_array($term) ? $term['term_id'] : $term);
    }

    // Etiketler
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

    $featured = sanitize_url($_POST['featured_image'] ?? '');
    if ($featured) {
        $att = cm_sideload_image($featured, $post_id, $title);
        if ($att && ! is_wp_error($att)) set_post_thumbnail($post_id, $att);
    }

    wp_send_json(['post_id' => $post_id, 'post_url' => get_permalink($post_id), 'status' => $status], 201);
}

// ─── Ayarlar Sayfası ─────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        'Content Manager API',
        'Content Manager API',
        'manage_options',
        'content-manager-api',
        'cm_settings_page'
    );
});

function cm_settings_page(): void
{
    if (isset($_POST['cm_token'])) {
        check_admin_referer('cm_save_token');
        update_option(CM_TOKEN_OPTION, sanitize_text_field($_POST['cm_token']));
        echo '<div class="notice notice-success"><p>Token kaydedildi.</p></div>';
    }

    $token = get_option(CM_TOKEN_OPTION, '');
    ?>
    <div class="wrap">
        <h1>Content Manager API Ayarları</h1>
        <p>Bu token, <strong>content-manager.tr</strong> servisindeki <code>WORDPRESS_PLUGIN_TOKEN</code> değeriyle eşleşmelidir.</p>

        <form method="post">
            <?php wp_nonce_field('cm_save_token'); ?>
            <table class="form-table">
                <tr>
                    <th>API Token</th>
                    <td>
                        <input type="text" name="cm_token"
                               value="<?php echo esc_attr($token); ?>"
                               class="regular-text" />
                        <button type="button" onclick="
                            const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                            let t=''; for(let i=0;i<48;i++) t+=chars[Math.floor(Math.random()*chars.length)];
                            document.querySelector('[name=cm_token]').value=t;
                        " class="button">Yeni Token Oluştur</button>
                    </td>
                </tr>
                <tr>
                    <th>Endpoint URL</th>
                    <td>
                        <code><?php echo esc_url(rest_url('content-manager/v1/posts')); ?></code>
                        <p class="description">Bu URL'yi content-manager.tr ayarlarına girin.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Kaydet'); ?>
        </form>
    </div>
    <?php
}
