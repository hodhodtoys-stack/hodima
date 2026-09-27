/**
 * Hodima Core — پنل ماژول‌ها
 * Path: plugins/hodima-core/assets/admin-hub.js
 *
 * Vanilla JS:
 *   - خاموش کردن ماژول‌های حساس (مثلا آدرس تمیز) فقط بعد از تأیید در <dialog>؛
 *   - هماهنگ کردن ظاهر کارت با وضعیت کلید؛
 *   - نمایش «تغییرات ذخیره نشده» و هشدار هنگام ترک صفحه.
 */
(() => {
	'use strict';

	const form = document.querySelector('[data-hodima-modules]');
	if (!form) {
		return;
	}

	const dirtyNote = form.querySelector('[data-hodima-dirty]');
	const dialog = document.querySelector('[data-hodima-dialog]');
	const dialogText = dialog?.querySelector('[data-hodima-dialog-text]');
	const switches = [...form.querySelectorAll('input[role="switch"]')];
	const initial = new Map(switches.map((input) => [input, input.checked]));
	let submitting = false;

	const isDirty = () => switches.some((input) => input.checked !== initial.get(input));

	const refresh = (input) => {
		const card = input.closest('[data-hodima-module]');
		card?.classList.toggle('is-on', input.checked);
		card?.classList.toggle('is-off', !input.checked);
		if (dirtyNote) {
			dirtyNote.hidden = !isDirty();
		}
	};

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
