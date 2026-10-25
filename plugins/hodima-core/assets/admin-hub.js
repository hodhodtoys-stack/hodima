/**
 * Hodima Core — پنل ماژول‌ها
 * Path: plugins/hodima-core/assets/admin-hub.js
 *
 * Vanilla JS:
 *   - خاموش کردن ماژول‌های حساس (مثلا آدرس تمیز) فقط بعد از تأیید در <dialog>؛
 *   - هماهنگ کردن ظاهر کارت با وضعیت کلید؛
 *   - نمایش «تغییرات ذخیره نشده» (نوار ذخیره فقط وقتی تغییری هست) و هشدار هنگام ترک صفحه؛
 *   - شمارنده‌های روشن/خاموش/ذخیره‌نشده، جستجو و فیلتر «همه/روشن/خاموش».
 */
(() => {
	'use strict';

	const form = document.querySelector('[data-hodima-modules]');
	if (!form) {
		return;
	}

	const dirtyNote = form.querySelector('[data-hodima-dirty]');
	const actions = form.querySelector('[data-hodima-actions]');
	const tools = form.querySelector('[data-hodima-tools]');
	const searchInput = form.querySelector('[data-hodima-search]');
	const filterButtons = [...form.querySelectorAll('[data-hodima-filter]')];
	const emptyNote = form.querySelector('[data-hodima-empty]');
	const modules = [...form.querySelectorAll('[data-hodima-module]')];
	const faDigits = new Intl.NumberFormat('fa-IR');
	let filter = 'all';

	// «جزئیات» هر ردیف: بدون JS دکمه پنهان و توضیح کامل است؛ با JS توضیح کوتاه و جزئیات با دکمه باز می‌شود
	form.classList.add('is-js');
	form.querySelectorAll('[data-hodima-more]').forEach((button) => {
		const body = document.getElementById(button.getAttribute('aria-controls'));
		button.hidden = false;
		button.addEventListener('click', () => {
			const open = 'true' !== button.getAttribute('aria-expanded');
			button.setAttribute('aria-expanded', String(open));
			if (body) {
				body.hidden = !open;
			}
			button.closest('[data-hodima-module]')?.classList.toggle('is-open', open);
		});
	});
	const dialog = document.querySelector('[data-hodima-dialog]');
	const dialogText = dialog?.querySelector('[data-hodima-dialog-text]');
	const switches = [...form.querySelectorAll('input[role="switch"]')];
	const initial = new Map(switches.map((input) => [input, input.checked]));
	let submitting = false;

	const isDirty = () => switches.some((input) => input.checked !== initial.get(input));

	const setCount = (key, value) => {
		form.querySelectorAll(`[data-hodima-count="${ key }"]`).forEach((node) => {
			node.textContent = faDigits.format(value);
		});
	};

	/** جستجو (روی عنوان و توضیح) و فیلتر وضعیت؛ ردیف نامطابق پنهان می‌شود. */
	const applyFilter = () => {
		const needle = (searchInput?.value ?? '').trim().toLowerCase();
		let shown = 0;
		modules.forEach((card) => {
			const on = card.querySelector('input[role="switch"]').checked;
			const okState = 'all' === filter || ( 'on' === filter ) === on;
			const okText = '' === needle || (card.dataset.hodimaText ?? '').toLowerCase().includes(needle);
			card.hidden = ! ( okState && okText );
			shown += card.hidden ? 0 : 1;
		});
		if (emptyNote) {
			emptyNote.hidden = shown > 0;
		}
	};

	const refreshSummary = () => {
		const on = switches.filter((input) => input.checked).length;
		const dirty = switches.filter((input) => input.checked !== initial.get(input)).length;
		setCount('on', on);
		setCount('off', switches.length - on);
		setCount('dirty', dirty);
		if (dirtyNote) {
			dirtyNote.hidden = 0 === dirty;
		}
		if (actions) {
			actions.hidden = 0 === dirty;
		}
		applyFilter();
	};

	const refresh = (input) => {
		const card = input.closest('[data-hodima-module]');
		card?.classList.toggle('is-on', input.checked);
		card?.classList.toggle('is-off', !input.checked);
		refreshSummary();
	};

	if (tools) {
		tools.hidden = false;
		searchInput?.addEventListener('input', applyFilter);
		filterButtons.forEach((button) => {
			button.addEventListener('click', () => {
				filter = button.dataset.hodimaFilter;
				filterButtons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
				applyFilter();
			});
		});
		// Enter در جستجو نباید فرم را ذخیره کند
		searchInput?.addEventListener('keydown', (event) => {
			if ('Enter' === event.key) {
				event.preventDefault();
			}
		});
	}

	refreshSummary();

	/** تأیید با <dialog> بومی؛ در نبود پشتیبانی، confirm() مرورگر. */
	const confirmOff = (message) => new Promise((resolve) => {
		if (typeof dialog?.showModal !== 'function') {
			resolve(window.confirm(message));
			return;
		}
		dialogText.textContent = message;
		dialog.returnValue = '';
		dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
		dialog.showModal();
	});

	switches.forEach((input) => {
		input.addEventListener('change', async () => {
			const warning = input.dataset.warning;

			if (!input.checked && warning) {
				const confirmed = await confirmOff(warning);
				if (!confirmed) {
					input.checked = true;
				}
				input.focus();
			}

			refresh(input);
		});
	});

	form.addEventListener('submit', () => {
		submitting = true;
	});

	window.addEventListener('beforeunload', (event) => {
		if (!submitting && isDirty()) {
			event.preventDefault();
		}
	});
})();
