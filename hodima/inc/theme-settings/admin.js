/**
 * Hodima — صفحه «تنظیمات قالب هدیما»
 * Path: inc/theme-settings/admin.js
 *
 * Vanilla JS (بدون jQuery). انتخابگر تصویر از API رسانه وردپرس (wp.media)
 * استفاده می‌کند که با wp_enqueue_media() بارگذاری می‌شود.
 */
(() => {
	'use strict';

	const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/**
	 * یک انتخابگر رسانه: دکمه انتخاب، پیش‌نمایش (با حالت «بدون تصویر»)، دکمه حذف و
	 * فیلد مخفی شناسه. data-hodima-media-kind="font": فایل فونت (نام فایل به‌جای
	 * تصویر؛ رویداد hodima:font برای پیش‌نمایش تایپوگرافی).
	 */
	const initMediaField = (root) => {
		const input = root.querySelector('[data-hodima-media-input]');
		const preview = root.querySelector('[data-hodima-media-preview]');
		const selectBtn = root.querySelector('[data-hodima-media-select]');
		const removeBtn = root.querySelector('[data-hodima-media-remove]');
		const isFont = root.dataset.hodimaMediaKind === 'font';
		const noun = isFont ? 'فایل' : 'تصویر';

		if (!input || !preview || !selectBtn || !removeBtn || typeof window.wp?.media !== 'function') {
			return;
		}

		let frame = null;

		const render = (attachment) => {
			preview.querySelector('img')?.remove();
			const name = preview.querySelector('[data-hodima-media-name]');

			if (!attachment) {
				input.value = '';
				preview.classList.add('is-empty');
				if (name) {
					name.textContent = '';
				}
				removeBtn.hidden = true;
				selectBtn.textContent = `انتخاب ${noun}`;
				input.dispatchEvent(new Event('change', { bubbles: true }));
				return;
			}

			if (isFont) {
				if (name) {
					name.textContent = attachment.filename ?? '';
				}
				root.dispatchEvent(new CustomEvent('hodima:font', { bubbles: true, detail: { url: attachment.url, input } }));
			} else {
				const size = attachment.sizes?.medium ?? attachment.sizes?.full ?? attachment;
				const img = document.createElement('img');
				img.src = size.url;
				img.alt = '';
				img.decoding = 'async';
				preview.prepend(img);
			}

			preview.classList.remove('is-empty');
			input.value = String(attachment.id);
			removeBtn.hidden = false;
			selectBtn.textContent = `تغییر ${noun}`;
			// فیلد مخفی رویداد ندارد؛ برای نشانه «ذخیره‌نشده»
			input.dispatchEvent(new Event('change', { bubbles: true }));
		};

		selectBtn.addEventListener('click', () => {
			frame ??= window.wp.media({
				title: selectBtn.dataset.title ?? '',
				library: { type: isFont ? ['font/woff2', 'font/woff'] : 'image' },
				button: { text: `استفاده از این ${noun}` },
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
	 * تب «تایپوگرافی»: نمونه زنده. هر فیلد یک متغیر همان tokens.css را روی ظرف
	 * نمونه عوض می‌کند (فرمول‌ها در admin.css همان style.css‌اند).
	 */
	const initTypography = () => {
		const preview = document.querySelector('[data-hodima-type-preview]');
		if (!preview) {
			return;
		}

		const stacks = JSON.parse(preview.dataset.stacks ?? '{}');
		const field = (key) => document.getElementById(`hodima-setting-${key}`);
		const colorVar = (key) => (key === 'text' ? 'var(--hodima-text-dark)' : `var(--hodima-${key})`);
		const set = (name, value) => {
			if (value !== undefined && value !== '') {
				preview.style.setProperty(name, value);
			}
		};

		const sync = () => {
			const body = field('font_body')?.value ?? 'vazirmatn';
			const heading = field('font_heading')?.value ?? 'body';
			set('--hodima-font', stacks[body]);
			set('--hodima-font-heading', heading === 'body' ? stacks[body] : stacks[heading]);
			set('--hodima-body-size', field('body_size')?.value);
			set('--hodima-body-line-height', field('body_line_height')?.value);
			set('--hodima-text-dark', field('color_text')?.value);
			set('--hodima-link', colorVar(field('link_color')?.value ?? 'secondary'));
			set('--hodima-link-hover', colorVar(field('link_hover_color')?.value ?? 'primary'));
			set('--hodima-content-link-line', field('content_link_underline')?.checked === false ? 'none' : 'underline');

			for (let level = 1; level <= 6; level++) {
				set(`--hodima-h${level}-size-max`, field(`h${level}_size`)?.value);
				set(`--hodima-h${level}-size-min`, field(`h${level}_size_mobile`)?.value);
				set(`--hodima-h${level}-weight`, field(`h${level}_weight`)?.value);
				set(`--hodima-h${level}-line-height`, field(`h${level}_line_height`)?.value);
				set(`--hodima-h${level}-color`, colorVar(field(`h${level}_color`)?.value ?? 'text'));
			}
		};

		document.querySelector('[data-section="typography"]')?.addEventListener('input', sync);
		document.querySelector('[data-section="typography"]')?.addEventListener('change', sync);

		// فایل فونت تازه (پیش از ذخیره): همان‌جا به مرورگر اضافه شود
		document.addEventListener('hodima:font', async (event) => {
			const weight = event.detail.input?.id.match(/font_custom_(\d+)$/)?.[1];
			if (!weight || typeof FontFace !== 'function') {
				return;
			}
			try {
				const face = new FontFace(preview.dataset.family ?? 'Hodima Custom', `url("${event.detail.url}")`, { weight });
				document.fonts.add(await face.load());
			} catch {
				// فایل خراب یا نامعتبر: پیش‌نمایش با فونت جایگزین می‌ماند
			}
		});

		preview.querySelectorAll('[data-hodima-type-mode]').forEach((button) => {
			button.addEventListener('click', () => {
				preview.dataset.mode = button.dataset.hodimaTypeMode;
				preview.querySelectorAll('[data-hodima-type-mode]').forEach((other) => {
					other.setAttribute('aria-pressed', String(other === button));
				});
			});
		});

		sync();
	};

	/**
	 * منوی کناری: هر تب لینک ?tab=… است (بدون JS سرور پنل درست را نشان می‌دهد).
	 * اینجا جابه‌جایی بدون بارگذاری مجدد انجام می‌شود، آدرس صفحه و آدرس بازگشت
	 * فرم (_wp_http_referer) به‌روز می‌شوند تا بعد از «ذخیره» همان تب باز بماند.
	 */
	const initNav = () => {
		const nav = document.querySelector('[data-hodima-nav]');
		if (!nav) {
			return null;
		}

		const links = [...nav.querySelectorAll('[data-tab]')];
		const referer = document.querySelector('[data-hodima-form] input[name="_wp_http_referer"]');
		const panelOf = (link) => document.getElementById(link.getAttribute('aria-controls'));

		const activate = (link, { focus = false } = {}) => {
			links.forEach((item) => {
				const selected = item === link;
				if (selected) {
					item.setAttribute('aria-current', 'page');
				} else {
					item.removeAttribute('aria-current');
				}
				const panel = panelOf(item);
				if (panel) {
					panel.hidden = !selected;
				}
			});

			const url = new URL(window.location.href);
			url.searchParams.set('tab', link.dataset.tab);
			url.searchParams.delete('settings-updated');
			window.history.replaceState(null, '', url);

			if (referer) {
				const back = new URL(referer.value, window.location.origin);
				back.searchParams.set('tab', link.dataset.tab);
				back.searchParams.delete('settings-updated');
				referer.value = back.pathname + back.search;
			}

			// تب فعال در منوی لغزنده موبایل دیده شود
			link.scrollIntoView({ block: 'nearest', inline: 'nearest' });

			// صفحه‌خوان: تمرکز روی عنوان تب تازه (فقط با کیبورد؛ کلیک موس صفحه را جابه‌جا نکند)
			if (focus) {
				panelOf(link)?.querySelector('h2')?.focus({ preventScroll: true });
			}

			const top = document.querySelector('.hodima-settings__layout');
			if (top && top.getBoundingClientRect().top < 0) {
				top.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
			}
		};

		nav.addEventListener('click', (event) => {
			const link = event.target.closest('[data-tab]');
			if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
				return; // باز کردن در زبانه جدید دست نخورد
			}
			event.preventDefault();
			// detail === 0: فعال‌سازی با Enter
			activate(link, { focus: event.detail === 0 });
		});

		// اگر فیلدی نامعتبر در تب پنهان بود، همان تب باز شود
		document.querySelector('[data-hodima-form]')?.addEventListener('invalid', (event) => {
			const panel = event.target.closest('[data-section]');
			const link = panel && links.find((item) => item.getAttribute('aria-controls') === panel.id);
			if (link && panel.hidden) {
				activate(link);
			}
		}, true);

		return { links, panelOf };
	};

	/**
	 * نشانه «تغییرهای ذخیره‌نشده»: در نوار ذخیره و کنار تب‌هایی که تغییر کرده‌اند،
	 * هشدار مرورگر هنگام ترک صفحه، و Ctrl/⌘ + S برای ذخیره.
	 */
	const initDirty = (nav) => {
		const form = document.querySelector('[data-hodima-form]');
		if (!form) {
			return;
		}

		let dirty = false;
		let submitting = false;

		const mark = (target) => {
			if (!target?.name || target.closest('template')) {
				return;
			}
			dirty = true;
			form.classList.add('is-dirty');
			const panel = target.closest('[data-section]');
			const link = panel && nav?.links.find((item) => item.getAttribute('aria-controls') === panel.id);
			const dot = link?.querySelector('[data-hodima-dirty]');
			if (dot) {
				dot.hidden = false;
			}
		};

		form.addEventListener('input', (event) => mark(event.target));
		form.addEventListener('change', (event) => mark(event.target));
		// جابه‌جایی، افزودن و حذف بخش‌های صفحه اصلی (admin.js خودش رویداد می‌فرستد)
		form.addEventListener('hodima:changed', (event) => mark(event.target.querySelector('[name]') ?? form.querySelector('[data-hodima-home] [name]')));

		form.addEventListener('submit', () => {
			submitting = true;
		});

		window.addEventListener('beforeunload', (event) => {
			if (dirty && !submitting) {
				event.preventDefault();
				event.returnValue = '';
			}
		});

		document.addEventListener('keydown', (event) => {
			if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 's') {
				event.preventDefault();
				form.requestSubmit(form.querySelector('[type="submit"][name="submit"]') ?? undefined);
			}
		});
	};

	/** پالت: نمونه زنده، مقدار هگز و دکمه «بازگشت به پیش‌فرض» هر رنگ. */
	const initPalette = () => {
		const preview = document.querySelector('[data-hodima-palette-preview]');

		document.querySelectorAll('[data-hodima-color]').forEach((input) => {
			const field = input.closest('.hodima-field');
			const value = field?.querySelector('[data-hodima-color-value]');
			const reset = field?.querySelector('[data-hodima-color-reset]');

			const sync = () => {
				const color = input.value.toLowerCase();
				if (value) {
					value.textContent = color;
				}
				if (reset) {
					reset.hidden = color === reset.dataset.hodimaColorReset;
				}
				preview?.style.setProperty(`--pv-${input.dataset.hodimaColor}`, color);
			};

			input.addEventListener('input', sync);
			reset?.addEventListener('click', () => {
				input.value = reset.dataset.hodimaColorReset;
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.focus();
			});
		});
	};

	/** پیش‌نمایش لوگو روی رنگ هدر، هم‌زمان با کلید «لوگو سفید نمایش داده شود». */
	const initLogoInvert = () => {
		document.querySelectorAll('[data-hodima-invert-source]').forEach((preview) => {
			const source = document.getElementById(preview.dataset.hodimaInvertSource);
			source?.addEventListener('change', () => preview.classList.toggle('is-inverted', source.checked));
		});
	};

	/** برچسب «در حال استفاده» قاب بخش‌های صفحه اصلی، هم‌زمان با کلید ساخت از چیدمان. */
	const initBuilderStatus = () => {
		const status = document.querySelector('[data-hodima-builder-status]');
		const source = document.getElementById('hodima-setting-home_builder');
		source?.addEventListener('change', () => {
			if (status) {
				status.classList.toggle('is-on', source.checked);
				status.textContent = source.checked ? status.dataset.on : status.dataset.off;
			}
		});
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
			// جابه‌جایی/افزودن/حذف رویداد input ندارد؛ برای نشانه «ذخیره‌نشده»
			list.dispatchEvent(new CustomEvent('hodima:changed', { bubbles: true }));
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
			item.scrollIntoView({ block: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' });
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
		initDirty(initNav());
		initPalette();
		initLogoInvert();
		initBuilderStatus();
		initTypography();
	});
})();
