/*
 * لینک‌های مرتبط دستی — ثبت کلیک در Google Analytics / Tag Manager
 * Path: plugins/hodima-seo/inc/manual_related_link/assets/front.js
 *
 * فقط وقتی «ثبت کلیک» در تنظیمات روشن است و کادری در صفحه هست لود می‌شود.
 * با gtag: رویداد استاندارد select_content؛ بدون آن، رویداد dataLayer برای GTM.
 */
(() => {
	'use strict';

	document.addEventListener('click', (event) => {
		const card = event.target instanceof Element ? event.target.closest('a[data-hodima-rl]') : null;
		if (!card) return;

		const detail = {
			content_type: 'related_link',
			item_id: card.href,
			hodima_rl_group: card.dataset.hodimaRl ?? '',
			hodima_rl_position: Number(card.dataset.hodimaRlPos ?? 0),
		};

		if (typeof window.gtag === 'function') {
			window.gtag('event', 'select_content', { ...detail, transport_type: 'beacon' });
		} else if (Array.isArray(window.dataLayer)) {
			window.dataLayer.push({ event: 'hodima_related_click', ...detail });
		}
	});
})();
