<?php

function bahrpu_test_assert(bool $condition, string $message): void
{
    if (! $condition) {
        WP_CLI::error($message);
    }
}

$token = wp_generate_password(48, false);
update_option(BAHRPU_TOKEN_OPTION, $token);

$create = new WP_REST_Request('POST', '/bahricanli-publisher/v1/posts');
$create->set_header('Content-Type', 'application/json');
$create->set_body(wp_json_encode(['title' => 'REST smoke test', 'content' => '<p>Testing.</p>']));

$unauthorized = rest_do_request($create);
bahrpu_test_assert(in_array($unauthorized->get_status(), [401, 403], true), 'A request without a token must be rejected.');

$create->set_header('X-Content-Manager-Token', $token);
$response = rest_do_request($create);
bahrpu_test_assert($response->get_status() === 201, 'A valid create request must return HTTP 201.');
$post_id = (int) $response->get_data()['post_id'];
$post = get_post($post_id);
bahrpu_test_assert($post && $post->post_type === 'post' && $post->post_status === 'draft', 'Default creation must save a draft post.');
bahrpu_test_assert($response->get_data()['status'] === 'draft', 'The create response must report the default status.');

$update = new WP_REST_Request('PUT', '/bahricanli-publisher/v1/posts/' . $post_id);
$update->set_header('X-Content-Manager-Token', $token);
$update->set_header('Content-Type', 'application/json');
$update->set_body(wp_json_encode(['title' => 'Updated REST smoke test']));
bahrpu_test_assert(rest_do_request($update)->get_status() === 200, 'A valid update must return HTTP 200.');
bahrpu_test_assert(get_post($post_id)->post_title === 'Updated REST smoke test', 'The update must persist the title.');

wp_delete_post($post_id, true);
delete_option(BAHRPU_TOKEN_OPTION);
WP_CLI::success('Publisher REST smoke test passed.');
