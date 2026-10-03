/* =========================================================================
 * Hodima Topic Cluster — پیشخوان
 * Version: 4.0.0
 * -------------------------------------------------------------------------
 * JavaScript خالص (بدون jQuery — نسخه قبلی فقط برای یک رویداد به jQuery
 * وابسته بود):
 *   - انتخابگر چندتایی با جستجوی زنده (فقط پیلارها، با مسیر کامل دسته)
 *   - «خارج از خوشه» بخش والد را پنهان می‌کند
 *   - ترتیب دستی زیرمجموعه‌ها (دکمه بالا/پایین، با کیبورد هم)
 *   - کپی آدرس در «پیشنهاد لینک داخلی»
 *   - کارهای سریع گزارش یتیم‌ها
 * ========================================================================= */
(() => {
    'use strict';

    const config = window.hodimaTcConfig ?? null;
    const MAX_OPTIONS = 50;
    const DEBOUNCE_MS = 250;
    let uid = 0;

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    /* ---------------------------------------------------------------------
     * انتخابگر چندتایی
     * ------------------------------------------------------------------- */
    class MultiSelect {

        #select;
        #selected = new Map();
        #options = [];
        #active = -1;
        #loaded = false;
        #controller = null;
        #timer = 0;
        #id = `hodima-ms-${++uid}`;

        constructor(select) {
            this.#select = select;

            for (const opt of select.options) {
                if (opt.selected && opt.value) {
                    this.#selected.set(String(opt.value), opt.text);
                }
            }

            this.#build();
            this.#renderTokens();
            this.#bind();
            select.hodimaMultiSelect = this;
        }

        #build() {
            const select = this.#select;
            select.hidden = true;
            select.setAttribute('aria-hidden', 'true');
            select.tabIndex = -1;

            this.wrap = document.createElement('div');
            this.wrap.className = 'hodima-ms';
            this.wrap.innerHTML = `
                <div class="hodima-ms__control">
                    <div class="hodima-ms__tokens"></div>
                    <input type="text" class="hodima-ms__input" autocomplete="off" role="combobox"
                        aria-autocomplete="list" aria-expanded="false" aria-controls="${this.#id}-list">
                </div>
                <div class="hodima-ms__dropdown" id="${this.#id}-list" role="listbox" aria-multiselectable="true" hidden></div>`;

            this.control = this.wrap.querySelector('.hodima-ms__control');
            this.tokens = this.wrap.querySelector('.hodima-ms__tokens');
            this.input = this.wrap.querySelector('.hodima-ms__input');
            this.dropdown = this.wrap.querySelector('.hodima-ms__dropdown');

            // برچسب <label for> انتخابگر اصلی به ورودی جستجو منتقل می‌شود
            const label = select.id ? document.querySelector(`label[for="${CSS.escape(select.id)}"]`) : null;
            if (label) {
                this.input.id = `${select.id}-search`;
                label.htmlFor = this.input.id;
            }

            select.after(this.wrap);
        }

        open() {
            if (!this.dropdown.hidden) return;
            this.dropdown.hidden = false;
            this.input.setAttribute('aria-expanded', 'true');
            this.wrap.classList.add('is-open');
            if (!this.#loaded) {
                this.#loaded = true;
                this.search('');
            }
        }

        close() {
            this.dropdown.hidden = true;
            this.input.setAttribute('aria-expanded', 'false');
            this.input.removeAttribute('aria-activedescendant');
            this.wrap.classList.remove('is-open');
            this.#active = -1;
        }

        #bind() {
            this.input.addEventListener('focus', () => this.open());

            this.input.addEventListener('input', () => {
                clearTimeout(this.#timer);
                this.open();
                const q = this.input.value;
                this.#timer = setTimeout(() => this.search(q), DEBOUNCE_MS);
            });

            this.input.addEventListener('keydown', (e) => {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.open();
                        this.#move(1);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.#move(-1);
                        break;
                    case 'Enter': {
                        // Enter نباید فرم ویرایش را ارسال کند
                        e.preventDefault();
                        const opt = this.#options[this.#active];
                        if (opt) this.#pick(opt.value, opt.text);
                        break;
                    }
                    case 'Escape':
                        this.close();
                        break;
                    case 'Backspace':
                        if (this.input.value === '' && this.#selected.size > 0) {
                            this.#remove([...this.#selected.keys()].pop());
                        }
                        break;
                    case 'Tab':
                        this.close();
                        break;
                }
            });

            this.control.addEventListener('click', (e) => {
                if (e.target === this.control || e.target === this.tokens) this.input.focus();
            });

            this.tokens.addEventListener('click', (e) => {
                const btn = e.target.closest('.hodima-ms__remove');
                if (!btn) return;
                e.preventDefault();
                this.#remove(btn.dataset.value);
                this.input.focus();
            });

            // mousedown به جای click تا فوکوس ورودی از دست نرود
            this.dropdown.addEventListener('mousedown', (e) => {
                const row = e.target.closest('.hodima-ms__option');
                if (!row) return;
                e.preventDefault();
                this.#pick(row.dataset.value, row.dataset.label);
            });

            this.onDocDown = (e) => {
                if (!this.wrap.contains(e.target)) this.close();
            };
            document.addEventListener('mousedown', this.onDocDown);
        }

        async search(query) {
            if (!config) {
                this.#message('پیکربندی در دسترس نیست.');
                return;
            }

            // درخواست قبلی لغو می‌شود تا پاسخ دیرتر روی پاسخ تازه ننشیند
            this.#controller?.abort();
            this.#controller = new AbortController();

            this.#message('در حال جستجو...');

            const params = new URLSearchParams({
                action: 'hodima_tc_search',
                nonce: config.nonce,
                q: query || '',
                context: this.#select.dataset.context || '',
                type: this.#select.dataset.type || '',
                exclude: this.#select.dataset.exclude || '',
            });

            try {
                const res = await fetch(`${config.ajaxUrl}?${params}`, { credentials: 'same-origin', signal: this.#controller.signal });
                const json = await res.json();
                this.#render(json?.success && Array.isArray(json.data) ? json.data : []);
            } catch (err) {
                if (err.name !== 'AbortError') this.#message('ارتباط با سرور برقرار نشد.');
            }
        }

        #message(text) {
            this.#options = [];
            this.#active = -1;
            this.dropdown.innerHTML = `<div class="hodima-ms__msg" role="status">${escapeHtml(text)}</div>`;
        }

        #render(items) {
            this.#options = items
                .filter((item) => !this.#selected.has(String(item.value)))
                .slice(0, MAX_OPTIONS)
                .map((item) => ({ value: String(item.value), text: String(item.text) }));

            this.#active = -1;

            if (this.#options.length === 0) {
                this.#message('پیلاری یافت نشد. فقط صفحه‌ها و دسته‌هایی که «پیلار» هستند والد می‌شوند.');
                return;
            }

            this.dropdown.innerHTML = this.#options.map((opt, i) =>
                `<div class="hodima-ms__option" role="option" aria-selected="false" id="${this.#id}-opt-${i}"
                    data-value="${escapeHtml(opt.value)}" data-label="${escapeHtml(opt.text)}">${escapeHtml(opt.text)}</div>`
            ).join('');
        }

        #move(step) {
            if (this.#options.length === 0) return;
            const rows = this.dropdown.querySelectorAll('.hodima-ms__option');
            rows[this.#active]?.classList.remove('is-active');
            this.#active = (this.#active + step + this.#options.length) % this.#options.length;
            const row = rows[this.#active];
            if (row) {
                row.classList.add('is-active');
                row.scrollIntoView({ block: 'nearest' });
                this.input.setAttribute('aria-activedescendant', row.id);
            }
        }

        #pick(value, label) {
            if (!value || this.#selected.has(String(value))) return;
            this.#selected.set(String(value), label);
            this.input.value = '';
            this.#renderTokens();
            this.#sync();
            this.search('');
            this.input.focus();
        }

        #remove(value) {
            if (!this.#selected.delete(String(value))) return;
            this.#renderTokens();
            this.#sync();
            // گزینه حذف‌شده دوباره قابل انتخاب شود؛ کادر بسته باز نمی‌شود
            if (!this.dropdown.hidden) {
                this.search(this.input.value);
            } else {
                this.#loaded = false;
            }
        }

        #renderTokens() {
            this.input.placeholder = this.#selected.size ? '' : 'جستجو و انتخاب پیلار...';
            this.tokens.innerHTML = [...this.#selected].map(([value, label]) =>
                `<span class="hodima-ms__token" title="${escapeHtml(label)}">
                    <span class="hodima-ms__token-text">${escapeHtml(label)}</span>
                    <button type="button" class="hodima-ms__remove" data-value="${escapeHtml(value)}" aria-label="حذف ${escapeHtml(label)}">&times;</button>
                </span>`
            ).join('');
        }

        /** همگام‌سازی <select> اصلی تا فرم همان name قبلی را ارسال کند. */
        #sync() {
            this.#select.replaceChildren(...[...this.#selected].map(([value, label]) => {
                const opt = new Option(label, value, true, true);
                return opt;
            }));
            this.#select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        clear() {
            this.#selected.clear();
            this.#renderTokens();
            this.#sync();
        }

        destroy() {
            document.removeEventListener('mousedown', this.onDocDown);
            this.wrap.remove();
            this.#select.hidden = false;
            this.#select.removeAttribute('aria-hidden');
            this.#select.removeAttribute('tabindex');
            delete this.#select.hodimaMultiSelect;
        }
    }

    const initSelects = (root = document) => {
        root.querySelectorAll('.hodima-tc-ajax-select').forEach((el) => {
            el.hodimaMultiSelect?.destroy();
            new MultiSelect(el);
        });
    };

    /* ---------------------------------------------------------------------
     * رفتارهای کادر
     * ------------------------------------------------------------------- */

    // «خارج از خوشه» → بخش والد پنهان (مقدارها حفظ می‌شوند)
    document.addEventListener('change', (e) => {
        const box = e.target.closest?.('.hodima-tc-exclude');
        if (!box) return;
        const parents = box.closest('.htc-box')?.querySelector('.htc-parents');
        if (parents) parents.hidden = box.checked;
    });

    // ترتیب زیرمجموعه‌ها
    document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('.htc-order__up, .htc-order__down');
        if (!btn) return;
        e.preventDefault();
        const item = btn.closest('.htc-order__item');
        const up = btn.classList.contains('htc-order__up');
        const sibling = up ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        up ? sibling.before(item) : sibling.after(item);
        btn.focus();
    });

    // کپی آدرس
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('.htc-copy');
        if (!btn) return;
        e.preventDefault();
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            btn.classList.add('is-copied');
            btn.setAttribute('aria-label', 'کپی شد');
            setTimeout(() => btn.classList.remove('is-copied'), 1500);
        } catch {
            window.prompt('آدرس را کپی کنید:', btn.dataset.copy);
        }
    });

    // کارهای سریع گزارش یتیم‌ها
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('[data-hodima-tc-quick] [data-do]');
        if (!btn || !config) return;
        e.preventDefault();

        const row = btn.closest('tr');
        const msg = row.querySelector('.htc-row-msg');
        const body = new URLSearchParams({
            action: 'hodima_tc_quick',
            nonce: config.nonce,
            ref: row.dataset.ref,
            do: btn.dataset.do,
            parent: row.querySelector('.htc-quick__parent')?.value ?? '',
        });

        if (btn.dataset.do === 'parent' && !body.get('parent')) {
            msg.textContent = 'اول پیلار والد را انتخاب کنید.';
            return;
        }

        row.querySelectorAll('button, select').forEach((el) => { el.disabled = true; });

        try {
            const res = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
            const json = await res.json();
            msg.textContent = json?.data?.message ?? '';
            if (json?.success) {
                row.classList.add('is-done');
                return;
            }
        } catch {
            msg.textContent = 'ارتباط با سرور برقرار نشد.';
        }
        row.querySelectorAll('button, select').forEach((el) => { el.disabled = false; });
    });

    /* ---------------------------------------------------------------------
     * راه‌اندازی
     * ------------------------------------------------------------------- */
    const start = () => {
        initSelects();

        /*
         * فرم «افزودن دسته» وردپرس بعد از ذخیره با AJAX ردیف جدید را به
         * جدول اضافه می‌کند و فقط ورودی‌های متنی را پاک می‌کند؛ بدون این،
         * والدها و تیک «پیلار» دسته قبلی به دسته بعدی به ارث می‌رسید.
         */
        const list = document.getElementById('the-list');
        const form = document.getElementById('addtag');
        if (list && form) {
            new MutationObserver((records) => {
                if (!records.some((r) => r.addedNodes.length)) return;
                form.querySelectorAll('.hodima-tc-ajax-select').forEach((sel) => sel.hodimaMultiSelect?.clear());
                form.querySelectorAll('.hodima-pillar-toggle, .hodima-tc-exclude').forEach((box) => { box.checked = false; });
                const parents = form.querySelector('.htc-parents');
                if (parents) parents.hidden = false;
            }).observe(list, { childList: true });
        }
    };

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
})();
