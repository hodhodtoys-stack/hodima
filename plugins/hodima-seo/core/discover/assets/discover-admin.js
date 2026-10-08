/**
 * ماژول «Google Discover» — کادر ویرایش نوشته، برگه، محصول و دسته محصول
 * Path: core/discover/assets/discover-admin.js
 *
 * جاوااسکریپت خالص:
 *   - عنوان: شمارنده زنده، بررسی طول و طعمه کلیک (همان فهرست و قاعده PHP)،
 *     پیش‌نمایش کارت Discover
 *   - تصویر: انتخاب از کتابخانه رسانه، کارت تصویر، بررسی عرض، پیش‌نمایش
 *   - موضوعات: برچسب‌ها (Enter یا ویرگول = افزودن، × یا Backspace = حذف)؛
 *     مقدار واقعی در textarea پنهان (یک موضوع در هر خط) برای ذخیره
 *   - امتیاز آمادگی و نوار پیشرفت با هر تغییر بالا
 */
(() => {
	'use strict';

	const config = window.hodimaDiscover || { minWidth: 1200, clickbait: [] };
	const nf = new Intl.NumberFormat('fa-IR', { useGrouping: false });
	const icons = { ok: 'dashicons-yes-alt', warn: 'dashicons-warning', error: 'dashicons-dismiss' };
	const norm = (v) => v.replaceAll('‌', ' '); // بدون نیم‌فاصله؛ همان قاعده PHP

	/** پیام وضعیت زیر یک فیلد. */
	function setStatus(field, text, level = 'ok') {
		const el = field?.querySelector('[data-hodima-dc-status]');
		if (!el) return;
		el.textContent = text;
		el.dataset.level = level;
		el.hidden = text === '';
	}

	/** امتیاز و نوار پیشرفت از روی ردیف‌های فعلی فهرست. */
	function updateScore(box) {
		const rows = [...box.querySelectorAll('[data-hodima-dc-check]')];
		const ok = rows.filter((r) => r.classList.contains('is-ok')).length;
		const level = ok === rows.length ? 'ok' : (ok >= rows.length * 0.6 ? 'warn' : 'error');
		const score = box.querySelector('[data-hodima-dc-score]');
		const bar = box.querySelector('[data-hodima-dc-bar]');
		if (score) {
			score.textContent = `${nf.format(ok)} از ${nf.format(rows.length)}`;
			score.className = `hodima-dc__score is-${level}`;
		}
		if (bar) {
			bar.className = `hodima-dc__bar is-${level}`;
			bar.setAttribute('aria-valuenow', String(ok));
			bar.firstElementChild.style.inlineSize = `${rows.length ? Math.round((100 * ok) / rows.length) : 0}%`;
		}
	}

	/** یک ردیف فهرست بررسی. */
	function setCheck(box, key, level, text) {
		const row = box.querySelector(`[data-hodima-dc-check="${key}"]`);
		if (!row) return;
		row.className = `is-${level}`;
		row.querySelector('.dashicons').className = `dashicons ${icons[level]}`;
		row.querySelector('[data-hodima-dc-check-text]').textContent = text;
		updateScore(box);
	}

	/* ── عنوان ── */
	function checkTitle(box, input) {
		const title = input.value.trim() || input.dataset.hodimaDcFallback || '';
		const len = [...title].length;
		let level = 'ok';
		let text = `${nf.format(len)} کاراکتر.`;
		if (config.clickbait.some((p) => norm(title).includes(norm(p)))) [level, text] = ['warn', 'عبارت اغراق‌آمیز یا طعمه کلیک دارد؛ گوگل در Discover آن را جریمه می‌کند.'];
		else if (len < 30) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید (۴۰ تا ۱۰۰ کاراکتر).`];
		else if (len > 110) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ بیشتر از ۱۱۰ کوتاه می‌شود.`];
		setCheck(box, 'title', level, text);

		const counter = box.querySelector('[data-hodima-dc-counter]');
		if (counter) {
			counter.textContent = `${nf.format(len)} / ۱۱۰`;
			counter.classList.toggle('is-over', len > 110);
		}
		const card = box.querySelector('[data-hodima-dc-card-title]');
		if (card) card.textContent = title;
	}

	/* ── تصویر ── */
	function showImage(box, url) {
		const card = box.querySelector('[data-hodima-dc-image-card]');
		const img = box.querySelector('[data-hodima-dc-image-preview]');
		const empty = box.querySelector('[data-hodima-dc-image-empty]');
		const pick = box.querySelector('[data-hodima-dc-pick]');
		const clear = box.querySelector('[data-hodima-dc-clear]');
		const has = url !== '';
		if (has) img.src = url; else img.removeAttribute('src');
		img.hidden = !has;
		empty.hidden = has;
		card.classList.toggle('has-image', has);
		pick.textContent = has ? 'تغییر تصویر' : 'انتخاب تصویر';
		clear.hidden = !has;

		// پیش‌نمایش گوشی: تصویر انتخابی، وگرنه تصویر شاخص/محصول/دسته
		const phone = box.querySelector('[data-hodima-dc-card-img]');
		const noimg = box.querySelector('[data-hodima-dc-card-noimg]');
		const shown = url || box.querySelector('[data-hodima-dc-default-img]')?.dataset.hodimaDcDefaultImg || '';
		if (phone) {
			if (shown) phone.src = shown; else phone.removeAttribute('src');
			phone.hidden = !shown;
			if (noimg) noimg.hidden = Boolean(shown);
		}
	}

	function checkImage(box, field, attachment) {
		const w = Number(attachment.width) || 0;
		const ok = w >= config.minWidth;
		const text = ok
			? `${nf.format(w)}×${nf.format(Number(attachment.height) || 0)} پیکسل؛ مناسب. برش‌ها بعد از ذخیره ساخته می‌شوند.`
			: `عرض ${nf.format(w)} پیکسل است؛ برای کارت بزرگ Discover حداقل ${nf.format(config.minWidth)} لازم است.`;
		setStatus(field, text, ok ? 'ok' : 'error');
		setCheck(box, 'image', ok ? 'ok' : 'error', text);
	}

	function openPicker(box, field) {
		if (!window.wp?.media) return;
		const frame = wp.media({ title: 'انتخاب تصویر Discover', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });

		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			field.querySelector('[data-hodima-dc-image-id]').value = a.id;
			showImage(box, a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url);
			checkImage(box, field, a);
		});

		frame.open();
	}

	function clearPicker(box, field) {
		field.querySelector('[data-hodima-dc-image-id]').value = '';
		showImage(box, '');
		setStatus(field, 'تصویر پیش‌فرض صفحه استفاده می‌شود (بعد از ذخیره دوباره بررسی می‌شود).', 'ok');
	}

	/* ── موضوعات (برچسب‌ها) ── */
	function syncTopics(box) {
		const values = [...box.querySelectorAll('.hodima-dc__chip')].map((c) => c.dataset.value);
		box.querySelector('[data-hodima-dc-entities]').value = values.join('\n');
	}

	function addTopic(box, raw) {
		const value = raw.replace(/\s+/g, ' ').trim();
		if (!value) return;
		const m = value.match(/^(.*?)\s+(https?:\/\/\S+|Q\d{1,12})$/u);
		const name = (m ? m[1] : value).trim();
		const chips = box.querySelector('[data-hodima-dc-chips]');
		const key = norm(name).toLowerCase();
		if (!name || [...chips.querySelectorAll('.hodima-dc__chip')].some((c) => norm(c.firstElementChild.textContent).toLowerCase() === key)) return;

		const chip = document.createElement('span');
		chip.className = 'hodima-dc__chip';
		chip.dataset.value = value;
		const label = document.createElement('span');
		label.textContent = name;
		chip.append(label);
		if (m) {
			const link = document.createElement('span');
			link.className = 'hodima-dc__chip-link';
			link.title = m[2];
			link.textContent = 'ویکی';
			chip.append(link);
		}
		const x = document.createElement('button');
		x.type = 'button';
		x.className = 'hodima-dc__chip-x';
		x.dataset.hodimaDcChipRemove = '';
		x.setAttribute('aria-label', `حذف ${name}`);
		x.textContent = '×';
		chip.append(x);
		chips.insertBefore(chip, chips.querySelector('[data-hodima-dc-topic-input]'));
		syncTopics(box);
	}

	/* ── رویدادها ── */
	document.addEventListener('click', (e) => {
		const box = e.target.closest?.('[data-hodima-dc]');
		if (!box) return;
		const button = e.target.closest('button');

		if (button?.matches('[data-hodima-dc-chip-remove]')) {
			e.preventDefault();
			button.closest('.hodima-dc__chip')?.remove();
			syncTopics(box);
			box.querySelector('[data-hodima-dc-topic-input]')?.focus();
			return;
		}

		const field = button?.closest('[data-hodima-dc-image]');
		if (field && button.matches('[data-hodima-dc-pick]')) { e.preventDefault(); openPicker(box, field); }
		else if (field && button.matches('[data-hodima-dc-clear]')) { e.preventDefault(); clearPicker(box, field); }

		// کلیک روی خالی کادر برچسب‌ها = تمرکز روی ورودی
		if (e.target.matches?.('[data-hodima-dc-chips]')) e.target.querySelector('input')?.focus();
	});

	document.addEventListener('input', (e) => {
		const box = e.target.closest?.('[data-hodima-dc]');
		if (box && e.target.matches('[data-hodima-dc-title]')) checkTitle(box, e.target);
	});

	document.addEventListener('keydown', (e) => {
		const input = e.target;
		if (!input.matches?.('[data-hodima-dc-topic-input]')) return;
		const box = input.closest('[data-hodima-dc]');

		// Enter یا ویرگول (لاتین/فارسی) = افزودن؛ Enter نباید فرم را ارسال کند
		if (e.key === 'Enter' || e.key === ',' || e.key === '،') {
			e.preventDefault();
			addTopic(box, input.value);
			input.value = '';
		} else if (e.key === 'Backspace' && input.value === '') {
			const last = [...box.querySelectorAll('.hodima-dc__chip')].pop();
			if (last) { last.remove(); syncTopics(box); }
		}
	});

	// متن تایپ‌شده‌ای که Enter نخورده، هنگام ترک فیلد یا ارسال فرم از دست نرود
	document.addEventListener('focusout', (e) => {
		const input = e.target;
		if (!input.matches?.('[data-hodima-dc-topic-input]') || input.value.trim() === '') return;
		addTopic(input.closest('[data-hodima-dc]'), input.value);
		input.value = '';
	});
})();
