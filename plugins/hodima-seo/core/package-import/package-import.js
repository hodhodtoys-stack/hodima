/**
 * «ورود بسته» — کادر بالای ویرایش محصول (core/package-import)
 *
 * چسباندن ← «بررسی بسته» (admin-ajax، فقط خواندن) ← پیش‌نمایش «فعلی ← جدید» هر کادر ←
 * «پر کردن فرم» (فقط خانه‌های همین صفحه، در مرورگر) ← «به‌روزرسانی» خود مدیر. هیچ درخواستی
 * چیزی ذخیره نمی‌کند. «بازگرداندن» مقدارهای پیش از پر کردن را با همان آداپتورها برمی‌گرداند.
 *
 * هر کادر یک آداپتور دارد: present (کادر در صفحه هست؟)، read (مقدار فعلی فرم به شکل بسته)،
 * fill (گذاشتن در فرم)، summary و details (متن پیش‌نمایش). کادرهایی که داده را در حافظه
 * جاوااسکریپت خودشان نگه می‌دارند (جدول، پرسش‌های رسانه، لینک‌های مرتبط، فهرست‌های AEO) رویداد
 * «hodima:fill» را خودشان می‌گیرند و detail.done را true می‌کنند؛ نسخه قدیمی‌شان که گیرنده ندارد
 * «پر نشد» می‌گیرد و هیچ مقدار نیمه‌کاره‌ای نمی‌ماند. جاوااسکریپت خالص؛ jQuery فقط اگر خود
 * ووکامرس آن را دارد، برای خبر دادن به select2 فهرست محصولات.
 */
(() => {
	'use strict';

	const cfg = window.hodimaPkg ?? {};
	const root = document.querySelector('[data-hodima-pkg]');
	if (!root || !cfg.ajax) {
		return;
	}

	const $ = (sel, scope = root) => scope.querySelector(sel);
	const fa = (n) => Number(n).toLocaleString('fa-IR');
	const labels = cfg.labels ?? {};
	const aeoKeys = cfg.aeoKeys ?? {};
	const fieldMaps = cfg.fieldMaps ?? {};
	// کلید بسته ← آداپتور (نسخه ۱ فهرست بود: کلید = آداپتور)
	const boxMap = Array.isArray(cfg.boxes) ? Object.fromEntries(cfg.boxes.map((k) => [k, k])) : (cfg.boxes ?? {});

	const el = {
		icon: $('[data-pkg-icon]'),
		status: $('[data-pkg-status]'),
		pill: $('[data-pkg-pill]'),
		toggle: $('[data-pkg-toggle]'),
		body: $('#hodima-pkg-body'),
		steps: $('[data-pkg-steps]'),
		paste: $('[data-pkg-paste]'),
		json: $('[data-pkg-json]'),
		check: $('[data-pkg-check]'),
		fileBtn: $('[data-pkg-file-btn]'),
		file: $('[data-pkg-file]'),
		pasteError: $('[data-pkg-paste-error]'),
		loaded: $('[data-pkg-loaded]'),
		info: $('[data-pkg-info]'),
		edit: $('[data-pkg-edit]'),
		lock: $('[data-pkg-lock]'),
		errors: $('[data-pkg-errors]'),
		review: $('[data-pkg-review]'),
		note: $('[data-pkg-note]'),
		chips: $('[data-pkg-chips]'),
		rows: $('[data-pkg-rows]'),
		ignored: $('[data-pkg-ignored]'),
		fillbar: $('[data-pkg-fillbar]'),
		fill: $('[data-pkg-fill]'),
		fillHint: $('[data-pkg-fill-hint]'),
		done: $('[data-pkg-done]'),
		doneText: $('[data-pkg-done-text]'),
		undo: $('[data-pkg-undo]'),
	};

	const make = (tag, className = '', text = '') => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		if (text) node.textContent = text;
		return node;
	};

	/** گیرنده‌های کادرهای دیگر: true فقط اگر کادر خودش پر کردن را پذیرفت. */
	const send = (target, value) => {
		if (!target) return false;
		const detail = { value, done: false };
		target.dispatchEvent(new CustomEvent('hodima:fill', { bubbles: true, detail }));
		return detail.done === true;
	};

	const announce = (input, type = 'input') => input.dispatchEvent(new Event(type, { bubbles: true }));

	const plain = (html) => (new DOMParser().parseFromString(String(html), 'text/html').body.textContent || '').trim();

	const htmlStats = (html) => {
		const doc = new DOMParser().parseFromString(String(html), 'text/html');
		const words = (doc.body.textContent || '').split(/\s+/u).filter(Boolean).length;
		const heads = doc.querySelectorAll('h1,h2,h3,h4,h5,h6').length;
		const codes = (String(html).match(/\[(?!\/)[a-z_][\w-]*/giu) || []).length;
		return { words, heads, codes };
	};

	const listText = (items, key = 'title', max = 4) => {
		if (!items.length) return '—';
		const names = items.slice(0, max).map((i) => i[key]);
		return names.join('، ') + (items.length > max ? ` و ${fa(items.length - max)} مورد دیگر` : '');
	};

	const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

	/* =================================================================
	 * آداپتور هر کادر
	 * ================================================================= */

	/** ویرایشگر کلاسیک وردپرس با شناسه (TinyMCE دیداری یا حالت «کد»). */
	const visualEditor = (id) => {
		const ed = window.tinymce?.get?.(id);
		return ed && !ed.isHidden() ? ed : null;
	};

	const htmlSummary = (v) => {
		if (!plain(v.html) && !v.html.trim()) return 'خالی';
		const s = htmlStats(v.html);
		return `${fa(s.words)} کلمه، ${fa(s.heads)} تیتر${s.codes ? `، ${fa(s.codes)} شورت‌کد` : ''}`;
	};

	// وردپرس هنگام ذخیره پاراگراف‌ها و فاصله‌ها را از نو می‌چیند؛ مقایسه بی‌اثر از آن‌ها
	const htmlKey = (v) => v.html.replace(/<\/?p>|<br\s*\/?>/giu, '').replace(/\s+/gu, '');

	/**
	 * خانه HTML با ویرایشگر کلاسیک (بدنه محصول، «متن معرفی» تنظیمات رسانه، «توضیحات» دسته).
	 * HTML (مثل <details>) همان‌طور که هست گذاشته می‌شود؛ فقط «پاراگراف خودکار» وردپرس مثل باز شدن صفحه.
	 */
	const editorAdapter = (id) => ({
		present: () => !!document.getElementById(id),
		read: () => ({ html: visualEditor(id)?.getContent() ?? document.getElementById(id).value }),
		fill({ html }) {
			const area = document.getElementById(id);
			const ed = visualEditor(id);
			if (ed) {
				const autop = ed.getParam?.('wpautop') && window.wp?.editor?.autop;
				ed.setContent(autop ? window.wp.editor.autop(html) : html);
				ed.undoManager?.add();
				ed.setDirty(true);
				ed.save();
			} else {
				area.value = html;
				// TinyMCE پنهان (حالت «کد») هنگام برگشت به «دیداری» از همین textarea می‌خواند
				announce(area);
			}
			return true;
		},
		key: htmlKey,
		summary: htmlSummary,
		details: () => [],
	});

	/**
	 * بدنه در ویرایشگر بلوکی (مقاله و ویدیو): از wp.data خوانده و با resetEditorBlocks گذاشته
	 * می‌شود. HTML بسته بلوک‌بندی‌نشده یک بلوک «کلاسیک» می‌شود و دست نمی‌خورد؛ بدنه‌ای که بلوک
	 * دارد (برای «بازگرداندن») همان بلوک‌ها را پس می‌گیرد.
	 */
	const blockBody = {
		editor: () => window.wp?.data?.select?.('core/editor'),
		present() { return !!(this.editor() && window.wp?.blocks?.parse); },
		read() {
			// بلوک‌های فعلی هم نگه داشته می‌شوند تا «بازگرداندن» دقیقا همان بلوک‌ها (نه HTML تبدیل‌شده) را برگرداند
			const blocks = window.wp.data.select('core/block-editor')?.getBlocks?.() ?? null;
			return { html: this.editor().getEditedPostContent(), blocks };
		},
		fill({ html, blocks }) {
			window.wp.data.dispatch('core/editor').resetEditorBlocks(Array.isArray(blocks) ? blocks : window.wp.blocks.parse(html));
			return true;
		},
		key: (v) => htmlKey({ html: v.html.replace(/<!--\s*\/?wp:[^>]*-->/gu, '') }),
		summary: (v) => htmlSummary({ html: v.html.replace(/<!--\s*\/?wp:[^>]*-->/gu, '') }),
		details: () => [],
	};

	/** خانه با name (input/textarea/select)؛ چند خانه با هم (ویدیو، پادکست، کادر «تنظیمات ویدیو»). */
	const byName = (name) => document.querySelector(`[name="${CSS.escape(name)}"]`);

	const fieldsAdapter = (map, label) => ({
		names() { return Object.values(map).filter((n) => byName(n)); },
		present() { return this.names().length > 0; },
		read() {
			const out = {};
			this.names().forEach((n) => { out[n] = byName(n).value; });
			// شناسه پیوست کنار آدرس (انتخاب از کتابخانه) برای «بازگرداندن»
			this.names().forEach((n) => {
				const id = byName(n.replace(/\]$/u, '_id]'));
				if (n.endsWith(']') && id) out[`${n}#id`] = id.value;
			});
			return out;
		},
		fill(v) {
			Object.entries(v).forEach(([name, value]) => {
				if (name.endsWith('#id')) {
					const id = byName(name.slice(0, -3).replace(/\]$/u, '_id]'));
					if (id) id.value = String(value);
					return;
				}
				const input = byName(name);
				if (!input) return;
				if (input.value !== String(value)) {
					// آدرس تازه: پیوست کتابخانه قبلی دیگر مال این خانه نیست
					const id = name.endsWith(']') ? byName(name.replace(/\]$/u, '_id]')) : null;
					if (id && !(`${name}#id` in v)) id.value = '';
				}
				input.value = String(value);
				announce(input);
				announce(input, 'change');
			});
			return true;
		},
		key(v) {
			const out = {};
			this.names().forEach((n) => { out[n] = String(v[n] ?? '').trim(); });
			return out;
		},
		changed(cur, next) { return this.names().filter((n) => String(cur[n] ?? '').trim() !== String(next[n] ?? '').trim()); },
		summary(v) {
			const filled = this.names().filter((n) => String(v[n] ?? '').trim() !== '').length;
			return filled ? `${fa(filled)} از ${fa(this.names().length)} خانه پر` : 'خالی';
		},
		details(cur, next) {
			const short = (x) => { const t = String(x ?? '').trim(); return t ? (t.length > 80 ? `${t.slice(0, 80)}…` : t) : '—'; };
			const nameOf = Object.fromEntries(Object.entries(map).map(([k, n]) => [n, `${label}.${k}`]));
			return this.changed(cur, next).map((n) => ({ label: nameOf[n] ?? n, from: short(cur[n]), to: short(next[n]) }));
		},
	});

	const productSelect = (id) => ({
		select: () => document.getElementById(id),
		present() { return !!this.select(); },
		read() {
			return [...this.select().selectedOptions].map((o) => ({ id: Number(o.value), title: o.textContent.trim() }));
		},
		fill(items) {
			const select = this.select();
			select.replaceChildren(...items.map((i) => new Option(i.title, String(i.id), true, true)));
			// select2 ووکامرس فهرست را از option ها می‌سازد و فقط با رویداد jQuery خبردار می‌شود
			if (window.jQuery) window.jQuery(select).trigger('change'); else announce(select, 'change');
			return true;
		},
		key: (items) => items.map((i) => i.id),
		summary: (items) => (items.length ? `${fa(items.length)} محصول` : 'خالی'),
		details: (cur, next) => [{ label: 'فهرست', from: listText(cur), to: listText(next) }],
	});

	const tableRoot = () => document.querySelector('[data-hodima-table]');
	const tableRows = (t) => (t.rows ?? []).filter((r) => r.some((c) => plain(c) !== ''));

	const rlGroups = () => ({
		products: document.querySelector('.hodima-rl-group[data-group="products"]'),
		article: document.querySelector('.hodima-rl-group[data-group="article"]'),
	});
	const rlSlots = (group) => (group ? [...group.querySelectorAll(':scope > .hodima-rl-slots > .hodima-rl-slot')] : []);
	const rlRead = (group) => rlSlots(group).filter((s) => s.dataset.filled === '1').map((slot) => {
		const f = (name) => slot.querySelector(`[data-field="${name}"]`)?.value ?? '';
		return {
			kind: f('kind'),
			id: Number(f('id')) || 0,
			url: f('url'),
			title: slot.querySelector('.hodima-rl-target__title')?.textContent.trim() ?? '',
			type: slot.querySelector('.hodima-rl-target__type')?.textContent.trim() ?? '',
			link: slot.querySelector('.hodima-rl-target__url')?.textContent.trim() ?? '',
			thumb: slot.querySelector('.hodima-rl-target__thumb img')?.getAttribute('src') ?? '',
			warnings: [],
			custom: f('title'),
			img_id: Number(f('img_id')) || 0,
		};
	});

	const tcBox = () => document.querySelector('[data-hodima-tc-box]');
	const aeoInput = (name) => document.querySelector(`[name="${name}"]`);
	const AEO_JSON = ['h_ai_en_specs', 'h_ai_faqs', 'h_ai_en_faqs'];

	const adapters = {
		body: cfg.editor === 'block' ? blockBody : editorAdapter('content'),
		intro_html: {
			...editorAdapter('hookcontent'),
			warn() {
				const on = document.querySelector('input[name="hodima_media[enabled]"]');
				const show = document.querySelector('input[name="hodima_media[show_parts][]"][value="intro"]');
				if (on && !on.checked) return ['«تنظیمات رسانه» خاموش است؛ متن معرفی در سایت دیده نمی‌شود (کلید بالای همان کادر).'];
				return show && !show.checked ? ['بخش «متن معرفی» در سایت پنهان است (کلید «نمایش این بخش در سایت»).'] : [];
			},
		},
		description_html: editorAdapter('category_description_edit'),
		video: fieldsAdapter(fieldMaps.video ?? {}, 'video'),
		podcast: fieldsAdapter(fieldMaps.podcast ?? {}, 'podcast'),
		video_watch: fieldsAdapter(fieldMaps.video_watch ?? {}, 'video'),

		discover: {
			box: () => document.querySelector('[data-hodima-dc-entities]')?.closest('.hodima-dc, [data-hodima-dc]') ?? document,
			present: () => !!byName('hodima_discover[title]'),
			read() {
				const topics = (document.querySelector('[data-hodima-dc-entities]')?.value ?? '').split('\n').map((l) => l.trim()).filter(Boolean);
				return { title: byName('hodima_discover[title]').value, topics, skip: !!byName('hodima_discover[skip]')?.checked };
			},
			fill(v) {
				const title = byName('hodima_discover[title]');
				title.value = v.title;
				announce(title);
				const skip = byName('hodima_discover[skip]');
				if (skip && skip.checked !== !!v.skip) {
					skip.checked = !!v.skip;
					announce(skip, 'change');
				}
				const entities = document.querySelector('[data-hodima-dc-entities]');
				if (!entities) return true;
				entities.value = v.topics.join('\n');
				return send(entities, v.topics);
			},
			key: (v) => [v.title.trim(), v.topics, !!v.skip],
			summary: (v) => (v.title || v.topics.length ? `عنوان ${v.title ? 'دارد' : 'خالی'}، ${fa(v.topics.length)} موضوع${v.skip ? '، «برای دیسکاور نیست»' : ''}` : 'خالی'),
			details: (cur, next) => [
				{ label: 'عنوان کارت', from: cur.title || '(خالی)', to: next.title || '(خالی)' },
				{ label: 'موضوعات', from: cur.topics.join('، ') || '—', to: next.topics.join('، ') || '—' },
			],
		},

		suggestions: {
			root: () => document.querySelector('[data-hdm-repeat][data-hdm-name="hod_related_links"]'),
			present() { return !!this.root(); },
			read() {
				return [...this.root().querySelectorAll('[data-hdm-rows] > [data-hdm-row]')].map((row) => ({
					title: row.querySelector('input[name$="[title]"]')?.value ?? '',
					url: row.querySelector('input[name$="[url]"]')?.value ?? '',
					cover: row.querySelector('input[name$="[cover]"]')?.value ?? '',
				}));
			},
			fill(v) { return send(this.root(), v); },
			key: (v) => v.map((c) => [c.title.trim(), c.url.trim(), c.cover.trim()]),
			summary: (v) => (v.length ? `${fa(v.length)} کارت` : 'بدون کارت'),
			details: (cur, next) => [{ label: 'کارت‌ها', from: listText(cur), to: listText(next) }],
		},

		product_button: {
			input: () => document.getElementById('hod_related_product_url'),
			present() { return !!this.input(); },
			read() { return { url: this.input().value }; },
			fill(v) {
				/*
				 * کلید کارت «دکمه محصول» (hd-opt هسته): روشن کردن، «آخرین مقدار» خانه را برمی‌گرداند و
				 * خانه خالی کلید را دوباره خاموش می‌کند؛ پس روشن = اول آدرس (input تا «آخرین مقدار» شود)
				 * بعد کلید، و خاموش = فقط کلید (خانه را خالی می‌کند).
				 */
				const sw = document.getElementById('hvw-product-switch');
				const input = this.input();
				const on = v.url.trim() !== '';
				if (on) {
					input.value = v.url;
					announce(input);
				}
				if (sw && sw.checked !== on) {
					sw.checked = on;
					announce(sw, 'change');
				}
				if (!sw || !on) {
					input.value = v.url;
					announce(input);
				}
				return true;
			},
			key: (v) => v.url.trim(),
			summary: (v) => (v.url.trim() ? 'روشن' : 'خاموش'),
			details: (cur, next) => [{ label: 'لینک محصول', from: cur.url || '—', to: next.url || '—' }],
		},

		upsells: productSelect('upsell_ids'),
		cross_sells: productSelect('crosssell_ids'),

		table: {
			present: () => !!tableRoot(),
			read() {
				const raw = tableRoot().querySelector('input[name="hodima_table_json"]')?.value;
				if (raw) {
					try { return JSON.parse(raw); } catch { /* فرم بدون جاوااسکریپت جدول */ }
				}
				const r = tableRoot();
				return {
					headers: [...r.querySelectorAll('thead input')].map((i) => i.value),
					rows: [...r.querySelectorAll('tbody tr')].map((tr) => [...tr.querySelectorAll('textarea')].map((t) => t.value)),
				};
			},
			fill: (v) => send(tableRoot(), v),
			key: (v) => [v.headers.filter((h) => h !== ''), tableRows(v)],
			summary(v) {
				const rows = tableRows(v).length;
				if (!rows) return 'خالی';
				const cols = Math.max(...tableRows(v).map((r) => r.filter((c) => c !== '').length), 1);
				return `${fa(rows)} ردیف، ${fa(cols)} ستون`;
			},
			details: (cur, next) => [
				{ label: 'سرستون', from: cur.headers.filter(Boolean).join(' | ') || '—', to: next.headers.filter(Boolean).join(' | ') || '—' },
				{ label: 'ردیف ۱', from: tableRows(cur)[0]?.map(plain).join(' | ') || '—', to: tableRows(next)[0]?.map(plain).join(' | ') || '—' },
			],
		},

		faq: {
			list: () => document.querySelector('[data-hodima-faqs]'),
			present() { return !!this.list(); },
			read() {
				return [...this.list().querySelectorAll(':scope > [data-hodima-faq]')].map((row) => ({
					q: row.querySelector('input')?.value ?? '',
					a: row.querySelector('textarea')?.value ?? '',
				}));
			},
			fill(v) { return send(this.list(), v); },
			key: (v) => v,
			summary: (v) => (v.length ? `${fa(v.length)} پرسش` : 'بدون پرسش'),
			details: (cur, next) => next.slice(0, 3).map((item, i) => ({ label: `پرسش ${fa(i + 1)}`, from: cur[i]?.q || '—', to: item.q || '—' })),
			warn() {
				const show = document.querySelector('input[name="hodima_media[show_parts][]"][value="faq"]');
				return show && !show.checked ? ['بخش «سوالات متداول» در سایت پنهان است (کلید «نمایش این بخش در سایت» در تنظیمات رسانه).'] : [];
			},
		},

		related_links: {
			present: () => !!(rlGroups().products || rlGroups().article),
			read() {
				const g = rlGroups();
				return { products: rlRead(g.products), article: rlRead(g.article) };
			},
			fill(v) {
				const g = rlGroups();
				return ['products', 'article'].every((k) => !g[k] || send(g[k], v[k] ?? []));
			},
			// موردهای بیشتر از خانه‌های کادر پر نمی‌شوند، پس در مقایسه هم نیستند
			key: (v) => ['products', 'article'].map((k) => (v[k] ?? []).slice(0, rlSlots(rlGroups()[k]).length).map((i) => `${i.kind}:${i.id || i.url}:${i.custom ?? ''}`)),
			summary: (v) => `خرید ${fa((v.products ?? []).length)}، مقاله ${fa((v.article ?? []).length)}`,
			details: (cur, next) => [
				{ label: 'پیشنهاد خرید', from: listText(cur.products ?? []), to: listText(next.products ?? []) },
				{ label: 'مقاله پیشنهادی', from: listText(cur.article ?? []), to: listText(next.article ?? []) },
			],
			warn(next) {
				const g = rlGroups();
				const out = [];
				[['products', 'پیشنهاد خرید'], ['article', 'مقاله پیشنهادی']].forEach(([k, name]) => {
					const slots = rlSlots(g[k]).length;
					const n = (next[k] ?? []).length;
					if (n > slots) out.push(`«${name}» ${fa(slots)} خانه دارد؛ ${fa(n - slots)} مورد آخر پر نمی‌شود (تعداد خانه‌ها: تنظیمات لینک‌های مرتبط).`);
				});
				return out;
			},
		},

		topic_cluster: {
			present: () => !!tcBox(),
			parts() {
				const box = tcBox();
				return {
					pillar: box.querySelector('input[name="hodima_is_pillar"]'),
					exclude: box.querySelector('input[name="hodima_tc_exclude"]'),
					select: box.querySelector('select[name="hodima_pillar_id[]"]'),
					postSelect: box.querySelector('select[name="hodima_pillar_post_id[]"]'),
				};
			},
			read() {
				const p = this.parts();
				const opts = (sel) => (sel ? [...sel.selectedOptions].map((o) => ({ id: Number(o.value), title: o.textContent.trim() })) : []);
				return {
					pillar: !!p.pillar?.checked,
					exclude: !!p.exclude?.checked,
					parents: opts(p.select),
					post_parents: opts(p.postSelect),
				};
			},
			fill(v) {
				const p = this.parts();
				[['pillar', p.pillar], ['exclude', p.exclude]].forEach(([k, box]) => {
					if (box && box.checked !== !!v[k]) {
						box.checked = !!v[k];
						announce(box, 'change');
					}
				});
				[[p.select, v.parents], [p.postSelect, v.post_parents ?? []]].forEach(([sel, items]) => {
					if (!sel) return;
					if (sel.hodimaMultiSelect?.setItems) {
						sel.hodimaMultiSelect.setItems(items);
					} else {
						sel.replaceChildren(...items.map((i) => new Option(i.title, String(i.id), true, true)));
						announce(sel, 'change');
					}
				});
				return true;
			},
			key(v) {
				const k = [v.pillar, v.exclude, v.parents.map((i) => i.id)];
				return this.parts().postSelect ? [...k, (v.post_parents ?? []).map((i) => i.id)] : k;
			},
			summary(v) {
				const role = v.exclude ? 'خارج از خوشه' : (v.pillar ? 'پیلار' : 'عضو');
				const parents = [...v.parents, ...(v.post_parents ?? [])];
				return `${role}، ${parents.length ? `والد: ${listText(parents, 'title', 2)}` : 'والد خودکار'}`;
			},
			details: () => [],
		},

		aeo: {
			present: () => !!aeoInput('h_ai_en_entity'),
			names: () => Object.keys(aeoKeys).filter((name) => aeoInput(name)),
			read() {
				const out = {};
				this.names().forEach((name) => {
					if (AEO_JSON.includes(name)) {
						try { out[name] = JSON.parse(aeoInput(name).value || '[]'); } catch { out[name] = []; }
					} else if (name === 'h_ai_biz_model') {
						out[name] = document.querySelector('input[name="h_ai_biz_model"]:checked')?.value ?? 'both';
					} else {
						out[name] = aeoInput(name).value;
					}
				});
				return out;
			},
			fill(v) {
				let ok = true;
				Object.entries(v).forEach(([name, value]) => {
					const input = aeoInput(name);
					if (!input) return;
					if (AEO_JSON.includes(name)) {
						input.value = JSON.stringify(value);
						ok = send(input, value) && ok;
					} else if (name === 'h_ai_biz_model') {
						const radio = document.querySelector(`input[name="h_ai_biz_model"][value="${CSS.escape(String(value))}"]`);
						if (radio) {
							radio.checked = true;
							announce(radio, 'change');
						}
					} else {
						input.value = String(value);
						announce(input, input.tagName === 'SELECT' ? 'change' : 'input');
					}
				});
				return ok;
			},
			key(v) {
				const out = {};
				this.names().forEach((n) => { out[n] = v[n] ?? ''; });
				return out;
			},
			filled: (v) => Object.values(v).filter((x) => (Array.isArray(x) ? x.length : String(x).trim() !== '')).length,
			summary(v) { return `${fa(this.filled(v))} از ${fa(this.names().length)} خانه پر`; },
			changed(cur, next) { return this.names().filter((n) => !same(cur[n] ?? '', next[n] ?? '')); },
			details(cur, next) {
				const show = (x) => (Array.isArray(x) ? (x.length ? `${fa(x.length)} مورد` : '—') : (String(x).trim() || '—'));
				return this.changed(cur, next).map((n) => ({ label: aeoKeys[n] ?? n, from: show(cur[n] ?? ''), to: show(next[n] ?? '') }));
			},
		},

		seobox: {
			present: () => !!document.getElementById('seobox_title'),
			read: () => ({
				title: document.getElementById('seobox_title').value,
				description: document.getElementById('seobox_description')?.value ?? '',
			}),
			fill(v) {
				[['seobox_title', v.title], ['seobox_description', v.description]].forEach(([id, value]) => {
					const input = document.getElementById(id);
					if (input) {
						input.value = value;
						announce(input);
					}
				});
				return true;
			},
			key: (v) => [v.title.trim(), v.description.trim()],
			summary: (v, px) => {
				if (!v.title && !v.description) return 'خالی';
				const part = (name, text, w) => `${name} ${text ? (w !== undefined ? `${fa(w)}px` : 'دارد') : 'خالی'}`;
				return `${part('عنوان', v.title, px?.title)}، ${part('توضیح', v.description, px?.description)}`;
			},
			details: (cur, next, px) => [
				{ label: 'عنوان', from: cur.title || '(خالی)', to: next.title || '(خالی)', note: px?.title_max ? `${fa(px.title)} از ${fa(px.title_max)} پیکسل` : '' },
				{ label: 'توضیح', from: cur.description || '(خالی)', to: next.description || '(خالی)', note: px?.description_max ? `${fa(px.description)} از ${fa(px.description_max)} پیکسل` : '' },
			],
		},
	};

	/* =================================================================
	 * وضعیت کادر
	 * ================================================================= */

	let result = null;   // پاسخ بررسی
	let plan = [];       // کادرهای بسته: { key, adapter, cur, next, changed, present, warnings }
	let snapshot = null; // مقدارهای پیش از پر کردن
	let guard = null;

	const setOpen = (open) => {
		el.body.hidden = !open;
		el.toggle.setAttribute('aria-expanded', String(open));
		el.toggle.textContent = open ? 'جمع کردن' : 'باز کردن';
	};

	/** steps: [done, current, bad] برای هر گام ۱ تا ۴. */
	const setSteps = (current, bad = 0) => {
		el.steps.querySelectorAll('li').forEach((li) => {
			const n = Number(li.dataset.step);
			const state = n === bad ? 'bad' : (n < current ? 'done' : (n === current ? 'current' : 'todo'));
			li.dataset.state = state;
			li.querySelector('.hodima-pkg__dot').textContent = state === 'bad' ? '!' : (state === 'done' ? '✓' : fa(n));
			li.querySelector('[data-step-sr]').textContent = { bad: ' (خطا)', done: ' (انجام شد)', current: ' (گام فعلی)', todo: '' }[state];
			if (state === 'current') li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
		});
	};

	const setHead = (text, pill = '', level = '', tone = '') => {
		el.status.textContent = text;
		el.pill.hidden = !pill;
		el.pill.textContent = pill;
		el.pill.className = `hds-pill${level ? ` hds-pill--${level}` : ''}`;
		root.dataset.tone = tone;
	};

	const showPaste = (message = '') => {
		el.paste.hidden = false;
		el.loaded.hidden = true;
		el.pasteError.hidden = !message;
		el.pasteError.textContent = message;
		setSteps(1);
		setHead('متن بسته را بچسبانید یا فایل .json را انتخاب کنید.');
	};

	const lockBox = (lock, ok) => {
		el.lock.replaceChildren();
		el.lock.dataset.state = ok ? 'ok' : 'error';
		el.lock.setAttribute('role', ok ? 'status' : 'alert');
		const icon = make('span', 'dashicons dashicons-lock');
		icon.setAttribute('aria-hidden', 'true');
		const body = make('div', 'hodima-pkg__lock-body');
		body.append(make('strong', 'hodima-pkg__lock-title', ok ? 'قفل آدرس: بسته همین صفحه است' : 'قفل آدرس: بسته مال این صفحه نیست'));
		const line = (label, url, tail = '') => {
			const p = make('p', '', `${label} `);
			const bdi = make('bdi', '', url || '—');
			bdi.dir = 'ltr';
			p.append(bdi, tail);
			return p;
		};
		if (ok) {
			body.append(line('آدرس بسته و صفحه یکی است:', lock.page, ` = شناسه وردپرس ${fa(lock.id)}`));
		} else {
			body.append(line('آدرس بسته:', lock.package), line('آدرس این صفحه:', lock.page, ` (شناسه ${fa(lock.id)})`));
		}
		el.lock.append(icon, body);
	};

	const showError = (data) => {
		el.paste.hidden = true;
		el.loaded.hidden = false;
		el.review.hidden = true;
		el.fillbar.hidden = true;
		el.done.hidden = true;
		el.info.textContent = infoText(data.meta ?? {});
		if (data.lock?.page) {
			lockBox(data.lock, !!data.lock.match);
			el.lock.hidden = false;
		} else {
			el.lock.hidden = true;
		}
		el.errors.replaceChildren(...(data.errors ?? []).map((t) => make('li', '', t)));
		el.errors.hidden = !(data.errors ?? []).length;
		setSteps(2, 2);
		setHead(data.lock && !data.lock.match && data.lock.package ? 'این بسته مال صفحه دیگری است؛ هیچ خانه‌ای پر نمی‌شود.' : (data.errors?.[0] ?? 'بسته پذیرفته نشد.'), 'خطا', 'error', 'error');
	};

	const infoText = (meta) => {
		const parts = [`بسته ${meta.format || '—'}`];
		const typeNames = { product: 'محصول', category: 'دسته محصول', article: 'مقاله', video: 'ویدیو' };
		if (meta.type) parts.push(typeNames[meta.type] ?? meta.type);
		if (meta.date) parts.push(meta.date);
		parts.push(`${fa(Math.max(1, Math.round(new Blob([el.json.value]).size / 1024)))} کیلوبایت`);
		return parts.join(' — ');
	};

	/** یک ردیف کادر: سرخط دکمه‌ای (باز/بسته کردن جزئیات)، هشدارها، جزئیات خانه‌به‌خانه. */
	const renderRow = (item, filled) => {
		const li = make('li', 'hodima-pkg__row');
		const state = !item.present ? 'missing' : (filled ? (item.result === false ? 'failed' : (item.changed ? 'filled' : 'same')) : (item.changed ? (item.empty ? 'empty' : 'change') : 'same'));
		li.dataset.state = state;
		// هشدار فقط برای کادری که پر می‌شود؛ کادر «بدون تغییر» چیزی در فرم عوض نمی‌کند
		const showWarns = item.warnings.length > 0 && !filled && (item.changed || !item.present);
		if (showWarns) li.dataset.warn = '1';

		const head = make('button', 'hodima-pkg__row-head');
		head.type = 'button';
		const detailId = `hodima-pkg-d-${item.key}`;
		const rows = item.present ? item.adapter.details(item.cur, item.next, item.px) : [];
		head.setAttribute('aria-expanded', 'false');
		if (rows.length) head.setAttribute('aria-controls', detailId); else head.disabled = true;

		const mark = { missing: '−', failed: '!', filled: '✓', same: '=', empty: '−', change: showWarns ? '!' : '↻' }[state];
		const dot = make('span', 'hodima-pkg__mark', mark);
		dot.setAttribute('aria-hidden', 'true');

		const change = make('span', 'hodima-pkg__change');
		if (item.present) {
			const arrow = make('span', 'hodima-pkg__arrow', '←');
			arrow.setAttribute('aria-label', 'به');
			change.append(`${item.from} `, arrow, ' ', make('strong', '', item.to));
		} else {
			change.textContent = 'این کادر در این صفحه نیست (ماژولش خاموش است)؛ پر نمی‌شود.';
		}

		const tagText = { missing: 'نیست', failed: 'پر نشد', filled: 'پر شد', same: 'بدون تغییر', empty: 'خالی می‌شود', change: 'عوض می‌شود' }[state];
		head.append(dot, make('span', 'hodima-pkg__name', labels[item.key] ?? item.key), change, make('span', 'hodima-pkg__tag', tagText));
		li.append(head);

		if (showWarns) {
			const ul = make('ul', 'hodima-pkg__warns');
			item.warnings.forEach((w) => ul.append(make('li', '', w)));
			li.append(ul);
		}
		if (state === 'failed') {
			li.append(make('p', 'hodima-pkg__warns', 'این کادر پر کردن از بیرون را نمی‌شناسد (نسخه قدیمی افزونه‌اش، یا نسخه قدیمی در کش مرورگر: صفحه را با Ctrl+Shift+R تازه کنید)؛ دست نخورد.'));
		}

		if (rows.length) {
			const detail = make('div', 'hodima-pkg__detail');
			detail.id = detailId;
			detail.hidden = true;
			rows.forEach((r) => {
				const line = make('div', 'hodima-pkg__field');
				const from = make('span', 'hodima-pkg__from');
				from.append(make('small', '', 'فعلی: '), r.from);
				const to = make('span', 'hodima-pkg__to');
				to.append(make('small', '', 'جدید: '), r.to);
				if (r.note) to.append(make('span', 'hodima-pkg__note-px', r.note));
				line.append(make('span', 'hodima-pkg__flabel', r.label), from, to);
				detail.append(line);
			});
			li.append(detail);
			head.addEventListener('click', () => {
				const open = head.getAttribute('aria-expanded') !== 'true';
				head.setAttribute('aria-expanded', String(open));
				detail.hidden = !open;
			});
		}
		return li;
	};

	const chip = (text, level) => make('span', `hds-pill${level ? ` hds-pill--${level}` : ''}`, text);

	const renderReview = (filled = false) => {
		el.rows.replaceChildren(...plan.map((item) => renderRow(item, filled)));

		const ready = plan.filter((i) => i.present && i.changed);
		const sameN = plan.filter((i) => i.present && !i.changed).length;
		const missing = plan.filter((i) => !i.present).length;
		const warns = plan.reduce((n, i) => n + (i.present && i.changed ? i.warnings.length : 0), 0);
		const failed = plan.filter((i) => i.result === false).length;

		el.chips.replaceChildren();
		if (filled) {
			el.chips.append(chip(`${fa(ready.length - failed)} کادر پر شد`, ''));
			if (failed) el.chips.append(chip(`${fa(failed)} کادر پر نشد`, 'error'));
		} else {
			el.chips.append(chip(`${fa(ready.length)} کادر عوض می‌شود`, 'info'));
			if (warns) el.chips.append(chip(`${fa(warns)} هشدار (پر کردن را نمی‌گیرد)`, 'warn'));
		}
		if (sameN) el.chips.append(chip(`${fa(sameN)} بدون تغییر`, 'info'));
		if (missing) el.chips.append(chip(`${fa(missing)} کادر در صفحه نیست`, 'warn'));

		el.fillbar.hidden = filled || !ready.length;
		el.fillHint.textContent = `فقط خانه‌های همین صفحه پر می‌شوند (${fa(ready.length)} کادر). ذخیره با دکمه «به‌روزرسانی» خودتان.`;
		el.done.hidden = !filled;

		if (filled) {
			setSteps(4);
			el.doneText.textContent = `${fa(ready.length - failed)} کادر پر شد؛ هنوز چیزی ذخیره نشده. کادرها را مرور کنید و «به‌روزرسانی» را بزنید. برای لغو: «بازگرداندن» یا ترک صفحه بدون ذخیره.`;
			setHead(`${fa(ready.length - failed)} کادر از بسته پر شد؛ هنوز ذخیره نشده است.`, 'پر شد، ذخیره نشده', '', 'ok');
		} else {
			setSteps(3);
			const pill = warns ? `آماده پر کردن، ${fa(warns)} هشدار` : (ready.length ? 'آماده پر کردن' : 'همه همان است');
			setHead(
				ready.length ? `بسته برای همین صفحه است: ${fa(ready.length)} کادر عوض می‌شود${sameN ? `، ${fa(sameN)} کادر همان می‌ماند` : ''}.` : 'بسته با فرم فعلی فرقی ندارد؛ چیزی برای پر کردن نیست.',
				pill, warns ? 'warn' : '', ''
			);
		}
	};

	const buildPlan = (data) => {
		const order = Object.keys(boxMap).length ? Object.keys(boxMap) : Object.keys(data.boxes);
		return order.filter((key) => data.boxes[key]).map((key) => {
			const adapter = adapters[boxMap[key] ?? key];
			const box = data.boxes[key];
			const present = !!adapter?.present();
			const next = box.value;
			const px = key === 'seobox' ? next.px : null;
			if (!present) return { key, adapter, present, warnings: box.warnings, changed: false };
			const cur = adapter.read();
			const curPx = px ? { title: px.title_current, description: px.description_current } : null;
			const changed = !same(adapter.key(cur), adapter.key(next));
			const warnings = [...box.warnings, ...(adapter.warn?.(next) ?? [])];
			let to = adapter.changed
				? `${fa(adapter.changed(cur, next).length)} خانه عوض می‌شود`
				: adapter.summary(next, px);
			if (!changed) to = 'همان';
			return {
				key, adapter, present, cur, next, px, changed, warnings,
				empty: changed && ['خالی', 'بدون پرسش', 'بدون کارت', 'خاموش'].includes(adapter.summary(next, px)),
				from: adapter.summary(cur, curPx),
				to,
			};
		});
	};

	const showReview = (data) => {
		result = data;
		plan = buildPlan(data);
		el.paste.hidden = true;
		el.loaded.hidden = false;
		el.errors.hidden = true;
		el.lock.hidden = false;
		el.review.hidden = false;
		el.info.textContent = infoText(data.meta);
		lockBox(data.lock, true);
		el.note.hidden = !data.meta.note;
		el.note.replaceChildren(make('strong', '', 'یادداشت بسته: '), data.meta.note ?? '');
		el.ignored.replaceChildren(...(data.ignored ?? []).map((t) => make('li', '', t)));
		el.ignored.hidden = !(data.ignored ?? []).length;
		renderReview(false);
	};

	/* =================================================================
	 * کنش‌ها
	 * ================================================================= */

	const check = async () => {
		const text = el.json.value.trim();
		if (!text) {
			showPaste('اول متن بسته را بچسبانید.');
			el.json.focus();
			return;
		}

		el.check.disabled = true;
		el.check.textContent = 'در حال بررسی…';
		const body = new FormData();
		body.append('action', 'hodima_seo_pkg_check');
		body.append('nonce', cfg.nonce);
		body.append('object_type', cfg.objectType ?? 'post');
		body.append('object_id', String(cfg.objectId ?? cfg.postId));
		body.append('post_id', String(cfg.postId ?? 0));
		body.append('package', text);
		body.append('current_title', document.getElementById('seobox_title')?.value ?? '');
		body.append('current_description', document.getElementById('seobox_description')?.value ?? '');

		try {
			const res = await fetch(cfg.ajax, { method: 'POST', body, credentials: 'same-origin' });
			const json = await res.json();
			if (!json.success) {
				showPaste(json.data?.message ?? 'بررسی انجام نشد.');
				return;
			}
			if (json.data.ok) showReview(json.data); else showError(json.data);
			el.edit.focus();
		} catch {
			showPaste('ارتباط با سرور برقرار نشد؛ دوباره «بررسی بسته» را بزنید.');
		} finally {
			el.check.disabled = false;
			el.check.textContent = 'بررسی بسته';
		}
	};

	const fill = () => {
		snapshot = {};
		plan.filter((i) => i.present && i.changed).forEach((item) => {
			snapshot[item.key] = item.cur;
			item.result = item.adapter.fill(item.next);
		});
		renderReview(true);
		el.done.focus();
		if (!guard) {
			// تا «به‌روزرسانی» نخورده، ترک صفحه هشدار بدهد (پر کردن هنوز ذخیره نشده)
			guard = (e) => { e.preventDefault(); e.returnValue = ''; };
			window.addEventListener('beforeunload', guard);
			const release = () => window.removeEventListener('beforeunload', guard);
			// فرم کلاسیک (نوشته) یا فرم ترم؛ ویرایشگر بلوکی بدون submit با REST ذخیره می‌کند
			(document.getElementById('post') ?? document.getElementById('edittag'))?.addEventListener('submit', release, { once: true });
			if (cfg.editor === 'block' && window.wp?.data?.subscribe) {
				let saving = false;
				const stop = window.wp.data.subscribe(() => {
					const ed = window.wp.data.select('core/editor');
					const now = ed.isSavingPost() && !ed.isAutosavingPost();
					if (saving && !now) {
						release();
						stop();
					}
					saving = now;
				});
			}
		}
	};

	const undo = () => {
		if (!snapshot) return;
		Object.entries(snapshot).forEach(([key, value]) => adapters[boxMap[key] ?? key]?.fill(value));
		snapshot = null;
		window.removeEventListener('beforeunload', guard);
		guard = null;
		showReview(result);
		el.fill.focus();
	};

	el.toggle.addEventListener('click', () => {
		const open = el.body.hidden;
		setOpen(open);
		if (open && !result) {
			showPaste();
			el.json.focus();
		}
	});
	el.check.addEventListener('click', check);
	el.fileBtn.addEventListener('click', () => el.file.click());
	el.file.addEventListener('change', async () => {
		const file = el.file.files?.[0];
		el.file.value = '';
		if (!file) return;
		if (file.size > 1024 * 1024) {
			showPaste('فایل بزرگ‌تر از یک مگابایت است.');
			return;
		}
		el.json.value = await file.text();
		check();
	});
	el.edit.addEventListener('click', () => {
		result = null;
		showPaste();
		el.json.focus();
	});
	el.fill.addEventListener('click', fill);
	el.undo.addEventListener('click', undo);

	// Enter داخل کادر فرم ویرایش را نفرستد (textarea خودش خط تازه می‌گیرد)
	root.addEventListener('keydown', (e) => {
		if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') e.preventDefault();
	});

	setSteps(1);
	root.hidden = false;
})();
