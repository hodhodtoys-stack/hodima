/*
 * بازخورد زنده فیلدهای ایرانی فرم آدرس (تسویه‌حساب و «حساب کاربری ← ویرایش آدرس»).
 * همان قاعده‌های modules/class-iran-input.php؛ بررسی اصلی و ذخیره یکدست‌شده در سرور است.
 *   - هنگام نوشتن: رقم فارسی/عربی ← لاتین؛ نشانه سبز وقتی مقدار درست شد (خطا وسط نوشتن نه)؛
 *   - هنگام ترک فیلد: یکدست‌سازی (+98 / 98 / 912… ← 09…، حذف فاصله و خط تیره) و نشانه خطا.
 * باگ قبلی: «+98912…» در مرورگر قرمز می‌شد و خطا از اولین رقم نشان داده می‌شد.
 */
(() => {
	'use strict';

	const latin = (value) =>
		value.replace(/[۰-۹٠-٩]/g, (digit) => String(digit.charCodeAt(0) - (digit >= '۰' ? 0x06f0 : 0x0660)));

	const mobile = (value) => {
		let digits = latin(value).trim().replace(/[\s\-().]/g, '');
		digits = digits.replace(/^(\+98|0098|98)(?=9\d{9}$)/, '');
		return /^9\d{9}$/.test(digits) ? `0${digits}` : digits;
	};

	const postcode = (value) => latin(value).trim().replace(/[\s-]/g, '');

	const nationalId = (value) => {
		const digits = latin(value).trim().replace(/[\s-]/g, '');
		return /^\d{8,9}$/.test(digits) ? digits.padStart(10, '0') : digits;
	};

	const isNationalId = (value) => {
		if (!/^\d{10}$/.test(value) || /^(\d)\1{9}$/.test(value)) {
			return false;
		}
		let sum = 0;
		for (let i = 0; i < 9; i++) {
			sum += Number(value[i]) * (10 - i);
		}
		const rem = sum % 11;
		const check = Number(value[9]);
		return rem < 2 ? check === rem : check === 11 - rem;
	};

	const isPostcode = (value) => /^\d{10}$/.test(value);

	const RULES = {
		billing_phone: { normalize: mobile, valid: (value) => /^09\d{9}$/.test(value) },
		billing_postcode: { normalize: postcode, valid: isPostcode },
		shipping_postcode: { normalize: postcode, valid: isPostcode },
		billing_national_id: { normalize: nationalId, valid: isNationalId },
		shipping_phone: { normalize: latin, valid: null },
	};

	const inIran = (field) => {
		const country = document.getElementById(`${field.id.split('_')[0]}_country`);
		return !country || country.value === 'IR';
	};

	// aria-invalid را اعتبارسنجی خود ووکامرس (checkout.js) هنگام ترک فیلد می‌گذارد/برمی‌دارد؛ اینجا فقط نشانه ظاهری
	const mark = (field, state) => {
		field.classList.toggle('hodima-input-success', state === 'ok');
		field.classList.toggle('hodima-input-error', state === 'error');
	};

	const check = (field, final) => {
		const rule = RULES[field.id];
		if (!rule) {
			return;
		}
		if (final) {
			const normalized = rule.normalize(field.value);
			if (normalized !== field.value) {
				field.value = normalized;
			}
		} else {
			// فقط رقم عوض می‌شود و طول ثابت است؛ جای مکان‌نما حفظ می‌شود
			const converted = latin(field.value);
			if (converted !== field.value) {
				const caret = field.selectionStart;
				field.value = converted;
				field.setSelectionRange?.(caret, caret);
			}
		}
		if (!rule.valid || !inIran(field) || field.value === '') {
			mark(field, '');
			return;
		}
		const ok = rule.valid(rule.normalize(field.value));
		mark(field, ok ? 'ok' : final ? 'error' : '');
	};

	document.addEventListener('input', (event) => check(event.target, false));
	document.addEventListener('change', (event) => check(event.target, true));
})();
