/**
 * ماژول «گوگل دیسکاور» — صفحه گزارش (ابزارهای هدیما ← گوگل دیسکاور)
 * Path: core/discover/assets/discover-report.js
 *
 * جاوااسکریپت خالص:
 *   - کار دسته‌ای با نوار پیشرفت: [data-hodima-dr-batch="crops|warm"]؛ هر
 *     درخواست admin-ajax چند صفحه را انجام می‌دهد و «next» جای ادامه است.
 *     data-autostart = شروع خودکار؛ data-reload = تازه کردن صفحه در پایان.
 *   - نمودار روزانه: خط عمودی، نقطه و راهنمای عدد و تاریخ زیر نشانگر/لمس،
 *     و با کلیدهای چپ/راست وقتی نمودار فوکوس دارد.
 */
(() => {
	'use strict';

	const config = window.hodimaDiscoverReport || {};
	const nf = new Intl.NumberFormat('fa-IR');

	/* ── کار دسته‌ای ── */
	async function runBatch(box) {
		const action = `hodima_discover_${box.dataset.hodimaDrBatch}`;
		const bar = box.querySelector('.hodima-dr-progress');
		const msg = box.querySelector('[data-hodima-dr-msg]');
		const count = box.querySelector('[data-hodima-dr-count]');
		const button = box.querySelector('[data-hodima-dr-start]');
		let next = 0;
		let made = 0;

		if (button) button.disabled = true;
		if (bar) bar.hidden = false;
		if (msg) { msg.textContent = 'در حال انجام…'; msg.dataset.level = ''; }

		try {
			for (;;) {
				const body = new URLSearchParams({ action, nonce: config.nonce || '', offset: String(next) });
				const res = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
				const json = await res.json().catch(() => null);
				if (!res.ok || !json?.success) throw new Error(json?.data?.message || `خطای سرور (${res.status})`);

				const { done, total, end } = json.data;
				next = json.data.next;
				made += json.data.made || 0;
				const pct = total ? Math.round((100 * done) / total) : 100;
				if (bar) {
					bar.firstElementChild.style.inlineSize = `${pct}%`;
					bar.setAttribute('aria-valuenow', String(bar.getAttribute('aria-valuemax') === '100' ? pct : done));
				}
				if (count) count.textContent = `${nf.format(done)} از ${nf.format(total)}`;
				if (msg) msg.textContent = `${nf.format(done)} از ${nf.format(total)} صفحه…`;
				if (end) break;
			}

			if (box.hasAttribute('data-reload')) { window.location.reload(); return; }
			if (msg) msg.textContent = made ? `تمام شد؛ ${nf.format(made)} برش تازه ساخته شد.` : 'تمام شد؛ همه صفحه‌ها برش‌هایشان را داشتند (یا تصویرشان کوچک‌تر از ۱۲۰۰ پیکسل است).';
		} catch (error) {
			if (msg) { msg.textContent = `متوقف شد: ${error.message}`; msg.dataset.level = 'error'; }
		} finally {
			if (button) button.disabled = false;
		}
	}

	document.addEventListener('click', (e) => {
		const button = e.target.closest?.('[data-hodima-dr-start]');
		const box = button?.closest('[data-hodima-dr-batch]');
		if (box) { e.preventDefault(); runBatch(box); }
	});

	document.querySelectorAll('[data-hodima-dr-batch][data-autostart]').forEach((box) => runBatch(box));

	/* ── کلید حساب سرویس: پنجره افزودن/جایگزینی (همان رفتار ماژول Google Indexing) ── */
	const keyDialog = document.querySelector('[data-hodima-dr-key-dialog]');
	if (keyDialog) {
		const text = keyDialog.querySelector('[data-hodima-dr-key-text]');
		const file = keyDialog.querySelector('[data-hodima-dr-key-file]');
		const name = keyDialog.querySelector('[data-hodima-dr-key-name]');
		const preview = keyDialog.querySelector('[data-hodima-dr-key-preview]');
		const save = keyDialog.querySelector('[data-hodima-dr-key-save]');

		/** بررسی سریع در مرورگر تا مدیر پیش از ذخیره بداند فایل درست است (سرور دوباره بررسی می‌کند). */
		const check = () => {
			const raw = text.value.trim();
			preview.className = 'hodima-dr-key-dialog__preview';
			save.disabled = true;
			if (!raw) { preview.textContent = ''; return; }
			let data = null;
			try { data = JSON.parse(raw); } catch { /* متن JSON نیست */ }
			if (!data || typeof data !== 'object') {
				preview.textContent = 'این متن JSON معتبر نیست.';
				preview.classList.add('is-error');
				return;
			}
			if (data.type !== 'service_account' || !data.client_email || !data.private_key) {
				preview.textContent = 'این فایل کلید Service Account نیست (باید type برابر service_account و client_email و private_key داشته باشد).';
				preview.classList.add('is-error');
				return;
			}
			preview.textContent = `حساب: ${data.client_email}`;
			preview.classList.add('is-ok');
			save.disabled = false;
		};

		document.querySelector('[data-hodima-dr-key-open]')?.addEventListener('click', () => {
			text.value = '';
			file.value = '';
			name.textContent = '';
			check();
			if (typeof keyDialog.showModal === 'function') keyDialog.showModal(); else keyDialog.setAttribute('open', '');
		});
		keyDialog.querySelector('[data-hodima-dr-key-cancel]')?.addEventListener('click', () => keyDialog.close?.());
		keyDialog.addEventListener('click', (e) => { if (e.target === keyDialog) keyDialog.close?.(); }); // کلیک بیرون کادر
		keyDialog.querySelector('[data-hodima-dr-key-pick]')?.addEventListener('click', () => file.click());
		text.addEventListener('input', check);
		file.addEventListener('change', () => {
			const picked = file.files?.[0];
			if (!picked) return;
			const reader = new FileReader();
			reader.onload = () => {
				text.value = String(reader.result || '');
				name.textContent = picked.name;
				check();
			};
			reader.readAsText(picked);
		});
	}

	/* ── «ویرایش سریع» یک ردیف گزارش (SEO 2.1.8) ── */
	const quick = document.querySelector('[data-hodima-dr-quick-dialog]');
	if (quick) {
		const field = (name) => quick.querySelector(`[data-hodima-dr-quick-${name}]`);
		const nf = new Intl.NumberFormat('fa-IR');
		let row = null;
		let imageId = '';

		const paintImage = (url, own) => {
			const img = field('img');
			img.hidden = !url;
			if (url) img.src = url; else img.removeAttribute('src');
			field('source').textContent = own ? 'تصویر جدای دیسکاور' : (url ? 'تصویر پیش‌فرض صفحه' : 'تصویری نیست');
			field('clear').hidden = !own;
		};
		const count = () => {
			const len = [...field('title').value.trim()].length;
			field('count').textContent = len ? `${nf.format(len)} کاراکتر (بهتر است ۳۰ تا ۱۱۰).` : '';
		};

		document.addEventListener('click', (e) => {
			const open = e.target.closest?.('[data-hodima-dr-quick]');
			if (!open) return;
			row = open.closest('[data-hodima-dr-row]');
			if (!row) return;
			const d = row.dataset;
			imageId = d.imageId || '';
			field('page').textContent = d.fallback || '';
			field('title').value = d.title || '';
			field('title').placeholder = d.fallback || '';
			field('skip').checked = d.skip === '1';
			field('msg').textContent = '';
			field('msg').className = 'hodima-dr-key-dialog__preview';
			paintImage(imageId ? d.imageUrl : d.defaultUrl, Boolean(imageId));
			count();
			if (typeof quick.showModal === 'function') quick.showModal(); else quick.setAttribute('open', '');
			field('title').focus();
		});

		field('title').addEventListener('input', count);
		field('cancel').addEventListener('click', () => quick.close?.());
		quick.addEventListener('click', (e) => { if (e.target === quick) quick.close?.(); });
		field('clear').addEventListener('click', () => { imageId = ''; paintImage(row?.dataset.defaultUrl || '', false); });
		field('pick').addEventListener('click', () => {
			if (!window.wp?.media) return;
			const frame = wp.media({ title: 'انتخاب تصویر کارت دیسکاور', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });
			frame.on('select', () => {
				const a = frame.state().get('selection').first().toJSON();
				imageId = String(a.id);
				paintImage(a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url, true);
			});
			frame.open();
		});

		field('save').addEventListener('click', async () => {
			if (!row) return;
			const save = field('save');
			const msg = field('msg');
			save.disabled = true;
			msg.className = 'hodima-dr-key-dialog__preview';
			msg.textContent = 'در حال ذخیره…';
			try {
				const body = new URLSearchParams({
					action: 'hodima_discover_quick', context: row.dataset.context, id: row.dataset.id, nonce: row.dataset.nonce,
					title: field('title').value, image_id: imageId, skip: field('skip').checked ? '1' : '',
				});
				const res = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
				const json = await res.json().catch(() => null);
				if (!json?.success) throw new Error(json?.data?.message || 'ذخیره نشد.');
				if (json.data.html) {
					const tpl = document.createElement('template');
					tpl.innerHTML = json.data.html.trim();
					const fresh = tpl.content.querySelector('tr');
					if (fresh) { row.replaceWith(fresh); row = fresh; }
				} else {
					row.remove();
					row = null;
				}
				msg.classList.add('is-ok');
				msg.textContent = json.data.message;
				setTimeout(() => quick.close?.(), 700);
			} catch (err) {
				msg.classList.add('is-error');
				msg.textContent = err.message || 'ذخیره نشد.';
			} finally {
				save.disabled = false;
			}
		});
	}

	// دکمه‌هایی که پیش از ارسال می‌پرسند (بازگرداندن پیش‌فرض تنظیمات)
	document.querySelectorAll('[data-hodima-dr-confirm-button]').forEach((button) => {
		button.addEventListener('click', (e) => {
			if (!window.confirm(button.dataset.hodimaDrConfirmButton)) e.preventDefault();
		});
	});

	// حذف کلید: پرسش پیش از ارسال
	document.querySelectorAll('form[data-hodima-dr-confirm]').forEach((form) => {
		form.addEventListener('submit', (e) => {
			if (!window.confirm(form.dataset.hodimaDrConfirm)) e.preventDefault();
		});
	});

	/* ── نمودار ── */
	function initChart(fig) {
		const values = JSON.parse(fig.dataset.values || '[]');
		const days = JSON.parse(fig.dataset.days || '[]');
		const marks = JSON.parse(fig.dataset.marks || '[]'); // متن تغییر کارت هر روز (یا '')
		const max = Number(fig.dataset.max) || 1;
		const plot = fig.querySelector('.hodima-dr-chart__plot');
		const cross = plot.querySelector('.hodima-dr-chart__cross');
		const dot = plot.querySelector('.hodima-dr-chart__dot');
		const tip = plot.querySelector('.hodima-dr-chart__tip');
		const unit = fig.querySelector('.hodima-dr-chart__title')?.firstChild?.textContent.trim() || '';
		if (values.length < 2) return;

		let current = values.length - 1;

		function show(i) {
			current = Math.max(0, Math.min(values.length - 1, i));
			const x = (100 * current) / (values.length - 1);
			const y = 100 * (1 - values[current] / max);
			// محور زمان همیشه چپ‌به‌راست است (dir=ltr)؛ پس جای فیزیکی
			cross.style.left = `${x}%`;
			dot.style.left = `${x}%`;
			dot.style.top = `${y}%`;
			tip.replaceChildren();
			const strong = document.createElement('strong');
			strong.textContent = `${nf.format(values[current])} ${unit}`;
			tip.append(strong, days[current] || '');
			if (marks[current]) {
				const note = document.createElement('span');
				note.className = 'hodima-dr-chart__tip-mark';
				note.textContent = marks[current];
				tip.append(note);
			}
			// راهنما سمت مخالف نشانگر تا زیر انگشت/ماوس نرود
			tip.style.left = x > 55 ? 'auto' : `calc(${x}% + 0.75rem)`;
			tip.style.right = x > 55 ? `calc(${100 - x}% + 0.75rem)` : 'auto';
			cross.hidden = dot.hidden = tip.hidden = false;
		}

		function hide() { cross.hidden = dot.hidden = tip.hidden = true; }

		function fromEvent(e) {
			const rect = plot.getBoundingClientRect();
			show(Math.round(((e.clientX - rect.left) / rect.width) * (values.length - 1)));
		}

		plot.tabIndex = 0;
		plot.addEventListener('pointermove', fromEvent);
		plot.addEventListener('pointerdown', fromEvent);
		plot.addEventListener('pointerleave', hide);
		plot.addEventListener('focus', () => show(current));
		plot.addEventListener('blur', hide);
		plot.addEventListener('keydown', (e) => {
			// محور زمان چپ‌به‌راست است (dir=ltr)
			if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); }
			else if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); }
			else if (e.key === 'Home') { e.preventDefault(); show(0); }
			else if (e.key === 'End') { e.preventDefault(); show(values.length - 1); }
		});
	}

	document.querySelectorAll('[data-hodima-dr-chart]').forEach(initChart);
})();
