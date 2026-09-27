/**
 * Hodima Stories — admin panel
 * Version: 3.0.0
 *
 *   - جابه‌جایی ردیف‌ها: کشیدن دستگیره (موس، لمس، قلم) و دکمه‌های بالا/پایین
 *     (برای صفحه‌کلید و جابه‌جایی دقیق). ترتیب فرم = ترتیب ذخیره = ترتیب سایت.
 *   - شماره ترتیب هر ردیف زنده به‌روز می‌شود.
 *   - حذف با تأیید (ذخیره، آمار استوری حذف‌شده را هم پاک می‌کند).
 *   - هشدار خروج با تغییرات ذخیره‌نشده.
 *   - انتخاب کاور فقط از میان تصاویر.
 */
(function () {
    'use strict';

    var cfg = window.HS_ADMIN;
    if (!cfg) return;

    var S = cfg.strings || {};

    function escAttr(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function init() {

        var list   = document.getElementById('hodima-stories-list');
        var addBtn = document.getElementById('hs-add-new');
        var form   = document.getElementById('hodima-stories-form');

        if (!list || !addBtn || !form) return;

        var dirty = false;
        var frame = null;
        var currentImg = null;
        var currentInput = null;

        function markDirty() { dirty = true; }

        window.addEventListener('beforeunload', function (e) {
            if (!dirty) return;
            e.preventDefault();
            e.returnValue = S.unsaved || '';
        });
        form.addEventListener('submit', function () { dirty = false; });
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        /* ── نام فیلدها و شماره ترتیب ──────────────────────────────── */

        var FIELDS = [
            ['.hs-uid-input', 'uid'],
            ['.hs-title-input', 'title'],
            ['.hs-link-input', 'link'],
            ['.hs-cover-id', 'cover_id'],
            ['.hs-cta-link-input', 'cta_link'],
            ['.hs-cta-text-input', 'cta_text']
        ];

        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('.hodima-story-row'));
        }

        function refresh() {
            var all = rows();
            all.forEach(function (row, i) {
                FIELDS.forEach(function (f) {
                    var el = row.querySelector(f[0]);
                    if (el) el.name = 'hodima_stories[' + i + '][' + f[1] + ']';
                });
                var num = row.querySelector('.hs-order-num');
                if (num) num.textContent = String(i + 1);
                var up = row.querySelector('.hs-move-up');
                var down = row.querySelector('.hs-move-down');
                if (up) up.disabled = (i === 0);
                if (down) down.disabled = (i === all.length - 1);
            });
        }

        /* ── ردیف جدید ───────────────────────────────────────────── */

        function createRow() {
            var row = document.createElement('div');
            row.className = 'hodima-story-row anim-slide_up';
            row.innerHTML =
                '<div class="hs-order-col">' +
                    '<span class="hs-drag-handle" title="' + escAttr(S.dragHint) + '" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>' +
                    '<span class="hs-order-num"></span>' +
                    '<button type="button" class="hs-move hs-move-up" aria-label="' + escAttr(S.moveUp) + '"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
                    '<button type="button" class="hs-move hs-move-down" aria-label="' + escAttr(S.moveDown) + '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
                '</div>' +
                '<input type="hidden" class="hs-uid-input" value="">' +
                '<div class="hdn-flex-col"><input type="text" class="hs-title-input hdn-input" placeholder="' + escAttr(S.title) + '"></div>' +
                '<div class="hdn-flex-col"><input type="url" class="hs-link-input hdn-input" dir="ltr" placeholder="' + escAttr(S.link) + ' (https://.../video.mp4)"></div>' +
                '<div class="hdn-flex-col hdn-cta-col">' +
                    '<input type="url" class="hs-cta-link-input hdn-input" dir="ltr" placeholder="' + escAttr(S.ctaLink) + '">' +
                    '<input type="text" class="hs-cta-text-input hdn-input" placeholder="' + escAttr(S.ctaText) + '">' +
                '</div>' +
                '<div class="hdn-flex-col"><div class="hdn-media-container">' +
                    '<div class="hdn-img-preview-box"><img src="' + escAttr(cfg.placeholderImg) + '" class="hs-cover-preview" alt=""></div>' +
                    '<div class="hdn-media-actions">' +
                        '<input type="hidden" class="hs-cover-id" value="">' +
                        '<button type="button" class="hdn-btn hdn-upload-btn hs-select-cover">' + escAttr(S.selectCover) + '</button>' +
                        '<button type="button" class="hdn-btn hdn-remove-btn hs-remove-row">' + escAttr(S.remove) + '</button>' +
                    '</div>' +
                '</div></div>';
            return row;
        }

        addBtn.addEventListener('click', function () {
            if (rows().length >= cfg.maxItems) {
                alert(S.limit);
                return;
            }
            var row = createRow();
            list.insertBefore(row, list.firstChild);
            refresh();
            markDirty();
            var title = row.querySelector('.hs-title-input');
            if (title) title.focus();
        });

        /* ── کلیک‌ها: حذف، کاور، بالا/پایین ────────────────────────── */

        list.addEventListener('click', function (e) {

            var removeBtn = e.target.closest('.hs-remove-row');
            if (removeBtn) {
                if (!confirm(S.confirmDel)) return;
                var row = removeBtn.closest('.hodima-story-row');
                row.classList.add('is-removing');
                setTimeout(function () { row.remove(); refresh(); markDirty(); }, 300);
                return;
            }

            var move = e.target.closest('.hs-move');
            if (move) {
                var r = move.closest('.hodima-story-row');
                if (move.classList.contains('hs-move-up') && r.previousElementSibling) {
                    list.insertBefore(r, r.previousElementSibling);
                } else if (move.classList.contains('hs-move-down') && r.nextElementSibling) {
                    list.insertBefore(r.nextElementSibling, r);
                }
                refresh();
                markDirty();
                flash(r);
                // فوکوس روی همان دکمه بماند تا بشود پشت سر هم جابه‌جا کرد
                var again = r.querySelector(move.classList.contains('hs-move-up') ? '.hs-move-up' : '.hs-move-down');
                if (again && !again.disabled) again.focus(); else move.blur();
                return;
            }

            var selectBtn = e.target.closest('.hs-select-cover');
            if (selectBtn) {
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) return;

                var wrap = selectBtn.closest('.hdn-media-container');
                currentImg = wrap.querySelector('.hs-cover-preview');
                currentInput = wrap.querySelector('.hs-cover-id');

                if (!frame) {
                    frame = wp.media({
                        title: cfg.mediaTitle,
                        button: { text: cfg.mediaBtn },
                        library: { type: 'image' },   // ویدیو به عنوان کاور رد می‌شد و کاور گم می‌شد
                        multiple: false
                    });
                    frame.on('select', function () {
                        var a = frame.state().get('selection').first().toJSON();
                        if (currentImg) currentImg.src = (a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) || a.url || currentImg.src;
                        if (currentInput) currentInput.value = a.id || '';
                        markDirty();
                    });
                }
                frame.open();
            }
        });

        function flash(row) {
            row.classList.remove('is-moved');
            void row.offsetWidth;
            row.classList.add('is-moved');
        }

        /* ── کشیدن و رها کردن (Pointer Events: موس، لمس، قلم) ─────── */

        var drag = null;

        list.addEventListener('pointerdown', function (e) {

            var handle = e.target.closest('.hs-drag-handle');
            if (!handle || e.button > 0) return;

            var row = handle.closest('.hodima-story-row');
            var rect = row.getBoundingClientRect();

            var placeholder = document.createElement('div');
            placeholder.className = 'hs-drop-placeholder';
            placeholder.style.height = rect.height + 'px';

            row.parentNode.insertBefore(placeholder, row.nextSibling);

            row.classList.add('is-dragging');
            row.style.width = rect.width + 'px';
            row.style.top = rect.top + 'px';
            row.style.left = rect.left + 'px';

            drag = { row: row, placeholder: placeholder, offsetY: e.clientY - rect.top, pointerId: e.pointerId };

            handle.setPointerCapture(e.pointerId);
            e.preventDefault();
        });

        list.addEventListener('pointermove', function (e) {

            if (!drag || e.pointerId !== drag.pointerId) return;

            drag.row.style.top = (e.clientY - drag.offsetY) + 'px';

            // نزدیک لبه‌های پنجره: اسکرول خودکار
            if (e.clientY < 60) window.scrollBy(0, -12);
            else if (e.clientY > window.innerHeight - 60) window.scrollBy(0, 12);

            var others = rows().filter(function (r) { return r !== drag.row; });
            var target = null;

            for (var i = 0; i < others.length; i++) {
                var b = others[i].getBoundingClientRect();
                if (e.clientY < b.top + b.height / 2) { target = others[i]; break; }
            }

            if (target) list.insertBefore(drag.placeholder, target);
            else list.appendChild(drag.placeholder);
        });

        function endDrag(e) {

            if (!drag || (e && e.pointerId !== drag.pointerId)) return;

            list.insertBefore(drag.row, drag.placeholder);
            drag.placeholder.remove();

            drag.row.classList.remove('is-dragging');
            drag.row.style.width = drag.row.style.top = drag.row.style.left = '';

            flash(drag.row);
            drag = null;

            refresh();
            markDirty();
        }

        list.addEventListener('pointerup', endDrag);
        list.addEventListener('pointercancel', endDrag);

        /* ── بستن پیام ذخیره ──────────────────────────────────────── */

        document.addEventListener('click', function (e) {
            if (!e.target.matches('.hdn-msg-close')) return;
            var msg = e.target.closest('.hdn-msg');
            if (!msg) return;
            msg.style.transition = 'opacity 0.2s ease';
            msg.style.opacity = '0';
            setTimeout(function () { msg.remove(); }, 200);
        });

        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
