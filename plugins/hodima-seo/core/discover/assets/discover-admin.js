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
 *   - پیشنهاد عنوان: کلیک = نشستن در فیلد عنوان
 *   - هماهنگی زنده با ویرایشگر (SEO 2.1.2): تغییر تصویر شاخص، عنوان و چکیده در
 *     ویرایشگر بلوکی (wp.data) یا کلاسیک/محصول (فیلدهای فرم) همان لحظه
 *     پیش‌نمایش و ردیف‌های تصویر، برش، عنوان و خلاصه را به‌روز می‌کند
 *     (قبلا تا ذخیره و بارگذاری دوباره کهنه می‌ماند).
 */
(() => {
	'use strict';

	const config = { minWidth: 1200, titleMin: 30, titleMax: 110, clickbait: [], ...window.hodimaDiscover };
	const nf = new Intl.NumberFormat('fa-IR', { useGrouping: false });
	const icons = { ok: 'dashicons-yes-alt', warn: 'dashicons-warning', error: 'dashicons-dismiss' };

	/** یکسان‌سازی متن؛ همان hodima_seo_discover_text_norm در PHP. */
	const norm = (v) => String(v)
		.replace(/\u200c/g, ' ')
		.replace(/[\u064B-\u0652\u0670]/g, '')
		.replace(/ي/g, 'ی')
		.replace(/ك/g, 'ک')
		.replace(/\s+/g, ' ')
		.trim()
		.toLowerCase();

	/*
	 * طعمه کلیک: کلمه کامل، «*» آخر = ادامه کلمه آزاد؛ همان
	 * hodima_seo_discover_clickbait_match در PHP (باگ قبلی: «افشان»، «شیراز»).
	 */
	const escape = (v) => v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const baits = config.clickbait.map((raw) => {
		let phrase = norm(raw);
		const prefix = phrase.endsWith('*');
		phrase = phrase.replace(/[\s*]+$/, '');
		const letter = /\p{L}/u.test(phrase);
		const body = escape(phrase).replace(/ /g, '\\s+');
		return {
			phrase: raw.replace(/[\s*]+$/, ''),
			re: new RegExp(`${letter ? '(?<![\\p{L}\\p{M}\\p{N}])' : ''}${body}${letter && !prefix ? '(?![\\p{L}\\p{M}\\p{N}])' : ''}`, 'u'),
		};
	});
	const clickbait = (title) => baits.find((b) => b.re.test(norm(title)))?.phrase || '';

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
		const bait = clickbait(title);
		let level = 'ok';
		let text = `${nf.format(len)} کاراکتر.`;
		// همان پیام‌های hodima_seo_discover_checks
		if (bait) [level, text] = ['warn', `عبارت «${bait}» اغراق‌آمیز یا طعمه کلیک است؛ گوگل در Discover آن را جریمه می‌کند.`];
		else if (len < config.titleMin) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید: ${nf.format(config.titleMin)} تا ${nf.format(config.titleMax)} کاراکتر.`];
		else if (len > config.titleMax) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ بیشتر از ${nf.format(config.titleMax)} در کارت کوتاه می‌شود.`];
		setCheck(box, 'title', level, text);

		const counter = box.querySelector('[data-hodima-dc-counter]');
		if (counter) {
			counter.textContent = `${nf.format(len)} / ${nf.format(config.titleMax)}`;
			counter.classList.toggle('is-over', len > config.titleMax);
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
		if (field) setStatus(field, text, ok ? 'ok' : 'error');
		setCheck(box, 'image', ok ? 'ok' : 'error', text);
		setCheck(box, 'crops', 'warn', 'تصویر عوض شد؛ برش‌ها بعد از ذخیره ساخته می‌شوند.');
		setCheck(box, 'alt', attachment.alt ? 'ok' : 'warn', attachment.alt ? 'دارد.' : 'تصویر متن جایگزین (alt) ندارد؛ گوگل با آن می‌فهمد تصویر چه نشان می‌دهد و برای نابینایان هم لازم است.');
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

		if (button?.matches('[data-hodima-dc-idea]')) {
			e.preventDefault();
			const input = box.querySelector('[data-hodima-dc-title]');
			input.value = button.dataset.hodimaDcIdea;
			checkTitle(box, input);
			input.focus();
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

	/* ── هماهنگی زنده با ویرایشگر (تصویر شاخص، عنوان، چکیده) ── */

	/** تصویر Discover خود کادر انتخاب شده؟ (آن‌وقت تصویر شاخص اثری ندارد) */
	const hasOwnImage = (box) => Boolean(box.querySelector('[data-hodima-dc-image-id]')?.value);

	/** تصویر پیش‌فرض صفحه (شاخص/محصول) عوض شد: پیش‌نمایش و ردیف‌های تصویر. */
	function defaultImageChanged(box, attachment) {
		const holder = box.querySelector('[data-hodima-dc-default-img]');
		const url = attachment ? (attachment.preview || attachment.url || '') : '';
		if (holder) holder.dataset.hodimaDcDefaultImg = url;
		if (hasOwnImage(box)) return;
		const own = box.querySelector('[data-hodima-dc-image-preview]');
		showImage(box, own && !own.hidden ? own.getAttribute('src') || '' : '');
		if (attachment) checkImage(box, null, attachment);
		else setCheck(box, 'image', 'error', 'تصویر شاخص یا تصویر Discover ندارد؛ Discover صفحه بی‌تصویر را تقریبا نشان نمی‌دهد.');
	}

	/** عنوان اصلی صفحه عوض شد: عنوان پیش‌فرض کارت. */
	function pageTitleChanged(box, title) {
		const input = box.querySelector('[data-hodima-dc-title]');
		if (!input) return;
		input.dataset.hodimaDcFallback = title;
		input.placeholder = title;
		checkTitle(box, input);
	}

	/** چکیده عوض شد: ردیف «خلاصه» (فقط نوشته و برگه؛ با توضیحات متا هم درست است). */
	function excerptChanged(box, excerpt) {
		if (box.dataset.hasMetaDesc === '1') return;
		const has = excerpt.trim() !== '';
		setCheck(box, 'desc', has ? 'ok' : 'warn', has ? 'چکیده یا توضیحات متا دارد.' : 'چکیده و توضیحات متا خالی است؛ متن کارت از ابتدای مطلب برداشته می‌شود.');
	}

	/** پیوست REST (ویرایشگر بلوکی) ← شکل مشترک. */
	const fromRest = (m) => ({
		width: m.media_details?.width,
		height: m.media_details?.height,
		alt: m.alt_text || '',
		url: m.source_url,
		preview: m.media_details?.sizes?.medium_large?.source_url || m.media_details?.sizes?.large?.source_url || m.source_url,
	});

	/** پیوست wp.media (ویرایشگر کلاسیک) ← شکل مشترک. */
	const fromBackbone = (a) => ({
		width: a.width,
		height: a.height,
		alt: a.alt || '',
		url: a.url,
		preview: a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url,
	});

	function watchBlockEditor(box) {
		const { select, subscribe } = window.wp.data;
		const editor = () => select('core/editor');
		const last = { media: editor().getEditedPostAttribute('featured_media'), title: editor().getEditedPostAttribute('title'), excerpt: editor().getEditedPostAttribute('excerpt'), resolved: true };

		subscribe(() => {
			const ed = editor();
			if (!ed) return;
			const media = ed.getEditedPostAttribute('featured_media') || 0;
			const title = ed.getEditedPostAttribute('title') || '';
			const excerpt = ed.getEditedPostAttribute('excerpt') || '';

			if (title !== last.title) { last.title = title; pageTitleChanged(box, title); }
			if (excerpt !== last.excerpt) { last.excerpt = excerpt; excerptChanged(box, excerpt); }

			// پیوست تازه شاید هنوز از REST نرسیده باشد؛ با رسیدنش subscribe دوباره صدا زده می‌شود
			if (media !== last.media || !last.resolved) {
				last.media = media;
				if (!media) { last.resolved = true; defaultImageChanged(box, null); return; }
				const m = select('core').getMedia(media);
				last.resolved = Boolean(m);
				if (m) defaultImageChanged(box, fromRest(m));
			}
		});
	}

	function watchClassicEditor(box) {
		// عنوان (#title) و چکیده (#excerpt) فرم کلاسیک
		document.getElementById('title')?.addEventListener('input', (e) => pageTitleChanged(box, e.target.value.trim()));
		document.getElementById('excerpt')?.addEventListener('input', (e) => excerptChanged(box, e.target.value));

		// تصویر شاخص/محصول: وردپرس HTML کادر را با AJAX عوض می‌کند (بدون رویداد)
		const thumbBox = document.getElementById('postimagediv');
		if (!thumbBox || !window.wp?.media?.attachment) return;
		let current = thumbBox.querySelector('#_thumbnail_id')?.value || '-1';
		new MutationObserver(() => {
			const id = thumbBox.querySelector('#_thumbnail_id')?.value || '-1';
			if (id === current) return;
			current = id;
			if (Number(id) <= 0) { defaultImageChanged(box, null); return; }
			const attachment = wp.media.attachment(Number(id));
			attachment.fetch().then(() => defaultImageChanged(box, fromBackbone(attachment.toJSON())));
		}).observe(thumbBox, { childList: true, subtree: true });
	}

	function initLive() {
		const box = document.querySelector('[data-hodima-dc][data-context="post"]');
		if (!box) return; // دسته محصول: تصویر دسته را ووکامرس بدون رویداد عوض می‌کند؛ بعد از ذخیره
		if (window.wp?.data?.select?.('core/editor')?.getCurrentPostId?.()) watchBlockEditor(box);
		else watchClassicEditor(box);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initLive);
	else initLive();
})();
