/**
 * ماژول «Google Discover» — کادر ویرایش نوشته، برگه، محصول و دسته محصول
 * Path: core/discover/assets/discover-admin.js
 *
 * جاوااسکریپت خالص (طراحی تازه SEO 2.1.3):
 *   - تب‌ها (تنظیمات، آمادگی، آمار) با الگوی ARIA: کلیک، کلیدهای جهت (راست‌به‌چپ)،
 *     Home/End؛ آخرین تب در مرورگر همین کاربر به خاطر می‌ماند
 *   - دکمه‌های ⓘ: باز/بسته کردن راهنمای هر فیلد
 *   - عنوان: شمارنده داخل فیلد، پیام کوتاه زیر فیلد، طول و طعمه کلیک (همان
 *     قاعده PHP)، پیشنهاد عنوان با یک کلیک
 *   - تصویر: یک تصویر کوچک (تصویر جدای Discover یا تصویر پیش‌فرض صفحه) با
 *     ابعاد و وضعیت برش؛ انتخاب از کتابخانه رسانه و برگشت به پیش‌فرض
 *   - موضوعات: برچسب‌ها (Enter یا ویرگول = افزودن، × یا Backspace = حذف)
 *   - آمادگی: هر تغییر ردیف را بین «نیاز به توجه» و «موارد درست» جابه‌جا و
 *     دایره امتیاز، شمارنده تب و خلاصه سربرگ را به‌روز می‌کند
 *   - پیش‌نمایش کارت در <dialog>: Discover گوشی، Discover دسکتاپ، اشتراک‌گذاری
 *   - هماهنگی زنده با ویرایشگر بلوکی (wp.data) و کلاسیک/محصول: تصویر شاخص،
 *     عنوان و چکیده
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
		if (bait) [level, text] = ['warn', `عبارت «${bait}» اغراق‌آمیز یا طعمه کلیک است؛ گوگل در Discover آن را جریمه می‌کند.`];
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
	const ownImages = new WeakMap(); // تصویر جدای Discover هر کادر: {url, width, height, alt} یا null

	function defaultImage(field) {
		const d = field.dataset;
		return d.defaultUrl ? { url: d.defaultUrl, width: Number(d.defaultWidth) || 0, height: Number(d.defaultHeight) || 0, alt: d.defaultAlt === '1' } : null;
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
		field.querySelector('[data-hodima-dc-image-source]').textContent = own ? 'تصویر جدای Discover' : (shown ? `${label} (پیش‌فرض)` : 'تصویری انتخاب نشده');

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

		if (!changed) return;

		// تصویر عوض شد: ردیف‌های آمادگی (همان پیام‌های PHP)
		const crops = field.querySelector('[data-hodima-dc-crops]');
		if (crops) { crops.className = 'hodima-dc__fact is-warn'; crops.textContent = 'برش بعد از ذخیره'; }
		if (!shown) {
			setCheck(box, 'image', 'error', `${label} یا تصویر Discover ندارد؛ Discover صفحه بی‌تصویر را تقریبا نشان نمی‌دهد.`);
		} else if (!big) {
			setCheck(box, 'image', 'error', `عرض تصویر ${nf.format(shown.width)} پیکسل است؛ برای کارت بزرگ Discover حداقل ${nf.format(config.minWidth)} لازم است.`);
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
			: (shown ? `برای کارت بزرگ حداقل ${nf.format(config.minWidth)} پیکسل عرض لازم است.` : 'بدون تصویر، Discover کارت را تقریبا نشان نمی‌دهد.');
	}

	function openPicker(box) {
		if (!window.wp?.media) return;
		const frame = wp.media({ title: 'انتخاب تصویر Discover', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });
		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			box.querySelector('[data-hodima-dc-image-id]').value = a.id;
			ownImages.set(box, { url: a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url, width: Number(a.width) || 0, height: Number(a.height) || 0, alt: Boolean(a.alt) });
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
		renderImage(box, !ownImages.get(box)); // تصویر جدای Discover انتخاب شده؟ آمادگی دست نمی‌خورد
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
	});

	/** پیوست wp.media (ویرایشگر کلاسیک) ← شکل مشترک. */
	const fromBackbone = (a) => ({
		url: a.sizes?.medium_large?.url || a.sizes?.large?.url || a.url,
		width: a.width,
		height: a.height,
		alt: Boolean(a.alt),
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

	function init() {
		document.querySelectorAll('[data-hodima-dc]').forEach((box) => {
			// تصویر جدای Discover که از قبل ذخیره شده
			const own = box.querySelector('[data-hodima-dc-image-id]');
			if (own?.value) {
				const d = own.dataset;
				ownImages.set(box, { url: d.ownUrl || '', width: Number(d.ownWidth) || 0, height: Number(d.ownHeight) || 0, alt: d.ownAlt === '1' });
			}

			const saved = storage.get(TAB_KEY);
			if (saved && box.querySelector(`[data-hodima-dc-tab="${saved}"]`)) selectTab(box, saved);

			if (box.dataset.context !== 'post') return; // دسته محصول: تصویر دسته را ووکامرس بدون رویداد عوض می‌کند؛ بعد از ذخیره
			if (window.wp?.data?.select?.('core/editor')?.getCurrentPostId?.()) watchBlockEditor(box);
			else watchClassicEditor(box);
		});
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
