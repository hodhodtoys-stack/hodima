document.addEventListener("DOMContentLoaded", () => {
    const q = (s) => document.querySelector(s), qa = (s) => document.querySelectorAll(s);

    // پاسخ غیر JSON (مثلا خطای کشنده PHP یا انقضای نشست) قبلا یک
    // استثنای مدیریت‌نشده می‌داد و دکمه در حالت «در حال ذخیره...» می‌ماند.
    const req = async (data) => {
        const fd = new URLSearchParams();
        for (let k in data) Array.isArray(data[k]) ? data[k].forEach(v => fd.append(k + '[]', v)) : fd.append(k, data[k] ?? '');
        try {
            const res = await fetch(hodimaObj.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' });
            const text = await res.text();
            try { return JSON.parse(text); }
            catch (e) { return { success: false, data: { message: 'پاسخ نامعتبر از سرور (کد ' + res.status + ').' } }; }
        } catch (e) {
            return { success: false, data: { message: 'ارتباط با سرور برقرار نشد.' } };
        }
    };

    /*
     * پیام هم‌شکل پیام‌های وردپرس (notice + دکمه X). قبلا کادر اختصاصی بالای
     * صفحه بود (دکمه ذخیره پایین صفحه است، پس دیده نمی‌شد)، بعد از ۵ ثانیه
     * خودش پاک می‌شد و در ذخیره موفق ۱.۵ ثانیه بعد صفحه از نو بارگذاری
     * می‌شد. حالا پیام موفق را سرور بعد از بارگذاری مجدد زیر هدر نشان می‌دهد
     * و پیام خطا تا بستن می‌ماند و به آن اسکرول می‌شود.
     */
    const showHodimaNotice = (message, type = 'success') => {
        const container = document.getElementById('hodima-notices');
        if(!container) return;
        const el = document.createElement('div');
        // inline: common.js وردپرس پیام را از زیر تب‌ها بالا نبرد
        el.className = `notice notice-${type === 'success' ? 'success' : 'error'} is-dismissible inline hd-notice`;
        el.setAttribute('role', type === 'success' ? 'status' : 'alert');
        // textContent به جای innerHTML: پیام ممکن است آدرس یا متن خطای
        // گوگل را در خود داشته باشد و نباید به عنوان HTML تفسیر شود.
        const p = document.createElement('p');
        p.textContent = String(message ?? '');
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'notice-dismiss';
        const label = document.createElement('span');
        label.className = 'screen-reader-text';
        label.textContent = 'بستن این اعلان';
        close.append(label);
        close.addEventListener('click', () => el.remove());
        el.append(p, close);
        container.replaceChildren(el);
        el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
    };

    // پیامی که باید بعد از بارگذاری مجدد صفحه دیده شود (نه اینکه با رفرش پاک شود)
    const FLASH_KEY = 'hodima_gi_flash';
    const reloadWithNotice = (message, type) => {
        try { sessionStorage.setItem(FLASH_KEY, JSON.stringify({ message, type })); } catch (e) {}
        location.reload();
    };
    try {
        const flash = JSON.parse(sessionStorage.getItem(FLASH_KEY) || 'null');
        sessionStorage.removeItem(FLASH_KEY);
        if (flash) showHodimaNotice(flash.message, flash.type);
    } catch (e) {}

    /** نتیجه یک عملیات: موفق و نیازمند رفرش → پیام بعد از رفرش؛ وگرنه همین حالا. */
    const report = (r, reload = false) => {
        const message = r.data?.message || r.message || (r.success ? 'انجام شد' : 'خطا در انجام عملیات');
        const type = r.success ? 'success' : 'error';
        if (reload && r.success) reloadWithNotice(message, type);
        else showHodimaNotice(message, type);
    };

    /*
     * اجرای یک درخواست با دکمه قفل‌شده.
     * قبلا دکمه‌ها هنگام درخواست فعال می‌ماندند (دو کلیک = دو درخواست) و
     * در خطا متن «در حال...» روی دکمه می‌ماند.
     */
    const run = async (btn, busyText, data) => {
        const orig = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); if (busyText) btn.textContent = busyText; }
        const r = await req(data);
        if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = orig; }
        return r;
    };

    const checkedValues = (sel) => [...qa(sel + ':checked')].map(c => c.value);

    // تب فعال (همان منطق اسکریپت درون‌خطی قالب؛ تب ناموجود → تب اول)
    let activeTab = 'g';
    try { activeTab = localStorage.getItem('hodima_active_tab') || 'g'; } catch (e) {}
    if (!q('#in-tab-' + activeTab)) activeTab = 'g';
    qa('.in-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === activeTab));
    qa('.in-tab-content').forEach(c => c.classList.toggle('active', c.id === 'in-tab-' + activeTab));

    qa('.in-tab-btn').forEach(b => b.addEventListener('click', () => {
        qa('.in-tab-btn').forEach(x => x.classList.remove('active')); qa('.in-tab-content').forEach(x => x.classList.remove('active'));
        b.classList.add('active'); q('#in-tab-'+b.dataset.tab)?.classList.add('active');
        try { localStorage.setItem('hodima_active_tab', b.dataset.tab); } catch (e) {}
    }));

    qa('.btn-export').forEach(b => b.addEventListener('click', () => {
        window.location.href = hodimaObj.ajax_url + '?action=hodima_export_csv&nonce=' + encodeURIComponent(hodimaObj.nonce) + '&type=' + encodeURIComponent(b.dataset.type);
    }));

    /* =================================================================
     * کلید Service Account — پنجره افزودن/جایگزینی
     * ================================================================= */
    const keyDialog = q('#g-key-dialog'), keyText = q('#g-json'), keyFile = q('#g-up');
    const keySave = q('#g-key-save'), keyPreview = q('#g-key-preview'), keyFileName = q('#g-key-file');

    /** بررسی سریع در مرورگر تا کاربر قبل از ذخیره بداند فایل درست است. */
    const previewKey = () => {
        const raw = (keyText?.value || '').trim();
        keyPreview.className = 'in-key-preview';
        if (!raw) { keyPreview.textContent = ''; keySave.disabled = true; return; }
        let data = null;
        try { data = JSON.parse(raw); } catch (e) {}
        if (!data || typeof data !== 'object') {
            keyPreview.textContent = 'این متن JSON معتبر نیست.';
            keyPreview.classList.add('is-error'); keySave.disabled = true; return;
        }
        if (data.type !== 'service_account' || !data.client_email || !data.private_key) {
            keyPreview.textContent = 'این فایل کلید Service Account نیست (باید type برابر service_account و client_email و private_key داشته باشد).';
            keyPreview.classList.add('is-error'); keySave.disabled = true; return;
        }
        keyPreview.textContent = 'حساب: ' + data.client_email;
        keyPreview.classList.add('is-ok'); keySave.disabled = false;
    };

    const resetKeyDialog = () => {
        if (keyText) keyText.value = '';
        if (keyFile) keyFile.value = '';
        if (keyFileName) keyFileName.textContent = '';
        previewKey();
    };

    q('#g-key-open')?.addEventListener('click', () => { resetKeyDialog(); keyDialog?.showModal(); });
    q('#g-key-cancel')?.addEventListener('click', () => keyDialog?.close());
    q('#g-key-pick')?.addEventListener('click', () => keyFile?.click());
    keyText?.addEventListener('input', previewKey);

    keyFile?.addEventListener('change', () => {
        const f = keyFile.files[0]; if (!f) return;
        const r = new FileReader();
        r.onload = ev => {
            keyText.value = String(ev.target.result || '');
            keyFileName.textContent = f.name;
            previewKey();
        };
        r.readAsText(f);
    });

    keySave?.addEventListener('click', async () => {
        const r = await run(keySave, 'در حال ذخیره...', { action: 'hodima_save_key', nonce: hodimaObj.nonce, json_data: keyText.value });
        if (r.success) { keyDialog.close(); report(r, true); return; }
        keyPreview.className = 'in-key-preview is-error';
        keyPreview.textContent = r.data?.message || 'ذخیره کلید ناموفق بود.';
    });

    q('#g-key-remove')?.addEventListener('click', async (e) => {
        if (!confirm('کلید حساب سرویس حذف شود؟ تا تنظیم کلید جدید هیچ لینکی به گوگل ارسال نمی‌شود.')) return;
        report(await run(e.currentTarget, 'در حال حذف...', { action: 'hodima_remove_key', nonce: hodimaObj.nonce }), true);
    });

    /* =================================================================
     * ذخیره پیکربندی (کلید دیگر از اینجا ارسال نمی‌شود)
     * ================================================================= */
    q('#hodima-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await run(q('#save-all'), 'در حال ذخیره...', {
            action: 'hodima_save_all_settings', nonce: hodimaObj.nonce,
            enable_google: q('#g-en')?.checked ? 1 : 0,
            debounce: q('#c-db')?.value, penalty: q('#c-pn')?.value,
            cf_token: q('#cf-tok')?.value, cf_zone_id: q('#cf-zon')?.value,
            stale_days: q('#c-stale')?.value, google_post_types: checkedValues('.g-pt')
        });
        report(r, true);
    });

    q('#t-api')?.addEventListener('click', async (e) => {
        report(await run(e.currentTarget, 'در حال تست...', { action: 'hodima_test_google_api', nonce: hodimaObj.nonce }));
    });

    // قبلا نتیجه این دکمه‌ها نادیده گرفته می‌شد و در خطا هم صفحه بی‌پیام رفرش می‌شد
    q('#c-crawls')?.addEventListener('click', async (e) => {
        if (!confirm('آیا مطمئن هستید که می‌خواهید کل گزارش‌های خزش ربات را حذف کنید؟')) return;
        report(await run(e.currentTarget, 'در حال پاکسازی...', { action: 'hodima_clear_crawls', nonce: hodimaObj.nonce }), true);
    });

    q('#c-l')?.addEventListener('click', async (e) => {
        if (!confirm('پاکسازی تاریخچه؟')) return;
        report(await run(e.currentTarget, 'در حال پاکسازی...', { action: 'hodima_clear_log', nonce: hodimaObj.nonce }), true);
    });

    /* =================================================================
     * صف
     * ================================================================= */
    q('#q-sel-all')?.addEventListener('change', (e) => { qa('.q-chk').forEach(c => c.checked = e.target.checked); });

    q('#q-process-sel')?.addEventListener('click', async (e) => {
        const ids = checkedValues('.q-chk');
        if (!ids.length) return showHodimaNotice('هیچ موردی انتخاب نشده است.', 'error');
        report(await run(e.currentTarget, 'در حال پردازش...', { action: 'hodima_process_selected_queue', nonce: hodimaObj.nonce, ids }), true);
    });

    q('#q-delete-sel')?.addEventListener('click', async (e) => {
        const ids = checkedValues('.q-chk');
        if (!ids.length) return showHodimaNotice('هیچ موردی انتخاب نشده است.', 'error');
        if (!confirm('حذف شوند؟')) return;
        report(await run(e.currentTarget, 'در حال حذف...', { action: 'hodima_delete_selected_queue', nonce: hodimaObj.nonce, ids }), true);
    });

    q('#f-q')?.addEventListener('click', async (e) => {
        report(await run(e.currentTarget, 'در حال اجرا...', { action: 'hodima_force_process_queue', nonce: hodimaObj.nonce }), true);
    });

    /* =================================================================
     * محتوای راکد
     * ================================================================= */
    q('#st-sel-all')?.addEventListener('change', (e) => { qa('.st-chk').forEach(c => c.checked = e.target.checked); });

    const queuePosts = async (btn, ids) => {
        if (!ids.length) return showHodimaNotice('هیچ موردی انتخاب نشده است.', 'error');
        const r = await run(btn, 'در حال افزودن...', { action: 'hodima_queue_posts', nonce: hodimaObj.nonce, ids });
        report(r);
        if (r.success) {
            ids.forEach(id => {
                const one = q('.st-queue-one[data-id="' + CSS.escape(String(id)) + '"]');
                if (one) { one.disabled = true; one.textContent = 'در صف گوگل'; }
            });
        }
    };

    q('#st-queue-sel')?.addEventListener('click', (e) => queuePosts(e.currentTarget, checkedValues('.st-chk')));
    qa('.st-queue-one').forEach(b => b.addEventListener('click', () => queuePosts(b, [b.dataset.id])));

    /* =================================================================
     * عملیات
     * ================================================================= */
    q('#s-bulk')?.addEventListener('click', async (e) => {
        const r = await run(e.currentTarget, 'در حال اجرا...', { action: 'hodima_send_bulk_urls', nonce: hodimaObj.nonce, urls: q('#b-url').value, bulk_action_type: q('#b-act').value });
        report(r);
        if (r.success) q('#b-url').value = ''; // در خطا آدرس‌های واردشده پاک نشوند
    });

    q('#m-prune')?.addEventListener('click', async (e) => {
        if (!confirm('دیتابیس پاکسازی شود؟')) return;
        report(await run(e.currentTarget, 'در حال پاکسازی...', { action: 'hodima_manual_prune', nonce: hodimaObj.nonce }));
    });
});
