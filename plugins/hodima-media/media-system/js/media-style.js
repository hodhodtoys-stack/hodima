/**
 * Media System — front-end
 * Path: media-system/js/media-style.js
 *
 *   - «نما»ی ویدیو: کلیک روی کاور ← پخش‌کننده آپارات/یوتیوب/ویمئو ساخته می‌شود
 *   - فصل‌ها: کلیک ← پخش از همان زمان؛ آدرس ?t=ثانیه (لحظه‌های کلیدی گوگل) هم
 *   - فقط یک رسانه همزمان پخش شود؛ رسانه خارج از دید متوقف شود
 *
 * راه‌اندازی مستقل از DOMContentLoaded (حالت «Delay JS» لایت‌اسپید اسکریپت را
 * بعد از آن رویداد اجرا می‌کند).
 */
(() => {
	'use strict';

	const observer = 'IntersectionObserver' in window
		? new IntersectionObserver((entries) => {
			for (const { isIntersecting, target } of entries) {
				if (isIntersecting) continue;
				if (target.tagName === 'IFRAME') {
					pauseIframe(target);
				} else if (!target.paused && !target.ended) {
					target.pause();
				}
			}
		}, { threshold: 0 })
		: null;

	function pauseIframe(frame) {
		try {
			frame.contentWindow?.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*'); // YouTube (enablejsapi=1)
			frame.contentWindow?.postMessage('{"method":"pause"}', '*');                                 // Vimeo و سازگارها
		} catch { /* پلیری که پیام را نمی‌پذیرد */ }
	}

	/** آدرس پخش‌کننده با زمان شروع (یوتیوب start، ویمئو #t؛ آپارات زمان شروع مستند ندارد). */
	function withStart(src, provider, seconds) {
		if (!seconds) return src;
		const url = new URL(src, location.href);
		if (provider === 'youtube') url.searchParams.set('start', String(seconds));
		if (provider === 'vimeo') url.hash = `t=${seconds}s`;
		return url.toString();
	}

	/** «نما» را با iframe پخش‌کننده جایگزین می‌کند. */
	function activateFacade(facade, seconds = 0) {
		const frame = document.createElement('iframe');
		frame.src = withStart(facade.dataset.hookEmbed, facade.dataset.hookProvider, seconds);
		frame.title = facade.dataset.hookTitle || 'ویدیو';
		frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
		frame.allowFullscreen = true;
		frame.referrerPolicy = 'strict-origin-when-cross-origin';

		const box = document.createElement('div');
		box.className = 'hook-oembed-container';
		box.append(frame);
		facade.replaceWith(box);
		frame.focus();
		observer?.observe(frame);
		return frame;
	}

	/** پخش ویدیوی یک بلوک از زمان مشخص. */
	function seek(wrapper, seconds) {
		const video = wrapper.querySelector('video.hook-video-el');
		if (video) {
			const play = () => { video.currentTime = seconds; video.play().catch(() => {}); };
			video.readyState >= 1 ? play() : (video.preload = 'metadata', video.addEventListener('loadedmetadata', play, { once: true }), video.load());
			return;
		}

		const facade = wrapper.querySelector('.hook-video-facade');
		if (facade) {
			activateFacade(facade, seconds);
			return;
		}

		const frame = wrapper.querySelector('.hook-oembed-container iframe');
		if (frame?.src.includes('youtube')) {
			frame.contentWindow?.postMessage(JSON.stringify({ event: 'command', func: 'seekTo', args: [seconds, true] }), '*');
		}
	}

	document.addEventListener('click', (e) => {
		const play = e.target.closest?.('.hook-video-facade .hook-video-play');
		if (play) {
			e.preventDefault();
			activateFacade(play.closest('.hook-video-facade'));
			return;
		}

		const chapter = e.target.closest?.('[data-hook-seek]');
		if (chapter) {
			const wrapper = chapter.closest('.hook-video-wrapper');
			if (wrapper) seek(wrapper, Number(chapter.dataset.hookSeek) || 0);
		}
	});

	// یک رسانه همزمان — روی کل سند، پس پلیرهای دیگر (استوری، صفحه ویدیو) را هم پوشش می‌دهد
	document.addEventListener('play', (e) => {
		const t = e.target;
		if (t.tagName !== 'AUDIO' && t.tagName !== 'VIDEO') return;
		for (const m of document.querySelectorAll('audio, video')) {
			if (m !== t && !m.paused) m.pause();
		}
	}, true);

	// پایان ویدیو: بازگشت به کاور (load() پوستر را دوباره نمایش می‌دهد)
	document.addEventListener('ended', (e) => {
		if (e.target.tagName === 'VIDEO' && e.target.hasAttribute('poster')) e.target.load();
	}, true);

	function init() {
		for (const m of document.querySelectorAll('.hook-video-el, .hook-audio-el, .hook-oembed-container iframe')) {
			observer?.observe(m);
		}

		// ?t=ثانیه (آدرس «لحظه کلیدی» در نتایج گوگل): اولین ویدیوی صفحه از همان زمان
		const t = Number(new URLSearchParams(location.search).get('t'));
		const wrapper = document.querySelector('.hook-video-wrapper');
		if (t > 0 && wrapper) {
			wrapper.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
			seek(wrapper, t);
		}
	}

	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
