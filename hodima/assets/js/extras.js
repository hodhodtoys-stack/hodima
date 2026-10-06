/**
 * امکانات کاربری (inc/extras.php): دکمه بازگشت به بالا و رفتار نوار پایین موبایل.
 * فقط وقتی یکی از این دو در تنظیمات قالب روشن است بارگذاری می‌شود (defer).
 */

(() => {
	'use strict';

	const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const scrollTop = () => window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });

	/** بازگشت به بالا: بعد از ۶۰۰ پیکسل اسکرول دیده می‌شود؛ تمرکز کیبورد به ابتدای هدر برمی‌گردد. */
	const toTop = document.querySelector('[data-hodima-to-top]');

	if (toTop) {
		let ticking = false;

		const update = () => {
			toTop.classList.toggle('is-visible', window.scrollY > 600);
			ticking = false;
		};

		window.addEventListener('scroll', () => {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		}, { passive: true });

		toTop.addEventListener('click', () => {
			scrollTop();
			document.querySelector('#mainHeader a, #mainHeader button')?.focus({ preventScroll: true });
		});

		update();
	}

	/**
	 * نوار موبایل: «جستجو» کادر جستجوی هدر را فعال می‌کند و «پشتیبانی» پنجره هدر را باز
	 * می‌کند. بدون JS همان لینک‌ها به هدر می‌روند.
	 */
	document.querySelectorAll('[data-hodima-nav-action]').forEach((link) => {
		link.addEventListener('click', (event) => {
			if (link.dataset.hodimaNavAction === 'support') {
				const trigger = document.getElementById('supportTrigger');
				if (trigger) {
					event.preventDefault();
					trigger.click();
				}
				return;
			}

			const input = document.querySelector('.header__search input:not([type="hidden"], [type="submit"])');
			if (input) {
				event.preventDefault();
				scrollTop();
				input.focus({ preventScroll: true });
			}
		});
	});
})();
