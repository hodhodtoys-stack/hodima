/*
 * دستار سئو — دکمه «کپی آدرس» کنار هر آدرس کامل (hodima_seo_advisor_url_copy؛ صفحه دستار سئو و کادر ویرایش).
 * بدون JS دکمه پنهان است (CSS) و آدرس را دستی انتخاب می‌کنید. ردیف‌هایی که بعدا با admin-ajax می‌آیند («لینک‌های این
 * کلمه») هم کار می‌کنند (یک شنونده روی سند).
 */
(() => {
	'use strict';

	document.documentElement.classList.add('hodima-advisor-js');

	/** پیام صفحه‌خوان (یک ناحیه زنده مشترک). */
	let live = null;
	const say = (text) => {
		if (!live) {
			live = document.createElement('span');
			live.className = 'screen-reader-text';
			live.setAttribute('aria-live', 'polite');
			document.body.append(live);
		}
		live.textContent = '';
		setTimeout(() => { live.textContent = text; }, 50);
	};

	document.addEventListener('click', async (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-hdsa-copy-url],[data-hdsa-copy]') : null;
		if (!button) return;

		// data-hdsa-copy-url = آدرس؛ data-hdsa-copy = هر متن (پیش‌نویس عنوان/متا، آدرس تصویر؛ SEO 3.3.6)
		const url = button.dataset.hdsaCopyUrl || button.dataset.hdsaCopy || '';
		const said = button.dataset.hdsaSaid || 'آدرس کپی شد';
		const label = button.getAttribute('aria-label') || '';
		const icon = button.querySelector('.dashicons');

		try {
			await navigator.clipboard.writeText(url);
			button.classList.add('is-copied');
			button.setAttribute('aria-label', 'کپی شد');
			icon?.classList.replace('dashicons-admin-page', 'dashicons-yes');
			say(said);
		} catch {
			// مرورگر کپی نداد (صفحه بدون HTTPS یا اجازه): خود آدرس انتخاب می‌شود تا با Ctrl+C کپی شود
			const link = button.parentElement?.querySelector('.hodima-advisor-url__link, .hodima-advisor__draft-text');
			if (link) window.getSelection()?.selectAllChildren(link);
			say('انتخاب شد؛ Ctrl+C را بزنید');
			return;
		}

		setTimeout(() => {
			button.classList.remove('is-copied');
			button.setAttribute('aria-label', label);
			icon?.classList.replace('dashicons-yes', 'dashicons-admin-page');
		}, 2000);
	});
})();
