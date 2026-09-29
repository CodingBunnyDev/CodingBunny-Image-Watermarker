<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__DIR__) . '/includes/watermark-functions.php';

class Cbiw_Watermark {
	const OPTION_KEY = 'watermark_options';
	const META_WATERMARKED = '_cbio_watermarked';
	const LOCK_TTL = 90;
	const LOCK_PREFIX = 'cbio_wm_lock_';
	const VERSION_FALLBACK = '1.0.0';

	private $options = array();

	private static $DEFAULTS = array(
		'enable_auto_watermark' => '0',
		'enable_bulk_actions'   => '0',
		'watermark_type'        => 'image',
		'watermark_text'        => '',
		'watermark_text_size'   => 50,
		'watermark_text_font'   => 'montserrat',
		'watermark_text_color'  => '#000000',
		'watermark_image_id'    => '',
		'watermark_position'    => 'bottom-right',
		'watermark_size'        => 20,
		'watermark_opacity'     => 100,
		'watermark_padding'     => 10,
		'watermark_min_width'   => 600
	);

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'apply_watermark' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	private function user_can_manage() {
		return current_user_can('manage_options');
	}

	private function get_options() {
		if (empty($this->options)) {
			$stored = get_option(self::OPTION_KEY, array());
			$this->options = wp_parse_args($stored, self::$DEFAULTS);
		}
		return $this->options;
	}

	private function acquire_lock($identifier) {
		$lock_key = self::LOCK_PREFIX . md5($identifier);
		$existing = get_transient($lock_key);
		if ($existing) return false;
		set_transient($lock_key, time(), self::LOCK_TTL);
		return true;
	}

	private function release_lock($identifier) {
		delete_transient(self::LOCK_PREFIX . md5($identifier));
	}

	private function mark_watermarked($attachment_id) {
		update_post_meta($attachment_id, self::META_WATERMARKED, 1);
	}

	private function is_already_watermarked($attachment_id) {
		return (bool) get_post_meta($attachment_id, self::META_WATERMARKED, true);
	}

	public function add_admin_menu() {
		add_submenu_page(
		'coding-bunny-image-optimizer',
		esc_html__('Image Watermarker', 'coding-bunny-image-watermarker'),
		esc_html__('Watermarker', 'coding-bunny-image-watermarker'),
		'manage_options',
		'coding-bunny-image-watermark',
		[$this, 'render_tabs_page']
	);
}

private function render_watermark_image_picker($image_id) {
	$image_url = $image_id ? wp_get_attachment_url($image_id) : '';
	?>
	<input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY . '[watermark_image_id]'); ?>" id="watermark_image_id" value="<?php echo esc_attr($image_id); ?>" />
	<button type="button" class="button button-primary" id="choose-watermark-image" aria-describedby="desc-watermark-image"><?php esc_html_e('Select Image', 'coding-bunny-image-watermarker'); ?></button>
	<p id="desc-watermark-image" class="description"><?php esc_html_e('Upload or select a PNG/JPEG/WebP image.', 'coding-bunny-image-watermarker'); ?></p>
	<br>
	<img src="<?php echo esc_url($image_url); ?>" id="watermark-image-preview" alt="<?php esc_attr_e('Selected watermark image preview', 'coding-bunny-image-watermarker'); ?>" class="cbio-picker-preview"<?php echo $image_url ? '' : ' style="display:none;"'; ?> />
	<?php
}

public function render_tabs_page() {
	if (!$this->user_can_manage()) wp_die(esc_html__('Insufficient permissions.', 'coding-bunny-image-watermarker'));
	$this->options = $this->get_options();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (isset($_GET['cbio_backups_deleted'])) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = intval($_GET['cbio_backups_deleted']);		
		// translators: %d: Number of backup files deleted.
		add_settings_error('cbio_backups_notice', 'cbio_backups_deleted', sprintf(esc_html__('%d backup files deleted.', 'coding-bunny-image-watermarker'), $count), 'updated');
	}
	settings_errors();
	$logo_file   = CBIW_PLUGIN_DIR . 'assets/images/cbio-logo.svg';
	$logo_url    = file_exists( $logo_file ) ? CBIW_PLUGIN_URL . 'assets/images/cbio-logo.svg' : '';
	$sponsor_url = defined( 'CBIO_SPONSOR_URL' ) ? CBIO_SPONSOR_URL : 'https://github.com/sponsors/CodingBunnyDev';
	$auto_on     = isset( $this->options['enable_auto_watermark'] ) && '1' === (string) $this->options['enable_auto_watermark'];
	?>
	<div class="wrap cbio-wrap cbio-dashboard">
		<h1 class="screen-reader-text"><?php esc_html_e( 'CodingBunny Image Watermarker', 'coding-bunny-image-watermarker' ); ?></h1>
		<div class="cbio-header">
			<div class="cbio-header-left">
				<?php if ( '' !== $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>"
						alt="<?php esc_attr_e( 'CodingBunny logo', 'coding-bunny-image-watermarker' ); ?>"
						class="cbio-logo" />
				<?php else : ?>
					<div class="cbio-logo-fallback"><?php esc_html_e( 'CodingBunny', 'coding-bunny-image-watermarker' ); ?></div>
				<?php endif; ?>
				<div class="cbio-title">
					<p>
						<?php esc_html_e( 'CodingBunny Image Watermarker', 'coding-bunny-image-watermarker' ); ?>
						<span class="cbio-version">v<?php echo esc_html( CBIW_VERSION ); ?></span>
					</p>
				</div>
			</div>
			<div class="cbio-header-right">
				<a class="cbio-sponsor-link" href="<?php echo esc_url( $sponsor_url ); ?>" target="_blank" rel="noopener">
					<span class="dashicons dashicons-heart" aria-hidden="true"></span>
					<?php esc_html_e( 'Love this plugin? Support the development', 'coding-bunny-image-watermarker' ); ?>
				</a>
			</div>
		</div>
		<div class="cbio-ic-wrap">
			<nav class="cbio-ic-tabs" aria-label="<?php esc_attr_e( 'Watermark tabs', 'coding-bunny-image-watermarker' ); ?>">
				<a class="cbio-ic-tab cbio-ic-tab-active"
					href="<?php echo esc_url( admin_url( 'admin.php?page=coding-bunny-image-watermark&tab=settings' ) ); ?>"
					aria-current="page">
					<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
					<span class="cbio-sidebar-label"><?php esc_html_e( 'Watermark Settings', 'coding-bunny-image-watermarker' ); ?></span>
					<?php if ( $auto_on ) : ?>
						<span class="cbio-sidebar-badges">
							<span class="cbio-tab-status"><?php esc_html_e( 'On', 'coding-bunny-image-watermarker' ); ?></span>
						</span>
					<?php endif; ?>
				</a>

				<div class="cbio-sidebar-group">
					<span class="dashicons dashicons-images-alt2 cbio-sidebar-group-icon" aria-hidden="true"></span>
					<span class="cbio-sidebar-group-label"><?php esc_html_e( 'Image Optimizer', 'coding-bunny-image-watermarker' ); ?></span>
				</div>
				<a class="cbio-ic-tab" href="<?php echo esc_url( admin_url( 'admin.php?page=coding-bunny-image-optimizer' ) ); ?>">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span class="cbio-sidebar-label"><?php esc_html_e( 'Optimizer Settings', 'coding-bunny-image-watermarker' ); ?></span>
				</a>
			</nav>
			<div class="cbio-ic-content">
				<?php $this->render_settings_tab(); ?>
			</div>
		</div>
	</div>
	<?php
}

	public function render_settings_tab() {
		$this->options = $this->get_options();
		$type   = $this->options['watermark_type'] ?? 'image';
		$image_id = $this->options['watermark_image_id'] ?? '';
		if (isset($_POST['delete_backup_images']) && check_admin_referer('cbio_delete_backup_images', 'cbio_delete_backup_images_nonce')) {
			$deleted = $this->delete_backup_images();
			delete_transient('cbio_backup_stats');
			$url = add_query_arg(array('page' => 'coding-bunny-image-watermark','cbio_backups_deleted' => $deleted), admin_url('admin.php'));
			wp_safe_redirect($url);
			exit;
		}
		$backup_stats = get_transient('cbio_backup_stats');
		if ($backup_stats === false) {
			$upload_dir = wp_upload_dir();
			$backup_dir = $upload_dir['basedir'] . '/cbio_watermark_backups/';
			$backup_count = 0;
			$backup_size = 0;
			if (file_exists($backup_dir)) {
				$files = glob($backup_dir . '*');
				if (is_array($files)) {
					$backup_count = count($files);
					foreach ($files as $file) {
						$backup_size += filesize($file);
					}
				}
			}
			$backup_stats = array(
				'count' => $backup_count,
				'size' => $backup_size
			);
			set_transient('cbio_backup_stats', $backup_stats, 300);
		}
		if (!function_exists('cbio_human_filesize')) {
			function cbio_human_filesize($bytes, $decimals = 2) {
				$size = array('B','KB','MB','GB','TB','PB');
				$factor = floor((strlen($bytes) - 1) / 3);
				return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
			}
		}
		?>	
		<form method="post" action="options.php" aria-describedby="cbio-watermark-form-desc">
			<p id="cbio-watermark-form-desc" class="screen-reader-text"><?php esc_html_e('Configure watermark behavior for uploaded images.', 'coding-bunny-image-watermarker'); ?></p>
			<?php settings_fields('watermark_option_group'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="enable_auto_watermark"><?php esc_html_e('Automatic Watermark', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<label class="cbio-toggle-label">
							<input type="checkbox" class="cbio-toggle" id="enable_auto_watermark" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_auto_watermark]" value="1" <?php checked($this->options['enable_auto_watermark'], '1'); ?> />
							<span class="cbio-slider"></span>
							<?php esc_html_e('Apply watermark when uploading new images.', 'coding-bunny-image-watermarker'); ?>
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="enable_bulk_actions"><?php esc_html_e('Bulk Actions', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<label class="cbio-toggle-label">
							<input type="checkbox" class="cbio-toggle" id="enable_bulk_actions" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_bulk_actions]" value="1" <?php checked($this->options['enable_bulk_actions'], '1'); ?> />
							<span class="cbio-slider"></span>
							<?php esc_html_e('Enable bulk actions in the media library.', 'coding-bunny-image-watermarker'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<td colspan="2" class="cbio-preview-cell">
						<h4 class="cbio-preview-title"><?php esc_html_e('Preview', 'coding-bunny-image-watermarker'); ?></h4>
						<div id="cbio-watermark-preview-container" class="cbio-preview" aria-label="<?php esc_attr_e('Watermark preview area', 'coding-bunny-image-watermarker'); ?>">
							<img src="<?php echo esc_url( CBIW_PLUGIN_URL . 'assets/images/placeholder.webp' ); ?>" alt="<?php esc_attr_e('Placeholder image', 'coding-bunny-image-watermarker'); ?>" class="cbio-preview-image" />
							<canvas id="cbio-watermark-preview-canvas" width="370" height="240" role="img" aria-label="<?php esc_attr_e('Watermarked preview canvas', 'coding-bunny-image-watermarker'); ?>" class="cbio-preview-canvas"></canvas>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="watermark_type"><?php esc_html_e('Watermark Type', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<select name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_type]" id="watermark_type">
							<option value="image" <?php selected($type, 'image'); ?>><?php esc_html_e('Image', 'coding-bunny-image-watermarker'); ?></option>
							<option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Text', 'coding-bunny-image-watermarker'); ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top" id="watermark_text_settings" style="<?php echo ($type === 'text') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-text"><?php esc_html_e('Watermark Text', 'coding-bunny-image-watermarker'); ?></label></th>
					<td><input type="text" id="cbio-watermark-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_text]" value="<?php echo esc_attr($this->options['watermark_text'] ?? ''); ?>" maxlength="200" /></td>
				</tr>
				<tr valign="top" id="watermark_text_size_row" style="<?php echo ($type === 'text') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-text-size"><?php esc_html_e('Text Width (%)', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<input type="number" id="cbio-watermark-text-size"
						name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_text_size]"
						value="<?php echo esc_attr($this->options['watermark_text_size'] ?? 50); ?>"
						min="1" max="100"
						oninput="this.nextElementSibling.value=this.value + '%'" />
						<p class="cbio-notes"><?php esc_html_e('Set the width of the watermark text as a percentage of the image width. 100% makes the text as wide as the image.', 'coding-bunny-image-watermarker'); ?></p>
					</td>
				</tr>
				<tr valign="top" id="watermark_text_font_row" style="<?php echo ($type === 'text') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-font"><?php esc_html_e('Font Family', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<select id="cbio-watermark-font" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_text_font]">
							<option value="montserrat" <?php selected($this->options['watermark_text_font'] ?? '', 'montserrat'); ?>>Montserrat</option>
							<option value="playfair_display" <?php selected($this->options['watermark_text_font'] ?? '', 'playfair_display'); ?>>Playfair Display</option>
							<option value="roboto" <?php selected($this->options['watermark_text_font'] ?? '', 'roboto'); ?>>Roboto</option>
							<option value="verdana" <?php selected($this->options['watermark_text_font'] ?? '', 'verdana'); ?>>Verdana</option>
						</select>
					</td>
				</tr>
				<tr valign="top" id="watermark_text_color_row" style="<?php echo ($type === 'text') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-text-color"><?php esc_html_e('Text Color', 'coding-bunny-image-watermarker'); ?></label></th>
					<td><input type="color" id="cbio-watermark-text-color" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_text_color]" value="<?php echo esc_attr($this->options['watermark_text_color'] ?? '#000000'); ?>" /></td>
				</tr>
				<tr valign="top" id="watermark_image_settings" style="<?php echo ($type === 'image') ? '' : 'display:none'; ?>">
					<th scope="row"><?php esc_html_e('Watermark Image', 'coding-bunny-image-watermarker'); ?></th>
					<td>
						<p><?php esc_html_e('Upload a PNG, JPEG or WebP image to use as watermark.', 'coding-bunny-image-watermarker'); ?></p>
						<?php $this->render_watermark_image_picker($image_id); ?>
					</td>
				</tr>
				<tr valign="top" id="watermark_image_size_row" style="<?php echo ($type === 'image') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-size"><?php esc_html_e('Image Width (%)', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<input type="number" id="cbio-watermark-size"
						name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_size]"
						value="<?php echo esc_attr($this->options['watermark_size'] ?? 20); ?>"
						min="1" max="100"
						oninput="this.nextElementSibling.value=this.value + '%'"
						aria-valuemin="1" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($this->options['watermark_size'] ?? 20); ?>">
						<p class="cbio-notes"><?php esc_html_e('Set the width of the watermark image as a percentage of the image width. 100% makes the watermark image as wide as the image.', 'coding-bunny-image-watermarker'); ?></p>
					</td>
				</tr>
				<tr valign="top" id="watermark_image_opacity_row" style="<?php echo ($type === 'image') ? '' : 'display:none'; ?>">
					<th scope="row"><label for="cbio-watermark-opacity"><?php esc_html_e('Image Opacity', 'coding-bunny-image-watermarker'); ?></label></th>
					<td><?php $this->watermark_opacity_callback(); ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Position', 'coding-bunny-image-watermarker'); ?></th>
					<td><?php $this->watermark_position_callback(); ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="cbio-watermark-padding"><?php esc_html_e('Padding (px)', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<input type="number" id="cbio-watermark-padding" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_padding]" value="<?php echo esc_attr($this->options['watermark_padding'] ?? 10); ?>" min="0" max="500" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="cbio-watermark-min-width"><?php esc_html_e('Minimum width threshold (px)', 'coding-bunny-image-watermarker'); ?></label></th>
					<td>
						<input type="number" id="cbio-watermark-min-width" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_min_width]" value="<?php echo esc_attr($this->options['watermark_min_width'] ?? 1024); ?>" min="0" max="10000" />
						<p class="cbio-notes"><?php esc_html_e('The watermark will only be applied to images with a width greater than or equal to this threshold.', 'coding-bunny-image-watermarker'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(esc_html__('Save Settings', 'coding-bunny-image-watermarker')); ?>
		</form><hr>
		<div class="cbio-warning" role="alert">
			<?php esc_html_e('WARNING: If you feel you do not need the original watermark-free versions of your images, you can delete them to free up space on your server. This operation is irreversible.', 'coding-bunny-image-watermarker'); ?>
		</div>
		<div class="cbio-info">
			<strong><?php esc_html_e('BACKUP FOLDER:', 'coding-bunny-image-watermarker'); ?></strong>
			<?php echo esc_html($backup_stats['count']); ?>
			<?php esc_html_e('images, potential space to free', 'coding-bunny-image-watermarker'); ?>
			<?php echo esc_html(cbio_human_filesize($backup_stats['size'])); ?>
		</div>
		<form method="post" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to proceed? This action is irreversible.', 'coding-bunny-image-watermarker')); ?>');">
			<?php wp_nonce_field('cbio_delete_backup_images', 'cbio_delete_backup_images_nonce'); ?>
			<button type="submit" name="delete_backup_images" class="button button-delete">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php esc_html_e('Delete All Backup Images', 'coding-bunny-image-watermarker'); ?></button>
			</form>
			<?php
		}



		public function delete_backup_images() {
			$upload_dir = wp_upload_dir();
			$backup_dir = $upload_dir['basedir'] . '/cbio_watermark_backups/';
			$deleted = 0;
			if (file_exists($backup_dir)) {
				$files = glob($backup_dir . '*');
				if (is_array($files)) {
					foreach ($files as $file) {
						if (wp_delete_file($file)) $deleted++;
					}
				}
			}
			return $deleted;
		}

		public function register_settings() {
			register_setting(
			'watermark_option_group',
			self::OPTION_KEY,
			array($this, 'sanitize_watermark_options')
		);
	}

	public function sanitize_watermark_options($input) {
		$sanitized_input = wp_parse_args(get_option(self::OPTION_KEY, array()), self::$DEFAULTS);
		$boolean_keys = array('enable_auto_watermark','enable_bulk_actions');
		foreach ($boolean_keys as $bk) {
			$sanitized_input[$bk] = '0';
		}
		if (is_array($input)) {
			foreach ($input as $key => $value) {
				switch ($key) {
					case 'enable_auto_watermark':
					case 'enable_bulk_actions':
					$sanitized_input[$key] = ($value === '1') ? '1' : '0';
					break;
					case 'watermark_type':
					$sanitized_input[$key] = in_array($value, ['image', 'text'], true) ? $value : 'image';
					break;
					case 'watermark_text':
					$sanitized_input[$key] = substr(sanitize_text_field($value), 0, 200);
					break;
					case 'watermark_text_size':
					$sanitized_input[$key] = max(1, min(100, intval($value)));
					break;
					case 'watermark_text_font':
					$allowed_fonts = ['montserrat', 'playfair_display', 'roboto', 'verdana'];
					$sanitized_input[$key] = in_array($value, $allowed_fonts, true) ? $value : 'montserrat';
					break;
					case 'watermark_text_color':
					$sanitized_input[$key] = preg_match('/^#[a-fA-F0-9]{6}$/', $value) ? $value : '#000000';
					break;
					case 'watermark_image_id':
					$img_id = intval($value);
					$mime  = $img_id ? get_post_mime_type($img_id) : '';
					$sanitized_input[$key] = ($img_id && $mime && strpos($mime, 'image/') === 0) ? $img_id : '';
					break;
					case 'watermark_position':
					$allowed_positions = ['top-left', 'top-center', 'top-right', 'center-left', 'center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right'];
					$sanitized_input[$key] = in_array($value, $allowed_positions, true) ? $value : 'bottom-right';
					break;
					case 'watermark_size':
					$sanitized_input[$key] = max(1, min(100, floatval($value)));
					break;
					case 'watermark_opacity':
					$sanitized_input[$key] = max(0, min(100, floatval($value)));
					break;
					case 'watermark_padding':
					case 'watermark_min_width':
					$sanitized_input[$key] = max(0, intval($value));
					break;
					default:
					$sanitized_input[$key] = sanitize_text_field($value);
					break;
				}
			}
		}
		return $sanitized_input;
	}

	public function watermark_size_callback() {
		$size = isset($this->options['watermark_size']) ? $this->options['watermark_size'] : 20;
		?>
		<input type="number" id="cbio-watermark-text-size"
		name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_text_size]"
		value="<?php echo esc_attr($this->options['watermark_text_size'] ?? 50); ?>"
		min="1" max="100"
		oninput="this.nextElementSibling.value=this.value + '%'" />
		<p class="cbio-notes"><?php esc_html_e('Set a number between 0 and 100. 100 makes the width of the watermark image equal to the width of the image to which it is applied.', 'coding-bunny-image-watermarker'); ?></p>
		<?php
	}

	public function watermark_opacity_callback() {
		$opacity = isset($this->options['watermark_opacity']) ? $this->options['watermark_opacity'] : 100;
		?>
		<input type="number" id="cbio-watermark-opacity" name="<?php echo esc_attr(self::OPTION_KEY); ?>[watermark_opacity]" value="<?php echo esc_attr($opacity); ?>" min="0" max="100" oninput="this.nextElementSibling.value = this.value" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($opacity); ?>">
		<p class="cbio-notes"><?php esc_html_e('Set a number between 0 and 100. 0 makes the watermark image completely transparent, while 100 displays the original image.', 'coding-bunny-image-watermarker'); ?></p>
		<?php
	}

	public function watermark_position_callback() {
		$positions = [
			['top-left', 'Top Left'], ['top-center', 'Top Center'], ['top-right', 'Top Right'],
			['center-left', 'Center Left'], ['center', 'Center'], ['center-right', 'Center Right'],
			['bottom-left', 'Bottom Left'], ['bottom-center', 'Bottom Center'], ['bottom-right', 'Bottom Right']
		];
		$selected = $this->options['watermark_position'] ?? 'bottom-right';
		echo '<div class="watermark-position-radios"><table role="presentation">';
		for ($i = 0; $i < 3; $i++) {
			echo '<tr>';
			for ($j = 0; $j < 3; $j++) {
				list($pos, $lbl) = $positions[$i * 3 + $j];
				echo '<td><div class="cbio-radio-button-wrapper">';
				printf(
				'<input type="radio" id="position_%1$s" name="%4$s[watermark_position]" value="%1$s" %2$s />'.
				'<label for="position_%1$s" class="cbio-watermark-label">%3$s</label>',
				esc_attr($pos),
				checked($selected, $pos, false),
				esc_html($lbl),
				esc_attr(self::OPTION_KEY)
			);
			echo '</div></td>';
		}
		echo '</tr>';
	}
	echo '</table></div>';
}

public function enqueue_scripts($hook) {
	if ($hook !== 'coding-bunny-image-optimizer_page_coding-bunny-image-watermarker' && $hook !== 'upload.php') return;
	$this->options = $this->get_options();
	wp_enqueue_media();
	wp_enqueue_script(
	'cbio-watermark',
	plugin_dir_url(__DIR__) . 'assets/js/cbiw-scripts.js',
	array(),
	defined('CBIW_VERSION') ? CBIW_VERSION : self::VERSION_FALLBACK,
	true
);
$preview_data = array(
	'type'       => $this->options['watermark_type'] ?? 'image',
	'text'       => apply_filters('cbio_watermark_text', $this->options['watermark_text'] ?? ''),
	'text_size'  => $this->options['watermark_text_size'] ?? 50,
	'text_font'  => $this->options['watermark_text_font'] ?? 'montserrat',
	'text_color' => $this->options['watermark_text_color'] ?? '#000000',
	'image_id'   => $this->options['watermark_image_id'] ?? '',
	'image_url'  => !empty($this->options['watermark_image_id']) ? wp_get_attachment_url($this->options['watermark_image_id']) : '',
	'opacity'    => $this->options['watermark_opacity'] ?? 100,
	'position'   => $this->options['watermark_position'] ?? 'bottom-right',
	'size'       => $this->options['watermark_size'] ?? 20,
	'padding'    => $this->options['watermark_padding'] ?? 10,
);
wp_add_inline_script(
'cbio-watermark',
'window.CBIO_WATERMARK_PREVIEW_DATA = ' . wp_json_encode($preview_data) . ';',
'before'
);
}

public function register_bulk_actions($bulk_actions) {
$this->options = $this->get_options();
if (($this->options['enable_bulk_actions'] ?? '0') == '1') {
$bulk_actions['cbio_watermark_separator'] = '--WATERMARK--';
$bulk_actions['apply_watermark'] = __('Apply Watermark', 'coding-bunny-image-watermarker');
$bulk_actions['restore_images'] = __('Restore Original Images', 'coding-bunny-image-watermarker');
}
return $bulk_actions;
}

public function handle_bulk_actions($redirect_to, $doaction, $attachment_ids) {
if (!$this->user_can_manage()) wp_die(esc_html__('Insufficient permissions.', 'coding-bunny-image-watermarker'));
if (!is_array($attachment_ids) || empty($attachment_ids)) return $redirect_to;
global $wp_filesystem;
if (empty($wp_filesystem)) {
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
}
$upload_dir = wp_upload_dir();
$backup_dir = trailingslashit($upload_dir['basedir']) . 'cbio_watermark_backups/';
if (!$wp_filesystem->is_dir($backup_dir)) {
if (!$wp_filesystem->mkdir($backup_dir, 0755)) return $redirect_to;
}
if ($doaction === 'apply_watermark') {
$processed_count = $this->process_watermark_batch($attachment_ids, $backup_dir);
$redirect_to = add_query_arg('bulk_watermarked', $processed_count, $redirect_to);
} elseif ($doaction === 'restore_images') {
	$restored_count = $this->restore_images_batch($attachment_ids, $backup_dir);
	$redirect_to = add_query_arg('bulk_restored', $restored_count, $redirect_to);
}
return $redirect_to;
}

private function process_watermark_batch($attachment_ids, $backup_dir) {
$batch_size = 10;
$processed_count = 0;
foreach (array_chunk($attachment_ids, $batch_size) as $batch) {
	foreach ($batch as $attachment_id) {
		try {
			$this->backup_and_watermark_image($attachment_id, $backup_dir, true);
			$processed_count++;
		} catch (Exception $e) {}
		}
	}
	return $processed_count;
}

private function restore_images_batch($attachment_ids, $backup_dir) {
	$batch_size = 10;
	$restored_count = 0;
	foreach (array_chunk($attachment_ids, $batch_size) as $batch) {
		foreach ($batch as $attachment_id) {
			try {
				$this->restore_single_image($attachment_id, $backup_dir);
				delete_post_meta($attachment_id, self::META_WATERMARKED);
				$restored_count++;
			} catch (Exception $e) {}
			}
		}
		return $restored_count;
	}

	public function admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!isset($_GET['bulk_watermarked']) && !isset($_GET['bulk_restored'])) return;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET['bulk_watermarked'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = intval($_GET['bulk_watermarked']);
			// translators: %d: Number of images watermarked.
			printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html(sprintf(_n('%d image watermarked.', '%d images watermarked.', $count, 'coding-bunny-image-watermarker'), $count)));
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET['bulk_restored'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = intval($_GET['bulk_restored']);
			// translators: %d: Number of images restored.
			printf('<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html(sprintf(_n('%d image restored.', '%d images restored.', $count, 'coding-bunny-image-watermarker'), $count)));
		}
	}

	public function apply_watermark_to_image($image_url, $is_thumbnail = false) {
		if (empty($image_url)) return false;
		$options = wp_parse_args(get_option(self::OPTION_KEY), self::$DEFAULTS);
		if (!apply_filters('cbio_watermark_should_apply', true, $image_url, $options, array('is_thumbnail' => $is_thumbnail))) return false;
		$image_path = Cbio_Watermark_Functions::get_local_path_from_url($image_url);
		if (file_exists($image_path)) {
			list($img_w) = getimagesize($image_path);
			if ($img_w < intval($options['watermark_min_width'])) return false;
		} else {
			return false;
		}
		$type = $options['watermark_type'];
		if ($type === 'image') {
			$watermark_image = wp_get_attachment_image_src($options['watermark_image_id'], 'full');
			if (!$watermark_image) return false;
			try {
				$watermark_image_path = Cbio_Watermark_Functions::get_local_path_from_url($watermark_image[0]);
				$image_path = Cbio_Watermark_Functions::get_local_path_from_url($image_url);
				$watermark = Cbio_Watermark_Functions::create_image_resource($watermark_image_path);
				$original_image = Cbio_Watermark_Functions::create_image_resource($image_path);
				if (!$watermark || !$original_image) throw new Exception('Failed to create image resources');
				list($image_width, $image_height) = getimagesize($image_path);
				list($watermark_width, $watermark_height) = getimagesize($watermark_image_path);
				$scale = max(0.01, min(1.0, $options['watermark_size'] / 100));
				$new_watermark_width = (int)($image_width * $scale);
				$new_watermark_height = (int)($new_watermark_width * $watermark_height / $watermark_width);
				$watermark_resized = Cbio_Watermark_Functions::resize_image_with_transparency(
				$watermark,
				$watermark_width,
				$watermark_height,
				$new_watermark_width,
				$new_watermark_height
			);
			Cbio_Watermark_Functions::apply_image_opacity($watermark_resized, $options['watermark_opacity']);
			$position = Cbio_Watermark_Functions::calculate_watermark_position(
			$image_width,
			$image_height,
			$new_watermark_width,
			$new_watermark_height,
			$options['watermark_position'],
			$options['watermark_padding']
		);
		imagecopy(
		$original_image,
		$watermark_resized,
		(int)$position['x'],
		(int)$position['y'],
		0,
		0,
		(int)$new_watermark_width,
		(int)$new_watermark_height
	);
	Cbio_Watermark_Functions::save_image($original_image, $image_path);
	return true;
} catch (Exception $e) {
	return false;
} finally {
	if (isset($watermark) && $watermark !== false) imagedestroy($watermark);
	if (isset($original_image) && $original_image !== false) imagedestroy($original_image);
	if (isset($watermark_resized) && $watermark_resized !== false) imagedestroy($watermark_resized);
}
}
if ($type === 'text') {
$text = apply_filters('cbio_watermark_text', $options['watermark_text']);
$font_size_percent = intval($options['watermark_text_size']);
$font_name = $options['watermark_text_font'];
$color = $options['watermark_text_color'];
$position = $options['watermark_position'];
$padding = intval($options['watermark_padding']);
$opacity = intval($options['watermark_opacity']);
$image_path = Cbio_Watermark_Functions::get_local_path_from_url($image_url);
$result = Cbio_Watermark_Functions::apply_text_watermark(
$image_path,
$text,
$font_name,
$font_size_percent,
$color,
$position,
$padding,
$image_path,
$opacity
);
return $result;
}
return false;
}

public function apply_watermark($metadata, $attachment_id) {
$options = $this->get_options();
$force = apply_filters('cbio_watermark_force', false, $attachment_id, 'upload');
if ($this->is_already_watermarked($attachment_id) && !$force) return $metadata;
if (isset($options['enable_auto_watermark']) && $options['enable_auto_watermark'] == '1') {
$file_url = wp_get_attachment_url($attachment_id);
if (!$file_url) return $metadata;
$lock_id = 'auto_' . $attachment_id;
if (!$this->acquire_lock($lock_id)) return $metadata;
$min_width = isset($options['watermark_min_width']) ? intval($options['watermark_min_width']) : 1024;
$upload_dir = wp_upload_dir();
$backup_dir = $upload_dir['basedir'] . '/cbio_watermark_backups/';
global $wp_filesystem;
if (empty($wp_filesystem)) {
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
}
if (!$wp_filesystem->is_dir($backup_dir)) $wp_filesystem->mkdir($backup_dir, 0755);
$backup_file_path = trailingslashit($backup_dir) . basename($file_url);
if (!$wp_filesystem->exists($backup_file_path)) {
if ($wp_filesystem->exists(Cbio_Watermark_Functions::get_local_path_from_url($file_url))) {
	$wp_filesystem->copy(Cbio_Watermark_Functions::get_local_path_from_url($file_url), $backup_file_path);
}
}
$image_path = Cbio_Watermark_Functions::get_local_path_from_url($file_url);
if (file_exists($image_path)) {
list($orig_w) = getimagesize($image_path);
if ($orig_w >= $min_width) {
	if ($this->apply_watermark_to_image($file_url)) {
		$this->mark_watermarked($attachment_id);
		do_action('cbio_watermark_applied', $attachment_id, array('context' => 'upload_main'));
	}
}
}
if (isset($metadata['sizes'])) {
foreach ($metadata['sizes'] as $size) {
	if (empty($size['file'])) continue;
	$thumbnail_file = $size['file'];
	$thumbnail_url = str_replace(basename($file_url), $thumbnail_file, $file_url);
	$thumb_path = Cbio_Watermark_Functions::get_local_path_from_url($thumbnail_url);
	if (file_exists($thumb_path)) {
		list($w) = getimagesize($thumb_path);
		if ($w >= $min_width) {
			$backup_thumbnail_path = trailingslashit($backup_dir) . basename($thumbnail_url);
			if (!$wp_filesystem->exists($backup_thumbnail_path)) {
				if ($wp_filesystem->exists($thumb_path)) {
					$wp_filesystem->copy($thumb_path, $backup_thumbnail_path);
				}
			}
			if ($this->apply_watermark_to_image($thumbnail_url, true)) {
				do_action('cbio_watermark_applied', $attachment_id, array('context' => 'upload_thumbnail', 'size' => $thumbnail_file));
			}
		}
	}
}
}
$this->release_lock($lock_id);
}
return $metadata;
}

private function backup_and_watermark_image($attachment_id, $backup_dir, $force = false) {
$file_url = wp_get_attachment_url($attachment_id);
if (!$file_url) return;
if (!$this->acquire_lock('bulk_' . $attachment_id)) return;
$backup_file_path = trailingslashit($backup_dir) . basename($file_url);
Cbio_Watermark_Functions::safe_backup_file($file_url, $backup_file_path);
if ($force || !$this->is_already_watermarked($attachment_id)) {
if ($this->apply_watermark_to_image($file_url)) {
$this->mark_watermarked($attachment_id);
do_action('cbio_watermark_applied', $attachment_id, array('context' => 'bulk_main'));
}
}
$metadata = wp_get_attachment_metadata($attachment_id);
if (isset($metadata['sizes'])) {
foreach ($metadata['sizes'] as $size) {
if (empty($size['file'])) continue;
$thumbnail_file = $size['file'];
$thumbnail_url = str_replace(basename($file_url), $thumbnail_file, $file_url);
$backup_thumbnail_path = trailingslashit($backup_dir) . basename($thumbnail_url);
Cbio_Watermark_Functions::safe_backup_file($thumbnail_url, $backup_thumbnail_path);
if ($this->apply_watermark_to_image($thumbnail_url, true)) {
	do_action('cbio_watermark_applied', $attachment_id, array('context' => 'bulk_thumbnail', 'size' => $thumbnail_file));
}
}
}
$this->release_lock('bulk_' . $attachment_id);
}

private function restore_single_image($attachment_id, $backup_dir) {
$file_url = wp_get_attachment_url($attachment_id);
if (!$file_url) return;
$local_path = Cbio_Watermark_Functions::get_local_path_from_url($file_url);
$backup_file_path = $backup_dir . basename($local_path);
Cbio_Watermark_Functions::safe_restore_file($backup_file_path, $local_path);
$metadata = wp_get_attachment_metadata($attachment_id);
if (isset($metadata['sizes'])) {
foreach ($metadata['sizes'] as $size) {
if (empty($size['file'])) continue;
$thumbnail_file = $size['file'];
$thumbnail_url = str_replace(basename($file_url), $thumbnail_file, $file_url);
$local_thumbnail_path = Cbio_Watermark_Functions::get_local_path_from_url($thumbnail_url);
$backup_thumbnail_path = $backup_dir . basename($local_thumbnail_path);
Cbio_Watermark_Functions::safe_restore_file($backup_thumbnail_path, $local_thumbnail_path);
}
}
do_action('cbio_watermark_restored', $attachment_id);
}
}

add_filter('wp_get_attachment_url', function($url, $post_id) {
$pathinfo = pathinfo($url);
$wm_url = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '_wm.' . $pathinfo['extension'];

$upload_dir = wp_upload_dir();
$wm_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $wm_url);

if (file_exists($wm_path)) {
return $wm_url;
} else {
return $url;
}
}, 10, 2);

add_filter('wp_get_attachment_image_src', function($image, $post_id) {
if (!is_array($image) || empty($image[0]) || !is_string($image[0])) {
return $image;
}

$pathinfo = pathinfo($image[0]);
if (empty($pathinfo['dirname']) || empty($pathinfo['filename']) || empty($pathinfo['extension'])) {
return $image;
}

$wm_url = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '_wm.' . $pathinfo['extension'];

$upload_dir = wp_upload_dir();
if (empty($upload_dir['baseurl']) || empty($upload_dir['basedir'])) {
return $image;
}

$wm_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $wm_url);

if (file_exists($wm_path)) {
$image[0] = $wm_url;
}
return $image;
}, 10, 2);

if (is_admin()) {
$cbiw_watermark_plugin = new Cbiw_Watermark();
}