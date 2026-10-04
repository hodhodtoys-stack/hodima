/*
 * لینک‌های مرتبط دستی — کادر ویرایشگر
 * Path: plugins/hodima-seo/inc/manual_related_link/assets/admin.js
 *
 * جستجوی زنده مقصد (کیبوردی: بالا/پایین/Enter/Escape) و چسباندن آدرس.
 * JavaScript خالص، بدون jQuery. (انتخاب تصویر دلخواه در ۲.۴ حذف شد.)
 * (جابه‌جایی، شماره‌گذاری و «افزودن خانه ذخیره» در ۲.۳ به خواست کاربر حذف شد.)
 */
(() => {
	'use strict';

	const cfg = window.hodimaRL ?? {};
	const t = cfg.i18n ?? {};

	const field = (slot, name) => slot.querySelector(`[data-field="${name}"]`);

	const markUnsaved = (slot) => {
		const list = slot.querySelector('.hodima-rl-warnings');
		list.textContent = '';
		const li = document.createElement('li');
		li.className = 'is-info';
		li.textContent = t.unsaved ?? '';
		list.append(li);
	};

	/* ── انتخاب مقصد ── */
	const setTarget = (slot, item) => {
		field(slot, 'kind').value = item.kind;
		field(slot, 'id').value = item.kind === 'url' ? '' : String(item.id);
		field(slot, 'url').value = item.kind === 'url' ? item.url : '';

		const target = slot.querySelector('.hodima-rl-target');
		const thumb = target.querySelector('.hodima-rl-target__thumb');
		thumb.textContent = '';
		if (item.thumb) {
			const img = document.createElement('img');
			img.src = item.thumb;
			img.alt = '';
			thumb.append(img);
		}
		target.querySelector('.hodima-rl-target__title').textContent = item.title || item.link;
		target.querySelector('.hodima-rl-target__type').textContent = item.type;
		target.querySelector('.hodima-rl-target__url').textContent = item.link;

		target.hidden = false;
		slot.querySelector('.hodima-rl-picker').hidden = true;
		slot.dataset.filled = '1';

		markUnsaved(slot);
		const list = slot.querySelector('.hodima-rl-warnings');
		(item.warnings ?? []).forEach((w) => {
			const li = document.createElement('li');
			li.className = w.hiding ? 'is-error' : 'is-warning';
			li.textContent = w.text + (w.hiding ? (t.hidden ?? '') : '');
			list.append(li);
		});

		// آدرس دلخواه بدون عنوان نمایش داده نمی‌شود: بخش عنوان باز شود
		if (item.kind === 'url') {
			field(slot, 'title').focus();
		} else {
			target.querySelector('.hodima-rl-clear').focus();
		}
	};

	const clearTarget = (slot) => {
		// عنوان دلخواه و تصویر قدیمی ذخیره‌شده هم پاک می‌شوند تا روی مقصد بعدی نمانند
		['kind', 'id', 'url', 'title', 'img_id'].forEach((name) => { field(slot, name).value = ''; });
		slot.querySelector('.hodima-rl-target').hidden = true;
		const picker = slot.querySelector('.hodima-rl-picker');
		picker.hidden = false;
		slot.dataset.filled = '0';
		markUnsaved(slot);
		picker.querySelector('.hodima-rl-search').focus();
	};

	/* ── جستجو ── */
	const setupSearch = (slot, group) => {
		const input = slot.querySelector('.hodima-rl-search');
		const list = slot.querySelector('.hodima-rl-results');
		const status = slot.querySelector('.hodima-rl-status');
		let timer = 0;
		let controller = null;
		let results = [];
		let active = -1;

		const close = () => {
			list.hidden = true;
			input.setAttribute('aria-expanded', 'false');
			input.removeAttribute('aria-activedescendant');
			active = -1;
		};

		const highlight = (i) => {
			const options = [...list.children];
			options.forEach((o, n) => o.setAttribute('aria-selected', n === i ? 'true' : 'false'));
			active = i;
			if (options[i]) {
				input.setAttribute('aria-activedescendant', options[i].id);
				options[i].scrollIntoView({ block: 'nearest' });
			}
		};

		const render = () => {
			list.textContent = '';
			results.forEach((item, i) => {
				const li = document.createElement('li');
				li.id = `${list.id}-${i}`;
				li.role = 'option';
				li.className = 'hodima-rl-result';
				li.setAttribute('aria-selected', 'false');

				const thumb = document.createElement('span');
				thumb.className = 'hodima-rl-result__thumb';
				if (item.thumb) {
					const img = document.createElement('img');
					img.src = item.thumb;
					img.alt = '';
					thumb.append(img);
				}

				const body = document.createElement('span');
				body.className = 'hodima-rl-result__body';
				const title = document.createElement('strong');
				title.textContent = item.title || item.link;
				const meta = document.createElement('span');
				meta.className = 'hodima-rl-result__meta';
				meta.textContent = item.type;
				body.append(title, meta);

				if ((item.warnings ?? []).some((w) => w.hiding)) {
					li.classList.add('is-problem');
					const warn = document.createElement('span');
					warn.className = 'hodima-rl-result__warn';
					warn.textContent = item.warnings.filter((w) => w.hiding).map((w) => w.text).join('، ');
					body.append(warn);
				}

				li.append(thumb, body);
				li.addEventListener('mousedown', (e) => e.preventDefault());
				li.addEventListener('click', () => { close(); input.value = ''; setTarget(slot, item); });
				list.append(li);
			});

			list.hidden = results.length === 0;
			input.setAttribute('aria-expanded', results.length ? 'true' : 'false');
			status.textContent = results.length ? '' : (t.noResults ?? '');
		};

		const search = async () => {
			controller?.abort();
			controller = new AbortController();
			status.textContent = t.searching ?? '';

			const params = new URLSearchParams({
				action: 'hodima_rl_search',
				nonce: cfg.nonce ?? '',
				group: group.dataset.group,
				q: input.value.trim(),
				self_id: String(cfg.selfId ?? 0),
				self_ctx: cfg.selfCtx ?? 'post',
			});

			try {
				const res = await fetch(`${cfg.ajax}?${params}`, { credentials: 'same-origin', signal: controller.signal });
				const json = await res.json();
				results = json.success ? json.data : [];
				render();
			} catch (err) {
				if (err.name !== 'AbortError') status.textContent = t.error ?? '';
			}
		};

		input.addEventListener('input', () => {
			clearTimeout(timer);
			// نتیجه‌های جستجوی قبلی دیگر معتبر نیستند؛ Enter نباید آن‌ها را انتخاب کند
			results = [];
			list.textContent = '';
			close();
			timer = setTimeout(search, 250);
		});
		input.addEventListener('focus', () => { if (!results.length) search(); });
		input.addEventListener('blur', () => setTimeout(close, 150));

		input.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				if (list.hidden && results.length) { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
				const step = e.key === 'ArrowDown' ? 1 : -1;
				highlight((active + step + results.length) % Math.max(results.length, 1));
			} else if (e.key === 'Enter') {
				// Enter فرم ویرایش را ارسال نکند
				e.preventDefault();
				if (active >= 0 && results[active]) {
					const item = results[active];
					close();
					input.value = '';
					setTarget(slot, item);
				} else {
					clearTimeout(timer);
					search();
				}
			} else if (e.key === 'Escape') {
				close();
			}
		});
	};

	const setupSlot = (slot, group) => {
		setupSearch(slot, group);
		slot.querySelector('.hodima-rl-clear').addEventListener('click', () => clearTarget(slot));
	};

	/* ── راه‌اندازی ── */
	const init = () => {
		document.querySelectorAll('.hodima-rl-group').forEach((group) => {
			group.querySelectorAll(':scope > .hodima-rl-slots > .hodima-rl-slot').forEach((slot) => setupSlot(slot, group));
		});

		document.querySelectorAll('.hodima-rl-copy').forEach((button) => {
			button.addEventListener('click', async () => {
				try {
					await navigator.clipboard.writeText(button.dataset.copy);
					const label = button.textContent;
					button.textContent = t.copied ?? '';
					setTimeout(() => { button.textContent = label; }, 1500);
				} catch {
					/* کلیپ‌بورد در دسترس نیست (HTTP)؛ شورت‌کد کنار دکمه قابل انتخاب است */
				}
			});
		});
	};

	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
