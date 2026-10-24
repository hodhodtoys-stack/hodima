/**
 * Hodima Phone Form — Vanilla JS
 *
 * قاعده شماره همان hodima_phone_normalize() در phone-form.php است؛ هر تغییر هر دو جا.
 * پیش از ارسال، توکن امضاشده سرور گرفته و تا گذشتن حداقل زمان (waitMs) صبر می‌شود؛
 * ربات‌هایی که بلافاصله می‌فرستند رد می‌شوند و کاربر واقعی چیزی حس نمی‌کند.
 */
(() => {
	'use strict';

	const cfg = window.hodimaAjax;
	if (!cfg?.ajaxurl) return;

	const WAIT_MS = Number(cfg.waitMs) || 2300;
	const TIMEOUT_MS = 20000;

	const DIGITS = {
		'۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
		'٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
	};

	/** موبایل 09xxxxxxxxx یا ثابت با کد شهر (۱۱ رقم)؛ نامعتبر = رشته خالی. */
	const normalize = (raw = '') => {
		let d = String(raw).replace(/[۰-۹٠-٩]/g, (c) => DIGITS[c]).replace(/\D+/g, '');
		if (d.startsWith('0098')) d = d.slice(4);
		else if (d.startsWith('98') && d.length >= 11) d = d.slice(2);
		if (d.length === 10 && !d.startsWith('0')) d = `0${d}`;

		return /^(?:09\d{9}|0[1-8]\d{9})$/.test(d) ? d : '';
	};

	const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

	// توکن مشترک همه فرم‌های صفحه
	const session = { token: '', issuedAt: 0, pending: null };

	const fetchToken = (force = false) => {
		if (force) session.token = '';
		if (session.token) return Promise.resolve(session.token);
		if (session.pending) return session.pending;

		const body = new FormData();
		body.append('action', 'hodima_phone_nonce');

		session.pending = fetch(cfg.ajaxurl, { method: 'POST', body, credentials: 'same-origin' })
			.then((response) => response.json())
			.then((res) => {
				if (res?.success && typeof res.data === 'string') {
					session.token = res.data;
					session.issuedAt = Date.now();
				}
				return session.token;
			})
			.catch(() => '')
			.finally(() => {
				session.pending = null;
			});

		return session.pending;
	};

	/** توکن آماده با رعایت حداقل زمان از لحظه دریافتش. */
	const readyToken = async () => {
		const token = await fetchToken();
		const left = WAIT_MS - (Date.now() - session.issuedAt);
		if (token && left > 0) await sleep(left);
		return token;
	};

	/** درخواست با زمان‌سنج؛ { json } یا json=null اگر پاسخ JSON نبود (مثل صفحه خطای WAF). */
	const send = async (data) => {
		const controller = new AbortController();
		const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
		try {
			const response = await fetch(cfg.ajaxurl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
				signal: controller.signal,
			});
			return { json: await response.json().catch(() => null) };
		} finally {
			clearTimeout(timer);
		}
	};

	const init = (wrapper) => {
		const form = wrapper.querySelector('.hodima-phone-form');
		const input = wrapper.querySelector('.hodima-phone-input');
		const button = wrapper.querySelector('.hodima-phone-submit');
		const msg = wrapper.querySelector('.hodima-phone-msg');

		if (!form || !input || !button || form.dataset.ready) return;
		form.dataset.ready = '1';

		// توکن را از قبل بگیر تا هنگام ارسال معطل نشود
		const warm = () => {
			fetchToken();
		};
		wrapper.addEventListener('mouseenter', warm, { once: true });
		input.addEventListener('focus', warm, { once: true });
		input.addEventListener('touchstart', warm, { once: true, passive: true });

		const buttonText = button.textContent;
		let hideTimer = null;

		const show = (text, type) => {
			if (!msg) return;
			clearTimeout(hideTimer);
			msg.textContent = text;
			msg.className = `hodima-phone-msg hodima-msg-${type}`;
			msg.hidden = false;
			if (type === 'success') {
				hideTimer = setTimeout(() => {
					msg.hidden = true;
				}, 8000);
			}
		};

		const busy = (state) => {
			button.disabled = state;
			button.setAttribute('aria-busy', state ? 'true' : 'false');
			button.textContent = state ? 'در حال ثبت…' : buttonText;
		};

		input.addEventListener('input', () => input.removeAttribute('aria-invalid'));

		form.addEventListener('submit', async (e) => {
			e.preventDefault();

			const phone = normalize(input.value);

			if (!phone) {
				input.setAttribute('aria-invalid', 'true');
				show(input.value.trim() ? 'شماره وارد شده نامعتبر است. موبایل را با ۰۹ و شماره ثابت را با کد شهر وارد کنید.' : 'لطفا شماره تماس را وارد کنید.', 'error');
				input.focus();
				return;
			}

			busy(true);

			const data = new FormData();
			data.append('action', 'submit_hodima_phone');
			data.append('phone', phone);
			data.append('website', form.querySelector('input[name="website"]')?.value || '');
			data.append('page_url', window.location.href);
			data.append('page_title', document.title);

			try {
				let json = null;
				// اگر توکن رد شد (منقضی یا نیامده) یک بار با توکن تازه تکرار می‌شود
				for (let attempt = 0; attempt < 2; attempt++) {
					data.set('security_token', await readyToken());
					({ json } = await send(data));
					if (json?.data?.code !== 'token') break;
					session.token = '';
				}

				if (json?.success) {
					show(json.data?.message || 'شماره شما ثبت شد.', 'success');
					input.value = '';
					input.blur();
				} else if (json) {
					show(json.data?.message || 'خطا در ثبت درخواست. لطفا دوباره تلاش کنید.', 'error');
					input.focus();
				} else {
					show('پاسخ سرور درست نبود. کمی بعد دوباره تلاش کنید.', 'error');
				}
			} catch {
				show('ارتباط برقرار نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.', 'error');
			} finally {
				busy(false);
			}
		});
	};

	const boot = () => document.querySelectorAll('.hodima-phone-wrapper').forEach(init);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
