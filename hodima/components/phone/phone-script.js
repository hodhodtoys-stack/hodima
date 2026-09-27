/**
 * Hodima Phone Form — v4.0.0 (Modern Vanilla JS)
 */
(() => {
    'use strict';

    if (typeof hodimaAjax === 'undefined') return;

    const FA_DIGITS = { '۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9',
                        '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9' };

    const normalize = (raw = '') => {
        let d = String(raw).replace(/[۰-۹٠-٩]/g, c => FA_DIGITS[c]).replace(/\D+/g, '');
        if (d.startsWith('0098')) d = `0${d.slice(4)}`;
        else if (d.startsWith('98') && d.length >= 10) d = `0${d.slice(2)}`;
        else if (d.length === 10 && !d.startsWith('0')) d = `0${d}`;
        
        return /^0\d{9,10}$/.test(d) ? d : '';
    };

    let secureToken = '';
    let isFetchingToken = false;

    const fetchToken = async () => {
        if (secureToken || isFetchingToken) return;
        isFetchingToken = true;
        try {
            const response = await fetch(`${hodimaAjax.ajaxurl}?action=hodima_phone_nonce`);
            const res = await response.json();
            if (res.success) secureToken = res.data;
        } catch (error) {
            console.error('Failed to fetch security token.');
        } finally {
            isFetchingToken = false;
        }
    };

    const init = (wrapper) => {
        const form   = wrapper.querySelector('.hodima-phone-form');
        const input  = wrapper.querySelector('.hodima-phone-input');
        const button = wrapper.querySelector('.hodima-phone-submit');
        const msg    = wrapper.querySelector('.hodima-phone-msg');

        if (!form || !input || !button || form.dataset.ready) return;
        form.dataset.ready = '1';

        wrapper.addEventListener('mouseenter', fetchToken, { once: true });
        input.addEventListener('focus', fetchToken, { once: true });
        input.addEventListener('touchstart', fetchToken, { once: true });

        const buttonText = button.textContent;
        let hideTimer = null;

        const show = (text, type) => {
            if (!msg) return;
            clearTimeout(hideTimer);
            msg.textContent = text;
            msg.className = `hodima-phone-msg hodima-msg-${type}`;
            msg.hidden = false;
            if (type === 'success') {
                hideTimer = setTimeout(() => { msg.hidden = true; }, 8000);
            }
        };

        const busy = (state) => {
            button.disabled = state;
            button.setAttribute('aria-busy', state ? 'true' : 'false');
            button.textContent = state ? 'در حال ثبت…' : buttonText;
        };

        input.addEventListener('input', () => input.removeAttribute('aria-invalid'));

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const phone = normalize(input.value);

            if (!phone) {
                input.setAttribute('aria-invalid', 'true');
                show(input.value.trim() ? 'شماره وارد شده نامعتبر است.' : 'لطفا شماره تماس را وارد کنید.', 'error');
                input.focus();
                return;
            }

            busy(true);

            const data = new FormData();
            data.append('action', 'submit_hodima_phone');
            data.append('phone', phone);
            data.append('website', form.querySelector('input[name="website"]')?.value || '');
            data.append('page_url', window.location.href);
            data.append('page_title', document.title);
            data.append('js_time', Date.now().toString());
            if (secureToken) data.append('security_token', secureToken);

            try {
                const response = await fetch(hodimaAjax.ajaxurl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin'
                });
                
                const json = await response.json();
                
                if (json?.success) {
                    show(json.data?.message || 'شماره شما ثبت شد.', 'success');
                    input.value = '';
                    input.blur();
                } else {
                    show(json?.data?.message || 'خطا در ثبت درخواست. لطفا دوباره تلاش کنید.', 'error');
                    input.focus();
                }
            } catch (error) {
                show('ارتباط برقرار نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.', 'error');
            } finally {
                busy(false);
            }
        });
    };

    const boot = () => document.querySelectorAll('.hodima-phone-wrapper').forEach(init);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();