/*
 * استان و شهرهای ایران در فرم آدرس ووکامرس (تسویه‌حساب و «حساب کاربری ← ویرایش آدرس»).
 * گزینه‌های شهر را سرور می‌سازد (شهر ذخیره‌شده انتخاب‌شده می‌ماند)؛ این فایل فقط:
 *   - با تغییر استان فهرست را از نو می‌سازد و با تغییر کشور بین فهرست و کادر متنی جابه‌جا می‌کند؛
 *   - جستجو در فهرست (selectWoo ووکامرس؛ بی‌فاصله، ی/ک عربی، آ = ا، نام رایج مثل «لارستان»)؛
 *   - «شهر من در فهرست نیست» ← کادر متنی برای نوشتن نام شهر.
 * باگ قبلی: رویدادها روی خود فیلد استان بودند و ووکامرس با تغییر کشور آن فیلد را عوض می‌کند،
 * پس فهرست شهر دیگر به‌روز نمی‌شد؛ حالا رویدادها روی سند (delegation) هستند.
 * jQuery فقط برای سازگاری با selectWoo و رویدادهای خود ووکامرس.
 */
(() => {
	'use strict';

	const data = window.hodimaCitiesData;
	if (!data || !data.cities) {
		return;
	}
	const OTHER = data.other;
	const jq = window.jQuery;
	const TYPES = ['billing', 'shipping'];

	const norm = (text) =>
		String(text ?? '')
			.replace(/[يى]/g, 'ی')
			.replace(/ك/g, 'ک')
			.replace(/[آأإ]/g, 'ا')
			.replace(/ئ/g, 'ی')
			.replace(/[ء\s‌]/g, '')
			.toLowerCase();

	const el = (type, name) => document.getElementById(`${type}_${name}`);

	const country = (type) => {
		const field = el(type, 'country');
		return field ? field.value : 'IR';
	};

	const hasSelectWoo = () => Boolean(jq && jq.fn && jq.fn.selectWoo);

	const destroySelectWoo = (field) => {
		if (hasSelectWoo() && jq(field).data('select2')) {
			jq(field).selectWoo('destroy');
		}
	};

	/** رویداد change که هم ووکامرس (jQuery) و هم شنونده‌های معمولی ببینند. */
	const fireChange = (field) => {
		if (jq) {
			jq(field).trigger('change');
		} else {
			field.dispatchEvent(new Event('change', { bubbles: true }));
		}
	};

	const removeBackButton = (type) => {
		document.querySelector(`.hodima-city-back[data-type="${type}"]`)?.remove();
	};

	/** فیلد شهر را با عنصر تازه (هم‌نام و هم‌شناسه) عوض می‌کند. */
	const replaceField = (field, tag) => {
		destroySelectWoo(field);
		const next = document.createElement(tag);
		for (const attr of ['id', 'name', 'autocomplete', 'aria-required', 'data-placeholder']) {
			if (field.hasAttribute(attr)) {
				next.setAttribute(attr, field.getAttribute(attr));
			}
		}
		next.className = tag === 'select' ? 'select hodima-city-select' : 'input-text';
		field.replaceWith(next);
		return next;
	};

	const toText = (type, value, withBack) => {
		let field = el(type, 'city');
		if (!field) {
			return;
		}
		if (field.tagName !== 'INPUT') {
			field = replaceField(field, 'input');
			field.type = 'text';
		}
		field.value = value;
		removeBackButton(type);
		if (withBack) {
			const back = document.createElement('button');
			back.type = 'button';
			back.className = 'hodima-city-back';
			back.dataset.type = type;
			back.textContent = 'انتخاب از فهرست شهرها';
			// شنونده روی خود دکمه: کلیک روی فرم تسویه‌حساب به سند نمی‌رسد
			back.addEventListener('click', () => {
				render(type, 'reset');
				el(type, 'city')?.focus();
			});
			field.after(back);
		}
	};

	const enhance = (type, select) => {
		if (!hasSelectWoo()) {
			return;
		}
		const aliases = data.aliases?.[el(type, 'state')?.value] ?? {};
		jq(select).selectWoo({
			width: '100%',
			dir: 'rtl',
			placeholder: select.options[0]?.textContent ?? '',
			matcher: (params, item) => {
				const term = norm(params.term);
				if (!term || item.id === OTHER || item.id === '') {
					return item;
				}
				const alias = aliases[item.text];
				return norm(item.text).includes(term) || (alias && norm(alias).includes(term)) ? item : null;
			},
		});
	};

	/**
	 * فهرست شهرهای استان فعلی. mode:
	 *   'init'  ← شهر فعلی می‌ماند حتی اگر در فهرست نباشد (ذخیره‌شده یا نوشته مشتری)؛
	 *   'state' ← فقط اگر در فهرست استان تازه باشد (شهر استان قبلی پاک می‌شود)؛
	 *   'reset' ← خالی.
	 */
	const lastState = {};
	const render = (type, mode = 'init') => {
		const field = el(type, 'city');
		if (!field) {
			return;
		}
		// ووکامرس هنگام بارگذاری هم رویداد تغییر کشور/استان می‌فرستد؛ تا استان واقعا عوض نشده، شهر ذخیره‌شده بماند
		const state = `${country(type)}:${el(type, 'state')?.value ?? ''}`;
		if (mode === 'state' && lastState[type] === state) {
			mode = 'init';
		}
		lastState[type] = state;
		if (country(type) !== 'IR') {
			toText(type, mode === 'reset' || field.value === OTHER ? '' : field.value, false);
			return;
		}
		const cities = data.cities[el(type, 'state')?.value] ?? [];
		const before = field.value;
		const current = mode !== 'reset' && field.value !== OTHER ? field.value : '';
		const select = field.tagName === 'SELECT' ? field : replaceField(field, 'select');
		removeBackButton(type);
		destroySelectWoo(select);

		const options = [new Option(cities.length ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید', '')];
		let selected = '';
		for (const city of cities) {
			// همان شهر با املای دیگر (مثلا «نجف‌آباد»): مقدار ذخیره‌شده بماند، مثل گزینه‌های سرور
			if (!selected && current && norm(city) === norm(current)) {
				selected = current;
				options.push(new Option(city, current));
			} else {
				options.push(new Option(city, city));
			}
		}
		// شهری که مشتری خودش نوشته بود (در فهرست نیست) گم نشود
		if (current && !selected && mode === 'init') {
			options.push(new Option(current, current));
			selected = current;
		}
		if (cities.length) {
			options.push(new Option('شهر من در فهرست نیست', OTHER));
		}
		select.replaceChildren(...options);
		select.value = selected;
		enhance(type, select);
		if (select.value !== before || select !== field) {
			fireChange(select);
		}
	};

	const typeOf = (target) => TYPES.find((type) => target.id === `${type}_state` || target.id === `${type}_city`);

	const onChange = (target) => {
		const type = typeOf(target);
		if (!type) {
			return;
		}
		if (target.id.endsWith('_state')) {
			render(type, 'state');
		} else if (target.value === OTHER) {
			toText(type, '', true);
			el(type, 'city')?.focus();
		}
	};

	if (jq) {
		// selectWoo فقط رویداد jQuery می‌فرستد؛ تغییر معمولی هم به همین شنونده می‌رسد
		jq(document.body).on('change', '#billing_state, #shipping_state, #billing_city, #shipping_city', (event) => onChange(event.target));
		// ووکامرس با تغییر کشور فیلد استان را می‌سازد و این رویداد را می‌فرستد
		jq(document.body).on('country_to_state_changed', () => TYPES.forEach((type) => render(type, 'state')));
	} else {
		document.addEventListener('change', (event) => onChange(event.target));
	}

	const init = () => TYPES.forEach((type) => render(type, 'init'));
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
