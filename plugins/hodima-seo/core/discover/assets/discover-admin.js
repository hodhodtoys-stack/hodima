/**
 * ماژول «Google Discover» — کادر ویرایش نوشته و برگه
 * Path: core/discover/assets/discover-admin.js
 *
 * جاوااسکریپت خالص:
 *   - بررسی زنده عنوان Discover (طول، عبارت طعمه کلیک — همان فهرست PHP)
 *   - انتخاب تصویر Discover از کتابخانه رسانه و بررسی عرض آن
 */
(() => {
	'use strict';

	const config = window.hodimaDiscover || { minWidth: 1200, clickbait: [] };
	const nf = new Intl.NumberFormat('fa-IR', { useGrouping: false });
	const icons = { ok: 'dashicons-yes-alt', warn: 'dashicons-warning', error: 'dashicons-dismiss' };

	/** پیام وضعیت زیر یک فیلد. */
	function setStatus(field, text, level = 'ok') {
		const el = field?.querySelector('[data-hodima-dc-status]');
		if (!el) return;
		el.textContent = text;
		el.dataset.level = level;
		el.hidden = text === '';
	}

	/** یک ردیف فهرست بررسی. */
	function setCheck(key, level, text) {
		const row = document.querySelector(`[data-hodima-dc-check="${key}"]`);
		if (!row) return;
		row.className = `is-${level}`;
		row.querySelector('.dashicons').className = `dashicons ${icons[level]}`;
		row.querySelector('[data-hodima-dc-check-text]').textContent = text;
	}

	function checkTitle(input) {
		const title = input.value.trim() || input.dataset.hodimaDcFallback || '';
		const len = [...title].length;
		let level = 'ok';
		let text = `${nf.format(len)} کاراکتر.`;
		if (config.clickbait.some((p) => title.includes(p))) [level, text] = ['warn', 'عبارت اغراق‌آمیز یا طعمه کلیک دارد؛ گوگل در Discover آن را جریمه می‌کند.'];
		else if (len < 30) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید (۴۰ تا ۱۰۰ کاراکتر).`];
		else if (len > 110) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ بیشتر از ۱۱۰ کوتاه می‌شود.`];
		setCheck('title', level, text);
		setStatus(input.closest('.hodima-dc__field'), input.value.trim() === '' ? '' : text, level);
	}

	function checkImage(field, attachment) {
		if (!attachment) return setStatus(field, '');
		const w = Number(attachment.width) || 0;
		const ok = w >= config.minWidth;
		const text = ok
			? `${nf.format(w)}×${nf.format(Number(attachment.height) || 0)} پیکسل؛ مناسب. برش‌ها بعد از ذخیره ساخته می‌شوند.`
			: `عرض ${nf.format(w)} پیکسل است؛ برای کارت بزرگ Discover حداقل ${nf.format(config.minWidth)} لازم است.`;
		setStatus(field, text, ok ? 'ok' : 'error');
		setCheck('image', ok ? 'ok' : 'error', text);
	}

	function openPicker(field) {
		if (!window.wp?.media) return;
		const frame = wp.media({ title: 'انتخاب تصویر Discover', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });

		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			field.querySelector('[data-hodima-dc-image-id]').value = a.id;
			const preview = field.querySelector('[data-hodima-dc-preview]');
			preview.src = a.sizes?.medium?.url || a.url;
			preview.hidden = false;
			field.querySelector('[data-hodima-dc-clear]')?.removeAttribute('hidden');
			checkImage(field, a);
		});

		frame.open();
	}

	function clearPicker(field) {
		field.querySelector('[data-hodima-dc-image-id]').value = '';
		const preview = field.querySelector('[data-hodima-dc-preview]');
		preview.hidden = true;
		preview.removeAttribute('src');
		field.querySelector('[data-hodima-dc-clear]')?.setAttribute('hidden', '');
		setStatus(field, 'تصویر شاخص استفاده می‌شود (بعد از ذخیره دوباره بررسی می‌شود).', 'ok');
	}

	document.addEventListener('click', (e) => {
		const button = e.target.closest?.('[data-hodima-dc] button');
		const field = button?.closest('[data-hodima-dc-image]');
		if (!field) return;
		if (button.matches('[data-hodima-dc-pick]')) { e.preventDefault(); openPicker(field); }
		else if (button.matches('[data-hodima-dc-clear]')) { e.preventDefault(); clearPicker(field); }
	});

	document.addEventListener('input', (e) => {
		if (e.target.matches?.('[data-hodima-dc-title]')) checkTitle(e.target);
	});
})();
