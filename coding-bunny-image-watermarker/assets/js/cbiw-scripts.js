document.addEventListener('DOMContentLoaded', () => {
	initializeWatermarkImageChooser();
	initializeBulkActionSeparators();
	switchWatermarkType();
	initializeWatermarkTypeListener();
	initializeWatermarkPreviewCanvas();
	bootstrapExistingWatermarkImage();
	setTimeout(() => {
		if (typeof window.renderWatermarkCanvasPreview === 'function') {
			window.renderWatermarkCanvasPreview();
		}
	}, 50);
});

function initializeWatermarkImageChooser() {
	const button = document.getElementById('choose-watermark-image');
	if (!button) return;
	button.addEventListener('click', (e) => {
		e.preventDefault();
		if (!window.wp || !window.wp.media) {
			alert('The WordPress Media Library is not loaded.');
			return;
		}
		let frame;
		try {
			frame = wp.media({
				title: 'Choose Watermark Image',
				button: { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			});
		} catch (error) {
			console.error('Error creating media frame:', error);
			alert('Error opening the media library.');
			return;
		}
		frame.on('select', () => {
			try {
				const attachment = frame.state().get('selection').first().toJSON();
				updateWatermarkImageSelection(attachment);
			} catch (error) {
				console.error('Error selecting watermark image:', error);
				alert('Error selecting the image.');
			}
		});
		frame.open();
	});
}

function updateWatermarkImageSelection(attachment) {
	const input = document.getElementById('watermark_image_id');
	const preview = document.getElementById('watermark-image-preview');
	if (!attachment) {
		console.warn('No attachment object received.');
		return;
	}
	const url = attachment.url
	|| attachment.source_url
	|| (attachment.sizes && attachment.sizes.full && attachment.sizes.full.url)
	|| '';
	if (!url) {
		console.warn('No URL found in attachment:', attachment);
	}
	if (input) {
		input.value = attachment.id;
		input.setAttribute('value', attachment.id);
	}
	window.CBIO_WATERMARK_PREVIEW_DATA = window.CBIO_WATERMARK_PREVIEW_DATA || {};
	window.CBIO_WATERMARK_PREVIEW_DATA.image_url = url;
	if (window._wmImageCache) {
		window._wmImageCache.url = '';
		window._wmImageCache.img = null;
		window._wmImageCache.loaded = false;
	}
	if (preview && url) {
		preview.src = url + '?v=' + Date.now();
		preview.style.display = 'block';
		preview.alt = attachment.alt || 'Watermark preview';
	}
	if (typeof window.renderWatermarkCanvasPreview === 'function') {
		window.renderWatermarkCanvasPreview(true);
	} else {
		console.warn('renderWatermarkCanvasPreview not yet available.');
	}
}

function bootstrapExistingWatermarkImage() {
	const input = document.getElementById('watermark_image_id');
	if (!input || !input.value) return;
	window.CBIO_WATERMARK_PREVIEW_DATA = window.CBIO_WATERMARK_PREVIEW_DATA || {};
	if (window.CBIO_WATERMARK_PREVIEW_DATA.image_url) return;
	const previewEl = document.getElementById('watermark-image-preview');
	if (previewEl && previewEl.getAttribute('src')) {
		const rawSrc = previewEl.getAttribute('src').split('?')[0];
		if (rawSrc) {
			window.CBIO_WATERMARK_PREVIEW_DATA.image_url = rawSrc;
			return;
		}
	}
	if (window.wp && wp.media && typeof wp.media.attachment === 'function') {
		try {
			const att = wp.media.attachment(input.value);
			const cachedUrl = att && (att.get('url') || att.get('source_url'));
			if (cachedUrl) {
				window.CBIO_WATERMARK_PREVIEW_DATA.image_url = cachedUrl;
				return;
			}
			att.fetch().then(() => {
				const url = att.get('url') || att.get('source_url');
				if (url) {
					window.CBIO_WATERMARK_PREVIEW_DATA.image_url = url;
					if (typeof window.renderWatermarkCanvasPreview === 'function') {
						window.renderWatermarkCanvasPreview(true);
					}
				}
			}).catch(err => {
				console.warn('Unable to fetch attachment for watermark bootstrap:', err);
			});
			return;
		} catch (e) {
			console.warn('Error calling wp.media.attachment:', e);
		}
	}
}

function initializeBulkActionSeparators() {
	document
	.querySelectorAll('select[name="action"], select[name="action2"]')
	.forEach(bulkSelect => {
		const opt = bulkSelect.querySelector('option[value="cbio_watermark_separator"]');
		if (opt) {
			opt.disabled = true;
			opt.style.fontWeight = 'bold';
			opt.style.color = '#555';
			opt.title = '-- Watermark section --';
		}
	});
}

function switchWatermarkType() {
	const typeSelect = document.getElementById('watermark_type');
	if (!typeSelect) return;
	const type = typeSelect.value || 'image';
	const textSettings = document.getElementById('watermark_text_settings');
	const textSizeRow = document.getElementById('watermark_text_size_row');
	const textFontRow = document.getElementById('watermark_text_font_row');
	const textColorRow = document.getElementById('watermark_text_color_row');
	const imageSettings = document.getElementById('watermark_image_settings');
	const sizeRow = document.getElementById('watermark_image_size_row');
	const opacityRow = document.getElementById('watermark_image_opacity_row');
	toggleElementDisplay(textSettings, type === 'text');
	toggleElementDisplay(textSizeRow, type === 'text');
	toggleElementDisplay(textFontRow, type === 'text');
	toggleElementDisplay(textColorRow, type === 'text');
	toggleElementDisplay(imageSettings, type === 'image');
	toggleElementDisplay(sizeRow, type === 'image');
	toggleElementDisplay(opacityRow, type === 'image');
}

function toggleElementDisplay(el, show) {
	if (el) el.style.display = show ? '' : 'none';
}

function initializeWatermarkTypeListener() {
	const sel = document.getElementById('watermark_type');
	if (sel) sel.addEventListener('change', () => {
		switchWatermarkType();
		if (typeof window.renderWatermarkCanvasPreview === 'function') {
			window.renderWatermarkCanvasPreview();
		}
	});
}

function initializeWatermarkPreviewCanvas() {
	const canvas = document.getElementById('cbio-watermark-preview-canvas');
	if (!canvas) return;
	const ctx = canvas.getContext('2d');
	window.renderWatermarkCanvasPreview = function noop() {};

	window._wmImageCache = {
		url: '',
		img: null,
		loaded: false
	};

	const selectors = [
		'[name="watermark_options[watermark_type]"]',
		'[name="watermark_options[watermark_text]"]',
		'[name="watermark_options[watermark_text_size]"]',
		'[name="watermark_options[watermark_text_font]"]',
		'[name="watermark_options[watermark_text_color]"]',
		'[name="watermark_options[watermark_image_opacity]"]',
		'[name="watermark_options[watermark_opacity]"]',
		'[name="watermark_options[watermark_size]"]',
		'[name="watermark_options[watermark_padding]"]'
	];

	const fields = selectors
	.map(s => document.querySelector(s))
	.filter(Boolean);

	fields.forEach(f => {
		f.addEventListener('input', () => window.renderWatermarkCanvasPreview());
		f.addEventListener('change', () => window.renderWatermarkCanvasPreview());
	});

	document
	.querySelectorAll('input[name="watermark_options[watermark_position]"]')
	.forEach(r => {
		r.addEventListener('input', () => window.renderWatermarkCanvasPreview());
		r.addEventListener('change', () => window.renderWatermarkCanvasPreview());
	});

	let loading = false;
	let pending = false;

	function renderWatermarkCanvasPreview(force) {
		ctx.clearRect(0, 0, canvas.width, canvas.height);

		const type = document.getElementById('watermark_type')?.value || 'image';
		const padding = parseInt(document.querySelector('[name="watermark_options[watermark_padding]"]')?.value || 10, 10);
		const pos = document.querySelector('[name="watermark_options[watermark_position]"]:checked')?.value || 'bottom-right';
		const containerW = canvas.width;
		const containerH = canvas.height;

		if (type === 'text') {
			const text = document.querySelector('[name="watermark_options[watermark_text]"]')?.value || '';
			const sizePercent = parseInt(document.querySelector('[name="watermark_options[watermark_text_size]"]')?.value || 24, 10);
			const font = document.querySelector('[name="watermark_options[watermark_text_font]"]')?.value || 'montserrat';
			const color = document.querySelector('[name="watermark_options[watermark_text_color]"]')?.value || '#000000';
			if (!text) {
				ctx.save();
				ctx.fillStyle = '#aaa';
				ctx.font = '16px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('Enter watermark text', containerW / 2, containerH / 2);
				ctx.restore();
				return;
			}
			const fontMap = {
				montserrat: 'Montserrat,Arial,sans-serif',
				playfair_display: '"Playfair Display",serif',
				roboto: 'Roboto,sans-serif',
				verdana: 'Verdana,sans-serif'
			};
			const family = fontMap[font] || 'sans-serif';

			const availableWidth = Math.max(1, containerW - (2 * padding));
			const targetWidth = (availableWidth * sizePercent) / 100;
			let testFontSize = 20, iterations = 0;
			let testWidth;

			ctx.font = `${testFontSize}px ${family}`;
			testWidth = ctx.measureText(text).width;
			let fontSize = Math.round((targetWidth / testWidth) * testFontSize);
			fontSize = Math.max(8, Math.min(fontSize, 200));
			for (iterations = 0; iterations < 10; iterations++) {
				ctx.font = `${fontSize}px ${family}`;
				testWidth = ctx.measureText(text).width;
				if (Math.abs(testWidth - targetWidth) < 2) break;
				fontSize = Math.round(fontSize * targetWidth / testWidth);
				fontSize = Math.max(8, Math.min(fontSize, 200));
			}

			ctx.save();
			ctx.globalAlpha = 1;
			ctx.font = `${fontSize}px ${family}`;
			const metrics = ctx.measureText(text);
			const textW = metrics.width;
			const textH = fontSize;
			const coords = calculatePreviewPosition(containerW, containerH, textW, textH, pos, padding);
			ctx.fillStyle = color;
			ctx.fillText(text, coords.x, coords.y + textH);
			ctx.restore();
			return;
		}

		const imageId = document.getElementById('watermark_image_id')?.value || '';
		let imageUrl = window.CBIO_WATERMARK_PREVIEW_DATA?.image_url || '';

		if (imageId && !imageUrl && !window._cbioLazyBootstrapTried) {
			window._cbioLazyBootstrapTried = true;
			bootstrapExistingWatermarkImage();
			imageUrl = window.CBIO_WATERMARK_PREVIEW_DATA?.image_url || '';
			if (!imageUrl) {
				ctx.save();
				ctx.font = '14px sans-serif';
				ctx.fillStyle = '#666';
				ctx.textAlign = 'center';
				ctx.fillText('Loading watermark...', containerW / 2, containerH / 2);
				ctx.restore();
				return;
			}
		}

		if (!imageId || !imageUrl) {
			ctx.save();
			ctx.font = '16px sans-serif';
			ctx.fillStyle = '#aaa';
			ctx.textAlign = 'center';
			ctx.fillText('No watermark image selected', containerW / 2, containerH / 2);
			ctx.restore();
			return;
		}

		const sizePercent = parseFloat(document.querySelector('[name="watermark_options[watermark_size]"]')?.value || 20);

		const opacityField = document.querySelector(
			'[name="watermark_options[watermark_image_opacity]"], [name="watermark_options[watermark_opacity]"]'
		);
		let opacityVal = parseFloat(opacityField?.value || '100');
		if (isNaN(opacityVal)) opacityVal = 100;
		opacityVal = Math.min(100, Math.max(0, opacityVal));
		const opacity = opacityVal / 100;

		const cache = window._wmImageCache;
		const baseUrl = imageUrl;

		if (cache.url !== baseUrl) {
			cache.url = baseUrl;
			cache.img = new Image();
			cache.loaded = false;
			loading = true;
			cache.img.crossOrigin = 'anonymous';
			cache.img.onload = () => {
				cache.loaded = true;
				loading = false;
				pending = false;
				drawCached();
			};
			cache.img.onerror = (e) => {
				console.error('Error loading watermark image:', e, baseUrl);
				loading = false;
				pending = false;
				ctx.save();
				ctx.fillStyle = '#c00';
				ctx.font = '14px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('Error loading image', containerW / 2, containerH / 2);
				ctx.restore();
			};
			cache.img.src = baseUrl;
			ctx.save();
			ctx.font = '14px sans-serif';
			ctx.fillStyle = '#666';
			ctx.textAlign = 'center';
			ctx.fillText('Loading watermark...', containerW / 2, containerH / 2);
			ctx.restore();
			return;
		}

		if (!cache.loaded) {
			ctx.save();
			ctx.font = '14px sans-serif';
			ctx.fillStyle = '#666';
			ctx.textAlign = 'center';
			ctx.fillText('Loading watermark...', containerW / 2, containerH / 2);
			ctx.restore();
			return;
		}

		drawCached();

		function drawCached() {
			ctx.clearRect(0, 0, containerW, containerH);
			const img = cache.img;
			const availableWidth = Math.max(1, containerW - (2 * padding));
			let wmW = Math.round(availableWidth * (sizePercent / 100));
			let wmH = Math.round(wmW * img.height / img.width);
			if (wmH > containerH * 0.9) {
				wmH = Math.round(containerH * 0.9);
				wmW = Math.round(wmH * img.width / img.height);
			}
			const coords = calculatePreviewPosition(containerW, containerH, wmW, wmH, pos, padding);
			ctx.save();
			ctx.globalAlpha = opacity;
			ctx.drawImage(img, coords.x, coords.y, wmW, wmH);
			ctx.restore();
		}
	}

	function calculatePreviewPosition(containerW, containerH, wmW, wmH, pos, padding) {
		const positions = {
			'top-left': [padding, padding],
			'top-center': [(containerW - wmW) / 2, padding],
			'top-right': [containerW - wmW - padding, padding],
			'center-left': [padding, (containerH - wmH) / 2],
			'center': [(containerW - wmW) / 2, (containerH - wmH) / 2],
			'center-right': [containerW - wmW - padding, (containerH - wmH) / 2],
			'bottom-left': [padding, containerH - wmH - padding],
			'bottom-center': [(containerW - wmW) / 2, containerH - wmH - padding],
			'bottom-right': [containerW - wmW - padding, containerH - wmH - padding],
		};
		const [x, y] = positions[pos] || positions['bottom-right'];
		return { x, y };
	}

	window.renderWatermarkCanvasPreview = renderWatermarkCanvasPreview;
	renderWatermarkCanvasPreview();
}