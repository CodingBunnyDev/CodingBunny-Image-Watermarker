<?php
if (!defined('ABSPATH')) exit;

add_action('cbio_watermark_applied', function($attachment_id, $args = array()) {
    update_post_meta($attachment_id, '_cbio_watermark_version', time());
}, 10, 2);

add_action('cbio_watermark_restored', function($attachment_id) {
    update_post_meta($attachment_id, '_cbio_watermark_version', time());
}, 10, 1);

add_filter('wp_get_attachment_url', function($url, $post_id) {
    $version = get_post_meta($post_id, '_cbio_watermark_version', true);
    if ($version) {
        $url = add_query_arg('v', $version, $url);
    }
    return $url;
}, 99, 2);

add_filter('wp_get_attachment_image_src', function($image, $attachment_id) {
    if (!empty($image[0])) {
        $version = get_post_meta($attachment_id, '_cbio_watermark_version', true);
        if ($version) {
            $image[0] = add_query_arg('v', $version, $image[0]);
        }
    }
    return $image;
}, 99, 2);