/**
 * ماژول «گوگل دیسکاور» — کادر ویرایش نوشته، برگه، محصول و دسته محصول
 * Path: core/discover/assets/discover-admin.js
 *
 * جاوااسکریپت خالص (طراحی تازه SEO 2.1.3):
 *   - تب‌ها (تنظیمات، آمادگی، آمار) با الگوی ARIA: کلیک، کلیدهای جهت (راست‌به‌چپ)،
 *     Home/End؛ آخرین تب در مرورگر همین کاربر به خاطر می‌ماند
 *   - دکمه‌های ⓘ: باز/بسته کردن راهنمای هر فیلد
 *   - عنوان: شمارنده داخل فیلد، پیام کوتاه زیر فیلد، طول و طعمه کلیک (همان
 *     قاعده PHP)، پیشنهاد عنوان با یک کلیک
 *   - تصویر: یک تصویر کوچک (تصویر جدای دیسکاور یا تصویر پیش‌فرض صفحه) با
 *     ابعاد و وضعیت برش؛ انتخاب از کتابخانه رسانه و برگشت به پیش‌فرض
 *   - نقطه تمرکز برش‌ها (SEO 2.1.6): کلیک یا کلیدهای جهت روی تصویر، پیش‌نمایش
 *     همان سه برش (همان حساب PHP)، و همان جای تصویر در کارت‌های پیش‌نمایش
 *   - موضوعات: برچسب‌ها (Enter یا ویرگول = افزودن، × یا Backspace = حذف)
 *   - آمادگی: هر تغییر ردیف را بین «نیاز به توجه» و «موارد درست» جابه‌جا و
 *     دایره امتیاز، شمارنده تب و خلاصه سربرگ را به‌روز می‌کند
 *   - پیش‌نمایش کارت در <dialog>: دیسکاور گوشی، دیسکاور دسکتاپ، اشتراک‌گذاری
 *   - هماهنگی زنده با ویرایشگر بلوکی (wp.data) و کلاسیک/محصول: تصویر شاخص،
 *     عنوان و چکیده
 *   - ویرایشگر بلوکی: بعد از «به‌روزرسانی» کادر خودش از سرور تازه می‌شود
 *     (برش‌های ساخته‌شده، امتیاز و آمادگی واقعی؛ SEO 2.1.4)
 */
(() => {
	'use strict';

	const config = { minWidth: 1200, titleMin: 30, titleMax: 110, clickbait: [], ...window.hodimaDiscover };
	const nf = new Intl.NumberFormat('fa-IR', { useGrouping: false });
	const icons = { ok: 'dashicons-yes-alt', warn: 'dashicons-warning', error: 'dashicons-dismiss' };
	const TAB_KEY = 'hodimaDiscoverTab';

	/** یکسان‌سازی متن؛ همان hodima_seo_discover_text_norm در PHP. */
	const norm = (v) => String(v)
		.replace(/‌/g, ' ')
		.replace(/[ً-ْٰ]/g, '')
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

	const storage = {
		get(key) { try { return window.localStorage.getItem(key); } catch { return null; } },
		set(key, value) { try { window.localStorage.setItem(key, value); } catch { /* حالت خصوصی مرورگر */ } },
	};

	/* ── تب‌ها ── */
	function selectTab(box, name, focus = false) {
		box.querySelectorAll('[data-hodima-dc-tab]').forEach((tab) => {
			const on = tab.dataset.hodimaDcTab === name;
			tab.setAttribute('aria-selected', String(on));
			tab.tabIndex = on ? 0 : -1;
			if (on && focus) tab.focus();
		});
		box.querySelectorAll('[data-hodima-dc-panel]').forEach((panel) => {
			panel.hidden = panel.dataset.hodimaDcPanel !== name;
		});
		storage.set(TAB_KEY, name);
	}

	function tabKeys(box, e) {
		const tabs = [...box.querySelectorAll('[data-hodima-dc-tab]')];
		const i = tabs.indexOf(e.target);
		if (i < 0) return;
		// راست‌به‌چپ: «چپ» = تب بعدی
		const rtl = getComputedStyle(box).direction === 'rtl';
		const step = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1 }[e.key];
		let next = null;
		if (step) next = tabs[(i + step + tabs.length) % tabs.length];
		else if (e.key === 'Home') next = tabs[0];
		else if (e.key === 'End') next = tabs[tabs.length - 1];
		if (!next) return;
		e.preventDefault();
		selectTab(box, next.dataset.hodimaDcTab, true);
	}

	/* ── آمادگی: ردیف‌ها، امتیاز، شمارنده‌ها ── */
	function updateSummary(box) {
		const rows = [...box.querySelectorAll('[data-hodima-dc-check]')];
		const issues = rows.filter((r) => !r.classList.contains('is-ok'));
		const ok = rows.length - issues.length;
		const level = ok === rows.length ? 'ok' : (ok >= rows.length * 0.6 ? 'warn' : 'error');

		const ring = box.querySelector('[data-hodima-dc-ring]');
		if (ring) {
			ring.className = `hodima-dc__ring is-${level}`;
			ring.style.setProperty('--p', String(rows.length ? Math.round((100 * ok) / rows.length) : 0));
			ring.setAttribute('aria-label', `آمادگی ${nf.format(ok)} از ${nf.format(rows.length)}`);
			ring.querySelector('[data-hodima-dc-ring-text]').textContent = `${nf.format(ok)}/${nf.format(rows.length)}`;
		}

		const badge = box.querySelector('[data-hodima-dc-issue-count]');
		if (badge) {
			badge.textContent = nf.format(issues.length);
			badge.className = `hodima-dc__badge is-${level}`;
			badge.hidden = issues.length === 0;
		}

		const summary = box.querySelector('[data-hodima-dc-summary]');
		if (summary) summary.textContent = issues.length ? `${nf.format(issues.length)} مورد نیاز به توجه` : 'همه موارد درست است';

		const allOk = box.querySelector('[data-hodima-dc-all-ok]');
		if (allOk) allOk.hidden = issues.length > 0;
		const passedBox = box.querySelector('[data-hodima-dc-passed-box]');
		if (passedBox) passedBox.hidden = ok === 0;
		const okCount = box.querySelector('[data-hodima-dc-ok-count]');
		if (okCount) okCount.textContent = nf.format(ok);
	}

	/** یک ردیف آمادگی: وضعیت، متن، و جابه‌جایی بین «نیاز به توجه» و «موارد درست». */
	function setCheck(box, key, level, text) {
		const row = box.querySelector(`[data-hodima-dc-check="${key}"]`);
		if (!row) return;
		row.className = `hodima-dc__check is-${level}`;
		row.querySelector('summary .dashicons').className = `dashicons ${icons[level]}`;
		row.querySelector('[data-hodima-dc-check-text]').textContent = text;
		const fix = row.querySelector('.hodima-dc__fix');
		if (fix) fix.hidden = level === 'ok';

		const target = box.querySelector(level === 'ok' ? '[data-hodima-dc-passed]' : '[data-hodima-dc-issues]');
		if (target && row.parentElement !== target) target.append(row);

		// مشکل‌های قرمز اول
		const issues = box.querySelector('[data-hodima-dc-issues]');
		if (issues) [...issues.children].filter((r) => r.classList.contains('is-error')).reverse().forEach((r) => issues.prepend(r));
		updateSummary(box);
	}

	/* ── عنوان ── */
	function checkTitle(box, input) {
		const title = input.value.trim() || input.dataset.hodimaDcFallback || '';
		const len = [...title].length;
		const bait = clickbait(title);
		let level = 'ok';
		let text = `${nf.format(len)} کاراکتر.`;
		// همان پیام‌های hodima_seo_discover_checks
		if (bait) [level, text] = ['warn', `عبارت «${bait}» اغراق‌آمیز یا طعمه کلیک است؛ گوگل در دیسکاور آن را جریمه می‌کند.`];
		else if (len < config.titleMin) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید: ${nf.format(config.titleMin)} تا ${nf.format(config.titleMax)} کاراکتر.`];
		else if (len > config.titleMax) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ بیشتر از ${nf.format(config.titleMax)} در کارت کوتاه می‌شود.`];
		setCheck(box, 'title', level, text);

		const counter = box.querySelector('[data-hodima-dc-counter]');
		if (counter) {
			counter.textContent = `${nf.format(len)}/${nf.format(config.titleMax)}`;
			counter.classList.toggle('is-over', len > config.titleMax);
		}
		box.querySelector('[data-hodima-dc-title-wrap]')?.classList.toggle('is-warn', level !== 'ok');
		const msg = box.querySelector('[data-hodima-dc-title-msg]');
		if (msg) { msg.textContent = level === 'ok' ? '' : text; msg.hidden = level === 'ok'; }
		box.querySelectorAll('[data-hodima-dc-preview-title]').forEach((el) => { el.textContent = title; });
	}

	/* ── تصویر ── */
	const ownImages = new WeakMap(); // تصویر جدای دیسکاور هر کادر: {url, width, height, alt} یا null

	function defaultImage(field) {
		const d = field.dataset;
		return d.defaultUrl ? { url: d.defaultUrl, width: Number(d.defaultWidth) || 0, height: Number(d.defaultHeight) || 0, alt: d.defaultAlt === '1', focus: d.defaultFocus || CENTER } : null;
	}

	/** تصویر کوچک، واقعیت‌ها (ابعاد، برش)، دکمه‌ها و تصویر پیش‌نمایش‌ها از روی وضعیت فعلی. */
	function renderImage(box, changed = false) {
		const field = box.querySelector('[data-hodima-dc-image]');
		if (!field) return;
		const own = ownImages.get(box) ?? null;
		const shown = own || defaultImage(field);
		const label = field.dataset.ownLabel || 'تصویر شاخص';

		const img = field.querySelector('[data-hodima-dc-thumb-img]');
		if (shown) img.src = shown.url; else img.removeAttribute('src');
		img.hidden = !shown;
		field.querySelector('[data-hodima-dc-thumb-empty]').hidden = Boolean(shown);
		field.querySelector('[data-hodima-dc-thumb]').classList.toggle('has-image', Boolean(shown));
		field.querySelector('[data-hodima-dc-image-source]').textContent = own ? 'تصویر جدای دیسکاور' : (shown ? `${label} (پیش‌فرض)` : 'تصویری انتخاب نشده');

		const big = Boolean(shown) && shown.width >= config.minWidth;
		const dims = field.querySelector('[data-hodima-dc-dims]');
		dims.className = `hodima-dc__fact ${big ? 'is-ok' : 'is-error'}`;
		dims.textContent = shown ? `${nf.format(shown.width)}×${nf.format(shown.height)}` : 'بدون تصویر';

		field.querySelector('[data-hodima-dc-pick]').textContent = own ? 'تغییر تصویر' : 'انتخاب تصویر جدا';
		field.querySelector('[data-hodima-dc-clear]').hidden = !own;

		box.querySelectorAll('[data-hodima-dc-preview-img]').forEach((el) => {
			if (shown) el.src = shown.url; else el.removeAttribute('src');
			el.hidden = !shown;
		});
		box.querySelectorAll('[data-hodima-dc-preview-noimg]').forEach((el) => { el.hidden = Boolean(shown); });

		// تصویر عوض شد: نقطه تمرکز همان تصویر (هر تصویر نقطه خودش را دارد)
		renderFocus(box, shown, changed);

		if (!changed) return;

		// تصویر عوض شد: ردیف‌های آمادگی (همان پیام‌های PHP)
		const crops = field.querySelector('[data-hodima-dc-crops]');
		if (crops) { crops.className = 'hodima-dc__fact is-warn'; crops.textContent = 'برش بعد از ذخیره'; }
		if (!shown) {
			setCheck(box, 'image', 'error', `${label} یا تصویر دیسکاور ندارد؛ دیسکاور صفحه بی‌تصویر را تقریبا نشان نمی‌دهد.`);
		} else if (!big) {
			setCheck(box, 'image', 'error', `عرض تصویر ${nf.format(shown.width)} پیکسل است؛ برای کارت بزرگ دیسکاور حداقل ${nf.format(config.minWidth)} لازم است.`);
		} else {
			setCheck(box, 'image', 'ok', `${nf.format(shown.width)}×${nf.format(shown.height)} پیکسل.`);
			setCheck(box, 'crops', 'warn', 'تصویر عوض شد؛ برش‌ها بعد از ذخیره ساخته می‌شوند.');
		}
		if (shown) setCheck(box, 'alt', shown.alt ? 'ok' : 'warn', shown.alt ? 'دارد.' : 'تصویر متن جایگزین (alt) ندارد؛ گوگل با آن می‌فهمد تصویر چه نشان می‌دهد و برای نابینایان هم لازم است.');

		const status = field.querySelector('[data-hodima-dc-status]');
		status.hidden = false;
		status.dataset.level = big ? 'ok' : 'error';
		status.textContent = big
			? 'مناسب است؛ برش‌های ۱۶:۹، ۴:۳ و ۱:۱ بعد از ذخیره ساخته می‌شوند.'
			: (shown ? `برای کارت بزرگ حداقل ${nf.format(config.minWidth)} پیکسل عرض لازم است.` : 'بدون تصویر، دیسکاور کارت را تقریبا نشان نمی‌دهد.');
	}

	/* ── نقطه تمرکز برش‌ها (SEO 2.1.6) ── */
	const CENTER = '0.50,0.50';
	const RATIOS = { '16:9': [16, 9], '4:3': [4, 3], '1:1': [1, 1] };
	const pct = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });

	/** «x,y» ← [x, y] بین ۰ و ۱. */
	const parseFocus = (raw) => {
		const m = String(raw || '').match(/^\s*([\d.]+)\s*,\s*([\d.]+)\s*$/);
		const clamp = (v) => Math.round(Math.max(0, Math.min(1, Number(v))) * 100) / 100;
		return m ? [clamp(m[1]), clamp(m[2])] : [0.5, 0.5];
	};
	const focusString = ([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`;

	/*
	 * object-position (درصد) برای نمایش برش rw:rh دور نقطه تمرکز با object-fit: cover؛
	 * همان حساب PHP (hodima_seo_discover_crop_rect): بزرگ‌ترین ناحیه هم‌نسبت که
	 * مرکزش تا جای ممکن روی نقطه است و بیرون از تصویر نمی‌رود.
	 */
	function focusPosition(srcW, srcH, rw, rh, [fx, fy]) {
		if (!srcW || !srcH) return [fx * 100, fy * 100];
		const wide = srcW * rh > srcH * rw;
		const w = wide ? (srcH * rw) / rh : srcW;
		const h = wide ? srcH : (srcW * rh) / rw;
		const x = Math.max(0, Math.min(srcW - w, fx * srcW - w / 2));
		const y = Math.max(0, Math.min(srcH - h, fy * srcH - h / 2));
		return [srcW - w > 0 ? (100 * x) / (srcW - w) : 50, srcH - h > 0 ? (100 * y) / (srcH - h) : 50];
	}

	const focusImages = new WeakMap(); // تصویر فعلی بخش نقطه تمرکز هر کادر

	/** نقطه، برش‌های پیش‌نمایش، تصویر کوچک و کارت‌های پنجره را از روی نقطه فعلی می‌چیند. */
	function paintFocus(box) {
		const wrap = box.querySelector('[data-hodima-dc-focus]');
		const input = box.querySelector('[data-hodima-dc-focus-input]');
		const shown = focusImages.get(box);
		if (!wrap || !input || !shown) return;
		const focus = parseFocus(input.value);
		const dot = wrap.querySelector('[data-hodima-dc-focus-dot]');
		// تصویر تمرکز چپ‌به‌راست واقعی است؛ پس جای فیزیکی
		dot.style.left = `${focus[0] * 100}%`;
		dot.style.top = `${focus[1] * 100}%`;
		const at = (rw, rh) => focusPosition(shown.width, shown.height, rw, rh, focus).map((v) => `${v}%`).join(' ');
		wrap.querySelectorAll('[data-hodima-dc-crop-img]').forEach((img) => {
			const [rw, rh] = RATIOS[img.dataset.ratio] || [16, 9];
			img.style.objectPosition = at(rw, rh);
		});
		const wide = at(16, 9);
		box.querySelectorAll('[data-hodima-dc-thumb-img], .hodima-dc__card--phone [data-hodima-dc-preview-img], .hodima-dc__card--desktop [data-hodima-dc-preview-img]').forEach((img) => { img.style.objectPosition = wide; });
		box.querySelectorAll('.hodima-dc__card--social [data-hodima-dc-preview-img]').forEach((img) => { img.style.objectPosition = at(191, 100); });
		const center = input.value === CENTER;
		wrap.querySelector('[data-hodima-dc-focus-state]').textContent = center
			? 'وسط تصویر'
			: `${pct.format(focus[0] * 100)}٪ از چپ، ${pct.format(focus[1] * 100)}٪ از بالا`;
		wrap.querySelector('[data-hodima-dc-focus-reset]').hidden = center;
	}

	/** تصویر بخش نقطه تمرکز؛ با reset نقطه همان تصویر (ذخیره‌شده در خود تصویر) برمی‌گردد. */
	function renderFocus(box, shown, reset) {
		const wrap = box.querySelector('[data-hodima-dc-focus]');
		const input = box.querySelector('[data-hodima-dc-focus-input]');
		if (!wrap || !input) return;
		wrap.hidden = !shown;
		if (!shown) { focusImages.delete(box); return; }
		focusImages.set(box, shown);
		wrap.querySelectorAll('[data-hodima-dc-focus-img], [data-hodima-dc-crop-img]').forEach((img) => { img.src = shown.url; });
		if (reset) input.value = focusString(parseFocus(shown.focus));
		paintFocus(box);
	}

	/** مدیر نقطه را جابه‌جا کرد. */
	function setFocus(box, focus) {
		const input = box.querySelector('[data-hodima-dc-focus-input]');
		if (!input) return;
		const value = focusString(focus.map((v) => Math.max(0, Math.min(1, v))));
		if (value === input.value) return;
		input.value = value;
		paintFocus(box);
		// برش‌ها بعد از ذخیره با نقطه تازه ساخته می‌شوند
		const crops = box.querySelector('[data-hodima-dc-crops]');
		if (crops) { crops.className = 'hodima-dc__fact is-warn'; crops.textContent = 'برش بعد از ذخیره'; }
		if (box.querySelector('[data-hodima-dc-check="crops"]')) setCheck(box, 'crops', 'warn', 'نقطه تمرکز عوض شد؛ برش‌ها بعد از ذخیره دوباره ساخته می‌شوند.');
	}

	function openPicker(box) {
		if (!window.wp?.media) return;
		const frame = wp.media({ title: 'انتخاب تصویر دیسکاور', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });
		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			box.querySelector('[data-hodima-dc-image-id]').value = a.id;
			ownImages.set(box, { url: a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url, width: Number(a.width) || 0, height: Number(a.height) || 0, alt: Boolean(a.alt), focus: a.hodimaFocus || CENTER });
			renderImage(box, true);
		});
		frame.open();
	}

	function clearPicker(box) {
		box.querySelector('[data-hodima-dc-image-id]').value = '';
		ownImages.set(box, null);
		renderImage(box, true);
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
		const key = norm(name);
		if (!name || [...chips.querySelectorAll('.hodima-dc__chip')].some((c) => norm(c.firstElementChild.textContent) === key)) return;

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

	/* ── پنجره پیش‌نمایش ── */
	function openPreview(box) {
		const dialog = box.querySelector('[data-hodima-dc-dialog]');
		if (!dialog) return;
		if (typeof dialog.showModal === 'function') dialog.showModal();
		else dialog.setAttribute('open', '');
	}

	function closePreview(dialog) {
		if (typeof dialog.close === 'function') dialog.close();
		else dialog.removeAttribute('open');
	}

	function selectView(box, view) {
		box.querySelectorAll('[data-hodima-dc-view]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.hodimaDcView === view)));
		box.querySelectorAll('[data-hodima-dc-pane]').forEach((p) => { p.hidden = p.dataset.hodimaDcPane !== view; });
	}

	/* ── رویدادها ── */
	document.addEventListener('click', (e) => {
		const box = e.target.closest?.('[data-hodima-dc]');
		if (!box) return;

		// کلیک روی پس‌زمینه پنجره (خود dialog، نه محتوایش) = بستن
		if (e.target.matches('[data-hodima-dc-dialog]')) { closePreview(e.target); return; }

		const button = e.target.closest('button');
		if (!button) {
			if (e.target.matches('[data-hodima-dc-chips]')) e.target.querySelector('input')?.focus();
			return;
		}

		const d = button.dataset;
		if ('hodimaDcTab' in d) { selectTab(box, d.hodimaDcTab); return; }
		if ('hodimaDcFocusPad' in d) {
			e.preventDefault();
			const img = button.querySelector('[data-hodima-dc-focus-img]');
			const rect = (img || button).getBoundingClientRect();
			// کلید Enter/Space روی دکمه هم «click» می‌دهد (بدون مختصات): نقطه همان می‌ماند
			if (e.detail === 0 || !rect.width) return;
			setFocus(box, [(e.clientX - rect.left) / rect.width, (e.clientY - rect.top) / rect.height]);
			return;
		}
		e.preventDefault();

		if ('hodimaDcInfo' in d) {
			const help = document.getElementById(button.getAttribute('aria-controls'));
			const open = button.getAttribute('aria-expanded') !== 'true';
			button.setAttribute('aria-expanded', String(open));
			if (help) help.hidden = !open;
		} else if ('hodimaDcIdea' in d) {
			const input = box.querySelector('[data-hodima-dc-title]');
			input.value = d.hodimaDcIdea;
			checkTitle(box, input);
			input.focus();
		} else if ('hodimaDcChipRemove' in d) {
			button.closest('.hodima-dc__chip')?.remove();
			syncTopics(box);
			box.querySelector('[data-hodima-dc-topic-input]')?.focus();
		} else if ('hodimaDcPick' in d) {
			openPicker(box);
		} else if ('hodimaDcClear' in d) {
			clearPicker(box);
		} else if ('hodimaDcOpenPreview' in d) {
			openPreview(box);
		} else if ('hodimaDcClose' in d) {
			closePreview(button.closest('dialog'));
		} else if ('hodimaDcView' in d) {
			selectView(box, d.hodimaDcView);
		} else if ('hodimaDcFocusReset' in d) {
			setFocus(box, [0.5, 0.5]);
			box.querySelector('[data-hodima-dc-focus-pad]')?.focus();
		}
	});

	document.addEventListener('input', (e) => {
		const box = e.target.closest?.('[data-hodima-dc]');
		if (box && e.target.matches('[data-hodima-dc-title]')) checkTitle(box, e.target);
	});

	document.addEventListener('keydown', (e) => {
		const box = e.target.closest?.('[data-hodima-dc]');
		if (!box) return;

		if (e.target.matches('[data-hodima-dc-tab]')) { tabKeys(box, e); return; }

		// نقطه تمرکز با کلیدهای جهت (۵٪؛ با Shift یک‌درصدی). جهت فیزیکی تصویر، نه جهت متن
		if (e.target.matches('[data-hodima-dc-focus-pad]')) {
			const step = e.shiftKey ? 0.01 : 0.05;
			const move = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
			if (!move) return;
			e.preventDefault();
			const [x, y] = parseFocus(box.querySelector('[data-hodima-dc-focus-input]')?.value);
			setFocus(box, [x + move[0], y + move[1]]);
			return;
		}
		if (!e.target.matches('[data-hodima-dc-topic-input]')) return;

		// Enter یا ویرگول (لاتین/فارسی) = افزودن؛ Enter نباید فرم را ارسال کند
		if (e.key === 'Enter' || e.key === ',' || e.key === '،') {
			e.preventDefault();
			addTopic(box, e.target.value);
			e.target.value = '';
		} else if (e.key === 'Backspace' && e.target.value === '') {
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

	/** تصویر پیش‌فرض صفحه (شاخص/محصول) عوض شد. */
	function defaultImageChanged(box, attachment) {
		const field = box.querySelector('[data-hodima-dc-image]');
		if (!field) return;
		field.dataset.defaultUrl = attachment?.url || '';
		field.dataset.defaultWidth = String(attachment?.width || 0);
		field.dataset.defaultHeight = String(attachment?.height || 0);
		field.dataset.defaultAlt = attachment?.alt ? '1' : '0';
		field.dataset.defaultFocus = attachment?.focus || CENTER;
		renderImage(box, !ownImages.get(box)); // تصویر جدای دیسکاور انتخاب شده؟ آمادگی دست نمی‌خورد
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
		url: m.media_details?.sizes?.medium_large?.source_url || m.media_details?.sizes?.large?.source_url || m.source_url,
		width: m.media_details?.width,
		height: m.media_details?.height,
		alt: Boolean(m.alt_text),
		focus: m.meta?._hodima_discover_focus || CENTER,
	});

	/** پیوست wp.media (ویرایشگر کلاسیک) ← شکل مشترک. */
	const fromBackbone = (a) => ({
		url: a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url,
		width: a.width,
		height: a.height,
		alt: Boolean(a.alt),
		focus: a.hodimaFocus || CENTER,
	});

	/** کادر نوشته فعلی (بعد از تازه شدن، عنصر DOM عوض می‌شود). */
	const currentBox = () => document.querySelector('[data-hodima-dc][data-context="post"]');

	/** مقدار فیلدهای ذخیره‌شونده کادر (برای فهمیدن تغییر حین ذخیره). */
	const fieldState = (box) => ['[data-hodima-dc-title]', '[data-hodima-dc-image-id]', '[data-hodima-dc-entities]', '[data-hodima-dc-focus-input]']
		.map((sel) => box.querySelector(sel)?.value ?? '')
		.concat(box.querySelector('[data-hodima-dc-skip]')?.checked ? '1' : '')
		.join('\u0001');

	/*
	 * کادر تازه از سرور بعد از ذخیره ویرایشگر بلوکی (باگ تا SEO 2.1.3: ویرایشگر
	 * بلوکی صفحه را دوباره بارگذاری نمی‌کند، پس برش‌های تازه، امتیاز و آمادگی
	 * بعد از ذخیره کهنه می‌ماندند تا بارگذاری دستی). اگر مدیر حین ذخیره در کادر
	 * چیزی را عوض کرده باشد، کادر دست نمی‌خورد تا تغییرش از دست نرود.
	 */
	async function refreshBox(savedState) {
		const box = currentBox();
		if (!box || !config.ajax || !box.dataset.refreshNonce) return;
		if (fieldState(box) !== savedState) return;
		try {
			const body = new URLSearchParams({ action: 'hodima_discover_box', context: 'post', id: box.dataset.objectId || '', nonce: box.dataset.refreshNonce });
			const res = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
			const json = await res.json().catch(() => null);
			if (!res.ok || !json?.success || fieldState(box) !== savedState) return;
			const tpl = document.createElement('template');
			tpl.innerHTML = String(json.data.html).trim();
			const fresh = tpl.content.querySelector('[data-hodima-dc]');
			if (!fresh) return;
			const hadFocus = box.contains(document.activeElement);
			box.replaceWith(fresh);
			setupBox(fresh);
			if (hadFocus) fresh.querySelector('[role="tab"][aria-selected="true"]')?.focus();
		} catch {
			// شبکه قطع: همان کادر قبلی می‌ماند؛ بارگذاری دوباره صفحه همیشه کار می‌کند
		}
	}

	/*
	 * باگ تا SEO 2.1.4: این اسکریپت (defer) پیش از راه افتادن ویرایشگر بلوکی اجرا
	 * می‌شود (وردپرس ویرایشگر را در DOMContentLoaded می‌سازد). آن لحظه
	 * getCurrentPostId() هنوز خالی بود، پس صفحه «ویرایشگر کلاسیک» شمرده می‌شد:
	 * نه هماهنگی زنده با تصویر شاخص بود، نه تازه شدن کادر بعد از «به‌روزرسانی»
	 * (آزمون بخش ۸۹ با store از پیش آماده این را نمی‌دید). حالا ویرایشگر بلوکی از
	 * کلاس body شناخته می‌شود و پیگیری از اولین لحظه‌ای شروع می‌شود که نوشته در
	 * ویرایشگر بار شده است.
	 */
	const isBlockEditor = () => document.body.classList.contains('block-editor-page') && typeof window.wp?.data?.subscribe === 'function';

	let watchingBlockEditor = false;

	function watchBlockEditor() {
		if (watchingBlockEditor) return;
		watchingBlockEditor = true;

		const { select, subscribe } = window.wp.data;
		const editor = () => select('core/editor');
		let last = null;

		subscribe(() => {
			const ed = editor();
			const box = currentBox();
			if (!ed?.getCurrentPostId?.() || !box) return; // ویرایشگر هنوز نوشته را بار نکرده

			// وضعیت اولیه: همان چیزی که سرور کادر را با آن ساخته است
			if (!last) {
				last = { media: ed.getEditedPostAttribute('featured_media') || 0, title: ed.getEditedPostAttribute('title') || '', excerpt: ed.getEditedPostAttribute('excerpt') || '', resolved: true, savingBoxes: false, savedState: '' };
			}

			// پایان ذخیره کادرها (بعد از ذخیره خود نوشته؛ ذخیره خودکار کادرها را نمی‌فرستد)
			let savingBoxes = false;
			try { savingBoxes = Boolean(select('core/edit-post')?.isSavingMetaBoxes?.()); } catch { /* ویرایشگر بدون این store */ }
			if (savingBoxes && !last.savingBoxes) last.savedState = fieldState(box);
			if (!savingBoxes && last.savingBoxes) refreshBox(last.savedState);
			last.savingBoxes = savingBoxes;

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

	/** وضعیت اولیه یک کادر (بار اول و بعد از تازه شدن از سرور). */
	function setupBox(box) {
		// تصویر جدای دیسکاور که از قبل ذخیره شده
		const own = box.querySelector('[data-hodima-dc-image-id]');
		ownImages.set(box, own?.value ? { url: own.dataset.ownUrl || '', width: Number(own.dataset.ownWidth) || 0, height: Number(own.dataset.ownHeight) || 0, alt: own.dataset.ownAlt === '1', focus: own.dataset.ownFocus || CENTER } : null);

		// نقطه تمرکز: فقط با JS دیده می‌شود (کلیک لازم دارد)
		const field = box.querySelector('[data-hodima-dc-image]');
		const shown = ownImages.get(box) || (field ? defaultImage(field) : null);
		renderFocus(box, shown, false);

		const saved = storage.get(TAB_KEY);
		if (saved && box.querySelector(`[data-hodima-dc-tab="${saved}"]`)) selectTab(box, saved);
	}

	function init() {
		document.querySelectorAll('[data-hodima-dc]').forEach((box) => {
			setupBox(box);
			if (box.dataset.context !== 'post') return; // دسته محصول: با ذخیره، صفحه کامل بارگذاری می‌شود
			// ویرایشگر بلوکی: هماهنگی زنده + تازه شدن بعد از ذخیره؛ کلاسیک/محصول با ذخیره دوباره بارگذاری می‌شود
			if (isBlockEditor()) watchBlockEditor();
			else watchClassicEditor(box);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
