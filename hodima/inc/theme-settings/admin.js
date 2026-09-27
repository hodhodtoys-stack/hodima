/**
 * Hodima — صفحه «تنظیمات هدیما»
 * Path: inc/theme-settings/admin.js
 *
 * Vanilla JS (بدون jQuery). انتخابگر تصویر از API رسانه وردپرس (wp.media)
 * استفاده می‌کند که با wp_enqueue_media() بارگذاری می‌شود.
 */
(() => {
	'use strict';

	/** یک انتخابگر تصویر: دکمه انتخاب، پیش‌نمایش، دکمه حذف و فیلد مخفی شناسه. */
	const initMediaField = (root) => {
		const input = root.querySelector('[data-hodima-media-input]');
		const preview = root.querySelector('[data-hodima-media-preview]');
		const selectBtn = root.querySelector('[data-hodima-media-select]');
		const removeBtn = root.querySelector('[data-hodima-media-remove]');

		if (!input || !preview || !selectBtn || !removeBtn || typeof window.wp?.media !== 'function') {
			return;
		}

		let frame = null;

		const render = (attachment) => {
			preview.replaceChildren();

			if (!attachment) {
				input.value = '';
				preview.hidden = true;
				removeBtn.hidden = true;
				return;
			}

			const size = attachment.sizes?.medium ?? attachment.sizes?.full ?? attachment;
			const img = document.createElement('img');
			img.src = size.url;
			img.alt = '';
			img.decoding = 'async';

			preview.append(img);
			input.value = String(attachment.id);
			preview.hidden = false;
			removeBtn.hidden = false;
		};

		selectBtn.addEventListener('click', () => {
			frame ??= window.wp.media({
				title: selectBtn.dataset.title ?? '',
				library: { type: 'image' },
				button: { text: 'استفاده از این تصویر' },
				multiple: false,
			});

			// اتصال یک‌باره؛ frame بین کلیک‌ها نگه داشته می‌شود
			if (!frame.hodimaBound) {
				frame.on('select', () => render(frame.state().get('selection').first()?.toJSON()));
				frame.hodimaBound = true;
			}

			frame.open();
		});

		removeBtn.addEventListener('click', () => {
			render(null);
			selectBtn.focus();
		});
	};

	/** نشان دادن بخش فعال در نوار ناوبری هنگام اسکرول. */
	const initTabs = () => {
		const tabs = [...document.querySelectorAll('.hodima-settings__tab')];
		const sections = tabs
			.map((tab) => document.querySelector(tab.getAttribute('href')))
			.filter(Boolean);

		if (!sections.length || !('IntersectionObserver' in window)) {
			return;
		}

		const observer = new IntersectionObserver((entries) => {
			const visible = entries.find((entry) => entry.isIntersecting);
			if (!visible) {
				return;
			}
			tabs.forEach((tab) => {
				tab.setAttribute('aria-current', String(tab.getAttribute('href') === `#${visible.target.id}`));
			});
		}, { rootMargin: '-30% 0px -60% 0px' });

		sections.forEach((section) => observer.observe(section));
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-hodima-media]').forEach(initMediaField);
		initTabs();
	});
})();
