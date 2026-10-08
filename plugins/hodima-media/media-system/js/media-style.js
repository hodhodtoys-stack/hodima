/**
 * Media System — front-end
 * Path: media-system/js/media-style.js
 *
 *   - «نما»ی ویدیو: کلیک روی کاور ← پخش‌کننده آپارات/یوتیوب/ویمئو ساخته می‌شود
 *   - فصل‌ها: کلیک ← پخش از همان زمان؛ آدرس ?t=ثانیه (لحظه‌های کلیدی گوگل) هم
 *   - فقط یک رسانه همزمان پخش شود؛ ویدیوی خارج از دید متوقف شود (پادکست نه)
 *   - پادکست: دکمه سرعت پخش (هر کلیک یک پله)، و پلیر کوچک ثابت پایین صفحه وقتی در حال پخش
 *     است و کاربر از آن دور شده (توقف/ادامه و بازگشت به پلیر)
 *
 * راه‌اندازی مستقل از DOMContentLoaded (حالت «Delay JS» لایت‌اسپید اسکریپت را
 * بعد از آن رویداد اجرا می‌کند).
 */
(() => {
	'use strict';

	/*
	 * اولین گزارش هر عنصر (بلافاصله بعد از observe) نادیده گرفته می‌شود.
	 * باگ قبلی: با آدرس ?t= ویدیوی پایین صفحه از همان زمان پخش می‌شد و
	 * همین گزارش اول («خارج از دید»، پیش از پایان اسکرول) بلافاصله متوقفش می‌کرد.
	 */
	const seen = new WeakSet();
	const observer = 'IntersectionObserver' in window
		? new IntersectionObserver((entries) => {
			for (const { isIntersecting, target } of entries) {
				if (!seen.has(target)) {
					seen.add(target);
					continue;
				}
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

	/* ── پادکست: سرعت پخش (یک دکمه؛ هر کلیک یک پله) ── */
	const RATE_KEY = 'hodimaPodcastRate';
	const RATES = [1, 1.25, 1.5, 2];
	const fa = (n) => new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(n);

	function storedRate() {
		try {
			const r = Number(localStorage.getItem(RATE_KEY));
			return RATES.includes(r) ? r : 1;
		} catch {
			return 1;
		}
	}

	function setRate(button, rate) {
		const audio = button.closest('.hook-voice-container')?.querySelector('audio.hook-audio-el');
		if (audio) {
			audio.playbackRate = rate;
			audio.defaultPlaybackRate = rate;
		}
		button.dataset.hookSpeed = String(rate);
		button.textContent = `${fa(rate)}×`;
		button.setAttribute('aria-label', `سرعت پخش: ${fa(rate)} برابر (برای تغییر بزنید)`);
		button.classList.toggle('is-fast', rate !== 1);
	}

	document.addEventListener('click', (e) => {
		const btn = e.target.closest?.('.hook-audio-speed');
		if (!btn) return;
		const next = RATES[(RATES.indexOf(Number(btn.dataset.hookSpeed)) + 1) % RATES.length];
		setRate(btn, next);
		try { localStorage.setItem(RATE_KEY, String(next)); } catch { /* حالت خصوصی */ }
	});

	/* ── پادکست: پلیر کوچک ثابت ── */
	let mini = null;
	let current = null;      // صوتی که آخرین بار پخش شد
	let currentVisible = true;

	const audioObserver = 'IntersectionObserver' in window
		? new IntersectionObserver((entries) => {
			for (const { isIntersecting, target } of entries) {
				if (target === current?.closest('.hook-voice-wrapper')) {
					currentVisible = isIntersecting;
					updateMini();
				}
			}
		})
		: null;

	function buildMini() {
		mini = document.createElement('div');
		mini.className = 'hook-mini-player';
		mini.setAttribute('role', 'region');
		mini.setAttribute('aria-label', 'پخش‌کننده پادکست');
		mini.hidden = true;
		mini.innerHTML = '<button type="button" class="hook-mini-player__toggle" data-hook-mini="toggle"></button>'
			+ '<button type="button" class="hook-mini-player__title" data-hook-mini="back"></button>'
			+ '<button type="button" class="hook-mini-player__close" data-hook-mini="close" aria-label="بستن پخش‌کننده کوچک">×</button>';
		mini.addEventListener('click', (e) => {
			const action = e.target.closest('[data-hook-mini]')?.dataset.hookMini;
			if (!current || !action) return;
			if (action === 'toggle') current.paused ? current.play().catch(() => {}) : current.pause();
			if (action === 'back') current.closest('.hook-voice-wrapper')?.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
			if (action === 'close') { current.pause(); mini.hidden = true; }
		});
		document.body.append(mini);
	}

	function updateMini() {
		if (!current) return;
		// فقط وقتی پلیر اصلی از دید خارج است و صوت پخش شده (در حال پخش یا مکث موقت)
		const show = !currentVisible && current.currentTime > 0 && !current.ended;
		if (show && !mini) buildMini();
		if (!mini) return;
		mini.hidden = !show;
		const toggle = mini.querySelector('[data-hook-mini="toggle"]');
		toggle.textContent = current.paused ? '▶' : '❚❚';
		toggle.setAttribute('aria-label', current.paused ? 'ادامه پخش' : 'توقف');
		mini.querySelector('[data-hook-mini="back"]').textContent = current.dataset.hookTitle || 'پادکست';
	}

	for (const type of ['play', 'pause', 'ended']) {
		document.addEventListener(type, (e) => {
			if (!e.target.matches?.('audio.hook-audio-el')) return;
			if (type === 'play' && current !== e.target) {
				const old = current?.closest('.hook-voice-wrapper');
				if (old) audioObserver?.unobserve(old);
				current = e.target;
				currentVisible = true;
				const wrap = current.closest('.hook-voice-wrapper');
				if (wrap) audioObserver?.observe(wrap);
			}
			updateMini();
		}, true);
	}

	function init() {
		// سرعت ذخیره‌شده + نمایش دکمه سرعت (بدون JS پنهان است)
		const rate = storedRate();
		for (const btn of document.querySelectorAll('.hook-audio-speed')) {
			btn.hidden = false;
			setRate(btn, rate);
		}

		// پادکست عمدا نه: کاربر صوت را پخش می‌کند و برای خواندن متن پایین می‌رود
		// (باگ قبلی: با اسکرول پخش پادکست قطع می‌شد)
		for (const m of document.querySelectorAll('.hook-video-el, .hook-video-wrapper .hook-oembed-container iframe')) {
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
