/**
 * صفحه «شماره‌های تماس» پیشخوان — رفتار اضافه (بدون آن همه‌چیز با فرم معمولی کار می‌کند):
 * کپی شماره، انتخاب همه و فعال شدن «اجرا» فقط با انتخاب، پنجره تأیید حذف همیشگی.
 */
(() => {
	'use strict';

	const root = document.querySelector('.hd-leads');
	if (!root) return;

	const live = document.getElementById('hd-leads-live');
	const announce = (text) => {
		if (live) live.textContent = text;
	};

	// ── کپی شماره ──
	const copyText = async (text) => {
		if (navigator.clipboard?.writeText) {
			await navigator.clipboard.writeText(text);
			return;
		}
		// مرورگر یا صفحه ناامن (بدون HTTPS): راه قدیمی
		const area = document.createElement('textarea');
		area.value = text;
		area.setAttribute('readonly', '');
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.append(area);
		area.select();
		const ok = document.execCommand('copy');
		area.remove();
		if (!ok) throw new Error('copy failed');
	};

	root.addEventListener('click', async (e) => {
		const button = e.target instanceof Element ? e.target.closest('[data-hd-copy]') : null;
		if (!button) return;

		const text = button.getAttribute('data-hd-copy') || '';
		const label = button.querySelector('.hd-leads__btn-text');
		const before = label?.textContent || '';
		try {
			await copyText(text);
			announce(`شماره ${text} کپی شد.`);
			if (label) label.textContent = 'کپی شد';
		} catch {
			announce('کپی نشد؛ شماره را دستی انتخاب کنید.');
			if (label) label.textContent = 'کپی نشد';
		}
		setTimeout(() => {
			if (label) label.textContent = before;
		}, 1800);
	});

	// ── انتخاب و کار گروهی ──
	const form = document.getElementById('hd-leads-form');
	if (form) {
		const all = form.querySelector('[data-hd-select-all]');
		const rows = [...form.querySelectorAll('[data-hd-row]')];
		const apply = form.querySelector('[data-hd-bulk-apply]');
		const counter = form.querySelector('[data-hd-selected]');

		const sync = () => {
			const picked = rows.filter((row) => row.checked).length;
			if (apply) apply.disabled = picked === 0;
			if (counter) counter.textContent = picked > 0 ? `${new Intl.NumberFormat('fa-IR').format(picked)} شماره انتخاب شده` : '';
			if (all) {
				all.checked = picked > 0 && picked === rows.length;
				all.indeterminate = picked > 0 && picked < rows.length;
			}
		};

		all?.addEventListener('change', () => {
			rows.forEach((row) => {
				row.checked = all.checked;
			});
			sync();
		});
		rows.forEach((row) => row.addEventListener('change', sync));
		sync();
	}

	// ── تأیید حذف همیشگی (بدون JS خود دکمه مستقیم می‌فرستد) ──
	const dialog = document.getElementById('hd-leads-dialog');
	if (dialog instanceof HTMLDialogElement) {
		let pending = null;

		root.addEventListener('click', (e) => {
			const button = e.target instanceof Element ? e.target.closest('[data-hd-confirm]') : null;
			if (!button || button.dataset.hdConfirmed) return;
			e.preventDefault();
			pending = button;
			dialog.showModal();
		});

		dialog.addEventListener('close', () => {
			const button = pending;
			pending = null;
			if (dialog.returnValue === 'ok' && button instanceof HTMLButtonElement && button.form) {
				button.dataset.hdConfirmed = '1';
				button.form.requestSubmit(button);
			} else if (button) {
				button.focus();
			}
			dialog.returnValue = '';
		});
	}
})();
