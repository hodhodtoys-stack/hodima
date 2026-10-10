/**
 * ماژول «گوگل دیسکاور» — پنل «گوگل دیسکاور» در نوار کناری ویرایشگر بلوکی
 * Path: core/discover/assets/discover-panel.js
 *
 * SEO 2.1.8. کادر دیسکاور پایین صفحه ویرایش است و در ویرایشگر بلوکی باید تا
 * پایین اسکرول کرد. این پنل (تب «نوشته»/«برگه» نوار کناری) خلاصه همان کادر را
 * همیشه جلوی چشم می‌گذارد: امتیاز آمادگی، موارد نیازمند توجه، عنوان کارت و
 * دکمه‌های «باز کردن کادر» و «پیش‌نمایش کارت».
 *
 * داده از خود کادر (DOM) خوانده می‌شود و فیلدی در این پنل ذخیره نمی‌شود؛ پس
 * یک مسیر ذخیره می‌ماند (همان کادر) و دو ویرایشگر روی هم نمی‌نویسند. کادر با
 * هر تغییر رویداد «hodima-discover-change» می‌فرستد (discover-admin.js).
 * جاوااسکریپت خالص با wp.element (بدون مرحله ساخت).
 */
(() => {
	'use strict';

	const wp = window.wp;
	const Panel = wp?.editor?.PluginDocumentSettingPanel || wp?.editPost?.PluginDocumentSettingPanel;
	if (!wp?.plugins?.registerPlugin || !wp?.element || !Panel) return;

	const { createElement: h, useState, useEffect } = wp.element;
	const box = () => document.querySelector('[data-hodima-dc][data-context="post"]');

	/** خلاصه کادر: امتیاز، موارد نیازمند توجه، عنوان کارت. */
	function read() {
		const b = box();
		if (!b) return null;
		const ring = b.querySelector('[data-hodima-dc-ring]');
		const title = b.querySelector('[data-hodima-dc-title]');
		return {
			score: b.querySelector('[data-hodima-dc-ring-text]')?.textContent || '',
			level: (ring?.className.match(/is-(ok|warn|error)/) || [])[1] || 'warn',
			summary: b.querySelector('[data-hodima-dc-summary]')?.textContent || '',
			issues: [...b.querySelectorAll('[data-hodima-dc-issues] [data-hodima-dc-check]')].map((row) => ({
				key: row.dataset.hodimaDcCheck,
				label: row.querySelector('.hodima-dc__check-label')?.textContent || '',
				error: row.classList.contains('is-error'),
			})),
			title: title?.value.trim() || title?.dataset.hodimaDcFallback || '',
		};
	}

	/** رفتن به کادر پایین صفحه و (اختیاری) باز کردن یک تب آن. */
	function openBox(tab) {
		const b = box();
		if (!b) return;
		const meta = b.closest('.postbox');
		meta?.classList.remove('closed');
		if (tab) b.querySelector(`[data-hodima-dc-tab="${tab}"]`)?.click();
		(meta || b).scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
	}

	function DiscoverPanel() {
		const [state, setState] = useState(read);

		useEffect(() => {
			const update = () => setState(read());
			document.addEventListener('hodima-discover-change', update);
			const timer = window.setTimeout(update, 500); // کادر شاید بعد از پنل در DOM جابه‌جا شود
			return () => { document.removeEventListener('hodima-discover-change', update); window.clearTimeout(timer); };
		}, []);

		if (!state) {
			return h('p', { className: 'hodima-dp__empty' }, 'کادر گوگل دیسکاور در این صفحه نیست.');
		}

		return h('div', { className: 'hodima-dp' },
			h('p', { className: `hodima-dp__score is-${state.level}` },
				h('strong', null, `آمادگی ${state.score}`), ' — ', state.summary),
			state.issues.length ? h('ul', { className: 'hodima-dp__issues' },
				state.issues.slice(0, 8).map((issue) => h('li', { key: issue.key, className: issue.error ? 'is-error' : 'is-warn' },
					h('button', { type: 'button', className: 'hodima-dp__link', onClick: () => openBox('checks') }, issue.label)))) : null,
			state.title ? h('p', { className: 'hodima-dp__title' }, h('span', null, 'عنوان کارت: '), state.title) : null,
			h('p', { className: 'hodima-dp__actions' },
				h('button', { type: 'button', className: 'components-button is-secondary', onClick: () => openBox('settings') }, 'باز کردن کادر دیسکاور'),
				' ',
				h('button', { type: 'button', className: 'components-button is-tertiary', onClick: () => box()?.querySelector('[data-hodima-dc-open-preview]')?.click() }, 'پیش‌نمایش کارت')));
	}

	wp.plugins.registerPlugin('hodima-discover', {
		render: () => h(Panel, { name: 'hodima-discover', title: 'گوگل دیسکاور', className: 'hodima-dp-panel' }, h(DiscoverPanel)),
	});
})();
