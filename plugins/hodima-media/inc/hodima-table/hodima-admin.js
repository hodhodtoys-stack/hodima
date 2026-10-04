/**
 * سازنده جدول مشخصات در پیشخوان (هدیما) — جاوااسکریپت خالص، بدون jQuery.
 *
 * - کل جدول در یک فیلد مخفی JSON (hodima_table_json) فرستاده می‌شود و name
 *   خانه‌ها برداشته می‌شود؛ قبلا هر خانه یک فیلد بود و جدول بزرگ از سقف
 *   max_input_vars پی‌اچ‌پی (۱۰۰۰ فیلد) رد می‌شد و بریده/پاک می‌شد.
 *   فیلد با هر تغییر به‌روز می‌شود، چون ویرایشگر بلوکی فرم متاباکس‌ها را
 *   بدون رویداد submit می‌خواند.
 * - سقف ستون و ردیف برای همه راه‌ها (نه فقط CSV) اعمال می‌شود.
 * - دکمه‌ها با کیبورد در دسترس‌اند؛ جابه‌جایی ردیف با کشیدن یا دکمه بالا/پایین.
 * - پیام و تأیید با <dialog>، نه alert/confirm.
 */
(() => {
	'use strict';

	const root = document.querySelector('[data-hodima-table]');
	if (!root) {
		return;
	}

	const maxCols = parseInt(root.dataset.maxCols, 10) || 20;
	const maxRows = parseInt(root.dataset.maxRows, 10) || 100;

	const table   = root.querySelector('.hodima-admin-table');
	const headRow = table.tHead.rows[0];
	const body    = table.tBodies[0];
	const fileIn  = root.querySelector('[data-role="csv-file"]');

	let uid         = 0;
	let activeField = null;
	let dragged     = null;

	const nextId = () => `hodima-table-field-${++uid}`;

	// ---------- فیلد JSON ----------

	const jsonInput = document.createElement('input');
	jsonInput.type  = 'hidden';
	jsonInput.name  = 'hodima_table_json';
	root.append(jsonInput);

	// name خانه‌ها فقط برای حالت بدون جاوااسکریپت است
	root.querySelectorAll('[name="hodima_table_headers[]"], [name^="hodima_table_rows["]').forEach((el) => {
		el.removeAttribute('name');
	});

	const colCount = () => headRow.cells.length - 1;

	const readTable = () => ({
		headers: [...headRow.cells].slice(1).map((th) => th.querySelector('input').value),
		rows: [...body.rows].map((tr) => [...tr.cells].slice(1).map((td) => td.querySelector('textarea').value)),
	});

	const sync = () => {
		jsonInput.value = JSON.stringify(readTable());
	};

	const hasContent = (values) => values.some((v) => v.trim() !== '');

	// ---------- ساخت عناصر ----------

	const iconButton = (action, label, icon, extraClass = '') => {
		const button = document.createElement('button');
		button.type      = 'button';
		button.className = `hodima-icon-btn ${extraClass}`.trim();
		button.dataset.action = action;
		button.setAttribute('aria-label', label);
		button.innerHTML = `<span class="dashicons ${icon}" aria-hidden="true"></span>`;
		return button;
	};

	const makeHeaderCell = (value = '') => {
		const th    = document.createElement('th');
		const wrap  = document.createElement('div');
		const input = document.createElement('input');

		th.scope       = 'col';
		wrap.className = 'hodima-col-head';
		input.type        = 'text';
		input.value       = value;
		input.placeholder = 'عنوان ستون';
		input.id          = nextId();

		wrap.append(input, iconButton('remove-col', 'حذف ستون', 'dashicons-trash'));
		th.append(wrap);
		return th;
	};

	const makeBodyCell = (value = '') => {
		const td       = document.createElement('td');
		const textarea = document.createElement('textarea');

		textarea.rows        = 2;
		textarea.placeholder = 'مقدار';
		textarea.value       = value;
		textarea.id          = nextId();

		td.append(textarea);
		return td;
	};

	const makeRow = (values = []) => {
		const tr      = document.createElement('tr');
		const ops     = document.createElement('td');
		const actions = document.createElement('div');
		const handle  = document.createElement('span');

		ops.className     = 'hodima-col-ops';
		actions.className = 'hodima-row-actions';
		handle.className  = 'hodima-drag-handle dashicons dashicons-menu';
		handle.title      = 'برای جابه‌جایی بکشید';
		handle.setAttribute('aria-hidden', 'true');

		actions.append(
			handle,
			iconButton('row-up', 'انتقال ردیف به بالا', 'dashicons-arrow-up-alt2'),
			iconButton('row-down', 'انتقال ردیف به پایین', 'dashicons-arrow-down-alt2'),
			iconButton('remove-row', 'حذف ردیف', 'dashicons-trash', 'hodima-icon-btn--danger')
		);
		ops.append(actions);
		tr.append(ops);

		for (let c = 0; c < colCount(); c++) {
			tr.append(makeBodyCell(values[c] ?? ''));
		}
		return tr;
	};

	/** برچسب‌های دسترس‌پذیری بعد از هر تغییر ساختار. */
	const relabel = () => {
		[...headRow.cells].slice(1).forEach((th, c) => {
			th.querySelector('input').setAttribute('aria-label', `عنوان ستون ${c + 1}`);
			th.querySelector('[data-action="remove-col"]').setAttribute('aria-label', `حذف ستون ${c + 1}`);
		});
		[...body.rows].forEach((tr, r) => {
			[...tr.cells].slice(1).forEach((td, c) => {
				td.querySelector('textarea').setAttribute('aria-label', `ردیف ${r + 1}، ستون ${c + 1}`);
			});
		});
	};

	const changed = () => {
		relabel();
		sync();
	};

	// شناسه برای فیلدهای ساخته‌شده در PHP (لازم برای پنجره درج لینک)
	root.querySelectorAll('.hodima-admin-table input[type="text"], .hodima-admin-table textarea').forEach((el) => {
		el.id = el.id || nextId();
	});

	// ---------- پنجره پیام / تأیید ----------

	const dialog = document.createElement('dialog');
	dialog.className = 'hodima-table-dialog';
	dialog.innerHTML =
		'<p class="hodima-table-dialog__text"></p>' +
		'<div class="hodima-table-dialog__actions">' +
			'<button type="button" class="button button-primary" data-answer="ok">تأیید</button>' +
			'<button type="button" class="button" data-answer="cancel">انصراف</button>' +
		'</div>';
	root.append(dialog);

	dialog.addEventListener('click', (event) => {
		const answer = event.target.closest('[data-answer]');
		if (answer) {
			dialog.close(answer.dataset.answer);
		}
	});

	/** @returns {Promise<boolean>} */
	const ask = (message, confirmable = true) => new Promise((resolve) => {
		dialog.querySelector('.hodima-table-dialog__text').textContent = message;
		dialog.querySelector('[data-answer="cancel"]').hidden = !confirmable;
		dialog.returnValue = '';
		dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
		dialog.showModal();
		dialog.querySelector('[data-answer="ok"]').focus();
	});

	const notify = (message) => ask(message, false);

	// ---------- عملیات ساختار ----------

	const addRow = (focus = true) => {
		if (body.rows.length >= maxRows) {
			notify(`حداکثر ${maxRows} ردیف مجاز است.`);
			return;
		}
		const tr = makeRow();
		body.append(tr);
		changed();
		if (focus) {
			tr.querySelector('textarea')?.focus();
		}
	};

	const addCol = () => {
		if (colCount() >= maxCols) {
			notify(`حداکثر ${maxCols} ستون مجاز است.`);
			return;
		}
		const th = makeHeaderCell();
		headRow.append(th);
		[...body.rows].forEach((tr) => tr.append(makeBodyCell()));
		changed();
		th.querySelector('input').focus();
	};

	const removeCol = async (th) => {
		if (colCount() <= 1) {
			notify('حداقل یک ستون باید بماند.');
			return;
		}
		const index  = th.cellIndex;
		const values = [th.querySelector('input').value, ...[...body.rows].map((tr) => tr.cells[index].querySelector('textarea').value)];

		if (hasContent(values) && !(await ask('این ستون و همه مقدارهایش حذف شود؟'))) {
			return;
		}
		th.remove();
		[...body.rows].forEach((tr) => tr.cells[index].remove());
		changed();
	};

	const removeRow = async (tr) => {
		const values = [...tr.querySelectorAll('textarea')].map((t) => t.value);

		if (hasContent(values) && !(await ask('این ردیف حذف شود؟'))) {
			return;
		}
		if (body.rows.length > 1) {
			tr.remove();
		} else {
			tr.querySelectorAll('textarea').forEach((t) => { t.value = ''; });
		}
		changed();
	};

	const moveRow = (tr, step, button) => {
		const target = step < 0 ? tr.previousElementSibling : tr.nextElementSibling;
		if (!target) {
			return;
		}
		if (step < 0) {
			target.before(tr);
		} else {
			target.after(tr);
		}
		changed();
		button?.focus();
	};

	/** جایگزینی کامل جدول (ورود CSV). */
	const rebuild = (headers, rows) => {
		[...headRow.cells].slice(1).forEach((th) => th.remove());
		headers.forEach((h) => headRow.append(makeHeaderCell(h)));

		body.replaceChildren();
		(rows.length ? rows : [[]]).forEach((r) => body.append(makeRow(r)));
		changed();
	};

	// ---------- درج لینک (پنجره wpLink خود وردپرس) ----------

	const openLink = (field) => {
		if (!field) {
			notify('اول داخل یک خانه یا عنوان ستون کلیک کنید، بعد «درج لینک» را بزنید.');
			return;
		}
		if (typeof window.wpLink === 'undefined') {
			notify('پنجره درج لینک وردپرس در این صفحه در دسترس نیست.');
			return;
		}
		window.wpLink.open(field.id);
	};

	// wpLink متن را مستقیم در فیلد می‌نویسد و رویداد input نمی‌دهد؛ فقط رویداد
	// jQuery «wplink-close» دارد (jQuery خود وردپرس، نه وابستگی این فایل).
	if (window.jQuery) {
		window.jQuery(document).on('wplink-close', sync);
	}

	// ---------- رویدادها ----------

	root.addEventListener('focusin', (event) => {
		if (event.target.matches('.hodima-admin-table input[type="text"], .hodima-admin-table textarea')) {
			activeField = event.target;
		}
	});

	root.addEventListener('keydown', (event) => {
		// e.code مستقل از زبان صفحه‌کلید است؛ e.key با صفحه‌کلید فارسی «ن» است.
		const isK = event.code === 'KeyK' || event.key?.toLowerCase() === 'k';
		if ((event.ctrlKey || event.metaKey) && isK && event.target.matches('.hodima-admin-table input, .hodima-admin-table textarea')) {
			event.preventDefault();
			activeField = event.target;
			openLink(activeField);
		}
	});

	['input', 'change', 'focusout'].forEach((type) => root.addEventListener(type, sync));

	root.addEventListener('click', (event) => {
		const button = event.target.closest('button[data-action]');
		if (!button || !root.contains(button) || dialog.contains(button)) {
			return;
		}
		event.preventDefault();

		const tr = button.closest('tr');

		switch (button.dataset.action) {
			case 'add-row':    addRow(); break;
			case 'add-col':    addCol(); break;
			case 'link':       openLink(activeField); break;
			case 'export':     exportCsv(); break;
			case 'import':     fileIn.click(); break;
			case 'remove-col': removeCol(button.closest('th')); break;
			case 'remove-row': removeRow(tr); break;
			case 'row-up':     moveRow(tr, -1, button); break;
			case 'row-down':   moveRow(tr, 1, button); break;
		}
	});

	// ---------- کشیدن ردیف (Drag & Drop بومی، فقط از دستگیره) ----------

	body.addEventListener('pointerdown', (event) => {
		const handle = event.target.closest('.hodima-drag-handle');
		if (handle) {
			handle.closest('tr').draggable = true;
		}
	});

	body.addEventListener('dragstart', (event) => {
		// فقط خود ردیف (از دستگیره)؛ کشیدن متن انتخاب‌شده داخل خانه ردیف را جابه‌جا نکند
		const tr = event.target;
		if (!(tr instanceof HTMLTableRowElement) || !tr.draggable) {
			return;
		}
		dragged = tr;
		dragged.classList.add('is-dragging');
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', '');
	});

	body.addEventListener('dragover', (event) => {
		if (!dragged) {
			return;
		}
		event.preventDefault();
		const over = event.target.closest('tr');
		if (!over || over === dragged || over.parentElement !== body) {
			return;
		}
		const rect = over.getBoundingClientRect();
		if (event.clientY < rect.top + rect.height / 2) {
			over.before(dragged);
		} else {
			over.after(dragged);
		}
	});

	body.addEventListener('drop', (event) => {
		if (dragged) {
			event.preventDefault();
		}
	});

	body.addEventListener('dragend', () => {
		if (!dragged) {
			return;
		}
		dragged.classList.remove('is-dragging');
		dragged.draggable = false;
		dragged = null;
		changed();
	});

	document.addEventListener('pointerup', () => {
		if (!dragged) {
			body.querySelectorAll('tr[draggable="true"]').forEach((tr) => { tr.draggable = false; });
		}
	});

	// ---------- CSV ----------

	// اکسل و لیبره‌آفیس خانه‌ای که با = + - @ شروع شود را فرمول اجرا می‌کنند؛
	// خروجی با «'» خنثی و هنگام ورود همان «'» برداشته می‌شود (قبلا می‌ماند).
	const FORMULA_START = '=+-@\t\r';

	const csvSafe = (value) => {
		let text = String(value ?? '');
		if (text && FORMULA_START.includes(text.charAt(0))) {
			text = `'${text}`;
		}
		return `"${text.replace(/"/g, '""')}"`;
	};

	const csvUnsafe = (text) => (
		text.length > 1 && text.charAt(0) === "'" && FORMULA_START.includes(text.charAt(1)) ? text.slice(1) : text
	);

	function exportCsv() {
		const { headers, rows } = readTable();
		// ردیف عنوان همیشه نوشته می‌شود (حتی خالی) تا هنگام ورود ردیف اول داده جای آن را نگیرد
		const lines = [headers, ...rows].map((r) => r.map(csvSafe).join(','));

		// BOM لازم است وگرنه اکسل فایل فارسی را به‌هم‌ریخته باز می‌کند.
		const blob = new Blob([`﻿${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8;' });
		const url  = URL.createObjectURL(blob);
		const link = document.createElement('a');

		link.href     = url;
		link.download = `hodima-table-${new Date().toISOString().slice(0, 10)}.csv`;
		document.body.append(link);
		link.click();
		link.remove();
		setTimeout(() => URL.revokeObjectURL(url), 1000);
	}

	/** جداکننده: , یا ; یا Tab — هر کدام در خط اول (بیرون از گیومه) بیشتر بود. */
	const detectDelimiter = (text) => {
		const counts = { ',': 0, ';': 0, '\t': 0 };
		let quoted = false;
		for (const ch of text) {
			if (ch === '"') {
				quoted = !quoted;
			} else if (!quoted && (ch === '\n' || ch === '\r')) {
				break;
			} else if (!quoted && ch in counts) {
				counts[ch]++;
			}
		}
		return Object.keys(counts).reduce((a, b) => (counts[b] > counts[a] ? b : a), ',');
	};

	const parseCsv = (text) => {
		const delimiter = detectDelimiter(text);
		const rows = [];
		let row    = [];
		let cell   = '';
		let quoted = false;

		for (let i = 0; i < text.length; i++) {
			const ch   = text[i];
			const next = text[i + 1];

			if (quoted) {
				if (ch === '"' && next === '"') {
					cell += '"';
					i++;
				} else if (ch === '"') {
					quoted = false;
				} else {
					cell += ch;
				}
			} else if (ch === '"') {
				quoted = true;
			} else if (ch === delimiter) {
				row.push(cell);
				cell = '';
			} else if (ch === '\n' || ch === '\r') {
				if (ch === '\r' && next === '\n') {
					i++;
				}
				row.push(cell);
				rows.push(row);
				row  = [];
				cell = '';
			} else {
				cell += ch;
			}
		}
		if (cell !== '' || row.length) {
			row.push(cell);
			rows.push(row);
		}

		return rows.map((r) => r.map(csvUnsafe));
	};

	/**
	 * متن فایل: اول UTF-8؛ اگر معتبر نبود Windows-1256. اکسل با گزینه
	 * پیش‌فرض «CSV (Comma delimited)» فارسی را با کدگذاری ویندوز ذخیره می‌کند
	 * و قبلا هنگام ورود به‌هم می‌ریخت.
	 */
	const decodeFile = (buffer) => {
		let text;
		try {
			text = new TextDecoder('utf-8', { fatal: true }).decode(buffer);
		} catch {
			// Windows-1256 «ی» و «ک» فارسی ندارد و اکسل آن‌ها را «ي» و «ك» عربی ذخیره می‌کند
			text = new TextDecoder('windows-1256').decode(buffer).replace(/ي/g, 'ی').replace(/ك/g, 'ک');
		}
		return text.replace(/^﻿/, '');
	};

	fileIn.addEventListener('change', async () => {
		const file = fileIn.files?.[0];
		fileIn.value = ''; // بدون ریست، انتخاب دوباره همان فایل رویداد change نمی‌دهد
		if (!file) {
			return;
		}

		// بدون سقف، یک فایل چندمگابایتی مرورگر را قفل می‌کند.
		if (file.size > 2 * 1024 * 1024) {
			notify('حجم فایل بیش از ۲ مگابایت است.');
			return;
		}

		let rows;
		try {
			rows = parseCsv(decodeFile(await file.arrayBuffer()));
		} catch {
			notify('خواندن فایل ممکن نشد.');
			return;
		}

		// ردیف اول همیشه عنوان است؛ از بقیه فقط ردیف‌های کاملا خالی حذف می‌شوند
		const [headerRow = [], ...rest] = rows;
		const bodyRows = rest.filter(hasContent);

		if (!hasContent(headerRow) && !bodyRows.length) {
			notify('فایل CSV معتبر نیست یا خالی است.');
			return;
		}

		// عرض = بلندترین ردیف (قبلا خانه‌های بیشتر از تعداد عنوان‌ها بی‌صدا حذف می‌شدند)
		let width = Math.max(1, headerRow.length, ...bodyRows.map((r) => r.length));

		if (width > maxCols || bodyRows.length > maxRows) {
			if (!(await ask(`فایل از سقف مجاز (${maxCols} ستون و ${maxRows} ردیف) بزرگ‌تر است و بریده می‌شود. ادامه می‌دهید؟`))) {
				return;
			}
			width = Math.min(width, maxCols);
		}

		const current = readTable();
		if (hasContent([...current.headers, ...current.rows.flat()]) && !(await ask('جدول فعلی با محتوای فایل جایگزین شود؟'))) {
			return;
		}

		const fit = (r) => Array.from({ length: width }, (_, c) => r[c] ?? '');
		rebuild(fit(headerRow), bodyRows.slice(0, maxRows).map(fit));
	});

	// ---------- شروع ----------

	root.closest('form')?.addEventListener('submit', sync);
	changed();
})();
