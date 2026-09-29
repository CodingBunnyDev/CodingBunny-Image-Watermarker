<?php
/**
 * Plugin Name: CodingBunny Image Watermarker
 * Description: An add-on for CodingBunny Image Optimizer to automatically apply watermarks to your images.
 * Version:     1.1.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      CodingBunny
 * Text Domain: coding-bunny-image-watermarker
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: coding-bunny-image-optimizer-lite
 * Update URI:  false
 *
 * @package CodingBunny\ImageWatermarker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBIW_VERSION', '1.1.1' );
define( 'CBIW_PLUGIN_FILE', __FILE__ );
define( 'CBIW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBIW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CBIW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

class CBIW_Image_Watermarker {

	private static $instance = null;

	private $admin_dir;

	private $includes_dir;

	private $is_loaded = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->set_paths();
		$this->register_hooks();
	}

	private function __clone() {}

	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	private function set_paths() {
		$this->admin_dir    = CBIW_PLUGIN_DIR . 'admin/';
		$this->includes_dir = CBIW_PLUGIN_DIR . 'includes/';
	}

	private function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this, 'maybe_deactivate' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_action( 'deactivated_plugin', array( $this, 'handle_parent_deactivation' ), 10, 1 );
	}

	public function maybe_bootstrap() {
		if ( $this->check_dependencies() ) {
			$this->load_dependencies();
			require_once CBIW_PLUGIN_DIR . 'includes/watermark-cache-buster.php';
			$this->is_loaded = true;

			do_action( 'cbiw_loaded' );
		}
	}

	private function check_dependencies() {
		return class_exists( 'CodingBunnyImageOptimizer' );
	}

	private function load_dependencies() {
		$files_to_include = array(
			'image-watermark.php',
			'enqueue-scripts.php',
		);

		foreach ( $files_to_include as $file ) {
			$file_path = $this->admin_dir . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}

	public function maybe_deactivate() {
		if ( ! $this->check_dependencies() && current_user_can( 'activate_plugins' ) && is_plugin_active( CBIW_PLUGIN_BASENAME ) ) {
			deactivate_plugins( CBIW_PLUGIN_BASENAME );
			
			if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
	}

	public function show_admin_notices() {
		if ( ! is_admin() ) {
			return;
		}

		if ( get_transient( 'cbiw_parent_deactivated' ) ) {
			delete_transient( 'cbiw_parent_deactivated' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					esc_html_e(
						'CodingBunny Image Watermarker has been deactivated because CodingBunny Image Optimizer is no longer active.',
						'coding-bunny-image-watermarker'
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( ! $this->check_dependencies() && current_user_can( 'activate_plugins' ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: Link to parent plugin */
							__( 'CodingBunny Image Watermarker requires CodingBunny Image Optimizer to be installed and active. Please <a href="%s" target="_blank">install and activate it first</a>.', 'coding-bunny-image-watermarker' ),
							esc_url( 'https://wordpress.org/plugins/coding-bunny-image-optimizer-lite/' )
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	public function handle_parent_deactivation( $plugin ) {
		if ( defined( 'CBIO_PLUGIN_FILE' ) && plugin_basename( CBIO_PLUGIN_FILE ) === $plugin ) {
			deactivate_plugins( CBIW_PLUGIN_BASENAME );
			set_transient( 'cbiw_parent_deactivated', true, 30 );
		}
	}

	public function load_textdomain() {
		// phpcs:ignore
		load_plugin_textdomain(
			'coding-bunny-image-watermarker',
			false,
			dirname( CBIW_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	public function is_loaded() {
		return $this->is_loaded;
	}
}

function cbiw_init() {
	return CBIW_Image_Watermarker::get_instance();
}

cbiw_init();