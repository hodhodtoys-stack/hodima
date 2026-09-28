/* =========================================================
 * نمودار روند ربات‌های هوش مصنوعی — SVG وانیلا
 * ---------------------------------------------------------
 * جایگزین Chart.js از jsDelivr. دلایل حذف وابستگی:
 *   ۱. قانون پروژه: بدون کتابخانه ثالث.
 *   ۲. jsDelivr برای کاربران ایران پایدار نیست؛ با لود نشدن آن،
 *      تابع Chart تعریف نمی‌شد و پنل بدون هیچ خطایی نمودار خالی
 *      نشان می‌داد.
 *   ۳. حذف حدود ۲۰۰ کیلوبایت جاوااسکریپت از پنل مدیریت.
 * ========================================================= */
document.addEventListener('DOMContentLoaded', function () {

    var host = document.getElementById('hodimaAiChart');
    if (!host) return;

    var labels = [];
    var values = [];
    try { labels = JSON.parse(host.getAttribute('data-labels') || '[]'); } catch (e) {}
    try { values = JSON.parse(host.getAttribute('data-values') || '[]'); } catch (e) {}

    if (!Array.isArray(values) || values.length === 0) {
        host.innerHTML = '<p style="color:#5d6785;font-size:13px;margin:0;">داده‌ای برای نمایش وجود ندارد.</p>';
        return;
    }

    var NS      = 'http://www.w3.org/2000/svg';
    var W       = 900;
    var H       = 260;
    var padL    = 46;
    var padR    = 16;
    var padT    = 16;
    var padB    = 34;
    var plotW   = W - padL - padR;
    var plotH   = H - padT - padB;

    var max = Math.max.apply(null, values.map(Number));
    if (!isFinite(max) || max <= 0) max = 1;
    // سقف را به عدد رند بالاتر ببر تا خطوط راهنما خوانا باشند
    var step = Math.pow(10, Math.floor(Math.log10(max)));
    max = Math.ceil(max / step) * step;

    function x(i) {
        return values.length === 1
            ? padL + plotW / 2
            : padL + (i * plotW) / (values.length - 1);
    }
    function y(v) {
        return padT + plotH - (Number(v) / max) * plotH;
    }

    function el(name, attrs, text) {
        var node = document.createElementNS(NS, name);
        for (var k in attrs) { node.setAttribute(k, attrs[k]); }
        if (text !== undefined) node.textContent = text;
        return node;
    }

    var svg = el('svg', {
        viewBox: '0 0 ' + W + ' ' + H,
        width: '100%',
        height: 'auto',
        role: 'img',
        'aria-label': 'روند بازدید ربات‌های هوش مصنوعی در ۷ روز گذشته',
        style: 'display:block;overflow:visible;font-family:inherit;'
    });

    // گرادیان پرکننده زیر خط
    var defs = el('defs');
    var grad = el('linearGradient', { id: 'hodimaAiChartFill', x1: '0', y1: '0', x2: '0', y2: '1' });
    grad.appendChild(el('stop', { offset: '0%',   'stop-color': '#25316a', 'stop-opacity': '0.28' }));
    grad.appendChild(el('stop', { offset: '100%', 'stop-color': '#25316a', 'stop-opacity': '0'    }));
    defs.appendChild(grad);
    svg.appendChild(defs);

    // خطوط راهنمای افقی و برچسب محور عمودی
    for (var g = 0; g <= 4; g++) {
        var gv = (max / 4) * g;
        var gy = y(gv);
        svg.appendChild(el('line', {
            x1: padL, y1: gy, x2: W - padR, y2: gy,
            stroke: '#e2e4f0', 'stroke-width': '1'
        }));
        svg.appendChild(el('text', {
            x: padL - 10, y: gy + 4, 'text-anchor': 'end',
            'font-size': '12', fill: '#5d6785'
        }, String(Math.round(gv))));
    }

    // مسیر ناحیه و خط
    var linePoints = values.map(function (v, i) { return x(i) + ',' + y(v); });
    var areaPath = 'M' + x(0) + ',' + y(values[0]) +
                   values.map(function (v, i) { return 'L' + x(i) + ',' + y(v); }).join('') +
                   'L' + x(values.length - 1) + ',' + (padT + plotH) +
                   'L' + x(0) + ',' + (padT + plotH) + 'Z';

    svg.appendChild(el('path', { d: areaPath, fill: 'url(#hodimaAiChartFill)' }));
    svg.appendChild(el('polyline', {
        points: linePoints.join(' '),
        fill: 'none', stroke: '#25316a', 'stroke-width': '2.5',
        'stroke-linejoin': 'round', 'stroke-linecap': 'round'
    }));

    // نقاط داده + عنوان توضیحی هنگام هاور
    values.forEach(function (v, i) {
        var dot = el('circle', {
            cx: x(i), cy: y(v), r: '4',
            fill: '#ffffff', stroke: '#25316a', 'stroke-width': '2.5'
        });
        dot.appendChild(el('title', {}, (labels[i] || '') + ': ' + v));
        svg.appendChild(dot);

        if (labels[i] !== undefined) {
            svg.appendChild(el('text', {
                x: x(i), y: H - 12, 'text-anchor': 'middle',
                'font-size': '12', fill: '#5d6785'
            }, String(labels[i])));
        }
    });

    host.innerHTML = '';
    host.appendChild(svg);
});

// =========================================================
// توابع مربوط به ماژول استخراج‌گر هوشمند AEO
// =========================================================

window.hodimaToggleAeoInputs = function() {
    var mode = document.getElementById('hodima-aeo-filter-mode');
    var limitInput = document.getElementById('hodima-aeo-limit-count');
    var dateInputs = document.getElementById('hodima-aeo-date-inputs');
    
    if (!mode || !limitInput || !dateInputs) return;

    limitInput.style.display = (mode.value === 'limit') ? 'block' : 'none';
    dateInputs.style.display = (mode.value === 'date_range') ? 'flex' : 'none';
};

window.hodimaFetchAeoLinks = function() {
    var mode = document.getElementById('hodima-aeo-filter-mode').value;
    var limitCount = document.getElementById('hodima-aeo-limit-count').value;
    var dateFrom = document.getElementById('hodima-aeo-date-from').value;
    var dateTo = document.getElementById('hodima-aeo-date-to').value;
    
    var textarea = document.getElementById('hodima-aeo-export-textarea');
    var loading = document.getElementById('hodima-aeo-loading');
    var countMsg = document.getElementById('hodima-aeo-count-msg');
    
    if (mode === 'date_range' && !dateFrom) {
        alert('لطفاً حداقل تاریخ شروع (از) را انتخاب کنید.');
        return;
    }

    textarea.value = '';
    countMsg.innerText = '';
    loading.style.display = 'block';

    // دریافت Nonce امنیتی وردپرس از صفحه
    var nonceElement = document.getElementById('hodima_aeo_nonce');
    var securityNonce = nonceElement ? nonceElement.value : '';

    jQuery.ajax({
        url: ajaxurl, // متغیر گلوبال وردپرس برای AJAX
        type: 'POST',
        data: {
            action: 'hodima_get_aeo_links',
            filter_mode: mode,
            limit_count: limitCount,
            date_from: dateFrom,
            date_to: dateTo,
            lang_fa: document.getElementById('hodima-aeo-lang-fa').checked ? 1 : 0,
            lang_en: document.getElementById('hodima-aeo-lang-en').checked ? 1 : 0,
            type_md: document.getElementById('hodima-aeo-type-md').checked ? 1 : 0,
            type_llms: document.getElementById('hodima-aeo-type-llms').checked ? 1 : 0,
            type_feed: document.getElementById('hodima-aeo-type-feed').checked ? 1 : 0,
            nonce: securityNonce
        },
        success: function(response) {
            loading.style.display = 'none';
            if(response.success) {
                if(response.data.links.length > 0) {
                    textarea.value = response.data.links.join('\n');
                    countMsg.innerText = 'تعداد لینک یافت شده: ' + response.data.links.length;
                } else {
                    textarea.value = 'برای این فیلتر، هیچ موردی یافت نشد.';
                }
            } else {
                textarea.value = 'خطا در دریافت لینک‌ها: ' + (response.data || 'خطای ناشناخته');
            }
        },
        error: function(xhr, status, error) {
            loading.style.display = 'none';
            var errorDetails = '\n\nکد ارور: ' + xhr.status + '\nتوضیحات: ' + error + '\n' + xhr.responseText;
            textarea.value = 'خطای شبکه در ارتباط با سرور وردپرس.' + errorDetails;
        }
    });
};

window.hodimaCopyAeoLinks = function() {
    var copyText = document.getElementById("hodima-aeo-export-textarea");
    if(!copyText || !copyText.value || copyText.value.includes('هیچ موردی') || copyText.value.startsWith('خطا')) {
        alert('لیست لینک‌ها خالی است!');
        return;
    }
    
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    
    var msg = document.getElementById("hodima-aeo-copy-msg");
    if (msg) {
        msg.style.display = "inline";
        setTimeout(function() { msg.style.display = "none"; }, 3000);
    }
};