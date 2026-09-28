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

	/**
	 * تب‌های واقعی: هر بخش پنل جداگانه دارد (الگوی ARIA tabs).
	 * بدون JS تب‌ها لینک ?tab=… هستند و سرور پنل درست را نشان می‌دهد؛ اینجا
	 * جابه‌جایی بدون بارگذاری مجدد انجام می‌شود، آدرس صفحه و آدرس بازگشت فرم
	 * (_wp_http_referer) به‌روز می‌شوند تا بعد از «ذخیره» همان تب باز بماند.
	 */
	const initTabs = () => {
		const list = document.querySelector('[data-hodima-tabs]');
		if (!list) {
			return;
		}

		const tabs = [...list.querySelectorAll('[role="tab"]')];
		const referer = document.querySelector('.hodima-settings__form input[name="_wp_http_referer"]');

		const activate = (tab, focus = false) => {
			tabs.forEach((item) => {
				const selected = item === tab;
				item.setAttribute('aria-selected', String(selected));
				item.tabIndex = selected ? 0 : -1;
				const panel = document.getElementById(item.getAttribute('aria-controls'));
				if (panel) {
					panel.hidden = !selected;
				}
			});

			const url = new URL(window.location.href);
			url.searchParams.set('tab', tab.dataset.tab);
			url.searchParams.delete('settings-updated');
			window.history.replaceState(null, '', url);

			if (referer) {
				const back = new URL(referer.value, window.location.origin);
				back.searchParams.set('tab', tab.dataset.tab);
				back.searchParams.delete('settings-updated');
				referer.value = back.pathname + back.search;
			}

			if (focus) {
				tab.focus();
			}
		};

		list.addEventListener('click', (event) => {
			const tab = event.target.closest('[role="tab"]');
			if (!tab) {
				return;
			}
			event.preventDefault();
			activate(tab);
		});

		// کیبورد: چپ/راست (با توجه به RTL)، Home و End
		list.addEventListener('keydown', (event) => {
			const index = tabs.indexOf(document.activeElement);
			if (index < 0) {
				return;
			}
			const rtl = getComputedStyle(list).direction === 'rtl';
			const step = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1 }[event.key];
			let next = null;
			if (step) {
				next = tabs[(index + step + tabs.length) % tabs.length];
			} else if (event.key === 'Home') {
				next = tabs[0];
			} else if (event.key === 'End') {
				next = tabs.at(-1);
			}
			if (next) {
				event.preventDefault();
				activate(next, true);
			}
		});

		// اگر فیلدی نامعتبر در تب پنهان بود، همان تب باز شود
		document.querySelector('.hodima-settings__form')?.addEventListener('invalid', (event) => {
			const panel = event.target.closest('[role="tabpanel"]');
			const tab = panel && tabs.find((item) => item.getAttribute('aria-controls') === panel.id);
			if (tab && panel.hidden) {
				activate(tab);
			}
		}, true);
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-hodima-media]').forEach(initMediaField);
		initTabs();
	});
})();
