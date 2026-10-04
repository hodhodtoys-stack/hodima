/**
 * Hodima — صفحه «تنظیمات قالب هدیما»
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

	/**
	 * تب «صفحه اصلی»: فهرست بخش‌ها (inc/theme-settings/home-layout-admin.php).
	 * ترتیب ذخیره = ترتیب بخش‌ها در فرم؛ پس جابه‌جایی فقط جابه‌جایی عنصر است.
	 * کشیدن با دستگیره، و برای کیبورد/صفحه‌خوان دکمه‌های بالا/پایین.
	 */
	const initHomeLayout = (root) => {
		const list = root.querySelector('[data-hodima-home-list]');
		const empty = root.querySelector('[data-hodima-home-empty]');
		const typeSelect = root.querySelector('[data-hodima-home-type]');
		const live = document.createElement('p');

		if (!list || !typeSelect) {
			return;
		}

		// اعلان تغییرها برای صفحه‌خوان (جابه‌جایی، افزودن، حذف)
		live.className = 'screen-reader-text';
		live.setAttribute('aria-live', 'polite');
		root.append(live);
		const announce = (text) => {
			live.textContent = '';
			window.setTimeout(() => { live.textContent = text; }, 50);
		};

		const items = () => [...list.querySelectorAll(':scope > [data-hodima-home-item]')];
		const labelOf = (item) => item.querySelector('.hodima-home__title strong')?.textContent ?? '';

		/** بخش‌های تک‌نمونه (معرفی، دسته‌ها، …) وقتی هست دوباره قابل افزودن نیستند. */
		const refresh = () => {
			const present = new Set(items().map((item) => item.dataset.type));
			[...typeSelect.options].forEach((option) => {
				option.disabled = option.dataset.single === '1' && present.has(option.value);
			});
			if (typeSelect.selectedOptions[0]?.disabled) {
				typeSelect.value = [...typeSelect.options].find((option) => !option.disabled)?.value ?? '';
			}
			if (empty) {
				empty.hidden = items().length > 0;
			}
		};

		const toggleBody = (item, open) => {
			const button = item.querySelector('[data-hodima-home-toggle]');
			const body = item.querySelector('.hodima-home__body');
			if (!button || !body) {
				return;
			}
			const next = open ?? button.getAttribute('aria-expanded') !== 'true';
			button.setAttribute('aria-expanded', String(next));
			body.hidden = !next;
		};

		const move = (item, direction) => {
			const sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;
			if (!sibling) {
				return;
			}
			if (direction === 'up') {
				sibling.before(item);
			} else {
				sibling.after(item);
			}
			announce(`«${labelOf(item)}» جابه‌جا شد: ردیف ${items().indexOf(item) + 1} از ${items().length}`);
		};

		root.querySelector('[data-hodima-home-add]')?.addEventListener('click', () => {
			const template = root.querySelector(`template[data-hodima-home-template="${CSS.escape(typeSelect.value)}"]`);
			if (!template) {
				return;
			}
			const uid = `n${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
			const holder = document.createElement('div');
			holder.innerHTML = template.innerHTML.replaceAll('__UID__', uid).replaceAll('__uid__', uid);
			const item = holder.firstElementChild;
			list.append(item);
			refresh();
			item.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
			item.querySelector('[data-hodima-home-toggle]')?.focus();
			announce(`بخش «${labelOf(item)}» به انتهای فهرست اضافه شد`);
		});

		list.addEventListener('click', (event) => {
			const item = event.target.closest('[data-hodima-home-item]');
			if (!item) {
				return;
			}
			if (event.target.closest('[data-hodima-home-toggle]')) {
				toggleBody(item);
				return;
			}
			const mover = event.target.closest('[data-hodima-home-move]');
			if (mover) {
				move(item, mover.dataset.hodimaHomeMove);
				mover.focus();
				return;
			}
			if (event.target.closest('[data-hodima-home-remove]')) {
				// eslint-disable-next-line no-alert
				if (window.confirm(`بخش «${labelOf(item)}» از صفحه اصلی حذف شود؟ (بعد از «ذخیره» قطعی می‌شود)`)) {
					const next = item.nextElementSibling ?? item.previousElementSibling;
					const label = labelOf(item);
					item.remove();
					refresh();
					(next?.querySelector('[data-hodima-home-toggle]') ?? typeSelect).focus();
					announce(`بخش «${label}» حذف شد`);
				}
			}
		});

		// خلاصه جلوی نام بخش با تایپ عنوان یا انتخاب دسته به‌روز شود
		list.addEventListener('input', (event) => {
			const source = event.target.closest('[data-hodima-home-summary-source]');
			const item = source?.closest('[data-hodima-home-item]');
			const summary = item?.querySelector('[data-hodima-home-summary]');
			if (!summary) {
				return;
			}
			const sources = [...item.querySelectorAll('[data-hodima-home-summary-source]')];
			const title = sources.find((el) => el.tagName === 'INPUT')?.value.trim();
			const category = sources.find((el) => el.tagName === 'SELECT');
			summary.textContent = title || (category?.value ? category.selectedOptions[0]?.textContent.trim() : '') || '';
		});

		// کشیدن و رها کردن: فقط از دستگیره شروع می‌شود (تا انتخاب متن فیلدها خراب نشود)
		let dragged = null;
		list.addEventListener('pointerdown', (event) => {
			const handle = event.target.closest('[data-hodima-home-handle]');
			const item = handle?.closest('[data-hodima-home-item]');
			if (item) {
				item.draggable = true;
			}
		});
		list.addEventListener('dragstart', (event) => {
			dragged = event.target.closest('[data-hodima-home-item]');
			if (!dragged) {
				return;
			}
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', '');
			dragged.classList.add('is-dragging');
		});
		list.addEventListener('dragover', (event) => {
			if (!dragged) {
				return;
			}
			event.preventDefault();
			const over = event.target.closest('[data-hodima-home-item]');
			if (!over || over === dragged) {
				return;
			}
			const box = over.getBoundingClientRect();
			if (event.clientY < box.top + box.height / 2) {
				over.before(dragged);
			} else {
				over.after(dragged);
			}
		});
		list.addEventListener('dragend', () => {
			if (dragged) {
				dragged.classList.remove('is-dragging');
				dragged.draggable = false;
				announce(`«${labelOf(dragged)}» در ردیف ${items().indexOf(dragged) + 1} قرار گرفت`);
				dragged = null;
			}
		});

		root.querySelector('[data-hodima-home-import]')?.addEventListener('click', (event) => {
			// eslint-disable-next-line no-alert
			if (!window.confirm('چیدمان فعلی کنار گذاشته شود و دوباره از روی متن برگه صفحه اصلی ساخته شود؟ (بقیه تنظیمات این صفحه هم ذخیره می‌شوند)')) {
				event.preventDefault();
			}
		});

		refresh();
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-hodima-media]').forEach(initMediaField);
		document.querySelectorAll('[data-hodima-home]').forEach(initHomeLayout);
		initTabs();
	});
})();
