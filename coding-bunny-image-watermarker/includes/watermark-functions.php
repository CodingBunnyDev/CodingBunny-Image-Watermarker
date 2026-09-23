<?php

if (!defined('ABSPATH')) {
	exit;
}

class Cbio_Watermark_Functions {

	public static function apply_text_watermark_temp($image_path, $text, $font_name, $font_size_percent, $color, $position, $padding, $save_path_avif, $quality = null) {
		$tmp_png = tempnam(sys_get_temp_dir(), 'wm_') . '.png';

		$res = self::apply_text_watermark(
		$image_path,
		$text,
		$font_name,
		$font_size_percent,
		$color,
		$position,
		$padding,
		$tmp_png
	);

	if (!$res || !file_exists($tmp_png)) {
		if (file_exists($tmp_png)) wp_delete_file($tmp_png);
		return false;
	}

	if ($quality !== null) {
		update_option('cbio_quality_avif', $quality);
	}
	$ok = cbio_convert_images($tmp_png, $save_path_avif, 'avif');
	wp_delete_file($tmp_png);
	return $ok;
}

public static function apply_image_watermark_temp($image_path, $watermark_image_path, $position, $padding, $opacity, $scale, $save_path_avif, $quality = null) {
	$tmp_png = tempnam(sys_get_temp_dir(), 'wm_') . '.png';

	$res = self::apply_image_watermark(
	$image_path,
	$watermark_image_path,
	$position,
	$padding,
	$opacity,
	$scale,
	$tmp_png
);

if (!$res || !file_exists($tmp_png)) {
	if (file_exists($tmp_png)) wp_delete_file($tmp_png);
	return false;
}

if ($quality !== null) {
	update_option('cbio_quality_avif', $quality);
}
$ok = cbio_convert_images($tmp_png, $save_path_avif, 'avif');
wp_delete_file($tmp_png);
return $ok;
}

public static function create_image_resource($path) {
$image_info = getimagesize($path);

switch ($image_info[2]) {
	case IMAGETYPE_JPEG:
	return imagecreatefromjpeg($path);
	case IMAGETYPE_PNG:
	$image = imagecreatefrompng($path);
	imagealphablending($image, false);
	imagesavealpha($image, true);
	return $image;
	case IMAGETYPE_GIF:
	return imagecreatefromgif($path);
	case IMAGETYPE_WEBP:
	return imagecreatefromwebp($path);
	case IMAGETYPE_AVIF:
	return imagecreatefromavif($path);
	default:
	return false;
}
}

public static function resize_image_with_transparency($source_image, $src_width, $src_height, $dest_width, $dest_height) {
$resized_image = imagecreatetruecolor($dest_width, $dest_height);
imagealphablending($resized_image, false);
imagesavealpha($resized_image, true);

imagecopyresampled(
$resized_image, 
$source_image, 
0, 0, 0, 0, 
$dest_width, 
$dest_height, 
$src_width, 
$src_height
);

return $resized_image;
}

public static function apply_image_opacity(&$image, $opacity_percentage) {
if ($opacity_percentage >= 100) return;
$opacity = $opacity_percentage / 100;

$w = imagesx($image);
$h = imagesy($image);

for ($x = 0; $x < $w; $x++) {
for ($y = 0; $y < $h; $y++) {
	$rgba = imagecolorat($image, $x, $y);
	$a = ($rgba & 0x7F000000) >> 24;
	$rgb = $rgba & 0xFFFFFF;

	$new_alpha = 127 - (127 - $a) * $opacity;
	$new_alpha = min(127, max(0, (int)round($new_alpha)));
	$new_color = imagecolorallocatealpha(
	$image,
	($rgb >> 16) & 0xFF,
	($rgb >> 8) & 0xFF,
	$rgb & 0xFF,
	$new_alpha
);
imagesetpixel($image, $x, $y, $new_color);
}
}
}

public static function calculate_watermark_position(
$image_width, 
$image_height, 
$watermark_width, 
$watermark_height, 
$position, 
$padding
) {
$positions = [
'top-left' => ['x' => $padding, 'y' => $padding],
'top-center' => ['x' => ($image_width / 2) - ($watermark_width / 2), 'y' => $padding],
'top-right' => ['x' => $image_width - $watermark_width - $padding, 'y' => $padding],
'center-left' => ['x' => $padding, 'y' => ($image_height / 2) - ($watermark_height / 2)],
'center' => ['x' => ($image_width / 2) - ($watermark_width / 2), 'y' => ($image_height / 2) - ($watermark_height / 2)],
'center-right' => ['x' => $image_width - $watermark_width - $padding, 'y' => ($image_height / 2) - ($watermark_height / 2)],
'bottom-left' => ['x' => $padding, 'y' => $image_height - $watermark_height - $padding],
'bottom-center' => ['x' => ($image_width / 2) - ($watermark_width / 2), 'y' => $image_height - $watermark_height - $padding],
'bottom-right' => ['x' => $image_width - $watermark_width - $padding, 'y' => $image_height - $watermark_height - $padding]
];

return $positions[$position] ?? $positions['bottom-right'];
}

public static function save_image($image, $path) {
$image_info = getimagesize($path);

switch ($image_info[2]) {
case IMAGETYPE_JPEG:
imagejpeg($image, $path, 100);
break;
case IMAGETYPE_PNG:
imagepng($image, $path);
break;
case IMAGETYPE_GIF:
imagegif($image, $path);
break;
case IMAGETYPE_WEBP:
imagewebp($image, $path);
break;
case IMAGETYPE_AVIF:
imageavif($image, $path);
break;
default:
throw new Exception('Unsupported image type for saving');
}
}

public static function get_local_path_from_url($url) {
$upload_dir = wp_upload_dir();
$url = strtok($url, '?');
$relative_path = str_replace($upload_dir['baseurl'], '', $url);
return $upload_dir['basedir'] . $relative_path;
}

public static function safe_backup_file($source_url, $backup_path) {
global $wp_filesystem;

if (!$wp_filesystem->exists($backup_path)) {
$source_path = self::get_local_path_from_url($source_url);
if ($wp_filesystem->exists($source_path)) {
if (!$wp_filesystem->copy($source_path, $backup_path)) {
	throw new Exception(sprintf("Unable to back up %s", esc_html($source_url)));
}
}
}
}

public static function safe_restore_file($backup_path, $destination_path) {
if (file_exists($backup_path)) {
if (!copy($backup_path, $destination_path)) {
throw new Exception(sprintf("Unable to restore file from %s", esc_html($backup_path)));
}
}
}

public static function generate_watermark($image_width, $opacity, $size) {
return null;
}

public static function get_cached_watermark($image_width, $opacity, $size) {
if (!is_numeric($image_width) || !is_numeric($opacity) || !is_numeric($size)) {
throw new InvalidArgumentException('Invalid parameters for watermarking');
}

$cache_key = sprintf(
'watermark_%d_%d_%d', 
(int)$image_width, 
(int)$opacity, 
(int)$size
);

$cache_group = 'image_watermarks';
$cached_watermark = wp_cache_get($cache_key, $cache_group);

if ($cached_watermark === false) {
try {
$cached_watermark = self::generate_watermark($image_width, $opacity, $size);
$cache_expiration = apply_filters(
'cbio_watermark_cache_expiration', 
3600
);

wp_cache_set($cache_key, $cached_watermark, $cache_group, $cache_expiration);
} catch (Exception $e) {
return null;
}
}

return $cached_watermark;
}

public static function get_plugin_font_path($font_name) {
$plugin_font_dir = realpath(plugin_dir_path(__DIR__) . 'assets/fonts/') . '/';

$font_files = [
'montserrat'       => 'montserrat.ttf',
'playfair_display' => 'playfair_display.ttf',
'roboto'           => 'roboto.ttf',
'verdana'          => 'verdana.ttf',
];

$font_file = $font_files[$font_name] ?? $font_files['montserrat'];
$font_path = realpath($plugin_font_dir . $font_file);

if ($font_path && is_readable($font_path)) {
return $font_path;
}

foreach ($font_files as $file) {
$alt = realpath($plugin_font_dir . $file);
if ($alt && is_readable($alt)) {
return $alt;
}
}

return false;
}

public static function apply_image_watermark($image_path, $watermark_image_path, $position, $padding, $opacity, $scale, $save_path) {
if (!class_exists('Imagick')) {
return false;
}

try {
$image = new Imagick($image_path);
$watermark = new Imagick($watermark_image_path);
$image_width = $image->getImageWidth();
$image_height = $image->getImageHeight();

$wm_width = $watermark->getImageWidth();
$wm_height = $watermark->getImageHeight();
$image_width = $image->getImageWidth();
$image_height = $image->getImageHeight();

$available_width = max(1, $image_width - (2 * $padding));

if ($scale && $scale > 0) {
$target_width = ($available_width * $scale) / 100;
$ratio = $target_width / $wm_width;
$new_width = intval($wm_width * $ratio);
$new_height = intval($wm_height * $ratio);

$watermark->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
}


if ($opacity && $opacity < 100) {
$watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA);
}

$wm_width = $watermark->getImageWidth();
$wm_height = $watermark->getImageHeight();
$coordinates = self::calculate_watermark_position($image_width, $image_height, $wm_width, $wm_height, $position, $padding);

$image->compositeImage($watermark, Imagick::COMPOSITE_OVER, $coordinates['x'], $coordinates['y']);

$image->setImageFormat('PNG');
$image->writeImage($save_path);

$image->clear();
$image->destroy();
$watermark->clear();
$watermark->destroy();

return true;

} catch (Exception $e) {
return false;
}
}

public static function apply_text_watermark($image_path, $text, $font_name, $font_size_percent, $color, $position, $padding, $save_path) {
if (empty($text)) {
return false;
}

$info = getimagesize($image_path);
if (!$info) {
return false;
}

$image_width = $info[0];
$image_height = $info[1];

$target_width = (($image_width - (2 * $padding)) * $font_size_percent) / 100;

if (class_exists('Imagick')) {
try {
$image = new Imagick($image_path);
$draw = new ImagickDraw();

$font_path = self::get_plugin_font_path($font_name);
if ($font_path && file_exists($font_path)) {
	$draw->setFont($font_path);
}

$font_size = self::calculate_font_size_for_width($image, $draw, $text, $target_width);
$draw->setFontSize($font_size);
$draw->setFillColor(new ImagickPixel($color));

$metrics = $image->queryFontMetrics($draw, $text);
$text_width = $metrics['textWidth'];
$text_height = $metrics['textHeight'];

$coordinates = self::calculateTextPosition($image_width, $image_height, $text_width, $text_height, $position, $padding);

$y = $coordinates['y'] + $text_height;

$image->annotateImage($draw, $coordinates['x'], $y, 0, $text);

$image->setImageFormat('PNG');
$image->writeImage($save_path);
$image->clear();
$image->destroy();

return true;

} catch (Exception $e) {
}
}

switch ($info[2]) {
case IMAGETYPE_PNG:
$image = imagecreatefrompng($image_path);
imagealphablending($image, false);
imagesavealpha($image, true);
break;
case IMAGETYPE_GIF:
$image = imagecreatefromgif($image_path);
break;
case IMAGETYPE_WEBP:
$image = imagecreatefromwebp($image_path);
break;
case IMAGETYPE_AVIF:
if (function_exists('imagecreatefromavif')) {
	$image = imagecreatefromavif($image_path);
} else {
	return false;
}
break;
case IMAGETYPE_JPEG:
default:
$image = imagecreatefromjpeg($image_path);
break;
}

if (!$image) {
return false;
}

$rgb = sscanf($color, "#%02x%02x%02x");
if (!$rgb || count($rgb) !== 3) {
$rgb = [255, 255, 255];
}
$text_color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);

$font_path = self::get_plugin_font_path($font_name);
$use_ttf = $font_path && file_exists($font_path) && function_exists('imagettftext');

if ($use_ttf) {
$font_size = self::calculate_gd_font_size_for_width($font_path, $text, $target_width);

$bbox = imagettfbbox($font_size, 0, $font_path, $text);
$text_width = abs($bbox[4] - $bbox[0]);
$text_height = abs($bbox[1] - $bbox[5]);

$coordinates = self::calculateTextPosition($image_width, $image_height, $text_width, $text_height, $position, $padding);

$baseline_y = $coordinates['y'] + $text_height;

imagettftext($image, $font_size, 0, $coordinates['x'], $baseline_y, $text_color, $font_path, $text);

} else {
	$gd_font_size = self::convertToGdFontSize($font_size_percent * 2);

	$text_width = strlen($text) * imagefontwidth($gd_font_size);
	$text_height = imagefontheight($gd_font_size);

	$coordinates = self::calculateTextPosition($image_width, $image_height, $text_width, $text_height, $position, $padding);

	$bg_color = imagecolorallocatealpha($image, 0, 0, 0, 50);
	$bg_padding = 3;
	imagefilledrectangle(
	$image,
	$coordinates['x'] - $bg_padding,
	$coordinates['y'] - $bg_padding,
	$coordinates['x'] + $text_width + $bg_padding,
	$coordinates['y'] + $text_height + $bg_padding,
	$bg_color
);

imagestring($image, $gd_font_size, $coordinates['x'], $coordinates['y'], $text, $text_color);
}

$result = imagepng($image, $save_path);

imagedestroy($image);
return $result;
}

private static function calculate_font_size_for_width($image, $draw, $text, $target_width) {
$min_size = 8;
$max_size = 500;
$tolerance = 5;

while ($max_size - $min_size > 1) {
$mid_size = ($min_size + $max_size) / 2;
$draw->setFontSize($mid_size);
$metrics = $image->queryFontMetrics($draw, $text);
$current_width = $metrics['textWidth'];

if (abs($current_width - $target_width) <= $tolerance) {
	return $mid_size;
}

if ($current_width < $target_width) {
	$min_size = $mid_size;
} else {
	$max_size = $mid_size;
}
}

return $min_size;
}

private static function calculate_gd_font_size_for_width($font_path, $text, $target_width) {
$min_size = 8;
$max_size = 500;
$tolerance = 5;

while ($max_size - $min_size > 1) {
$mid_size = ($min_size + $max_size) / 2;
$bbox = imagettfbbox($mid_size, 0, $font_path, $text);
$current_width = abs($bbox[4] - $bbox[0]);

if (abs($current_width - $target_width) <= $tolerance) {
	return $mid_size;
}

if ($current_width < $target_width) {
	$min_size = $mid_size;
} else {
	$max_size = $mid_size;
}
}

return $min_size;
}

private static function calculateTextPosition($image_width, $image_height, $text_width, $text_height, $position, $padding) {
switch ($position) {
case 'top-left':
return ['x' => $padding, 'y' => $padding];
case 'top-center':
return ['x' => ($image_width - $text_width) / 2, 'y' => $padding];
case 'top-right':
return ['x' => $image_width - $text_width - $padding, 'y' => $padding];
case 'center-left':
return ['x' => $padding, 'y' => ($image_height - $text_height) / 2];
case 'center':
return ['x' => ($image_width - $text_width) / 2, 'y' => ($image_height - $text_height) / 2];
case 'center-right':
return ['x' => $image_width - $text_width - $padding, 'y' => ($image_height - $text_height) / 2];
case 'bottom-left':
return ['x' => $padding, 'y' => $image_height - $text_height - $padding];
case 'bottom-center':
return ['x' => ($image_width - $text_width) / 2, 'y' => $image_height - $text_height - $padding];
case 'bottom-right':
default:
return ['x' => $image_width - $text_width - $padding, 'y' => $image_height - $text_height - $padding];
}
}

private static function convertToGdFontSize($ttf_size) {
if ($ttf_size <= 8) return 1;
if ($ttf_size <= 12) return 2;
if ($ttf_size <= 16) return 3;
if ($ttf_size <= 20) return 4;
return 5;
}
}