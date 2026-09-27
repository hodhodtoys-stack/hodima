(function () {
  'use strict';

  const C = window.HS_PLAYER_STR || {};
  const S = C.settings || { autoplayNext: true, loopPlaylist: false, tapNavigation: true, swipeNavigation: true, preloadAdjacent: true };
  const SEL = C.selectors || { storyLink: '.story-link', playlistScope: '[data-story-group], .section-stories' };

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const t = (k, fb) => C[k] || fb || '';

  const canWarmUp = () => {
    const c = navigator.connection;
    if (!c) return true;
    return !c.saveData && !/^(slow-2g|2g|3g)$/.test(c.effectiveType || '');
  };

  // در RTL «بعدی» سمت چپ است؛ همان جهتی که ناحیه‌های لمسی دارند
  const isRTL = () => (document.documentElement.getAttribute('dir') || getComputedStyle(document.documentElement).direction) === 'rtl';

  let playlist = [], currentIndex = -1, toastTimer, touchX = 0, touchY = 0, modalOpen = false, lastFocus, prevOverflow, rAF_ID;
  // Bug fix: guards against a stale async v.play() promise (from a previous
  // openStory() call) resolving/rejecting after the user has already
  // navigated to a different story, which used to overwrite the message for
  // the wrong story.
  let openToken = 0;
  // Bug fix: closeModal() used to finish its cleanup (wiping `playlist`,
  // hiding the modal) inside a fire-and-forget 300ms setTimeout. If the user
  // closed one story and opened another within that window, the stale
  // timeout would fire AFTER the new story had already started and wipe out
  // its live state — breaking navigation and re-hiding a modal that was
  // supposed to be open. This timer is now tracked and cancelled whenever a
  // new story session starts.
  let closeTimeoutId = null;
  let historyPushed = false;
  const DOM = {};

  function initDOM() {
    ['hs-video-modal', 'hs-modal-video', 'hs-story-title', 'hs-story-position', 'hs-view-count', 'hs-player-message', 'hs-player-toast', 'hs-cta-box', 'hs-cta-text'].forEach(id => {
      DOM[id] = document.getElementById(id);
    });

    ['.hs-player', '.hs-progress-wrap', '.hs-stage', '.hs-modal__content'].forEach(c => {
      DOM[c] = $(c);
    });
  }

  const showToast = (msg, type = 'info') => {
    if (!DOM['hs-player-toast']) return;
    DOM['hs-player-toast'].textContent = msg;
    DOM['hs-player-toast'].className = `hs-toast is-show is-${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      DOM['hs-player-toast'].className = 'hs-toast';
    }, 2200);
  };

  const ajax = async (action, payload = {}) => {
    if (!C.ajaxUrl) return { ok: false };

    // ADD NONCE Security
    payload.security = C.nonce || '';

    const body = new URLSearchParams({ action, ...payload });

    try {
      const res = await fetch(C.ajaxUrl, {
        method: 'POST',
        body,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
      });
      const json = await res.json();
      return { ok: !!json.success, data: json.data };
    } catch {
      return { ok: false };
    }
  };

  const updateProgress = () => {
    const v = DOM['hs-modal-video'];
    if (!v || currentIndex < 0) return;

    $$('.hs-progress-fill').forEach((fill, i) => {
      if (!fill) return;
      fill.style.width = i < currentIndex ? '100%' : i > currentIndex ? '0%' : (v.duration ? (v.currentTime / v.duration) * 100 + '%' : '0%');
    });
  };

  const loopProgress = () => {
    updateProgress();
    if (modalOpen && !DOM['hs-modal-video'].paused) {
      rAF_ID = requestAnimationFrame(loopProgress);
    }
  };

  const syncUI = () => {
    const item = playlist[currentIndex];
    if (!item) return;

    if (DOM['hs-story-title']) DOM['hs-story-title'].textContent = item.title || '';
    if (DOM['hs-story-position']) DOM['hs-story-position'].textContent = `${currentIndex + 1} / ${playlist.length}`;
    if (DOM['hs-view-count']) DOM['hs-view-count'].textContent = item.views;

    // Show the CTA box only when this story has a product link; otherwise
    // nothing renders in its place (per the requested behavior).
    const ctaBox = DOM['hs-cta-box'];
    if (ctaBox) {
      if (item.ctaLink) {
        ctaBox.href = item.ctaLink;
        if (DOM['hs-cta-text']) DOM['hs-cta-text'].textContent = item.ctaText || t('ctaDefault', 'مشاهده محصول');
        ctaBox.style.display = '';
      } else {
        ctaBox.style.display = 'none';
        ctaBox.removeAttribute('href');
      }
    }

    const btnPrev = $('.hs-nav--prev');
    const btnNext = $('.hs-nav--next');
    if (btnPrev) btnPrev.disabled = currentIndex <= 0 && !S.loopPlaylist;
    if (btnNext) btnNext.disabled = currentIndex >= playlist.length - 1 && !S.loopPlaylist;

    $$('.hs-progress-item').forEach((it, i) => {
      it.classList.toggle('is-active', i === currentIndex);
      it.classList.toggle('is-complete', i < currentIndex);
    });

    updateProgress();
  };

  const goNext = () => currentIndex < playlist.length - 1 ? openStory(currentIndex + 1) : (S.loopPlaylist ? openStory(0) : showToast(t('playlistEnd', 'پایان استوری‌ها')));
  const goPrev = () => DOM['hs-modal-video']?.currentTime > 3 ? (DOM['hs-modal-video'].currentTime = 0) : (currentIndex > 0 ? openStory(currentIndex - 1) : (S.loopPlaylist && openStory(playlist.length - 1)));

  const togglePlay = async () => {
    const v = DOM['hs-modal-video'];
    if (!v) return;
    v.paused ? v.play().catch(() => showToast(t('playError', 'خطا در پخش'))) : v.pause();
  };

  const handleShare = async () => {
    const item = playlist[currentIndex];
    if (!item) return;

    try {
      if (navigator.share) {
        try {
          await navigator.share({ title: item.title, url: item.shareUrl });
        } catch (shareErr) {
          // A user dismissing the native share sheet throws AbortError —
          // that's a deliberate cancellation, not a failure, so it
          // shouldn't surface an error toast or count as a tracked share.
          // Re-throw anything else so the outer catch can report it.
          if (shareErr && shareErr.name === 'AbortError') return;
          throw shareErr;
        }
      } else if (navigator.clipboard) {
        await navigator.clipboard.writeText(item.shareUrl);
        showToast(t('copySuccess', 'کپی شد'), 'success');
      }

      const shareKey = 'hs_shared_' + item.storyId;
      const lastShare = localStorage.getItem(shareKey);
      const now = Date.now();

      if (item.storyId && (!lastShare || now - parseInt(lastShare, 10) > 86400000)) {
        localStorage.setItem(shareKey, now.toString());
        ajax('hs_track_share', { story_id: item.storyId });
      }
    } catch {
      showToast(t('shareError', 'اشتراک‌گذاری انجام نشد.'), 'error');
    }
  };

  const openStory = async (idx) => {
    if (!playlist.length || idx < 0 || idx >= playlist.length) return;

    cancelAnimationFrame(rAF_ID);
    currentIndex = idx;
    const item = playlist[idx];
    const myToken = ++openToken;

    item.trackedThisSession = false;

    const v = DOM['hs-modal-video'];

    DOM['hs-player-message'].textContent = '';
    DOM['.hs-player'].classList.add('is-loading');

    v.pause();
    v.src = item.url;
    item.poster ? v.setAttribute('poster', item.poster) : v.removeAttribute('poster');
    v.load();

    const wrap = DOM['.hs-progress-wrap'];
    if (wrap.children.length !== playlist.length) {
      wrap.innerHTML = playlist.map((_, i) => `<button type="button" class="hs-progress-item" data-index="${i}"><span class="hs-progress-fill"></span></button>`).join('');
    }

    syncUI();

    // پیش‌گرم کردن ویدیوی بعدی.
    // نسخه قبلی <link rel="preload" as="video"> می‌ساخت؛ کروم و فایرفاکس
    // مقدار as="video" را برای preload نمی‌پذیرند و فقط هشدار کنسول
    // می‌دادند. یک <video> جداشده با preload="metadata" واقعا اتصال و
    // ابتدای فایل را آماده می‌کند — فقط روی اینترنت سریع و بدون «صرفه‌جویی
    // در داده»، تا حجم اینترنت موبایل کاربر هدر نرود.
    if (S.preloadAdjacent && playlist[idx + 1] && canWarmUp()) {
      const warm = document.createElement('video');
      warm.preload = 'metadata';
      warm.muted = true;
      warm.src = playlist[idx + 1].url;
      setTimeout(() => { warm.removeAttribute('src'); warm.load(); }, 8000);
    }

    try {
      v.muted = false;
      await v.play();
    } catch {
      try {
        v.muted = true;
        await v.play();
      } catch {
        // Only act if the user hasn't already navigated to a different
        // story while these promises were pending.
        if (myToken === openToken) {
          // Both attempts failed (autoplay fully blocked) — we're no longer
          // "loading", we're waiting on the user, so the spinner should not
          // keep spinning next to the "tap to play" hint.
          DOM['.hs-player'].classList.remove('is-loading');
          DOM['hs-player-message'].textContent = t('playHint', 'برای پخش کلیک کنید.');
        }
      }
    }
  };

  const FOCUSABLE = 'button, [href], video, [tabindex]:not([tabindex="-1"])';

  const trapFocus = (e) => {
    if (!modalOpen || e.key !== 'Tab') return;
    const scope = DOM['.hs-modal__content'];
    if (!scope) return;

    const focusable = $$(FOCUSABLE, scope).filter(el => !el.disabled && el.offsetParent !== null);
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };

  const closeModal = (fromPopState = false) => {
    if (!modalOpen) return;

    cancelAnimationFrame(rAF_ID);
    modalOpen = false;

    // بستن با دکمه یا Escape: ورودی تاریخچه‌ای که هنگام باز شدن اضافه شد
    // برداشته شود، وگرنه «برگشت» بعدی کاربر ظاهرا کاری نمی‌کرد.
    if (historyPushed && !fromPopState) {
      historyPushed = false;
      try { history.back(); } catch (err) { /* noop */ }
    }
    historyPushed = false;
    DOM['hs-video-modal'].classList.remove('is-open');

    closeTimeoutId = setTimeout(() => {
      DOM['hs-modal-video'].pause();
      DOM['hs-modal-video'].removeAttribute('src');
      DOM['hs-video-modal'].style.display = 'none';
      document.body.style.overflow = prevOverflow;
      playlist = [];
      currentIndex = -1;
      if (lastFocus) lastFocus.focus();
      closeTimeoutId = null;
    }, 300);
  };

  const init = () => {
    initDOM();

    if (!DOM['hs-video-modal']) return;

    document.addEventListener('visibilitychange', () => {
      if (document.hidden && modalOpen && !DOM['hs-modal-video'].paused) {
          togglePlay();
      }
    });

    document.addEventListener('click', e => {
      const link = e.target.closest(SEL.storyLink);
      if (link && link.dataset.storyPlayer !== 'off') {
        e.preventDefault();

        // Cancel any pending close-cleanup from a just-closed modal so it
        // can't wipe out this new session's state a moment later.
        if (closeTimeoutId) {
          clearTimeout(closeTimeoutId);
          closeTimeoutId = null;
        }

        const scope = link.closest(SEL.playlistScope) || document;

        playlist = $$(SEL.storyLink, scope).map(el => ({
          storyId: el.dataset.storyId || '',
          url: el.dataset.video || el.href,
          poster: el.dataset.poster || '',
          title: el.dataset.title || el.title || el.textContent.trim(),
          views: parseInt(el.dataset.views, 10) || 0,
          shareUrl: el.dataset.shareUrl || el.href,
          ctaLink: el.dataset.ctaLink || '',
          ctaText: el.dataset.ctaText || '',
          trackedThisSession: false
        })).filter(i => i.url);

        currentIndex = playlist.findIndex(i => i.url === (link.dataset.video || link.href));
        if (currentIndex < 0) return;

        lastFocus = document.activeElement;
        prevOverflow = document.body.style.overflow;
        DOM['hs-video-modal'].style.display = 'flex';

        requestAnimationFrame(() => {
          DOM['hs-video-modal'].classList.add('is-open');
          document.body.style.overflow = 'hidden';
          modalOpen = true;
          // دکمه «برگشت» گوشی باید استوری را ببندد، نه از صفحه خارج شود
          try { history.pushState({ hsStory: true }, ''); historyPushed = true; } catch (err) { historyPushed = false; }
          openStory(currentIndex);
          const closeBtn = $('.hs-modal__close');
          if (closeBtn) closeBtn.focus();
        });
        return;
      }

      if (modalOpen) {
        if (e.target.closest('.hs-modal__close') || e.target.closest('.hs-modal__overlay')) return closeModal();
        if (e.target.closest('.hs-nav--prev')) return goPrev();
        if (e.target.closest('.hs-nav--next')) return goNext();
        if (e.target.closest('.hs-play-toggle')) return togglePlay();
        if (e.target.closest('.hs-share-btn')) return handleShare();

        const ctaLink = e.target.closest('.hs-cta-box');
        if (ctaLink) {
          // Same-tab navigation (no target="_blank" on this link anymore),
          // so hand the current video off to the sitewide mini-player
          // before the browser navigates away: it reads this on the
          // destination page and resumes playback in a small floating box.
          const item = playlist[currentIndex];
          const v = DOM['hs-modal-video'];
          if (item) {
            try {
              sessionStorage.setItem('hs_mini_player', JSON.stringify({
                url: item.url,
                poster: item.poster,
                title: item.title,
                time: v ? v.currentTime : 0,
                ts: Date.now()
              }));
            } catch { /* storage unavailable — link still navigates normally */ }
          }
          // ورودی تاریخچه‌ای که هنگام باز شدن استوری اضافه شد جای خودش را
          // به صفحه محصول بدهد؛ وگرنه برای برگشت از صفحه محصول دو بار باید
          // «برگشت» زد. کلیک وسط/با Ctrl (تب جدید) دست‌نخورده می‌ماند.
          if (historyPushed && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0 && ctaLink.href) {
            e.preventDefault();
            historyPushed = false;
            window.location.replace(ctaLink.href);
          }
          return;
        }

        const prog = e.target.closest('.hs-progress-item');
        if (prog) return openStory(parseInt(prog.dataset.index, 10));

        const tapZone = e.target.closest('.hs-tap-zone');
        if (tapZone && S.tapNavigation) {
          tapZone.dataset.tap === 'next' ? goNext() : tapZone.dataset.tap === 'prev' ? goPrev() : togglePlay();
        }
      }
    });

    const v = DOM['hs-modal-video'];
    v.addEventListener('playing', () => {
      DOM['.hs-player'].classList.remove('is-loading');
      DOM['hs-video-modal'].classList.remove('is-paused');
      DOM['hs-video-modal'].classList.add('is-playing');

      cancelAnimationFrame(rAF_ID);
      loopProgress();

      const item = playlist[currentIndex];

      if (item && item.storyId && !item.trackedThisSession) {
        item.trackedThisSession = true;

        const viewKey = 'hs_view_' + item.storyId;
        const lastView = localStorage.getItem(viewKey);
        const now = Date.now();

        if (!lastView || now - parseInt(lastView, 10) > 86400000) {
            localStorage.setItem(viewKey, now.toString());
            ajax('hs_track_view', { story_id: item.storyId });
            item.views++;
        }
        syncUI();
      }
    });

    v.addEventListener('pause', () => {
      cancelAnimationFrame(rAF_ID);
      DOM['hs-video-modal'].classList.remove('is-playing');
      DOM['hs-video-modal'].classList.add('is-paused');
    });

    v.addEventListener('waiting', () => DOM['.hs-player'].classList.add('is-loading'));
    v.addEventListener('ended', () => {
      cancelAnimationFrame(rAF_ID);
      S.autoplayNext ? goNext() : updateProgress();
    });
    v.addEventListener('timeupdate', updateProgress);

    if (S.swipeNavigation && DOM['.hs-stage']) {
      DOM['.hs-stage'].addEventListener('touchstart', e => {
        touchX = e.changedTouches[0].clientX;
        touchY = e.changedTouches[0].clientY;
      }, { passive: true });

      DOM['.hs-stage'].addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchX;
        const dy = e.changedTouches[0].clientY - touchY;
        if (Math.abs(dx) > 50 && Math.abs(dy) < 40) {
          dx > 0 ? goNext() : goPrev();
        }
      }, { passive: true });
    }

    window.addEventListener('popstate', () => {
      if (modalOpen) closeModal(true);
    });

    document.addEventListener('keydown', e => {
      if (!modalOpen) return;
      if (e.key === 'Escape') closeModal();
      // نسخه قبلی فلش راست را «بعدی» می‌گرفت در حالی که ناحیه لمسی چپ «بعدی»
      // است (RTL)؛ صفحه‌کلید و لمس برعکس هم کار می‌کردند.
      if (e.key === 'ArrowLeft')  { isRTL() ? goNext() : goPrev(); }
      if (e.key === 'ArrowRight') { isRTL() ? goPrev() : goNext(); }
      if (e.key === ' ') {
        e.preventDefault();
        togglePlay();
      }
      trapFocus(e);
    });
  };

  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
