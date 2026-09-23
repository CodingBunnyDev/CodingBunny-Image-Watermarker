<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'activate_plugins' ) ) {
	return;
}

if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	require_once ABSPATH . 'wp-includes/wp-load.php';
}

require_once ABSPATH . 'wp-admin/includes/file.php';

global $wpdb, $wp_filesystem;
$cbiw_wpdb        = $wpdb;
if ( empty( $wp_filesystem ) ) {
	WP_Filesystem();
}
$cbiw_filesystem  = $wp_filesystem;

delete_option( 'watermark_options' );
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	delete_site_option( 'watermark_options' );
}

delete_option( 'cbio_quality_avif' );

delete_transient( 'cbio_backup_stats' );

$cbiw_meta_keys    = array( '_cbio_watermarked', '_cbio_watermark_version' );
$cbiw_placeholders = implode( ',', array_fill( 0, count( $cbiw_meta_keys ), '%s' ) );
$cbiw_sql          = "DELETE FROM {$cbiw_wpdb->postmeta} WHERE meta_key IN ($cbiw_placeholders)";
$cbiw_wpdb->query( $cbiw_wpdb->prepare( $cbiw_sql, ...$cbiw_meta_keys ) );

$cbiw_upload_dir = wp_get_upload_dir();
if ( ! empty( $cbiw_upload_dir['basedir'] ) ) {
	$cbiw_basedir    = trailingslashit( $cbiw_upload_dir['basedir'] );
	$cbiw_backup_dir = $cbiw_basedir . 'cbio_watermark_backups/';

	$cbiw_backup_exists = ( ! empty( $cbiw_filesystem ) && method_exists( $cbiw_filesystem, 'is_dir' ) ) ? $cbiw_filesystem->is_dir( $cbiw_backup_dir ) : is_dir( $cbiw_backup_dir );
	if ( $cbiw_backup_exists ) {
		$cbiw_backup_files = glob( $cbiw_backup_dir . '*' );
		if ( is_array( $cbiw_backup_files ) ) {
			foreach ( $cbiw_backup_files as $cbiw_backup_file ) {
				if ( is_file( $cbiw_backup_file ) ) {
					wp_delete_file( $cbiw_backup_file );
				}
			}
		}
		if ( ! empty( $cbiw_filesystem ) && method_exists( $cbiw_filesystem, 'rmdir' ) ) {
			$cbiw_filesystem->rmdir( $cbiw_backup_dir, true );
		}
	}

	$cbiw_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cbiw_basedir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $cbiw_iterator as $cbiw_fileinfo ) {
		if ( $cbiw_fileinfo->isFile() ) {
			$cbiw_filename = $cbiw_fileinfo->getFilename();
			if ( preg_match( '/_wm\.[^.]+$/i', $cbiw_filename ) ) {
				wp_delete_file( $cbiw_fileinfo->getPathname() );
			}
		}
	}
}

if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}