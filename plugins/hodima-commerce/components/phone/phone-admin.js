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

	// ── انتخاب و کار گروهی (دکمه «اجرا» بیرون از فرم جدول و با ویژگی form وصل است) ──
	const apply = document.querySelector('[data-hd-bulk-apply]');
	const bulkSelect = document.getElementById('hd-leads-bulk');
	const form = document.getElementById('hd-leads-form');
	if (form) {
		const all = form.querySelector('[data-hd-select-all]');
		const rows = [...form.querySelectorAll('[data-hd-row]')];
		const applyText = apply?.textContent || 'اجرا';

		const sync = () => {
			const picked = rows.filter((row) => row.checked).length;
			if (apply) {
				apply.disabled = picked === 0;
				apply.textContent = picked > 0 ? `${applyText} (${new Intl.NumberFormat('fa-IR').format(picked)})` : applyText;
			}
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

	// ── تأیید حذف (بدون JS خود دکمه مستقیم می‌فرستد): انتقال به زباله‌دان و حذف همیشگی ──
	const dialog = document.getElementById('hd-leads-dialog');
	if (dialog instanceof HTMLDialogElement) {
		const titleEl = dialog.querySelector('[data-hd-dialog-title]');
		const textEl = dialog.querySelector('[data-hd-dialog-text]');
		const okEl = dialog.querySelector('[data-hd-dialog-ok]');
		const TEXTS = {
			trash: ['انتقال به زباله‌دان', 'شماره به زباله‌دان می‌رود و از آنجا می‌توانید برش گردانید. ادامه می‌دهید؟', 'بله، به زباله‌دان برود'],
			delete: ['حذف همیشگی', 'این کار قابل بازگشت نیست. ادامه می‌دهید؟', 'بله، حذف شود'],
		};
		let pending = null;

		root.addEventListener('click', (e) => {
			const target = e.target instanceof Element ? e.target.closest('[data-hd-confirm], [data-hd-bulk-apply]') : null;
			if (!target || target.dataset.hdConfirmed) return;
			// «اجرا» فقط برای کار «زباله‌دان» یا «حذف همیشگی» تأیید می‌خواهد
			const kind = target.hasAttribute('data-hd-bulk-apply') ? bulkSelect?.value : target.getAttribute('data-hd-confirm');
			if (!(kind in TEXTS)) return;
			e.preventDefault();
			pending = target;
			const [title, text, ok] = TEXTS[kind];
			if (titleEl) titleEl.textContent = title;
			if (textEl) textEl.textContent = text;
			if (okEl) okEl.textContent = ok;
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
