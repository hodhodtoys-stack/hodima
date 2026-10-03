/**
 * Media System — کادر «تنظیمات رسانه» (پیشخوان)
 * Path: media-system/js/media-admin.js
 *
 * جاوااسکریپت خالص (نسخه قبلی jQuery بود). کارها:
 *   - انتخاب از کتابخانه رسانه (ویدیو، صوت، کاور، تصویر Discover) + خواندن مدت و ابعاد فایل
 *   - بررسی زنده: سرویس لینک ویدیو، قالب مدت، فصل‌ها، عنوان و تصویر Discover
 *   - سوالات متداول: افزودن، جابه‌جایی، حذف (بدون پنجره confirm مرورگر)
 */
(() => {
	'use strict';

	const config = window.hodimaMediaAdmin || { minWidth: 1200, clickbait: [], ratios: [] };
	const faDigits = (s) => String(s).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
	const nf = new Intl.NumberFormat('fa-IR', { useGrouping: false });

	/** پیام وضعیت زیر یک فیلد. */
	function setStatus(field, text, level = 'info') {
		const el = field?.querySelector('[data-hodima-status]');
		if (!el) return;
		el.textContent = text;
		el.dataset.level = level;
		el.hidden = text === '';
		field.querySelector('input, textarea, select')?.setAttribute('aria-invalid', level === 'error' ? 'true' : 'false');
	}

	/* ── لینک ویدیو: کدام سرویس؟ (همان قوانین hodima_media_parse_video_url) ── */
	function parseVideo(url) {
		let u;
		try { u = new URL(url); } catch { return null; }
		const host = u.hostname.toLowerCase();
		const path = u.pathname;
		if (/\.(mp4|webm|ogg|ogv|mov|m4v)$/i.test(path)) return { provider: 'file', label: 'فایل ویدیو' };
		let m = host.endsWith('aparat.com') && path.match(/^\/(?:v\/|video\/video\/embed\/videohash\/)([A-Za-z0-9]+)/);
		if (m) return { provider: 'aparat', label: `آپارات (شناسه ${m[1]})` };
		if (host.endsWith('youtube.com') || host === 'youtu.be') {
			m = path.match(/^\/shorts\/([\w-]+)/);
			if (m) return { provider: 'youtube', label: 'یوتیوب شورتز (عمودی)', vertical: true };
			const id = host === 'youtu.be' ? path.slice(1) : (u.searchParams.get('v') || path.match(/^\/embed\/([\w-]+)/)?.[1] || '');
			if (/^[\w-]{6,20}$/.test(id)) return { provider: 'youtube', label: 'یوتیوب' };
		}
		if (/(^|\.)vimeo\.com$/.test(host) && /^\/(?:video\/)?\d+/.test(path)) return { provider: 'vimeo', label: 'ویمئو' };
		return { provider: 'other', label: '' };
	}

	function checkVideoUrl(input) {
		const field = input.closest('.hodima-mb__field');
		const url = input.value.trim();
		if (url === '') return setStatus(field, '');
		const info = parseVideo(url);
		if (!info) return setStatus(field, 'لینک کامل نیست (باید با https:// شروع شود).', 'error');
		if (info.provider === 'other') return setStatus(field, 'سرویس شناخته نشد؛ پخش با جاسازی خودکار وردپرس امتحان می‌شود. برای آپارات لینک صفحه ویدیو (aparat.com/v/…) را بگذارید.', 'warn');
		setStatus(field, `شناخته شد: ${info.label}`, 'ok');
	}

	/* ── مدت ── */
	function durationSeconds(value) {
		const v = faDigits(value).trim();
		if (v === '') return 0;
		if (/^\d+$/.test(v)) return Number(v);
		let m = v.match(/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
		if (m) return m[1] * 3600 + m[2] * 60 + Number(m[3]);
		m = v.match(/^(\d{1,3}):(\d{1,2})$/);
		if (m) return m[1] * 60 + Number(m[2]);
		m = v.match(/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?$/i);
		return m && v.length > 2 ? (m[1] || 0) * 3600 + (m[2] || 0) * 60 + Number(m[3] || 0) : -1;
	}

	function checkDuration(input) {
		const field = input.closest('.hodima-mb__field');
		const s = durationSeconds(input.value);
		if (input.value.trim() === '') return setStatus(field, '');
		if (s <= 0) return setStatus(field, 'قالب درست نیست؛ مثلا 2:35 یا 1:05:20. با این مقدار، مدت قبلی نگه داشته می‌شود.', 'error');
		setStatus(field, `${nf.format(Math.floor(s / 60))} دقیقه و ${nf.format(s % 60)} ثانیه`, 'ok');
	}

	/* ── فصل‌ها ── */
	function checkChapters(textarea) {
		const field = textarea.closest('.hodima-mb__field');
		const lines = faDigits(textarea.value).split(/\r?\n/).filter((l) => l.trim() !== '');
		if (!lines.length) return setStatus(field, '');
		const bad = lines.filter((l) => !/^\s*\d{1,2}(?::\d{1,2}){1,2}\s*[-–—|:.)]?\s*\S/.test(l));
		bad.length
			? setStatus(field, `${nf.format(bad.length)} خط فهم نشد (هر خط باید با زمان شروع شود، مثل «1:20 رنگ‌بندی»): ${bad[0].trim()}`, 'warn')
			: setStatus(field, `${nf.format(lines.length)} فصل.`, 'ok');
	}

	/* ── Discover ── */
	function setCheck(key, level, text) {
		const row = document.querySelector(`[data-hodima-check="${key}"]`);
		if (!row) return;
		row.className = `is-${level}`;
		row.querySelector('.dashicons').className = `dashicons ${{ ok: 'dashicons-yes-alt', warn: 'dashicons-warning', error: 'dashicons-dismiss' }[level]}`;
		row.querySelector('[data-hodima-check-text]').textContent = text;
	}

	function checkDiscoverTitle(input) {
		const title = input.value.trim() || input.dataset.hodimaFallback || '';
		const len = [...title].length;
		const field = input.closest('.hodima-mb__field');
		const bait = config.clickbait.find((p) => title.includes(p));
		let level = 'ok';
		let text = `${nf.format(len)} کاراکتر.`;
		if (bait) [level, text] = ['warn', 'عبارت اغراق‌آمیز یا طعمه کلیک دارد؛ گوگل در Discover آن را جریمه می‌کند.'];
		else if (len < 30) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید (۴۰ تا ۱۰۰ کاراکتر).`];
		else if (len > 110) [level, text] = ['warn', `${nf.format(len)} کاراکتر؛ بیشتر از ۱۱۰ کوتاه می‌شود.`];
		setCheck('title', level, text);
		setStatus(field, input.value.trim() === '' ? '' : text, level);
	}

	function checkDiscoverImage(field, attachment) {
		if (!attachment) return setStatus(field, '');
		const w = Number(attachment.width) || 0;
		const ok = w >= config.minWidth;
		const text = ok
			? `${nf.format(w)}×${nf.format(Number(attachment.height) || 0)} پیکسل؛ مناسب. برش‌ها بعد از ذخیره ساخته می‌شوند.`
			: `عرض ${nf.format(w)} پیکسل است؛ برای کارت بزرگ Discover حداقل ${nf.format(config.minWidth)} لازم است.`;
		setStatus(field, text, ok ? 'ok' : 'error');
		setCheck('image', ok ? 'ok' : 'error', text);
	}

	/* ── انتخاب از کتابخانه رسانه ── */
	function nearestRatio(w, h) {
		if (!w || !h) return '';
		let best = '';
		let delta = Infinity;
		for (const r of config.ratios) {
			const [rw, rh] = r.split(':').map(Number);
			const d = Math.abs(w / h - rw / rh);
			if (d < delta) [best, delta] = [r, d];
		}
		return best;
	}

	function clockFromSeconds(s) {
		s = Math.round(s);
		const pad = (n) => String(n).padStart(2, '0');
		return s >= 3600 ? `${Math.floor(s / 3600)}:${pad(Math.floor(s % 3600 / 60))}:${pad(s % 60)}` : `${Math.floor(s / 60)}:${pad(s % 60)}`;
	}

	function openPicker(field) {
		const type = field.dataset.hodimaPicker;
		if (!window.wp?.media) return;
		const titles = { image: 'انتخاب تصویر', video: 'انتخاب ویدیو', audio: 'انتخاب فایل صوتی' };
		const frame = wp.media({ title: titles[type], button: { text: 'انتخاب' }, multiple: false, library: { type } });

		frame.on('select', () => {
			const a = frame.state().get('selection').first().toJSON();
			const urlInput = field.querySelector('[data-hodima-url]');
			const idInput = field.querySelector('[data-hodima-id]');
			if (urlInput) {
				urlInput.value = a.url;
				urlInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
			if (idInput) idInput.value = a.id;

			const preview = field.querySelector('[data-hodima-preview]');
			if (preview) {
				preview.src = a.sizes?.medium?.url || a.url;
				preview.hidden = false;
			}
			field.querySelector('[data-hodima-clear]')?.removeAttribute('hidden');

			// مدت و نسبت تصویر از خود فایل
			const section = field.closest('.hodima-mb__body');
			const seconds = Number(a.fileLength ? durationSeconds(a.fileLength) : 0);
			const duration = section?.querySelector('[data-hodima-duration]');
			if (duration && seconds > 0 && duration.value.trim() === '') {
				duration.value = clockFromSeconds(seconds);
				checkDuration(duration);
			}
			const ratio = section?.querySelector('[data-hodima-ratio]');
			if (type === 'video' && ratio && ratio.value === 'auto') {
				const r = nearestRatio(Number(a.width), Number(a.height));
				if (r) ratio.value = r;
			}

			if (field.matches('[data-hodima-discover-image]')) checkDiscoverImage(field, a);
		});

		frame.open();
	}

	function clearPicker(field) {
		field.querySelectorAll('[data-hodima-url], [data-hodima-id]').forEach((i) => { i.value = ''; });
		const preview = field.querySelector('[data-hodima-preview]');
		if (preview) { preview.hidden = true; preview.removeAttribute('src'); }
		field.querySelector('[data-hodima-clear]')?.setAttribute('hidden', '');
		setStatus(field, '');
	}

	/* ── سوالات متداول ── */
	let faqCounter = Date.now();

	function addFaq(box) {
		const list = box.querySelector('[data-hodima-faqs]');
		const tpl = box.querySelector('[data-hodima-faq-template]');
		if (!list || !tpl) return;
		const index = `n${faqCounter++}`;
		list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', index));
		list.lastElementChild?.querySelector('input')?.focus();
	}

	function removeFaq(button) {
		// کلیک اول: تأیید درجا؛ کلیک دوم ظرف ۴ ثانیه: حذف (به جای confirm مرورگر)
		if (button.dataset.armed !== '1') {
			button.dataset.armed = '1';
			button.classList.add('is-armed');
			button.setAttribute('aria-label', 'برای حذف دوباره کلیک کنید');
			button.title = 'برای حذف دوباره کلیک کنید';
			setTimeout(() => {
				delete button.dataset.armed;
				button.classList.remove('is-armed');
				button.setAttribute('aria-label', 'حذف این سوال');
				button.title = '';
			}, 4000);
			return;
		}
		const row = button.closest('[data-hodima-faq]');
		const next = row.nextElementSibling || row.previousElementSibling;
		row.remove();
		next?.querySelector('input')?.focus();
	}

	function moveFaq(button) {
		const row = button.closest('[data-hodima-faq]');
		const dir = Number(button.dataset.hodimaMove);
		const sibling = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
		if (!sibling) return;
		dir < 0 ? sibling.before(row) : sibling.after(row);
		button.focus();
	}

	/* ── رویدادها (یک شنونده برای همه) ── */
	document.addEventListener('click', (e) => {
		const t = e.target.closest?.('button');
		if (!t || !t.closest('[data-hodima-mb]')) return;
		const field = t.closest('[data-hodima-picker]');

		if (t.matches('[data-hodima-pick]') && field) { e.preventDefault(); openPicker(field); }
		else if (t.matches('[data-hodima-clear]') && field) { e.preventDefault(); clearPicker(field); }
		else if (t.matches('[data-hodima-faq-add]')) { e.preventDefault(); addFaq(t.closest('.hodima-mb__body')); }
		else if (t.matches('[data-hodima-remove]')) { e.preventDefault(); removeFaq(t); }
		else if (t.matches('[data-hodima-move]')) { e.preventDefault(); moveFaq(t); }
	});

	document.addEventListener('input', (e) => {
		const t = e.target;
		if (!t.closest?.('[data-hodima-mb]')) return;

		if (t.matches('#hodima-mb-video-url')) checkVideoUrl(t);
		if (t.matches('[data-hodima-duration]')) checkDuration(t);
		if (t.matches('[data-hodima-chapters]')) checkChapters(t);
		if (t.matches('[data-hodima-discover-title]')) checkDiscoverTitle(t);

		// پیش‌نمایش کاور با تایپ یا چسباندن آدرس
		if (t.matches('[data-hodima-url]')) {
			const field = t.closest('[data-hodima-picker]');
			const preview = field?.querySelector('[data-hodima-preview]');
			const url = t.value.trim();
			if (preview) {
				/^https?:\/\//i.test(url) ? (preview.src = url, preview.hidden = false) : (preview.hidden = true);
			}
			field?.querySelector('[data-hodima-clear]')?.toggleAttribute('hidden', url === '');
			// آدرس دستی جای شناسه پیوست قبلی را می‌گیرد (سرور دوباره پیدا می‌کند)
			if (e.isTrusted) {
				const id = field?.querySelector('[data-hodima-id]');
				if (id) id.value = '';
			}
		}
	});

	function init() {
		document.querySelectorAll('[data-hodima-mb]').forEach((box) => {
			const url = box.querySelector('#hodima-mb-video-url');
			if (url) checkVideoUrl(url);
			box.querySelectorAll('[data-hodima-duration]').forEach(checkDuration);
			box.querySelectorAll('[data-hodima-chapters]').forEach(checkChapters);
		});
	}

	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
