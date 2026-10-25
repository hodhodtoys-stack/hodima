/**
 * Hodima Router — جستجوی لینک‌های داخلی قدیمی و خراب
 * Path: core/router/assets/router-links.js
 *
 * جاوااسکریپت خالص: هر درخواست admin-ajax (hodima_router_links) یک دسته از یک
 * منبع (نوشته‌ها، دسته‌ها، منوها، تنظیمات قالب) را می‌گردد؛ «source» و «cursor»
 * پاسخ جای ادامه است. ردیف‌ها همان‌جا به جدول اضافه و در پایان به ترتیب
 * اهمیت (بدشکل، خراب، قدیمی) مرتب می‌شوند. چیزی ذخیره نمی‌شود.
 * pill و where و fix از سرور escape‌شده می‌آیند؛ لینک فقط با textContent.
 */
(() => {
	'use strict';

	const config = window.hodimaRouterLinks || {};
	const nf = new Intl.NumberFormat('fa-IR');
	const ORDER = { malformed: 0, broken: 1, old: 2 };
	const LABELS = { malformed: 'بدشکل', broken: 'خراب (۴۰۴)', old: 'قدیمی' };

	function addRow(tbody, row) {
		const tr = document.createElement('tr');
		tr.dataset.order = String(ORDER[row.issue] ?? 9);

		const where = document.createElement('td');
		where.innerHTML = row.where;
		const field = document.createElement('span');
		field.className = 'hodima-router__field';
		field.textContent = row.field;
		where.append(field);
		if (row.edit) {
			const edit = document.createElement('a');
			edit.href = row.edit;
			edit.textContent = 'ویرایش';
			field.append(' · ', edit);
		}

		const link = document.createElement('td');
		link.className = 'hodima-router__url';
		link.textContent = row.link;

		const issue = document.createElement('td');
		issue.innerHTML = row.pill;

		const fix = document.createElement('td');
		fix.innerHTML = row.fix;

		tr.append(where, link, issue, fix);
		tbody.append(tr);
	}

	function renderSummary(box, counts) {
		box.replaceChildren();
		for (const [issue, count] of Object.entries(counts)) {
			if (!count) continue;
			const pill = document.createElement('span');
			pill.className = `hds-pill hds-pill--${issue === 'old' ? 'warn' : 'error'}`;
			pill.textContent = `${LABELS[issue]}: ${nf.format(count)}`;
			box.append(pill);
		}
		box.hidden = !box.childElementCount;
	}

	async function run(card) {
		const button = card.querySelector('[data-hodima-router-links-start]');
		const msg = card.querySelector('[data-hodima-router-links-msg]');
		const progress = card.querySelector('[data-hodima-router-links-progress]');
		const summary = card.querySelector('[data-hodima-router-links-summary]');
		const wrap = card.querySelector('[data-hodima-router-links-table]');
		const tbody = wrap.querySelector('tbody');
		const counts = { malformed: 0, broken: 0, old: 0 };

		button.disabled = true;
		tbody.replaceChildren();
		wrap.hidden = true;
		summary.hidden = true;
		progress.hidden = false;
		progress.value = 0;
		msg.textContent = 'در حال جستجو…';

		let source = '';
		let cursor = 0;

		try {
			for (;;) {
				const body = new URLSearchParams({ action: 'hodima_router_links', nonce: config.nonce || '', source, cursor: String(cursor) });
				const res = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
				const json = await res.json().catch(() => null);
				if (!res.ok || !json?.success) throw new Error(json?.data?.message || `خطای سرور (${res.status})`);

				const data = json.data;
				for (const row of data.rows) {
					addRow(tbody, row);
					counts[row.issue] = (counts[row.issue] || 0) + 1;
				}
				if (data.rows.length) {
					wrap.hidden = false;
					renderSummary(summary, counts);
				}

				progress.value = data.total ? Math.round((100 * data.done) / data.total) : 100;
				msg.textContent = `${data.label}: ${nf.format(data.done)} از ${nf.format(data.total)}`;

				if (data.end) break;
				source = data.source;
				cursor = data.cursor;
			}

			// مرتب‌سازی پایدار: بدشکل، خراب، قدیمی
			const rows = [...tbody.rows].map((tr, i) => [tr, Number(tr.dataset.order), i]);
			rows.sort((a, b) => a[1] - b[1] || a[2] - b[2]);
			tbody.append(...rows.map(([tr]) => tr));

			const total = counts.malformed + counts.broken + counts.old;

			// کارت آمار بالای صفحه هم با نتیجه جستجو تازه شود
			const stat = document.querySelector('[data-hodima-router-links-stat]');
			if (stat) {
				stat.textContent = nf.format(total);
				stat.toggleAttribute('data-empty', false);
				const sub = document.querySelector('[data-hodima-router-links-stat-sub]');
				if (sub) sub.textContent = total ? 'لینک نیاز به اصلاح دارد' : 'همه لینک‌ها سالم‌اند';
			}

			msg.textContent = total
				? `تمام شد؛ ${nf.format(total)} لینک نیاز به اصلاح دارد.`
				: 'تمام شد؛ هیچ لینک داخلی قدیمی یا خرابی پیدا نشد.';
		} catch (error) {
			msg.textContent = `متوقف شد: ${error.message}`;
		} finally {
			progress.hidden = true;
			button.disabled = false;
		}
	}

	document.querySelectorAll('[data-hodima-router-links]').forEach((card) => {
		card.querySelector('[data-hodima-router-links-start]')?.addEventListener('click', () => run(card));
	});
})();
