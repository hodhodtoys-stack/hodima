/**
 * Video Watch — player
 * Version: 2.0.0
 *
 * تغییرات:
 *   - قابلیت «تغییر صدا» (ترک‌های صوتی انگلیسی و عربی) حذف شد.
 *   - پرش با ?t= : اسکیمای صفحه (SeekToAction و Clip) به گوگل اعلام می‌کند
 *     که ?t=120 از ثانیه ۱۲۰ پخش می‌کند، ولی پلیر قبلا آن را نادیده می‌گرفت؛
 *     لینک‌های «لحظه‌های کلیدی» گوگل ویدیو را از ابتدا پخش می‌کردند.
 *   - کلیدهای فلش ←/→ برای پرش ۵ ثانیه‌ای، روی نوار پیشرفت هم.
 *   - Media Session: play و pause صریح (قبلا هر دو toggle بودند و اگر
 *     وضعیت هماهنگ نبود، برعکس عمل می‌کردند).
 *   - راه‌اندازی مستقل از DOMContentLoaded (سازگار با Delay JS لایت‌اسپید).
 *   - کپی لینک بدون Clipboard API (اتصال غیرامن یا مرورگر قدیمی) هم کار می‌کند.
 */
(function () {
    'use strict';

    var SKIP = 5;

    function formatTime(t) {
        if (!isFinite(t)) return '0:00';
        var h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = Math.floor(t % 60);
        var ss = (s < 10 ? '0' : '') + s;
        return h > 0 ? h + ':' + (m < 10 ? '0' : '') + m + ':' + ss : m + ':' + ss;
    }

    /** ?t=90 ، ?t=1:30 ، ?t=1m30s ، #t=90 → ثانیه */
    function startTimeFromUrl() {
        var raw = null;
        try { raw = new URLSearchParams(location.search).get('t'); } catch (e) {}
        if (!raw && /(^|[#&])t=/.test(location.hash)) raw = location.hash.replace(/^.*[#&]t=([^&]*).*$/, '$1');
        if (!raw) return 0;

        raw = String(raw).trim();
        if (/^\d+(\.\d+)?$/.test(raw)) return parseFloat(raw);

        var parts = raw.split(':');
        if (parts.length > 1 && parts.every(function (p) { return /^\d+$/.test(p); })) {
            return parts.reduce(function (acc, p) { return acc * 60 + parseInt(p, 10); }, 0);
        }

        var m = raw.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i);
        return m ? (parseInt(m[1] || 0, 10) * 3600 + parseInt(m[2] || 0, 10) * 60 + parseInt(m[3] || 0, 10)) : 0;
    }

    function HodVideoPlayer(container) {

        this.container = container;
        this.video = container.querySelector('.hs-main-video');
        if (!this.video) return;

        this.videoId    = this.video.getAttribute('data-id');
        this.videoTitle = this.video.getAttribute('data-title');
        this.poster     = this.video.getAttribute('poster');

        var q = function (s) { return container.querySelector(s); };

        this.playBtns      = container.querySelectorAll('.hs-play-btn, .hs-play-pause-small');
        this.playIconPaths = container.querySelectorAll('.hs-play-icon-path');
        this.progressWrap  = q('.hs-progress-wrap');
        this.progressBar   = q('.hs-progress-fill');
        this.currentTimeEl = q('.hs-current-time');
        this.durationEl    = q('.hs-duration');
        this.muteBtn       = q('.hs-mute-btn');
        this.volumeSlider  = q('.hs-volume-slider');
        this.speedBtn      = q('.hs-speed-btn');
        this.speedMenu     = q('.hs-speed-menu');
        this.speedOptions  = container.querySelectorAll('.hs-speed-menu .hs-menu-option');
        this.fullscreenBtn = q('.hs-fullscreen-btn');
        this.pipBtn        = q('.hs-pip-btn');
        this.moreBtn       = q('.hs-more-options-btn');
        this.moreMenu      = q('.hs-more-options-container .hs-more-menu');

        // دکمه‌های اشتراک و شمارنده در نوار زیر پلیرند (خواهر .hs-player، نه فرزند)
        var scope = container.closest('.hs-video-col') || container.parentElement || container;
        this.shareBtn      = scope.querySelector('.hs-share-btn');
        this.shareMenu     = scope.querySelector('.hs-share-menu');
        this.copyLinkBtn   = scope.querySelector('.hs-copy-link');
        this.viewCounterEl = scope.querySelector('.hs-live-view-count');

        this.viewTracked     = false;
        this.idleTimer       = null;
        this.ignoreNextClick = false;
        this.pendingStart    = startTimeFromUrl();

        this.bindEvents();
        this.setupMediaSession();
        this.setupKeyboard();
        this.applyStartTime();
    }

    var P = HodVideoPlayer.prototype;

    /* ── پرش به ?t= ─────────────────────────────────────────────────── */

    P.applyStartTime = function () {
        var self = this;
        var t = this.pendingStart;
        if (!(t > 0)) return;

        var seek = function () {
            if (!isFinite(self.video.duration) || t >= self.video.duration) return;
            self.video.currentTime = t;
            self.updateProgress();
        };

        if (this.video.readyState >= 1) seek();
        else {
            // با preload="metadata" اطلاعات فایل معمولا بدون کلیک هم بارگذاری می‌شود
            this.video.addEventListener('loadedmetadata', seek, { once: true });
        }

        // کاربر مستقیم از نتیجه گوگل آمده: پلیر در دید باشد
        requestAnimationFrame(function () {
            self.container.scrollIntoView({ block: 'center', behavior: 'auto' });
        });
    };

    /* ── رویدادها ───────────────────────────────────────────────────── */

    P.bindEvents = function () {

        var self = this;
        var v = this.video;

        this.container.addEventListener('mousemove', function () { self.showControls(); });
        this.container.addEventListener('touchstart', function () { self.showControls(); }, { passive: true });
        this.container.addEventListener('click', function () { self.showControls(); });
        this.container.addEventListener('mouseleave', function () { if (!v.paused) self.hideControls(); });

        v.addEventListener('play', function () { self.onPlay(); });
        v.addEventListener('pause', function () { self.onPause(); });
        v.addEventListener('loadedmetadata', function () {
            if (self.durationEl) self.durationEl.textContent = formatTime(v.duration);
        });
        v.addEventListener('timeupdate', function () { self.onTimeUpdate(); });
        v.addEventListener('click', function (e) {
            if (self.ignoreNextClick) { e.preventDefault(); return; }
            self.togglePlayState();
        });

        this.playBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { self.togglePlayState(); });
        });

        if (this.progressWrap) {
            this.progressWrap.setAttribute('role', 'slider');
            this.progressWrap.setAttribute('aria-valuemin', '0');
            this.progressWrap.addEventListener('click', function (e) {
                var rect = self.progressWrap.getBoundingClientRect();
                var pct = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1);
                if (isFinite(v.duration)) v.currentTime = pct * v.duration;
            });
        }

        if (this.muteBtn && this.volumeSlider) {
            this.muteBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                v.muted = !v.muted;
                self.volumeSlider.value = v.muted ? 0 : v.volume;
                self.updateMuteIcon(parseFloat(self.volumeSlider.value));
            });
            this.volumeSlider.addEventListener('input', function (e) {
                var vol = parseFloat(e.target.value);
                v.volume = vol;
                v.muted = vol === 0;
                self.updateMuteIcon(vol);
            });
        }

        this.setupMenu(this.moreBtn, this.moreMenu);
        this.setupMenu(this.speedBtn, this.speedMenu);

        this.speedOptions.forEach(function (option) {
            option.addEventListener('click', function () {
                var speed = parseFloat(option.getAttribute('data-speed'));
                v.playbackRate = speed;
                if (self.speedBtn) self.speedBtn.textContent = speed + 'x';
                self.speedOptions.forEach(function (o) { o.classList.remove('is-active'); });
                option.classList.add('is-active');
                if (self.speedMenu) self.speedMenu.classList.remove('is-open');
            });
        });

        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (navigator.share) {
                    navigator.share({ title: document.title, url: self.shareUrl() })
                        .then(function () { self.trackInteraction('shares'); })
                        .catch(function () {});
                } else if (self.shareMenu) {
                    var open = self.shareMenu.classList.contains('is-open');
                    self.closeAllMenus();
                    if (!open) self.shareMenu.classList.add('is-open');
                }
            });
        }

        if (this.copyLinkBtn) {
            this.copyLinkBtn.addEventListener('click', function (e) {
                e.preventDefault();
                self.copyText(self.shareUrl()).then(function () {
                    var original = self.copyLinkBtn.textContent;
                    self.copyLinkBtn.textContent = '✓';
                    self.trackInteraction('shares');
                    setTimeout(function () {
                        self.copyLinkBtn.textContent = original;
                        if (self.shareMenu) self.shareMenu.classList.remove('is-open');
                    }, 2000);
                });
            });
        }

        if (this.fullscreenBtn) {
            this.fullscreenBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                self.toggleFullscreen();
            });
        }

        if (this.pipBtn && document.pictureInPictureEnabled) {
            this.pipBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                (document.pictureInPictureElement !== v ? v.requestPictureInPicture() : document.exitPictureInPicture())
                    .catch(function () {});
            });
        } else if (this.pipBtn) {
            this.pipBtn.hidden = true;
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.hs-more-options-container') && !e.target.closest('.hs-action-item')) {
                self.closeAllMenus();
            }
        });
    };

    /** لینک اشتراک بدون ?t= (پرش کاربر قبلی به گیرنده منتقل نشود) */
    P.shareUrl = function () {
        try {
            var u = new URL(location.href);
            u.searchParams.delete('t');
            u.hash = '';
            return u.toString();
        } catch (e) {
            return location.href;
        }
    };

    P.copyText = function (text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy') ? resolve() : reject(); } catch (err) { reject(err); }
            ta.remove();
        });
    };

    P.toggleFullscreen = function () {
        var c = this.container;
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            var req = c.requestFullscreen || c.webkitRequestFullscreen;
            if (req) {
                Promise.resolve(req.call(c)).then(function () {
                    if (screen.orientation && screen.orientation.lock) screen.orientation.lock('landscape').catch(function () {});
                }).catch(function () {});
            } else if (this.video.webkitEnterFullscreen) {
                // Safari آیفون فقط تمام‌صفحه خود عنصر ویدیو را پشتیبانی می‌کند
                this.video.webkitEnterFullscreen();
            }
        } else {
            var exit = document.exitFullscreen || document.webkitExitFullscreen;
            if (exit) Promise.resolve(exit.call(document)).catch(function () {});
            if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
        }
    };

    /* ── Media Session ──────────────────────────────────────────────── */

    P.setupMediaSession = function () {

        if (!('mediaSession' in navigator)) return;

        var v = this.video;
        var ext = ((this.poster || '').split('.').pop() || '').split(/[?#]/)[0].toLowerCase();
        var mime = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', gif: 'image/gif' }[ext] || 'image/jpeg';

        try {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: this.videoTitle || document.title,
                artwork: this.poster ? [{ src: this.poster, sizes: '512x512', type: mime }] : []
            });
        } catch (e) { return; }

        var set = function (action, fn) {
            try { navigator.mediaSession.setActionHandler(action, fn); } catch (e) {}
        };

        set('play', function () { v.play().catch(function () {}); });
        set('pause', function () { v.pause(); });
        set('seekbackward', function (d) { v.currentTime = Math.max(v.currentTime - (d.seekOffset || 10), 0); });
        set('seekforward', function (d) { v.currentTime = Math.min(v.currentTime + (d.seekOffset || 10), v.duration || 0); });
        set('seekto', function (d) {
            if (d.fastSeek && 'fastSeek' in v) v.fastSeek(d.seekTime);
            else v.currentTime = d.seekTime;
        });
    };

    /* ── صفحه‌کلید ──────────────────────────────────────────────────── */

    P.setupKeyboard = function () {
        var self = this;
        var v = this.video;

        this.container.addEventListener('keydown', function (e) {
            var tag = document.activeElement ? document.activeElement.tagName : '';
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;

            switch (e.key) {
                case ' ':
                case 'k':
                case 'K':
                    e.preventDefault(); self.togglePlayState(); break;
                case 'f':
                case 'F':
                    e.preventDefault(); self.toggleFullscreen(); break;
                case 'm':
                case 'M':
                    e.preventDefault(); if (self.muteBtn) self.muteBtn.click(); break;
                case 'p':
                case 'P':
                    e.preventDefault(); if (self.pipBtn && !self.pipBtn.hidden) self.pipBtn.click(); break;
                // نوار پیشرفت LTR است (مثل همه پلیرها)؛ فلش راست = جلو
                case 'ArrowRight':
                    e.preventDefault(); v.currentTime = Math.min(v.currentTime + SKIP, v.duration || 0); self.showControls(); break;
                case 'ArrowLeft':
                    e.preventDefault(); v.currentTime = Math.max(v.currentTime - SKIP, 0); self.showControls(); break;
                case 'Home':
                    e.preventDefault(); v.currentTime = 0; break;
                case 'End':
                    if (isFinite(v.duration)) { e.preventDefault(); v.currentTime = v.duration; } break;
            }
        });
    };

    /* ── وضعیت ──────────────────────────────────────────────────────── */

    P.showControls = function () {
        var self = this;
        if (this.container.classList.contains('hs-controls-hidden')) {
            this.container.classList.remove('hs-controls-hidden');
            this.ignoreNextClick = true;
            setTimeout(function () { self.ignoreNextClick = false; }, 400);
        }
        clearTimeout(this.idleTimer);
        if (!this.video.paused) {
            this.idleTimer = setTimeout(function () { self.hideControls(); }, 2500);
        }
    };

    P.hideControls = function () {
        if (!this.video.paused) {
            this.container.classList.add('hs-controls-hidden');
            this.closeAllMenus();
        }
    };

    P.onPlay = function () {
        this.syncPlayState(true);
        this.showControls();
    };

    P.onPause = function () {
        this.syncPlayState(false);
        this.container.classList.remove('hs-controls-hidden');
        clearTimeout(this.idleTimer);
    };

    P.updateProgress = function () {
        var v = this.video;
        if (!isFinite(v.duration) || !v.duration) return;
        var pct = (v.currentTime / v.duration) * 100;
        if (this.progressBar) this.progressBar.style.width = pct + '%';
        if (this.currentTimeEl) this.currentTimeEl.textContent = formatTime(v.currentTime);
        if (this.progressWrap) {
            this.progressWrap.setAttribute('aria-valuemax', String(Math.floor(v.duration)));
            this.progressWrap.setAttribute('aria-valuenow', String(Math.floor(v.currentTime)));
            this.progressWrap.setAttribute('aria-valuetext', formatTime(v.currentTime) + ' / ' + formatTime(v.duration));
        }
        return pct;
    };

    P.onTimeUpdate = function () {

        var self = this;
        var v = this.video;
        if (!v.duration) return;

        var pct = (v.currentTime / v.duration) * 100;
        requestAnimationFrame(function () { self.updateProgress(); });

        if ('mediaSession' in navigator && navigator.mediaSession.setPositionState && isFinite(v.duration)) {
            try {
                navigator.mediaSession.setPositionState({ duration: v.duration, playbackRate: v.playbackRate, position: v.currentTime });
            } catch (e) {}
        }

        if (pct >= 15 && !this.viewTracked) {
            this.viewTracked = true;
            this.trackInteraction('views');
        }
    };

    P.togglePlayState = function () {
        if (this.video.paused) this.video.play().catch(function () {});
        else this.video.pause();
    };

    P.syncPlayState = function (isPlaying) {
        this.playBtns.forEach(function (btn) {
            if (btn.classList.contains('hs-play-toggle')) {
                btn.style.opacity = isPlaying ? '0' : '1';
                btn.style.pointerEvents = isPlaying ? 'none' : 'auto';
                btn.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
            }
        });
        this.playIconPaths.forEach(function (path) {
            path.setAttribute('d', isPlaying ? 'M6 19h4V5H6v14zm8-14v14h4V5h-4z' : 'M8 5v14l11-7z');
        });
    };

    P.updateMuteIcon = function (volume) {
        var muted = volume === 0 || this.video.muted;
        var inner = muted
            ? '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line>'
            : '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>';
        if (this.muteBtn) {
            this.muteBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
        }
        if (this.volumeSlider) {
            var val = (volume - this.volumeSlider.min) / (this.volumeSlider.max - this.volumeSlider.min) * 100;
            this.volumeSlider.style.background = 'linear-gradient(to right, var(--hs-primary) 0%, var(--hs-primary) ' + val + '%, rgba(255,255,255,0.3) ' + val + '%, rgba(255,255,255,0.3) 100%)';
        }
    };

    P.setupMenu = function (btn, menu) {
        var self = this;
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.classList.contains('is-open');
            self.closeAllMenus();
            if (!open) menu.classList.add('is-open');
        });
    };

    P.closeAllMenus = function () {
        [this.shareMenu, this.moreMenu, this.speedMenu].forEach(function (m) {
            if (m) m.classList.remove('is-open');
        });
    };

    /* ── آمار ───────────────────────────────────────────────────────── */

    P.trackInteraction = function (actionType, isRetry) {

        var self = this;
        if (!this.videoId || typeof hvwData === 'undefined') return;

        fetch(hvwData.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-HVW-Nonce': hvwData.nonce },
            body: JSON.stringify({ id: this.videoId, action: actionType })
        })
        .then(function (res) {
            // صفحه از کش سرو شده و نانس منقضی است: یک بار نانس تازه و تکرار
            if (res.status === 403 && !isRetry && hvwData.nonceUrl) {
                return fetch(hvwData.nonceUrl, { cache: 'no-store', credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (fresh) {
                        if (fresh && fresh.nonce) {
                            hvwData.nonce = fresh.nonce;
                            self.trackInteraction(actionType, true);
                        }
                        return null;
                    });
            }
            return res.ok ? res.json() : null;
        })
        .then(function (data) {
            if (data && data.success && actionType === 'views' && self.viewCounterEl && typeof data.views === 'number') {
                self.viewCounterEl.textContent = new Intl.NumberFormat('fa-IR').format(data.views);
            }
        })
        .catch(function () {});
    };

    function boot() {
        document.querySelectorAll('[data-player-instance]').forEach(function (c) { new HodVideoPlayer(c); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
