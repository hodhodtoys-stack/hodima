/*
 * دستار سئو — رفتار صفحه پیشخوان (core/advisor/advisor-admin.php). همه‌چیز بدون JS هم کار می‌کند:
 * دکمه «تنظیمات» لینک `settings=1` است، پنجره کلید بدون JS دیده نمی‌شود ولی کلید با wp-config هم ثابت می‌شود.
 */
(() => {
	'use strict';

	/* ── پنل «تنظیمات»: باز و بسته با دکمه، بدون بارگذاری دوباره ── */
	const toggle = document.querySelector('[data-hdsa-settings-toggle]');
	const panel = document.getElementById('hodima-advisor-settings');
	if (toggle && panel) {
		document.querySelectorAll('[data-hdsa-settings-toggle]').forEach((button) => {
			button.addEventListener('click', (event) => {
				event.preventDefault();
				const willOpen = panel.hidden;
				panel.hidden = !willOpen;
				document.querySelectorAll('[data-hdsa-settings-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(willOpen)));
				if (willOpen) {
					panel.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
				}
			});
		});
	}

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

	/* ── تأیید پیش از «حذف کلید» ── */
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
