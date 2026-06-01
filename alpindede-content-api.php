<?php
/**
 * Plugin Name: AlpinDede Content API
 * Description: AlpinDede içerik servisinden yazı almak için özel REST API endpoint.
 * Version: 1.0.0
 * Author: Bahri Meriç Canlı
 */

if (! defined('ABSPATH')) {
    exit;
}

// Token ayarı (WordPress admin → Ayarlar → AlpinDede API)
define('ALPINDEDE_TOKEN_OPTION', 'alpindede_api_token');

// ─── REST API Endpoint ────────────────────────────────────────────────────────

add_action('rest_api_init', function () {
    register_rest_route('alpindede/v1', '/posts', [
        'methods'             => 'POST',
        'callback'            => 'alpindede_create_post',
        'permission_callback' => 'alpindede_check_token',
    ]);
});

function alpindede_check_token(WP_REST_Request $request): bool
{
    $token  = get_option(ALPINDEDE_TOKEN_OPTION, '');
    $header = $request->get_header('X-AlpinDede-Token');

    if (empty($token) || ! hash_equals($token, (string) $header)) {
        return false;
    }
    return true;
}

function alpindede_create_post(WP_REST_Request $request): WP_REST_Response
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
        $attachment_id = alpindede_sideload_image($featured_image_url, $post_id, $title);
        if ($attachment_id && ! is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    return new WP_REST_Response([
        'post_id'   => $post_id,
        'post_url'  => get_permalink($post_id),
        'status'    => $status,
    ], 201);
}

function alpindede_sideload_image(string $url, int $post_id, string $desc): int|WP_Error
{
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    return media_sideload_image($url, $post_id, $desc, 'id');
}

// ─── Ayarlar Sayfası ─────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        'AlpinDede API',
        'AlpinDede API',
        'manage_options',
        'alpindede-api',
        'alpindede_settings_page'
    );
});

function alpindede_settings_page(): void
{
    if (isset($_POST['alpindede_token'])) {
        check_admin_referer('alpindede_save_token');
        update_option(ALPINDEDE_TOKEN_OPTION, sanitize_text_field($_POST['alpindede_token']));
        echo '<div class="notice notice-success"><p>Token kaydedildi.</p></div>';
    }

    $token = get_option(ALPINDEDE_TOKEN_OPTION, '');
    ?>
    <div class="wrap">
        <h1>AlpinDede Content API Ayarları</h1>
        <p>Bu token, içerik servisindeki <code>WORDPRESS_PLUGIN_TOKEN</code> değeriyle eşleşmelidir.</p>

        <form method="post">
            <?php wp_nonce_field('alpindede_save_token'); ?>
            <table class="form-table">
                <tr>
                    <th>API Token</th>
                    <td>
                        <input type="text" name="alpindede_token"
                               value="<?php echo esc_attr($token); ?>"
                               class="regular-text" />
                        <button type="button" onclick="
                            const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                            let t=''; for(let i=0;i<48;i++) t+=chars[Math.floor(Math.random()*chars.length)];
                            document.querySelector('[name=alpindede_token]').value=t;
                        " class="button">Yeni Token Oluştur</button>
                    </td>
                </tr>
                <tr>
                    <th>Endpoint URL</th>
                    <td>
                        <code><?php echo esc_url(rest_url('alpindede/v1/posts')); ?></code>
                        <p class="description">Bu URL'yi içerik servisine girin.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Kaydet'); ?>
        </form>
    </div>
    <?php
}
