/**
 * Hodima Slider — admin
 *   - افزودن / حذف اسلاید، انتخاب تصویر (فقط تصویر)
 *   - ترتیب: کشیدن دستگیره (موس و لمس) و دکمه‌های بالا/پایین، با شماره ردیف.
 *     ترتیب ذخیره = ترتیب ردیف‌ها در صفحه (PHP آرایه را به ترتیب ارسال
 *     فیلدها می‌سازد)، پس جابه‌جایی بدون تغییر دیگری ذخیره می‌شود.
 */
(function () {
    'use strict';

    function init() {
        const cfg = window.HodimaSliderAdmin || {};
        const maxItems = cfg.maxItems || 12;
        const i18n = cfg.i18n || {};

        const lists = () => document.querySelectorAll('[id^="h-list-"]');

        function renumber(list) {
            const rows = list.querySelectorAll(':scope > .h-grid');
            rows.forEach((row, i) => {
                const num = row.querySelector('.h-num');
                if (num) num.textContent = String(i + 1);
                const up = row.querySelector('.h-move-up');
                const down = row.querySelector('.h-move-down');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === rows.length - 1;
            });
        }
        const renumberAll = () => lists().forEach(renumber);

        function flash(row) {
            row.classList.remove('is-moved');
            void row.offsetWidth;
            row.classList.add('is-moved');
        }

        document.querySelectorAll('[data-h-add]').forEach(btn => {
            btn.addEventListener('click', () => {
                const list = document.getElementById(btn.dataset.list);
                const template = document.getElementById(btn.dataset.template);
                if (!list || !template) return;

                if (list.querySelectorAll(':scope > .h-grid').length >= maxItems) {
                    return alert(i18n.maxReached || 'حداکثر تعداد مجاز رعایت شده است.');
                }

                const uniqueIndex = Date.now();
                const clone = template.content.cloneNode(true);
                clone.querySelectorAll('[name*="__I__"]').forEach(el => {
                    el.name = el.name.replace('__I__', uniqueIndex);
                });
                list.appendChild(clone);
                renumber(list);
            });
        });

        document.addEventListener('click', (e) => {
            const row = e.target.closest('.h-grid');
            if (!row) return;
            const list = row.parentElement;

            if (e.target.classList.contains('h-btn-danger')) {
                if (confirm(i18n.confirmDelete || 'حذف شود؟')) { row.remove(); renumber(list); }
                return;
            }

            const move = e.target.closest('.h-move');
            if (move) {
                if (move.classList.contains('h-move-up') && row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                } else if (move.classList.contains('h-move-down') && row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                }
                renumber(list);
                flash(row);
                return;
            }

            if (e.target.classList.contains('h-preview')) {
                const wrapper = e.target.closest('.h-img-box') || row;
                // فقط تصویر: انتخاب ویدیو بی‌صدا در ذخیره رد و اسلاید حذف می‌شد
                const frame = wp.media({ title: i18n.mediaTitle || 'انتخاب تصویر', library: { type: 'image' }, multiple: false });
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    wrapper.querySelector('.h-preview').src = attachment.sizes?.thumbnail?.url || attachment.url;
                    wrapper.querySelector('.h-img-id').value = attachment.id;
                });
                frame.open();
            }
        });

        /* ── کشیدن و رها کردن (Pointer Events: موس، لمس، قلم) ── */
        let drag = null;

        document.addEventListener('pointerdown', (e) => {
            const handle = e.target.closest('.h-drag');
            if (!handle || e.button > 0) return;

            const row = handle.closest('.h-grid');
            const rect = row.getBoundingClientRect();
            const ph = document.createElement('div');
            ph.className = 'h-drop-placeholder';
            ph.style.height = rect.height + 'px';
            row.parentElement.insertBefore(ph, row.nextSibling);

            row.classList.add('is-dragging');
            row.style.width = rect.width + 'px';
            row.style.top = rect.top + 'px';
            row.style.left = rect.left + 'px';

            drag = { row, ph, list: row.parentElement, offsetY: e.clientY - rect.top, id: e.pointerId };
            handle.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        document.addEventListener('pointermove', (e) => {
            if (!drag || e.pointerId !== drag.id) return;
            drag.row.style.top = (e.clientY - drag.offsetY) + 'px';

            if (e.clientY < 60) window.scrollBy(0, -12);
            else if (e.clientY > window.innerHeight - 60) window.scrollBy(0, 12);

            const others = [...drag.list.querySelectorAll(':scope > .h-grid')].filter(r => r !== drag.row);
            const target = others.find(r => {
                const b = r.getBoundingClientRect();
                return e.clientY < b.top + b.height / 2;
            });
            if (target) drag.list.insertBefore(drag.ph, target);
            else drag.list.appendChild(drag.ph);
        });

        const endDrag = (e) => {
            if (!drag || (e && e.pointerId !== drag.id)) return;
            drag.list.insertBefore(drag.row, drag.ph);
            drag.ph.remove();
            drag.row.classList.remove('is-dragging');
            drag.row.style.width = drag.row.style.top = drag.row.style.left = '';
            flash(drag.row);
            renumber(drag.list);
            drag = null;
        };
        document.addEventListener('pointerup', endDrag);
        document.addEventListener('pointercancel', endDrag);

        renumberAll();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
