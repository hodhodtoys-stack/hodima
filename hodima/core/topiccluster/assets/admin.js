/* =========================================================================
 * Hodima Topic Cluster — انتخابگر چندتایی با جستجوی AJAX
 * Version: 3.1.0
 * -------------------------------------------------------------------------
 * اصلاحات نسبت به نسخه قبلی:
 *
 *   ۱. کادر گزینه‌ها موقع بارگذاری صفحه خودبه‌خود باز می‌شد.
 *      سازنده search('') را صدا می‌زد و search بدون شرط کادر را باز
 *      می‌کرد. حالا کادر فقط با تعامل کاربر (فوکوس، تایپ، کلیک) باز
 *      می‌شود.
 *
 *   ۲. هر انتخابگر موقع بارگذاری یک درخواست AJAX می‌زد، حتی اگر هرگز
 *      استفاده نمی‌شد. حالا اولین درخواست در اولین فوکوس ارسال می‌شود.
 *
 *   ۳. حذف یک توکن کادر را دوباره باز می‌کرد.
 *
 *   ۴. هر نمونه یک listener سراسری روی document می‌گذاشت که هنگام
 *      بازسازی (بعد از افزودن دسته‌بندی با AJAX) برداشته نمی‌شد.
 *
 *   ۵. ناوبری کیبورد و ARIA اضافه شد: فلش بالا/پایین، Enter، Escape،
 *      و نقش‌های combobox/listbox/option برای صفحه‌خوان.
 * ========================================================================= */
(function () {
    'use strict';

    var MAX_OPTIONS = 50;
    var DEBOUNCE_MS = 250;
    var instanceCounter = 0;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function HodimaMultiSelect(select) {

        this.select   = select;
        this.selected = new Map();
        this.options  = [];
        this.active   = -1;
        this.loaded   = false;
        this.ticket   = 0;
        this.uid      = 'hodima-ms-' + (++instanceCounter);

        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.selected && opt.value) {
                this.selected.set(String(opt.value), opt.text);
            }
        }, this);

        this.build();
        this.renderTokens();
        this.bind();
        // عمدا هیچ search() ای اینجا نیست — کادر بسته می‌ماند
    }

    HodimaMultiSelect.prototype.build = function () {

        var select = this.select;
        select.hidden = true;
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        this.wrap = document.createElement('div');
        this.wrap.className = 'hodima-ms';

        this.control = document.createElement('div');
        this.control.className = 'hodima-ms__control';

        this.tokens = document.createElement('div');
        this.tokens.className = 'hodima-ms__tokens';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'hodima-ms__input';
        this.input.placeholder = 'جستجو و انتخاب...';
        this.input.autocomplete = 'off';
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-controls', this.uid + '-list');

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'hodima-ms__dropdown';
        this.dropdown.id = this.uid + '-list';
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.setAttribute('aria-multiselectable', 'true');
        this.dropdown.hidden = true;

        this.control.appendChild(this.tokens);
        this.control.appendChild(this.input);
        this.wrap.appendChild(this.control);
        this.wrap.appendChild(this.dropdown);

        select.parentNode.insertBefore(this.wrap, select.nextSibling);
        select.hodimaMultiSelect = this;
    };

    /* ---------------------------------------------------------------------
     * باز و بسته کردن
     * ------------------------------------------------------------------- */

    HodimaMultiSelect.prototype.open = function () {
        if (!this.dropdown.hidden) return;
        this.dropdown.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        this.wrap.classList.add('is-open');

        // بارگذاری تنبل: اولین درخواست فقط در اولین باز شدن
        if (!this.loaded) {
            this.loaded = true;
            this.search('');
        }
    };

    HodimaMultiSelect.prototype.close = function () {
        this.dropdown.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        this.wrap.classList.remove('is-open');
        this.active = -1;
    };

    /* ---------------------------------------------------------------------
     * رویدادها
     * ------------------------------------------------------------------- */

    HodimaMultiSelect.prototype.bind = function () {

        var self  = this;
        var timer = null;

        this.input.addEventListener('focus', function () {
            self.open();
        });

        this.input.addEventListener('input', function () {
            clearTimeout(timer);
            self.open();
            var q = self.input.value;
            timer = setTimeout(function () { self.search(q); }, DEBOUNCE_MS);
        });

        this.input.addEventListener('keydown', function (e) {

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    self.open();
                    self.moveActive(1);
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    self.moveActive(-1);
                    break;

                case 'Enter':
                    // Enter نباید فرم ویرایش پست را ارسال کند
                    e.preventDefault();
                    if (self.active >= 0 && self.options[self.active]) {
                        var opt = self.options[self.active];
                        self.selectItem(opt.value, opt.text);
                    }
                    break;

                case 'Escape':
                    self.close();
                    break;

                case 'Backspace':
                    if (self.input.value === '' && self.selected.size > 0) {
                        var last = Array.from(self.selected.keys()).pop();
                        self.deselect(last);
                    }
                    break;

                case 'Tab':
                    self.close();
                    break;
            }
        });

        this.control.addEventListener('click', function (e) {
            if (e.target === self.control || e.target === self.tokens) {
                self.input.focus();
            }
        });

        this.tokens.addEventListener('click', function (e) {
            var btn = e.target.closest('.hodima-ms__remove');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            self.deselect(btn.dataset.value);
        });

        // mousedown به جای click تا فوکوس ورودی از دست نرود
        this.dropdown.addEventListener('mousedown', function (e) {
            var row = e.target.closest('.hodima-ms__option');
            if (!row) return;
            e.preventDefault();
            self.selectItem(row.dataset.value, row.dataset.label);
        });

        // listener سراسری با ارجاع ذخیره‌شده، تا در destroy برداشته شود
        this.onDocClick = function (e) {
            if (!self.wrap.contains(e.target)) {
                self.close();
            }
        };
        document.addEventListener('mousedown', this.onDocClick);
    };

    /* ---------------------------------------------------------------------
     * جستجو و گزینه‌ها
     * ------------------------------------------------------------------- */

    HodimaMultiSelect.prototype.search = function (query) {

        var self = this;

        if (typeof hodimaTcConfig === 'undefined') {
            this.renderMessage('پیکربندی در دسترس نیست.');
            return;
        }

        this.renderMessage('در حال جستجو...');

        var params = new URLSearchParams({
            action:  'hodima_tc_search',
            nonce:   hodimaTcConfig.nonce,
            q:       query || '',
            context: this.select.dataset.context || '',
            type:    this.select.dataset.type || '',
            exclude: this.select.dataset.exclude || ''
        });

        // پاسخ‌های قدیمی که دیرتر می‌رسند نادیده گرفته می‌شوند
        var ticket = ++this.ticket;

        fetch(hodimaTcConfig.ajaxUrl + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (ticket !== self.ticket) return;
                self.renderOptions((json && json.success && Array.isArray(json.data)) ? json.data : []);
            })
            .catch(function () {
                if (ticket !== self.ticket) return;
                self.renderMessage('ارتباط با سرور برقرار نشد.');
            });
    };

    HodimaMultiSelect.prototype.renderMessage = function (text) {
        this.options = [];
        this.active  = -1;
        this.dropdown.innerHTML = '<div class="hodima-ms__msg" role="status">' + escapeHtml(text) + '</div>';
    };

    HodimaMultiSelect.prototype.renderOptions = function (items) {

        this.options = items
            .filter(function (item) { return !this.selected.has(String(item.value)); }, this)
            .slice(0, MAX_OPTIONS)
            .map(function (item) { return { value: String(item.value), text: String(item.text) }; });

        this.active = -1;

        if (this.options.length === 0) {
            this.renderMessage('موردی یافت نشد.');
            return;
        }

        var uid = this.uid;

        this.dropdown.innerHTML = this.options.map(function (opt, i) {
            return '<div class="hodima-ms__option" role="option" aria-selected="false"'
                 + ' id="' + uid + '-opt-' + i + '"'
                 + ' data-value="' + escapeHtml(opt.value) + '"'
                 + ' data-label="' + escapeHtml(opt.text) + '">'
                 + escapeHtml(opt.text) + '</div>';
        }).join('');
    };

    HodimaMultiSelect.prototype.moveActive = function (step) {

        if (this.options.length === 0) return;

        var rows = this.dropdown.querySelectorAll('.hodima-ms__option');
        if (rows[this.active]) rows[this.active].classList.remove('is-active');

        this.active = (this.active + step + this.options.length) % this.options.length;

        var row = rows[this.active];
        if (row) {
            row.classList.add('is-active');
            row.scrollIntoView({ block: 'nearest' });
            this.input.setAttribute('aria-activedescendant', row.id);
        }
    };

    /* ---------------------------------------------------------------------
     * انتخاب و حذف
     * ------------------------------------------------------------------- */

    HodimaMultiSelect.prototype.selectItem = function (value, label) {
        if (!value || this.selected.has(String(value))) return;
        this.selected.set(String(value), label);
        this.input.value = '';
        this.renderTokens();
        this.syncSelect();
        this.search('');
        this.input.focus();
    };

    HodimaMultiSelect.prototype.deselect = function (value) {
        if (!this.selected.has(String(value))) return;
        this.selected.delete(String(value));
        this.renderTokens();
        this.syncSelect();

        // گزینه حذف‌شده باید دوباره قابل انتخاب شود، ولی فقط اگر کادر
        // از قبل باز است. نسخه قبلی کادر بسته را هم باز می‌کرد.
        if (!this.dropdown.hidden) {
            this.search(this.input.value);
        } else {
            this.loaded = false;
        }
    };

    HodimaMultiSelect.prototype.renderTokens = function () {

        this.input.placeholder = this.selected.size ? '' : 'جستجو و انتخاب...';

        var html = '';
        this.selected.forEach(function (label, value) {
            html += '<span class="hodima-ms__token" title="' + escapeHtml(label) + '">'
                  + '<span class="hodima-ms__token-text">' + escapeHtml(label) + '</span>'
                  + '<button type="button" class="hodima-ms__remove" data-value="' + escapeHtml(value)
                  + '" aria-label="حذف ' + escapeHtml(label) + '">&times;</button></span>';
        });
        this.tokens.innerHTML = html;
    };

    /** همگام‌سازی <select> اصلی تا فرم همان name قبلی را ارسال کند. */
    HodimaMultiSelect.prototype.syncSelect = function () {
        this.select.innerHTML = '';
        this.selected.forEach(function (label, value) {
            var opt = document.createElement('option');
            opt.value = value;
            opt.text = label;
            opt.selected = true;
            this.select.appendChild(opt);
        }, this);
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    HodimaMultiSelect.prototype.destroy = function () {
        document.removeEventListener('mousedown', this.onDocClick);
        if (this.wrap && this.wrap.parentNode) this.wrap.parentNode.removeChild(this.wrap);
        this.select.hidden = false;
        this.select.removeAttribute('aria-hidden');
        this.select.removeAttribute('tabindex');
        delete this.select.hodimaMultiSelect;
    };

    /* ---------------------------------------------------------------------
     * راه‌اندازی
     * ------------------------------------------------------------------- */

    function initAll() {
        document.querySelectorAll('.hodima-tc-ajax-select').forEach(function (el) {
            if (el.hodimaMultiSelect) el.hodimaMultiSelect.destroy();
            new HodimaMultiSelect(el);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {

        initAll();

        // فرم «افزودن دسته‌بندی» وردپرس بعد از ذخیره با AJAX پاک می‌شود
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ajaxComplete(function (event, xhr, settings) {
                if (settings && typeof settings.data === 'string' && settings.data.indexOf('action=add-tag') !== -1) {
                    setTimeout(function () {
                        /*
                         * tags.js وردپرس بعد از افزودن فقط input متنی و
                         * textarea را پاک می‌کند. بدون این، والدهای انتخاب‌شده
                         * و تیک «پیلار» دسته قبلی به دسته بعدی به ارث می‌رسید.
                         */
                        var form = document.getElementById('addtag');
                        if (form) {
                            form.querySelectorAll('.hodima-tc-ajax-select').forEach(function (sel) {
                                sel.innerHTML = '';
                            });
                            form.querySelectorAll('.hodima-pillar-toggle').forEach(function (box) {
                                box.checked = false;
                            });
                        }
                        initAll();
                    }, 300);
                }
            });
        }
    });
})();
