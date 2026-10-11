/*
 * تاریخ شمسی کنار فیلدهای تاریخ پیشخوان ووکامرس + تقویم شمسی.
 * مقدار فیلد همیشه میلادی «Y-m-d» (همان که ووکامرس ذخیره می‌کند) می‌ماند؛ تقویم ووکامرس هم مثل قبل کار می‌کند.
 * فیلدهای تنوع محصول بعدا با AJAX ساخته می‌شوند؛ MutationObserver آن‌ها را هم می‌گیرد.
 * تبدیل همان الگوریتم Hodima Core (includes/jalali.php).
 */
(() => {
	'use strict';

	const config = window.hodimaJalaliAdmin;
	if (!config) {
		return;
	}
	const SELECTOR = [
		'input.date-picker',
		'input.range_datepicker',
		'input[name="order_date"]',
		'input[name="_sale_price_dates_from"]',
		'input[name="_sale_price_dates_to"]',
		'input[name^="variable_sale_price_dates_"]',
		'input[name^="access_expires"]',
		'input#expiry_date',
	].join(',');
	const div = (a, b) => Math.floor(a / b);
	const fa = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

	const toJalali = (gy, gm, gd) => {
		const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		const gy2 = gm > 2 ? gy + 1 : gy;
		let days = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + gdm[gm - 1];
		let jy = -1595 + 33 * div(days, 12053);
		days %= 12053;
		jy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			jy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		const jm = days < 186 ? 1 + div(days, 31) : 7 + div(days - 186, 30);
		const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
		return [jy, jm, jd];
	};

	const toGregorian = (jy0, jm, jd) => {
		const jy = jy0 + 1595;
		let days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
		let gy = 400 * div(days, 146097);
		days %= 146097;
		if (days > 36524) {
			days--;
			gy += 100 * div(days, 36524);
			days %= 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			gy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		let gd = days + 1;
		const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
		const months = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		let gm = 1;
		for (const length of months) {
			if (gd <= length) {
				break;
			}
			gd -= length;
			gm++;
		}
		return [gy, gm, gd];
	};

	const monthDays = (jy, jm) => {
		if (jm <= 6) {
			return 31;
		}
		if (jm <= 11) {
			return 30;
		}
		const [gy, gm, gd] = toGregorian(jy, 12, 30);
		const [y, m] = toJalali(gy, gm, gd);
		return y === jy && m === 12 ? 30 : 29;
	};

	const parse = (value) => {
		const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value).trim());
		return match ? toJalali(Number(match[1]), Number(match[2]), Number(match[3])) : null;
	};

	const iso = (jy, jm, jd) => toGregorian(jy, jm, jd).map((n, i) => String(n).padStart(i ? 2 : 4, '0')).join('-');

	const label = ([jy, jm, jd]) => `${fa(jd)} ${config.months[jm - 1]} ${fa(jy)}`;

	/** رویداد change که ووکامرس (jQuery) و شنونده‌های معمولی هر دو ببینند. */
	const fireChange = (input) => {
		if (window.jQuery) {
			window.jQuery(input).trigger('change');
		} else {
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}
	};

	/* ---------- تقویم (یک پنجره مشترک برای همه فیلدها) ---------- */

	const panel = document.createElement('div');
	panel.className = 'hodima-jcal';
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-label', 'تقویم شمسی');
	panel.hidden = true;
	document.body.append(panel);

	let current = null; // { input, button, jy, jm, focus }

	const close = (returnFocus) => {
		if (!current) {
			return;
		}
		panel.hidden = true;
		current.button.setAttribute('aria-expanded', 'false');
		if (returnFocus) {
			current.button.focus();
		}
		current = null;
	};

	const choose = (value) => {
		const { input } = current;
		input.value = value;
		fireChange(input);
		close(true);
	};

	const draw = () => {
		const { input, jy, jm } = current;
		const selected = parse(input.value);
		const today = parse(config.today);
		const [gy, gm, gd] = toGregorian(jy, jm, 1);
		const offset = (new Date(Date.UTC(gy, gm - 1, gd)).getUTCDay() + 1) % 7; // ۰ = شنبه
		const total = monthDays(jy, jm);
		const focusDay = Math.min(current.focus ?? selected?.[2] ?? 1, total);

		const head = `<div class="hodima-jcal__head">
			<button type="button" class="hodima-jcal__nav" data-step="-1" aria-label="ماه قبل"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
			<strong class="hodima-jcal__title" aria-live="polite">${config.months[jm - 1]} ${fa(jy)}</strong>
			<button type="button" class="hodima-jcal__nav" data-step="1" aria-label="ماه بعد"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
		</div>`;
		let cells = config.weekdays.map((name) => `<span class="hodima-jcal__wd" title="${name}">${name.charAt(0)}</span>`).join('');
		cells += '<span class="hodima-jcal__pad"></span>'.repeat(offset);
		for (let d = 1; d <= total; d++) {
			const isSelected = selected && selected[0] === jy && selected[1] === jm && selected[2] === d;
			const isToday = today && today[0] === jy && today[1] === jm && today[2] === d;
			cells += `<button type="button" class="hodima-jcal__day${isToday ? ' is-today' : ''}" data-day="${d}" aria-pressed="${isSelected ? 'true' : 'false'}" aria-label="${label([jy, jm, d])}" tabindex="${d === focusDay ? 0 : -1}">${fa(d)}</button>`;
		}
		panel.innerHTML = `${head}<div class="hodima-jcal__grid">${cells}</div>
			<div class="hodima-jcal__foot">
				<button type="button" class="button button-small" data-action="today">امروز</button>
				<button type="button" class="button button-small" data-action="clear">پاک کردن</button>
			</div>`;
	};

	const focusDay = (day) => {
		current.focus = day;
		draw();
		panel.querySelector(`[data-day="${day}"]`)?.focus();
	};

	const move = (days) => {
		let { jy, jm } = current;
		let day = (current.focus ?? 1) + days;
		if (day < 1) {
			jm--;
			if (jm < 1) {
				jm = 12;
				jy--;
			}
			day += monthDays(jy, jm);
		} else if (day > monthDays(jy, jm)) {
			day -= monthDays(jy, jm);
			jm++;
			if (jm > 12) {
				jm = 1;
				jy++;
			}
		}
		Object.assign(current, { jy, jm });
		focusDay(day);
	};

	const open = (input, button) => {
		if (current?.input === input) {
			close(true);
			return;
		}
		close(false);
		const [jy, jm, jd] = parse(input.value) ?? parse(config.today) ?? [1404, 1, 1];
		current = { input, button, jy, jm, focus: jd };
		button.setAttribute('aria-expanded', 'true');
		draw();
		panel.hidden = false;
		const rect = button.getBoundingClientRect();
		const left = Math.max(8, Math.min(rect.right - panel.offsetWidth, document.documentElement.clientWidth - panel.offsetWidth - 8));
		// جای پنجره زیر دکمه، داخل عرض صفحه (مختصات فیزیکی صفحه؛ مستقل از جهت)
		panel.style.top = `${rect.bottom + window.scrollY + 4}px`;
		panel.style.left = `${left + window.scrollX}px`;
		panel.querySelector('[tabindex="0"]')?.focus();
	};

	panel.addEventListener('click', (event) => {
		const target = event.target.closest('button');
		if (!target || !current) {
			return;
		}
		if (target.dataset.step) {
			const step = Number(target.dataset.step);
			current.jm += step;
			if (current.jm < 1) {
				current.jm = 12;
				current.jy--;
			} else if (current.jm > 12) {
				current.jm = 1;
				current.jy++;
			}
			draw();
			panel.querySelector(`[data-step="${step}"]`)?.focus();
		} else if (target.dataset.day) {
			choose(iso(current.jy, current.jm, Number(target.dataset.day)));
		} else if (target.dataset.action === 'today') {
			choose(config.today);
		} else if (target.dataset.action === 'clear') {
			choose('');
		}
	});

	// صفحه راست‌به‌چپ است: چپ = روز بعد
	const KEYS = { ArrowLeft: 1, ArrowRight: -1, ArrowDown: 7, ArrowUp: -7 };
	panel.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			event.preventDefault();
			close(true);
		} else if (KEYS[event.key] && event.target.dataset.day) {
			event.preventDefault();
			move(KEYS[event.key]);
		}
	});

	document.addEventListener(
		'pointerdown',
		(event) => {
			if (current && !panel.contains(event.target) && !current.button.contains(event.target)) {
				close(false);
			}
		},
		true
	);

	/* ---------- برچسب و دکمه کنار هر فیلد ---------- */

	const enhance = (input) => {
		if (input.dataset.hodimaJalali || input.type === 'hidden') {
			return;
		}
		input.dataset.hodimaJalali = '1';
		const box = document.createElement('span');
		box.className = 'hodima-jdate';
		box.innerHTML = `<button type="button" class="hodima-jdate__btn" aria-expanded="false" aria-haspopup="dialog" aria-label="انتخاب از تقویم شمسی">
			<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></button>
			<span class="hodima-jdate__text" aria-live="polite"></span>`;
		input.after(box);
		const text = box.querySelector('.hodima-jdate__text');
		const update = () => {
			const jalali = parse(input.value);
			text.textContent = jalali ? label(jalali) : '';
		};
		update();
		input.addEventListener('input', update);
		input.addEventListener('change', update);
		// تقویم میلادی ووکامرس (jQuery UI) رویداد jQuery می‌فرستد
		window.jQuery?.(input).on('change', update);
		box.querySelector('button').addEventListener('click', (event) => open(input, event.currentTarget));
	};

	const scan = (root) => root.querySelectorAll?.(SELECTOR).forEach(enhance);
	scan(document);
	new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => node.nodeType === 1 && (node.matches(SELECTOR) ? enhance(node) : scan(node))))).observe(document.body, { childList: true, subtree: true });
})();
