/*
 * دستار سئو — رفتار صفحه پیشخوان (core/advisor/advisor-admin.php). همه‌چیز بدون JS هم کار می‌کند:
 * تنظیمات تب خودش است (tab=settings)؛ پنجره کلید بدون JS دیده نمی‌شود ولی کلید با wp-config هم ثابت می‌شود.
 */
(() => {
	'use strict';

	/* ── کپی مسیر پوشه خزش (data-hdsa-copy = شناسه عنصر حاوی متن) ── */
	document.querySelectorAll('[data-hdsa-copy]').forEach((button) => {
		button.addEventListener('click', async () => {
			const source = document.getElementById(button.dataset.hdsaCopy || '');
			if (!source) return;
			const label = button.textContent;
			try {
				await navigator.clipboard.writeText(source.textContent.trim());
				button.textContent = 'کپی شد';
			} catch {
				window.getSelection()?.selectAllChildren(source); // مرورگر کپی نداد: متن انتخاب می‌شود تا دستی کپی شود
				button.textContent = 'انتخاب شد؛ Ctrl+C';
			}
			setTimeout(() => { button.textContent = label; }, 2000);
		});
	});

	/* ── منوی «⋯» ردیف پیشنهادها (details): با کلیک بیرون یا Esc بسته می‌شود؛ بدون JS هم باز و بسته می‌شود ── */
	document.addEventListener('click', (event) => {
		const inside = event.target instanceof Element ? event.target.closest('.hodima-advisor__menu') : null;
		document.querySelectorAll('.hodima-advisor__menu[open]').forEach((menu) => {
			if (menu !== inside) menu.removeAttribute('open');
		});
	});
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		document.querySelectorAll('.hodima-advisor__menu[open]').forEach((menu) => {
			menu.removeAttribute('open');
			menu.querySelector('summary')?.focus();
		});
	});

	/* ── «بررسی لینک‌ها» زیر هر گروه رقابت: HTML نتیجه (escape‌شده در سرور) از admin-ajax می‌آید ── */
	document.addEventListener('click', async (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-hdsa-links-run]') : null;
		const box = button?.closest('[data-hdsa-links]');
		const config = window.hodimaAdvisorLinks;
		if (!button || !box || !config) return;

		const out = box.querySelector('[data-hdsa-links-out]');
		const hint = box.querySelector('[data-hdsa-links-hint]');
		const idle = button.textContent;
		const body = new FormData();
		body.set('action', 'hodima_advisor_links');
		body.set('nonce', config.nonce);
		body.set('term', box.dataset.term || '');
		if (button.dataset.force) body.set('force', '1');

		button.disabled = true;
		button.textContent = 'در حال بررسی…';
		out.setAttribute('aria-busy', 'true');
		try {
			const response = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
			const data = await response.json();
			if (!data.success) throw new Error(data.data?.message || 'بررسی انجام نشد.');
			out.innerHTML = data.data.html; // خروجی سرور؛ همه مقدارها در PHP escape شده‌اند
			if (hint) hint.hidden = true;
			box.querySelector('[data-hdsa-links-run]:not([data-force])')?.setAttribute('hidden', '');
		} catch (error) {
			out.textContent = error instanceof Error && error.message ? error.message : 'بررسی انجام نشد؛ دوباره تلاش کنید.';
		} finally {
			out.removeAttribute('aria-busy');
			if (button.isConnected) {
				button.disabled = false;
				button.textContent = idle;
			}
		}
	});

	/* ── تأیید پیش از «برداشتن کلید» ── */
	document.querySelectorAll('form[data-hdsa-confirm]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (!window.confirm(form.dataset.hdsaConfirm || '')) event.preventDefault();
		});
	});

	/* ── پنجره افزودن/جایگزینی کلید حساب سرویس (همان رفتار ماژول دیسکاور) ── */
	const dialog = document.querySelector('[data-hdsa-key-dialog]');
	if (!dialog) return;

	const text = dialog.querySelector('[data-hdsa-key-text]');
	const file = dialog.querySelector('[data-hdsa-key-file]');
	const name = dialog.querySelector('[data-hdsa-key-name]');
	const preview = dialog.querySelector('[data-hdsa-key-preview]');
	const save = dialog.querySelector('[data-hdsa-key-save]');

	/** بررسی سریع در مرورگر تا مدیر پیش از ذخیره بداند فایل درست است (سرور دوباره بررسی می‌کند). */
	const check = () => {
		const raw = text.value.trim();
		preview.className = 'hds-dialog__preview';
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

	document.querySelector('[data-hdsa-key-open]')?.addEventListener('click', () => {
		text.value = '';
		file.value = '';
		name.textContent = '';
		check();
		if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
	});
	dialog.querySelector('[data-hdsa-key-cancel]')?.addEventListener('click', () => dialog.close?.());
	dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close?.(); }); // کلیک بیرون کادر
	dialog.querySelector('[data-hdsa-key-pick]')?.addEventListener('click', () => file.click());
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
})();

/* ── خزش همین حالا: وقتی دوری در جریان است صفحه گام‌به‌گام آن را پیش می‌برد و وضعیت را تازه می‌کند ── */
(() => {
	const box = document.querySelector('[data-hdsa-crawl-live]');
	if (!box) return;

	const text = box.querySelector('[data-hdsa-crawl-text]');
	const pill = box.querySelector('[data-hdsa-crawl-pill]');
	let failures = 0;

	const step = async () => {
		const body = new URLSearchParams({ action: 'hodima_advisor_crawler_step', _ajax_nonce: box.dataset.hdsaCrawlLive || '' });
		try {
			const response = await fetch(box.dataset.hdsaAjax || '', { method: 'POST', credentials: 'same-origin', body });
			const json = await response.json();
			if (!json?.success) throw new Error('step');
			failures = 0;
			if (text) text.textContent = json.data.text;
			if (pill) pill.textContent = json.data.label;
			if (json.data.finished) {
				location.reload(); // دور تمام شد: نمره و ایرادها تازه
				return;
			}
			if (!json.data.running) return; // مکث یا بیرون از ساعت: صفحه بیهوده نمی‌کوبد
			setTimeout(step, 1500);
		} catch {
			if (++failures < 3) setTimeout(step, 8000); // خطای گذرا: چند بار دوباره
		}
	};

	step();
})();
