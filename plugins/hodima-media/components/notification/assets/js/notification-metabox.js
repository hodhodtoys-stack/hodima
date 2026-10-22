document.addEventListener("DOMContentLoaded", () => {

    const $ = (id) => document.getElementById(id);
    const getVal = (id, def = '') => ($(id) ? $(id).value : def);
    const getChecked = (id) => ($(id) ? $(id).checked : false);

    const activeCheckbox = $('hd_notif_is_active');
    const activeLabel = $('hd_status_label');
    const activeText = $('hd_status_text');

    const syncActiveState = () => {
        if (!activeCheckbox || !activeLabel || !activeText) return;
        if (activeCheckbox.checked) {
            activeLabel.classList.remove('inactive');
            activeText.textContent = 'نوتیفیکیشن فعال است و در سایت نمایش داده می‌شود';
        } else {
            activeLabel.classList.add('inactive');
            activeText.textContent = 'نوتیفیکیشن غیرفعال است';
        }
    };

    if (activeCheckbox) {
        activeCheckbox.addEventListener('change', syncActiveState);
        syncActiveState();
    }

    document.querySelectorAll('.hdn-upload-btn').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) { alert('لودر مدیا در دسترس نیست.'); return; }

            const targetInputSelector = this.getAttribute('data-target');
            const previewImgSelector = this.getAttribute('data-preview');
            const removeBtnSelector = this.getAttribute('data-remove');

            const mediaUploader = wp.media({ title: 'انتخاب تصویر', button: { text: 'انتخاب' }, library: { type: 'image' }, multiple: false });

            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const targetInput = document.querySelector(targetInputSelector);
                if (targetInput) targetInput.value = attachment.url;
                const previewImg = document.querySelector(previewImgSelector);
                if (previewImg) {
                    previewImg.src = attachment.url;
                    if (previewImg.parentElement) previewImg.parentElement.classList.remove('empty');
                }
                const removeBtn = document.querySelector(removeBtnSelector);
                if (removeBtn) removeBtn.style.display = 'inline-block';
                // عکس PNG معمولا پس‌زمینه ندارد: حالت شفاف (بدون کادر سفید) خودکار روشن شود
                const transparent = $('hd_notif_bg_transparent');
                if (transparent && attachment.subtype === 'png') transparent.checked = true;
            });
            mediaUploader.open();
        });
    });

    document.querySelectorAll('.hdn-remove-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const uploadBtn = this.previousElementSibling;
            if (uploadBtn) {
                const targetInput = document.querySelector(uploadBtn.getAttribute('data-target'));
                if (targetInput) targetInput.value = '';
                const previewImg = document.querySelector(uploadBtn.getAttribute('data-preview'));
                if (previewImg) {
                    previewImg.removeAttribute('src');
                    if (previewImg.parentElement) previewImg.parentElement.classList.add('empty');
                }
            }
            this.style.display = 'none';
        });
    });

    const displayTypeSelect = $('hd_notif_target_type');
    const targetPageWrapper = $('hdn_target_page_wrapper');
    const targetPageInput = $('hd_notif_target_page');

    function toggleTargetPageField() {
        if (!displayTypeSelect || !targetPageWrapper || !targetPageInput) return;
        const type = displayTypeSelect.value;
        if (type === 'all') {
            targetPageWrapper.classList.add('disabled');
            targetPageInput.disabled = true;
        } else {
            targetPageWrapper.classList.remove('disabled');
            targetPageInput.disabled = false;
        }
    }
    if (displayTypeSelect) {
        displayTypeSelect.addEventListener('change', toggleTargetPageField);
        toggleTargetPageField();
    }

    const previewBtn = $('hdn_btn_generate_preview');
    const previewCanvas = $('hdn_preview_canvas');

    if (previewBtn && previewCanvas) {
        previewBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const position = getVal('hd_notif_position', 'center');
            const width = parseInt(getVal('hd_notif_width', '400'), 10) || 400;
            const height = parseInt(getVal('hd_notif_height', '0'), 10) || 0;

            const bgImage = getVal('hd_notif_bg_image', '');
            const mainLink = getVal('hd_notif_main_link', '#');
            
            const isTransparent = getChecked('hd_notif_bg_transparent');
            const bgColor = isTransparent ? 'transparent' : getVal('hd_notif_bg_color', '#ffffff');
            
            const hasCloseBtn = getChecked('hd_notif_close_btn');
            const closeColor = getVal('hd_notif_close_color', '#ff0000');

            let posStyles = '';
            switch (position) {
                case 'center': posStyles = 'top:50%;left:50%;transform:translate(-50%,-50%);'; break;
                case 'bottom_right': posStyles = 'bottom:20px;right:20px;'; break;
                case 'bottom_left': posStyles = 'bottom:20px;left:20px;'; break;
            }

            let style = `
                position:absolute;
                ${posStyles}
                width:${width}px;
                max-width:95%;
                background:${bgColor};
                padding:${isTransparent ? 0 : 15}px;
                border-radius:${isTransparent ? 0 : 12}px;
                z-index:9999;
                box-sizing: border-box;
            `;

            if (isTransparent) {
                style += 'box-shadow: none; border: none;';
            } else {
                style += 'box-shadow: 0 10px 30px rgba(0,0,0,0.15);';
            }

            if (height > 0) {
                style += `height:${height}px;`;
            }

            // استفاده از تگ button و خنثی کردن تمام هاله‌های آبی و outline در مرورگر 
            // حالت شفاف (PNG): دکمه بستن با دایره سفید، مثل سایت
            const closeBase = isTransparent
                ? 'top:4px; right:4px; width:36px; height:36px; display:grid; place-items:center; border-radius:50%; background:rgba(255,255,255,0.92); box-shadow:0 2px 8px rgba(0,0,0,0.25); font-size:26px; text-shadow:none;'
                : 'top:35px; right:35px; font-size:32px; background:transparent; box-shadow:none; text-shadow: 0 1px 4px rgba(0,0,0,0.4);';
            const closeHtml = hasCloseBtn 
                ? `<button type="button" style="position:absolute; ${closeBase} cursor:pointer; font-weight:bold; opacity:0.9; color:${closeColor}; z-index:10; border:none; outline:none; padding:0;" onclick="return false;">&times;</button>` 
                : '';

            const imageHtml = bgImage 
                ? `<img src="${bgImage}" style="width: 100%; height: ${height > 0 ? '100%' : 'auto'}; display: block; ${isTransparent ? 'object-fit: contain; filter: drop-shadow(0 8px 18px rgba(0,0,0,0.25));' : 'object-fit: cover; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'}">`
                : `<div style="width:100%; height:150px; background:#e5e7eb; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#9ca3af;">تصویر انتخاب نشده است</div>`;

            const linkHtml = mainLink 
                ? `<a href="${mainLink}" onclick="return false;" style="position: absolute; inset: 0; z-index: 5; display: block; cursor: pointer;"></a>` 
                : '';

            const html = `
                <div style="${style}">
                    ${closeHtml}
                    <div style="position: relative; display: block; width: 100%; height: 100%; border-radius: 8px;">
                        ${imageHtml}
                        ${linkHtml}
                    </div>
                </div>
            `;
            
            previewCanvas.classList.add('active');
            previewCanvas.innerHTML = html;
            previewCanvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
});