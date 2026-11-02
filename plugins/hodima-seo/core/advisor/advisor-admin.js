/*
 * دستار سئو — رفتار صفحه پیشخوان (core/advisor/advisor-admin.php). همه‌چیز بدون JS هم کار می‌کند:
 * تنظیمات تب خودش است (tab=settings)؛ پنجره کلید بدون JS دیده نمی‌شود ولی کلید با wp-config هم ثابت می‌شود.
 */
(() => {
	'use strict';

	/* ── تب‌ها: پنج تب در موبایل کشیدنی است؛ تب فعال خودش وسط می‌آید (SEO 3.4.2) ── */
	const activeTab = document.querySelector('.hodima-advisor__tabs [aria-current="page"]');
	if (activeTab) {
		const strip = activeTab.parentElement;
		if (strip && strip.scrollWidth > strip.clientWidth) {
			strip.scrollLeft += activeTab.getBoundingClientRect().left + activeTab.offsetWidth / 2 - (strip.getBoundingClientRect().left + strip.clientWidth / 2);
		}
	}

	/* ── کپی مسیر پوشه خزش (data-hdsa-copy = شناسه عنصر حاوی متن) ── */
	document.querySelectorAll('[data-hdsa-copy]').forEach((button) => {
		button.addEventListener('click', async () => {
			const source = document.getElementById(button.dataset.hdsaCopy || '');
			if (!source) return;
			const label = button.textContent;
			try {
				await navigator.clipboard.writeText(source.textContent.trim());
				button.textContent = 'کپی شد';
			} catch {
				window.getSelection()?.selectAllChildren(source); // مرورگر کپی نداد: متن انتخاب می‌شود تا دستی کپی شود
				button.textContent = 'انتخاب شد؛ Ctrl+C';
			}
			setTimeout(() => { button.textContent = label; }, 2000);
		});
	});

	/* ── منوی «⋯» ردیف پیشنهادها (details): با کلیک بیرون یا Esc بسته می‌شود؛ بدون JS هم باز و بسته می‌شود ── */
	document.addEventListener('click', (event) => {
		const inside = event.target instanceof Element ? event.target.closest('.hodima-advisor__menu') : null;
		document.querySelectorAll('.hodima-advisor__menu[open]').forEach((menu) => {
			if (menu !== inside) menu.removeAttribute('open');
		});
	});
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		document.querySelectorAll('.hodima-advisor__menu[open]').forEach((menu) => {
			menu.removeAttribute('open');
			menu.querySelector('summary')?.focus();
		});
	});

	/* ── «بررسی لینک‌ها» زیر هر گروه رقابت: HTML نتیجه (escape‌شده در سرور) از admin-ajax می‌آید ── */
	document.addEventListener('click', async (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-hdsa-links-run]') : null;
		const box = button?.closest('[data-hdsa-links]');
		const config = window.hodimaAdvisorLinks;
		if (!button || !box || !config) return;

		const out = box.querySelector('[data-hdsa-links-out]');
		const hint = box.querySelector('[data-hdsa-links-hint]');
		const idle = button.textContent;
		const body = new FormData();
		body.set('action', 'hodima_advisor_links');
		body.set('nonce', config.nonce);
		body.set('term', box.dataset.term || '');
		if (button.dataset.force) body.set('force', '1');

		button.disabled = true;
		button.textContent = 'در حال بررسی…';
		out.setAttribute('aria-busy', 'true');
		try {
			const response = await fetch(config.ajax, { method: 'POST', body, credentials: 'same-origin' });
			const data = await response.json();
			if (!data.success) throw new Error(data.data?.message || 'بررسی انجام نشد.');
			out.innerHTML = data.data.html; // خروجی سرور؛ همه مقدارها در PHP escape شده‌اند
			if (hint) hint.hidden = true;
			box.querySelector('[data-hdsa-links-run]:not([data-force])')?.setAttribute('hidden', '');
		} catch (error) {
			out.textContent = error instanceof Error && error.message ? error.message : 'بررسی انجام نشد؛ دوباره تلاش کنید.';
		} finally {
			out.removeAttribute('aria-busy');
			if (button.isConnected) {
				button.disabled = false;
				button.textContent = idle;
			}
		}
	});

	/* ── تأیید پیش از «برداشتن کلید» ── */
	document.querySelectorAll('form[data-hdsa-confirm]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			if (!window.confirm(form.dataset.hdsaConfirm || '')) event.preventDefault();
		});
	});

	/* ── پنجره‌های تأیید عمومی (پاک‌سازی گروهی کارها؛ SEO 3.5.5): data-hdsa-open="شناسه" و data-hdsa-close ── */
	document.querySelectorAll('[data-hdsa-open]').forEach((button) => {
		const target = document.getElementById(button.dataset.hdsaOpen || '');
		if (!target) return;
		button.addEventListener('click', () => {
			if (typeof target.showModal === 'function') target.showModal(); else target.setAttribute('open', '');
		});
		target.querySelectorAll('[data-hdsa-close]').forEach((close) => close.addEventListener('click', () => target.close?.()));
		target.addEventListener('click', (event) => { if (event.target === target) target.close?.(); }); // کلیک بیرون کادر
	});

	/* ── پنجره افزودن/جایگزینی کلید حساب سرویس (همان رفتار ماژول دیسکاور) ── */
	const dialog = document.querySelector('[data-hdsa-key-dialog]');
	if (!dialog) return;

	const text = dialog.querySelector('[data-hdsa-key-text]');
	const file = dialog.querySelector('[data-hdsa-key-file]');
	const name = dialog.querySelector('[data-hdsa-key-name]');
	const preview = dialog.querySelector('[data-hdsa-key-preview]');
	const save = dialog.querySelector('[data-hdsa-key-save]');

	/** بررسی سریع در مرورگر تا مدیر پیش از ذخیره بداند فایل درست است (سرور دوباره بررسی می‌کند). */
	const check = () => {
		const raw = text.value.trim();
		preview.className = 'hds-dialog__preview';
		save.disabled = true;
		if (!raw) { preview.textContent = ''; return; }
		let data = null;
		try { data = JSON.parse(raw); } catch { /* متن JSON نیست */ }
		if (!data || typeof data !== 'object') {
			preview.textContent = 'این متن JSON معتبر نیست.';
			preview.classList.add('is-error');
			return;
		}
		if (data.type !== 'service_account' || !data.client_email || !data.private_key) {
			preview.textContent = 'این فایل کلید Service Account نیست (باید type برابر service_account و client_email و private_key داشته باشد).';
			preview.classList.add('is-error');
			return;
		}
		preview.textContent = `حساب: ${data.client_email}`;
		preview.classList.add('is-ok');
		save.disabled = false;
	};

	document.querySelector('[data-hdsa-key-open]')?.addEventListener('click', () => {
		text.value = '';
		file.value = '';
		name.textContent = '';
		check();
		if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
	});
	dialog.querySelector('[data-hdsa-key-cancel]')?.addEventListener('click', () => dialog.close?.());
	dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close?.(); }); // کلیک بیرون کادر
	dialog.querySelector('[data-hdsa-key-pick]')?.addEventListener('click', () => file.click());
	text.addEventListener('input', check);
	file.addEventListener('change', () => {
		const picked = file.files?.[0];
		if (!picked) return;
		const reader = new FileReader();
		reader.onload = () => {
			text.value = String(reader.result || '');
			name.textContent = picked.name;
			check();
		};
		reader.readAsText(picked);
	});
})();

/* ── ردیف‌های «خزش و داده» (SEO 3.4.8): نوار پیشرفت زنده برای «اجرا همین حالا»، پنل تنظیم مستقل هر ردیف ── */
(() => {
	'use strict';

	const root = document.querySelector('.hodima-advisor');
	if (!root) return;
	root.classList.add('is-js'); // بدون JS پنل‌های تنظیمات همیشه باز و دکمه «اجرا» فرم معمولی است

	const fa = (n) => Number(n).toLocaleString('fa-IR');
	const plain = (html) => new DOMParser().parseFromString(String(html || ''), 'text/html').body.textContent.trim();

	/* تنظیمات هر ردیف مستقل باز و بسته می‌شود */
	root.querySelectorAll('[data-hdsa-job-toggle]').forEach((button) => {
		const panel = document.getElementById(button.getAttribute('aria-controls') || '');
		button.addEventListener('click', () => {
			const open = button.getAttribute('aria-expanded') !== 'true';
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
			panel?.classList.toggle('is-open', open);
		});
	});

	const post = async (article, body) => {
		const data = new URLSearchParams(body);
		data.set('_ajax_nonce', article.dataset.hdsaJobNonce || '');
		const response = await fetch(article.dataset.hdsaAjax || '', { method: 'POST', credentials: 'same-origin', body: data });
		return response.json();
	};

	const parts = (article) => ({
		prog: article.querySelector('[data-hdsa-job-prog]'),
		text: article.querySelector('[data-hdsa-job-text]'),
		pc: article.querySelector('[data-hdsa-job-pc]'),
		bar: article.querySelector('[data-hdsa-crawl-bar]'),
		pill: article.querySelector('[data-hdsa-pill]'),
		done: article.querySelector('[data-hdsa-job-done]'),
		run: article.querySelector('[data-hdsa-job-form] button[type="submit"]'),
	});

	const paint = (article, percent, label) => {
		const p = parts(article);
		if (p.text && label) p.text.textContent = label;
		if (p.pc) p.pc.textContent = fa(percent) + '٪';
		if (p.bar) {
			p.bar.setAttribute('aria-valuenow', String(percent));
			const fill = p.bar.querySelector('i');
			if (fill) fill.style.inlineSize = percent + '%';
		}
	};

	const setRunning = (article, on, label = '', percent = 0) => {
		const p = parts(article);
		if (on) {
			article.dataset.running = '1';
			if (p.pill && !p.pill.dataset.was) {
				p.pill.dataset.was = p.pill.textContent;
				p.pill.dataset.wasClass = p.pill.className;
			}
			if (p.pill) { p.pill.className = 'hds-pill hds-pill--info hodima-advisor__job-pill'; p.pill.textContent = 'در حال اجرا'; }
			if (p.prog) p.prog.hidden = false;
			if (p.done) p.done.hidden = true;
			paint(article, percent, label);
		} else {
			delete article.dataset.running;
			if (p.prog) p.prog.hidden = true;
		}
		if (p.run) {
			p.run.disabled = on;
			const text = p.run.querySelector('[data-hdsa-run-label]'); // «در حال اجرا…» تا پایان کار؛ بعد برچسب اولش (SEO 3.4.9)
			if (text && on) {
				text.dataset.was = text.dataset.was || text.textContent;
				text.textContent = 'در حال اجرا…';
			} else if (text && text.dataset.was) {
				text.textContent = text.dataset.was;
				delete text.dataset.was;
			}
		}
	};

	const finish = (article, text, isError, facts) => {
		const p = parts(article);
		setRunning(article, false);
		if (p.pill) {
			if (facts?.pill) { p.pill.textContent = facts.pill; p.pill.className = facts.pill_class + ' hodima-advisor__job-pill'; }
			else if (p.pill.dataset.was) { p.pill.textContent = p.pill.dataset.was; p.pill.className = p.pill.dataset.wasClass; }
			delete p.pill.dataset.was;
		}
		if (facts) {
			const last = article.querySelector('[data-hdsa-fact="last"]');
			const expect = article.querySelector('[data-hdsa-fact="expect"]');
			if (last && facts.last) last.textContent = facts.last;
			if (expect && facts.expect) expect.textContent = facts.expect;
			article.dataset.level = facts.level || article.dataset.level;
		}
		if (p.done && text) {
			p.done.textContent = (isError ? '' : '✓ ') + text;
			p.done.classList.toggle('is-error', !!isError);
			p.done.hidden = false;
			setTimeout(() => { p.done.hidden = true; }, 12000);
		}
	};

	const label = (progress) => (progress.total > 1 ? `گام ${fa(progress.step)} از ${fa(progress.total)}: ${progress.label}` : progress.label);

	/* نظرسنجی وضعیت یک کار (غیر خزنده) تا وقتی در جریان است؛ `inFlight()` = درخواست «اجرا» هنوز باز است */
	const watch = (article, job, inFlight) => new Promise((resolve) => {
		let idle = 0;
		const tick = async () => {
			try {
				const json = await post(article, { action: 'hodima_advisor_job_status', job });
				if (json?.success) {
					const progress = json.data.progress;
					if (progress.running) {
						idle = 0;
						paint(article, progress.percent, label(progress));
					} else if (!inFlight()) {
						idle += 1;
					}
					if (!progress.running && !inFlight() && idle >= 1) {
						resolve({ progress, facts: json.data.facts });
						return;
					}
				}
			} catch { /* خطای گذرا: دوباره */ }
			setTimeout(tick, 1500);
		};
		tick();
	});

	const run = async (form) => {
		const article = form.closest('[data-job]');
		const job = form.dataset.hdsaJobForm || '';
		if (!article || !job || article.dataset.running) return;

		setRunning(article, true, 'در حال شروع…', 0);

		const data = new FormData(form); // شامل فیلد «بازه» که با ویژگی form به همین فرم وصل است
		const body = { action: 'hodima_advisor_job_run', job };
		if (data.get('days')) body.days = String(data.get('days'));

		let flying = true;
		let result = null;
		const request = post(article, body).then((json) => { result = json; }).catch(() => { result = { success: false, data: { message: 'ارتباط با سرور قطع شد؛ نتیجه را چند دقیقه بعد در همین ردیف ببینید.' } }; }).finally(() => { flying = false; });

		if (job === 'crawler') {
			await request;
			if (result?.success) {
				// خزنده با گام ایجکس خودش پیش می‌رود؛ همان حلقه صفحه (پایین) نوار را پر می‌کند
				const box = parts(article).prog;
				box.dataset.hdsaCrawlLive = article.dataset.hdsaCrawlNonce || '';
				box.dataset.hdsaAjax = article.dataset.hdsaAjax || '';
				document.dispatchEvent(new CustomEvent('hdsa:crawl-live', { detail: box }));
			} else {
				finish(article, result?.data?.message || 'شروع نشد.', true, null);
			}
			return;
		}

		const watched = await watch(article, job, () => flying);
		await request;
		const ok = !!result?.success;
		const text = ok ? plain(result.data.html) : (result?.data?.message || 'اجرا نشد.');
		const errored = !ok || result.data.type === 'error' || watched.progress.error;
		finish(article, text || watched.progress.text, errored, ok ? result.data.facts : watched.facts);
	};

	root.querySelectorAll('[data-hdsa-job-form]').forEach((form) => {
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			run(form);
		});
	});

	/* صفحه وسط کاری باز شد که در پس‌زمینه می‌دود (ساخت پیشنهادها بعد از دریافت، کرون): نوار زنده می‌شود */
	root.querySelectorAll('[data-hdsa-job-poll]').forEach((box) => {
		const article = box.closest('[data-job]');
		const job = article?.dataset.job || '';
		if (!article || !job) return;
		const run = parts(article).run;
		if (run) run.disabled = true; // کار از قبل در پس‌زمینه می‌دود
		watch(article, job, () => false).then(({ progress, facts }) => finish(article, progress.text, progress.error, facts));
	});
})();

/* ── خزنده: وقتی دوری در جریان است صفحه گام‌به‌گام آن را پیش می‌برد و وضعیت را تازه می‌کند ── */
(() => {
	const drive = (box) => {
		if (box.dataset.hdsaDriving) return;
		box.dataset.hdsaDriving = '1';

		const article = box.closest('[data-job]');
		const text = box.querySelector('[data-hdsa-crawl-text]');
		const pc = box.querySelector('[data-hdsa-job-pc]');
		const bar = box.querySelector('[data-hdsa-crawl-bar]'); // نوار پیشرفت ردیف خزنده
		const pill = article?.querySelector('[data-hdsa-pill]');
		let failures = 0;
		let wasRunning = false;

		if (article) {
			article.dataset.running = '1';
			box.hidden = false;
		}

		const step = async () => {
			const body = new URLSearchParams({ action: 'hodima_advisor_crawler_step', _ajax_nonce: box.dataset.hdsaCrawlLive || '' });
			try {
				const response = await fetch(box.dataset.hdsaAjax || '', { method: 'POST', credentials: 'same-origin', body });
				const json = await response.json();
				if (!json?.success) throw new Error('step');
				failures = 0;
				if (text) text.textContent = json.data.text;
				if (pill && json.data.running) pill.textContent = 'در حال اجرا';
				if (typeof json.data.percent === 'number') {
					if (pc) pc.textContent = Number(json.data.percent).toLocaleString('fa-IR') + '٪';
					if (bar) {
						bar.setAttribute('aria-valuenow', String(json.data.percent));
						const fill = bar.querySelector('i');
						if (fill) fill.style.inlineSize = `${json.data.percent}%`;
					}
				}
				if (json.data.finished) {
					location.reload(); // دور تمام شد: نمره و ایرادها تازه
					return;
				}
				if (!json.data.running) {
					if (wasRunning) location.reload(); // کرون دور را تمام کرد (یا مکث شد): وضعیت و نمره تازه (SEO 3.4.4)
					return; // مکث یا بیرون از ساعت: صفحه بیهوده نمی‌کوبد
				}
				wasRunning = true;
				setTimeout(step, Number(json.data.delay) || 1500); // کرون پیش می‌برد: پرسش وضعیت کم‌تکرار (SEO 3.4.4)
			} catch {
				if (++failures < 3) setTimeout(step, 8000); // خطای گذرا: چند بار دوباره
			}
		};

		step();
	};

	document.querySelectorAll('[data-hdsa-crawl-live]').forEach(drive);
	document.addEventListener('hdsa:crawl-live', (event) => drive(event.detail));
})();

/* ── نوار «خزش تمام شد» (SEO 3.4.9): دکمه «بستن» همان بار پنهانش می‌کند؛ ۱۵ دقیقه بعد خودش دیگر نمی‌آید ── */
document.querySelectorAll('[data-hdsa-crawlbar-close]').forEach((button) => {
	button.addEventListener('click', () => button.closest('.hodima-advisor__crawlbar')?.setAttribute('hidden', ''));
});
