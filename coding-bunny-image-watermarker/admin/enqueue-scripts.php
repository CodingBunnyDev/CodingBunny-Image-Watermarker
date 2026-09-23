<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cbiw_enqueue_admin_assets( $hook ) {
	if ( ! defined( 'CBIW_PLUGIN_FILE' ) ) {
		return;
	}

	$allowed_pages = array(
		'coding-bunny-image-watermark',
	);

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $page, $allowed_pages, true ) ) {
		return;
	}

	$assets_dir = CBIW_PLUGIN_DIR . 'assets';
	$assets_url = CBIW_PLUGIN_URL . 'assets';

	$js_file = $assets_dir . '/js/cbiw-scripts.js';
	if ( file_exists( $js_file ) ) {
		wp_enqueue_script(
			'cbiw-admin-script',
			$assets_url . '/js/cbiw-scripts.js',
			array( 'jquery', 'wp-util' ),
			(string) filemtime( $js_file ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'cbiw_enqueue_admin_assets' );