/**
 * Hodima Stories — floating mini-player.
 *
 * Loaded on every front-end page. Does nothing unless the visitor just
 * clicked a story's CTA (product) link, in which case hodima-player.js
 * left a small "resume this video" record in sessionStorage right before
 * the normal (same-tab) navigation happened. Here on the destination page
 * we pick that up once, render a small floating video in the corner, and
 * clear the record so it doesn't reappear on further navigation or on a
 * refresh of this page.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'hs_mini_player';
  var MAX_AGE_MS = 10 * 60 * 1000; // ignore stale records older than 10 minutes
  var STR = window.HS_MINI_STR || {};

  var raw;
  try {
    raw = sessionStorage.getItem(STORAGE_KEY);
  } catch (e) {
    return; // storage unavailable (privacy mode, etc.)
  }
  if (!raw) return;

  // Consume immediately so this only ever renders once per CTA click,
  // regardless of whether the user closes it, refreshes, or navigates on.
  try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* noop */ }

  var data;
  try { data = JSON.parse(raw); } catch (e) { return; }
  if (!data || !data.url || (Date.now() - (data.ts || 0)) > MAX_AGE_MS) return;

  function render() {
    var wrap = document.createElement('div');
    wrap.className = 'hs-mini-player';

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'hs-mini-player__close';
    closeBtn.setAttribute('aria-label', STR.close || 'بستن');
    closeBtn.innerHTML = '&times;';

    var video = document.createElement('video');
    video.className = 'hs-mini-player__video';
    video.setAttribute('playsinline', '');
    video.setAttribute('webkit-playsinline', '');
    if (data.poster) video.setAttribute('poster', data.poster);
    video.src = data.url;

    var muteIcon = document.createElement('span');
    muteIcon.className = 'hs-mini-player__mute';
    muteIcon.setAttribute('aria-hidden', 'true');
    muteIcon.textContent = '🔇';
    muteIcon.hidden = true;

    wrap.appendChild(video);
    wrap.appendChild(muteIcon);
    wrap.appendChild(closeBtn);
    document.body.appendChild(wrap);

    if (data.time) {
      video.addEventListener('loadedmetadata', function () {
        try { video.currentTime = data.time; } catch (e) { /* noop */ }
      }, { once: true });
    }

    // Bug fix: this used to force video.muted = true unconditionally, so
    // the mini-player never had sound even when the browser would have
    // allowed it. Since the CTA click that brought the visitor to this page
    // was itself a real user gesture, most browsers do allow unmuted
    // autoplay here — try that first and only fall back to muted (with a
    // visible speaker-off icon so the user knows to tap for sound) if the
    // browser actually blocks it.
    video.muted = false;
    video.play().catch(function () {
      video.muted = true;
      muteIcon.hidden = false;
      video.play().catch(function () {
        // Autoplay fully blocked even muted — it'll just sit on the poster
        // frame; the tap handler below lets the user start it manually.
      });
    });

    video.addEventListener('click', function () {
      if (video.paused) {
        video.play().catch(function () { /* noop */ });
      } else {
        video.muted = !video.muted;
        muteIcon.hidden = !video.muted;
      }
    });

    var close = function () {
      try { video.pause(); } catch (e) { /* noop */ }
      wrap.remove();
    };

    closeBtn.addEventListener('click', close);
    // Bug fix: the mini-player had no listener for the video finishing, so
    // it just sat there showing the last frame forever. It should close
    // itself automatically once playback ends.
    video.addEventListener('ended', close);
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', render)
    : render();
})();
