/*
 * لینک‌های مرتبط دستی — کادر ویرایشگر
 * Path: plugins/hodima-seo/inc/manual_related_link/assets/admin.js
 *
 * جستجوی زنده مقصد (کیبوردی: بالا/پایین/Enter/Escape)، چسباندن آدرس،
 * تصویر دلخواه از کتابخانه رسانه، جابه‌جایی خانه‌ها (کشیدن یا دکمه) و
 * افزودن خانه ذخیره. JavaScript خالص، بدون jQuery.
 */
(() => {
	'use strict';

	const cfg = window.hodimaRL ?? {};
	const t = cfg.i18n ?? {};
	const faNum = (n) => Number(n).toLocaleString('fa-IR');

	const field = (slot, name) => slot.querySelector(`[data-field="${name}"]`);

	/* ── شماره‌گذاری دوباره بعد از جابه‌جایی/افزودن ── */
	const renumber = (group) => {
		const count = Number(group.dataset.count ?? 1);
		const max = Number(group.dataset.max ?? 6);
		const slots = [...group.querySelectorAll(':scope > .hodima-rl-slots > .hodima-rl-slot')];

		slots.forEach((slot, i) => {
			slot.querySelectorAll('[name]').forEach((el) => {
				el.name = el.name.replace(/\[(products|article)\]\[\d+\]/, `[$1][${i}]`);
			});
			slot.querySelector('.hodima-rl-slot__num').textContent = faNum(i + 1);
			const reserve = i >= count;
			slot.classList.toggle('is-reserve', reserve);
			const badge = slot.querySelector('.hodima-rl-slot__badge');
			badge.textContent = reserve ? (t.reserve ?? 'ذخیره') : (t.shown ?? 'نمایش');
		});

		const add = group.querySelector('.hodima-rl-add');
		if (add) add.hidden = slots.length >= max;
	};

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
			slot.querySelector('.hodima-rl-more').open = true;
			field(slot, 'title').focus();
		} else {
			target.querySelector('.hodima-rl-clear').focus();
		}
	};

	const clearTarget = (slot) => {
		// عنوان و تصویر دلخواه هم پاک می‌شوند تا روی مقصد بعدی نمانند
		['kind', 'id', 'url', 'title', 'img_id'].forEach((name) => { field(slot, name).value = ''; });
		slot.querySelector('.hodima-rl-image__preview').textContent = '';
		slot.querySelector('.hodima-rl-remove-image').hidden = true;
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

	/* ── تصویر دلخواه ── */
	const setupImage = (slot) => {
		const pick = slot.querySelector('.hodima-rl-pick-image');
		const remove = slot.querySelector('.hodima-rl-remove-image');
		const preview = slot.querySelector('.hodima-rl-image__preview');
		const input = field(slot, 'img_id');
		let frame = null;

		if (typeof window.wp?.media !== 'function') {
			pick.disabled = true;
			return;
		}

		pick.addEventListener('click', () => {
			frame ??= window.wp.media({
				title: t.mediaTitle,
				button: { text: t.mediaButton },
				library: { type: 'image' },
				multiple: false,
			});
			frame.off('select').on('select', () => {
				const attachment = frame.state().get('selection').first()?.toJSON();
				if (!attachment) return;
				input.value = String(attachment.id);
				const img = document.createElement('img');
				img.src = attachment.sizes?.thumbnail?.url ?? attachment.url;
				img.alt = '';
				preview.replaceChildren(img);
				remove.hidden = false;
				markUnsaved(slot);
			});
			frame.open();
		});

		remove.addEventListener('click', () => {
			input.value = '';
			preview.textContent = '';
			remove.hidden = true;
			markUnsaved(slot);
		});
	};

	/* ── جابه‌جایی ── */
	const move = (slot, dir) => {
		const sibling = dir < 0 ? slot.previousElementSibling : slot.nextElementSibling;
		if (!sibling) return;
		dir < 0 ? sibling.before(slot) : sibling.after(slot);
		renumber(slot.closest('.hodima-rl-group'));
		slot.querySelector(dir < 0 ? '.hodima-rl-up' : '.hodima-rl-down').focus();
	};

	let dragged = null;

	const setupSlot = (slot, group) => {
		setupSearch(slot, group);
		setupImage(slot);

		slot.querySelector('.hodima-rl-clear').addEventListener('click', () => clearTarget(slot));
		slot.querySelector('.hodima-rl-up').addEventListener('click', () => move(slot, -1));
		slot.querySelector('.hodima-rl-down').addEventListener('click', () => move(slot, 1));

		// کشیدن فقط از دستگیره، تا انتخاب متن داخل فیلدها خراب نشود
		const handle = slot.querySelector('.hodima-rl-slot__handle');
		handle.addEventListener('pointerdown', () => { slot.draggable = true; });
		slot.addEventListener('dragstart', (e) => {
			dragged = slot;
			slot.classList.add('is-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});
		slot.addEventListener('dragend', () => {
			slot.draggable = false;
			slot.classList.remove('is-dragging');
			dragged = null;
			renumber(group);
		});
		slot.addEventListener('dragover', (e) => {
			if (!dragged || dragged === slot || dragged.parentElement !== slot.parentElement) return;
			e.preventDefault();
			const rect = slot.getBoundingClientRect();
			e.clientY < rect.top + rect.height / 2 ? slot.before(dragged) : slot.after(dragged);
		});
	};

	/* ── راه‌اندازی ── */
	const init = () => {
		document.querySelectorAll('.hodima-rl-group').forEach((group) => {
			group.querySelectorAll(':scope > .hodima-rl-slots > .hodima-rl-slot').forEach((slot) => setupSlot(slot, group));

			const template = group.querySelector('.hodima-rl-template');
			group.querySelector('.hodima-rl-add')?.addEventListener('click', () => {
				const slot = template.content.firstElementChild.cloneNode(true);
				// شناسه‌های یکتا برای label/aria-controls خانه تازه
				const suffix = `-n${Date.now().toString(36)}`;
				slot.querySelectorAll('[id]').forEach((el) => { el.id += suffix; });
				slot.querySelectorAll('[for]').forEach((el) => { el.htmlFor += suffix; });
				slot.querySelectorAll('[aria-controls]').forEach((el) => el.setAttribute('aria-controls', el.getAttribute('aria-controls') + suffix));
				group.querySelector('.hodima-rl-slots').append(slot);
				setupSlot(slot, group);
				renumber(group);
				slot.querySelector('.hodima-rl-search').focus();
			});
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
