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
        const message = r.data?.message || r.message || 'انجام شد';
        const type = r.success ? 'success' : 'error';
        if (reload && r.success) reloadWithNotice(message, type);
        else showHodimaNotice(message, type);
    };

    const activeTab = localStorage.getItem('hodima_active_tab') || 'g';
    qa('.in-tab-btn').forEach(b => { if(b.dataset.tab === activeTab) b.classList.add('active'); else b.classList.remove('active'); });
    qa('.in-tab-content').forEach(c => { if(c.id === 'in-tab-' + activeTab) c.classList.add('active'); else c.classList.remove('active'); });

    qa('.in-tab-btn').forEach(b => b.addEventListener('click', () => {
        qa('.in-tab-btn').forEach(x => x.classList.remove('active')); qa('.in-tab-content').forEach(x => x.classList.remove('active'));
        b.classList.add('active'); q('#in-tab-'+b.dataset.tab).classList.add('active');
        localStorage.setItem('hodima_active_tab', b.dataset.tab);
    }));

    qa('.btn-export').forEach(b => b.addEventListener('click', async () => {
        window.location.href = hodimaObj.ajax_url + '?action=hodima_export_csv&nonce=' + hodimaObj.nonce + '&type=' + b.dataset.type;
    }));

    q('#g-up')?.addEventListener('change', (e) => {
        const f = e.target.files[0]; if(!f) return;
        const r = new FileReader(); r.onload = ev => q('#g-json').value = ev.target.result; r.readAsText(f);
    });

    q('#hodima-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const b = q('#save-all'); const orig = b.innerText; b.innerText = 'در حال ذخیره...';
        const d = { 
            action: 'hodima_save_all_settings', nonce: hodimaObj.nonce, 
            json_data: q('#g-json')?.value, enable_google: q('#g-en')?.checked?1:0, 
            debounce: q('#c-db')?.value, penalty: q('#c-pn')?.value, 
            cf_token: q('#cf-tok')?.value, cf_zone_id: q('#cf-zon')?.value, 
            stale_days: q('#c-stale')?.value, google_post_types: [...qa('.g-pt:checked')].map(x=>x.value) 
        };
        b.disabled = true;
        const r = await req(d);
        b.disabled = false;
        b.innerText = orig;
        report(r, true);
    });

    q('#g-key-remove')?.addEventListener('click', async () => {
        if (!confirm('کلید حساب سرویس حذف شود؟ تا تنظیم کلید جدید هیچ لینکی به گوگل ارسال نمی‌شود.')) return;
        const r = await req({ action: 'hodima_remove_key', nonce: hodimaObj.nonce });
        report(r, true);
    });

    q('#t-api')?.addEventListener('click', async () => {
        const r = await req({action:'hodima_test_google_api', nonce:hodimaObj.nonce});
        showHodimaNotice(r.data?.message || r.message, r.success ? 'success' : 'error');
    });

    q('#c-crawls')?.addEventListener('click', async () => { 
        if(confirm('آیا مطمئن هستید که می‌خواهید کل گزارش‌های خزش ربات را حذف کنید؟')) { 
            await req({action:'hodima_clear_crawls', nonce:hodimaObj.nonce}); 
            location.reload(); 
        } 
    });

    q('#q-sel-all')?.addEventListener('change', (e) => { qa('.q-chk').forEach(c => c.checked = e.target.checked); });
    
    q('#q-process-sel')?.addEventListener('click', async () => {
        const checked = [...qa('.q-chk:checked')].map(c => c.value);
        if(!checked.length) return alert('هیچ موردی انتخاب نشده است.');
        q('#q-process-sel').innerText = 'درحال پردازش...';
        const r = await req({action:'hodima_process_selected_queue', nonce:hodimaObj.nonce, ids: checked});
        report(r, true);
    });
    
    q('#q-delete-sel')?.addEventListener('click', async () => {
        const checked = [...qa('.q-chk:checked')].map(c => c.value);
        if(!checked.length) return alert('هیچ موردی انتخاب نشده است.');
        if(!confirm('حذف شوند؟')) return;
        await req({action:'hodima_delete_selected_queue', nonce:hodimaObj.nonce, ids: checked}); location.reload();
    });
    
    q('#s-bulk')?.addEventListener('click', async () => {
        const b = q('#s-bulk'); const orig = b.innerText; b.innerText = 'در حال اجرا...';
        const r = await req({action:'hodima_send_bulk_urls', nonce:hodimaObj.nonce, urls:q('#b-url').value, bulk_action_type:q('#b-act').value});
        showHodimaNotice(r.data?.message || 'انجام شد', r.success ? 'success' : 'error'); 
        b.innerText = orig; q('#b-url').value = '';
    });
    
    q('#m-prune')?.addEventListener('click', async () => {
        if(confirm('دیتابیس پاکسازی شود؟')) {
            const b = q('#m-prune'); const orig = b.innerText; b.innerText = 'در حال پاکسازی...';
            const r = await req({action:'hodima_manual_prune', nonce:hodimaObj.nonce});
            showHodimaNotice(r.data?.message || 'انجام شد', r.success ? 'success' : 'error'); 
            b.innerText = orig;
        }
    });

    q('#f-q')?.addEventListener('click', async () => { 
        const b = q('#f-q'); const orig = b.innerText; b.innerText = 'درحال اجرا...';
        const r = await req({action:'hodima_force_process_queue', nonce:hodimaObj.nonce}); 
        b.innerText = orig;
        report(r, true);
    });
    
    q('#c-l')?.addEventListener('click', async () => { if(confirm('پاکسازی تاریخچه؟')) { await req({action:'hodima_clear_log', nonce:hodimaObj.nonce}); location.reload(); } });
});
