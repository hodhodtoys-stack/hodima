/**
 * Media System — front-end
 * Path: media-system/js/media-style.js
 *
 *   - «نما»ی ویدیو: کلیک روی کاور ← پخش‌کننده آپارات/یوتیوب/ویمئو ساخته می‌شود
 *   - فصل‌ها: کلیک ← پخش از همان زمان؛ آدرس ?t=ثانیه (لحظه‌های کلیدی گوگل) هم
 *   - فقط یک رسانه همزمان پخش شود؛ ویدیوی خارج از دید متوقف شود (پادکست نه)
 *   - پادکست: نوار پخش خود سایت به‌جای کنترل‌های بومی مرورگر (پخش، زمان، نوار
 *     پیشرفت، بلندگو، دایره سرعت، منوی ⋮)، و پلیر کوچک ثابت پایین صفحه وقتی در
 *     حال پخش است و کاربر از آن دور شده (توقف/ادامه و بازگشت به پلیر)
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
	// ۱ ← ۲ ← ۳ ← ۱ (خواسته کاربر: پله‌های ساده؛ قبلا ۱٫۲۵ و ۱٫۵ هم داشت)
	const RATES = [1, 2, 3];
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

	/*
	 * ── پادکست: نوار پخش ──
	 * مرورگرها اجازه نمی‌دهند دکمه‌ای داخل کنترل‌های بومی <audio> گذاشته شود؛
	 * پس نوار خود سایت با همان چیدمان (چپ‌به‌راست مثل پلیر بومی): پخش، زمان،
	 * نوار پیشرفت، بلندگو، دایره سرعت، ⋮ (دانلود). خود <audio> می‌ماند (پنهان)
	 * و همه رویدادها (یک رسانه همزمان، پلیر کوچک) مثل قبل از آن می‌آیند.
	 * بدون جاوااسکریپت همان پلیر بومی مرورگر نمایش داده می‌شود.
	 */
	const svg = (body) => `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${body}</svg>`;
	const ICONS = {
		play: svg('<path fill="currentColor" d="M8 5.5v13l10.5-6.5z"/>'),
		pause: svg('<path fill="currentColor" d="M7 5h3.5v14H7zm6.5 0H17v14h-3.5z"/>'),
		volume: svg('<path fill="currentColor" d="M4 9.5v5h3.5L12 19V5L7.5 9.5z"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>'),
		muted: svg('<path fill="currentColor" d="M4 9.5v5h3.5L12 19V5L7.5 9.5z"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="m15.5 9.5 5 5m0-5-5 5"/>'),
		more: svg('<circle fill="currentColor" cx="12" cy="5.5" r="2"/><circle fill="currentColor" cx="12" cy="12" r="2"/><circle fill="currentColor" cx="12" cy="18.5" r="2"/>'),
	};
	const faDigits = (str) => str.replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

	function clock(seconds) {
		const s = Number.isFinite(seconds) ? Math.max(0, Math.floor(seconds)) : 0;
		const two = (n) => String(n).padStart(2, '0');
		const h = Math.floor(s / 3600);
		const m = Math.floor((s % 3600) / 60);
		return faDigits(h ? `${h}:${two(m)}:${two(s % 60)}` : `${m}:${two(s % 60)}`);
	}

	function iconButton(cls, label, icon) {
		const b = document.createElement('button');
		b.type = 'button';
		b.className = `hook-player__btn ${cls}`;
		b.setAttribute('aria-label', label);
		b.innerHTML = ICONS[icon];
		return b;
	}

	function buildPlayer(audio) {
		const container = audio.closest('.hook-voice-container');
		if (!container || audio.dataset.hookPlayer) return;
		audio.dataset.hookPlayer = '1';

		const bar = document.createElement('div');
		bar.className = 'hook-player';
		bar.dir = 'ltr';
		bar.setAttribute('role', 'group');
		bar.setAttribute('aria-label', `پخش‌کننده ${audio.dataset.hookTitle || 'پادکست'}`);

		const play = iconButton('hook-player__play', 'پخش', 'play');
		const time = document.createElement('span');
		time.className = 'hook-player__time';
		const seekBar = document.createElement('input');
		seekBar.type = 'range';
		seekBar.className = 'hook-player__seek';
		seekBar.min = '0';
		seekBar.max = '1000';
		seekBar.step = '1';
		seekBar.value = '0';
		seekBar.setAttribute('aria-label', 'موقعیت پخش');
		const volume = iconButton('hook-player__volume', 'بی‌صدا کردن', 'volume');
		const speed = container.querySelector('.hook-audio-speed');

		// ⋮: منوی کوچک با «دانلود فایل» (همان گزینه منوی پلیر بومی)
		const more = document.createElement('div');
		more.className = 'hook-player__more';
		const moreBtn = iconButton('hook-player__more-btn', 'گزینه‌های بیشتر', 'more');
		moreBtn.setAttribute('aria-haspopup', 'true');
		moreBtn.setAttribute('aria-expanded', 'false');
		const menu = document.createElement('div');
		menu.className = 'hook-player__menu';
		menu.hidden = true;
		const download = document.createElement('a');
		download.href = audio.currentSrc || audio.querySelector('source')?.src || audio.src;
		download.download = '';
		download.textContent = 'دانلود فایل';
		menu.append(download);
		more.append(moreBtn, menu);

		bar.append(play, time, seekBar, volume);
		if (speed) bar.append(speed);
		bar.append(more);

		const sync = () => {
			const d = audio.duration;
			const known = Number.isFinite(d) && d > 0;
			const ratio = known ? audio.currentTime / d : 0;
			time.textContent = known ? `${clock(audio.currentTime)} / ${clock(d)}` : clock(audio.currentTime);
			seekBar.value = String(Math.round(ratio * 1000));
			seekBar.style.setProperty('--hook-progress', `${(ratio * 100).toFixed(2)}%`);
			seekBar.setAttribute('aria-valuetext', known ? `${clock(audio.currentTime)} از ${clock(d)}` : clock(audio.currentTime));
		};
		const syncPlay = () => {
			const paused = audio.paused || audio.ended;
			play.innerHTML = ICONS[paused ? 'play' : 'pause'];
			play.setAttribute('aria-label', paused ? 'پخش' : 'توقف');
		};
		const syncVolume = () => {
			volume.innerHTML = ICONS[audio.muted ? 'muted' : 'volume'];
			volume.setAttribute('aria-label', audio.muted ? 'صدادار کردن' : 'بی‌صدا کردن');
			volume.setAttribute('aria-pressed', String(audio.muted));
		};
		const closeMenu = () => {
			menu.hidden = true;
			moreBtn.setAttribute('aria-expanded', 'false');
		};

		play.addEventListener('click', () => (audio.paused || audio.ended ? audio.play().catch(() => {}) : audio.pause()));
		volume.addEventListener('click', () => { audio.muted = !audio.muted; });
		seekBar.addEventListener('input', () => {
			if (Number.isFinite(audio.duration) && audio.duration > 0) {
				audio.currentTime = (Number(seekBar.value) / 1000) * audio.duration;
			}
			sync();
		});
		// صفحه‌کلید: هر فلش ۵ ثانیه (پله پیش‌فرض نوار یک‌هزارم کل زمان است و تقریبا حرکت نمی‌کند)
		seekBar.addEventListener('keydown', (e) => {
			const step = { ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5 }[e.key];
			if (!step || !Number.isFinite(audio.duration)) return;
			e.preventDefault();
			audio.currentTime = Math.min(audio.duration, Math.max(0, audio.currentTime + step));
		});
		moreBtn.addEventListener('click', () => {
			const open = menu.hidden;
			menu.hidden = !open;
			moreBtn.setAttribute('aria-expanded', String(open));
			if (open) download.focus();
		});
		download.addEventListener('click', closeMenu);
		document.addEventListener('click', (e) => { if (!more.contains(e.target)) closeMenu(); });
		bar.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && !menu.hidden) { closeMenu(); moreBtn.focus(); }
		});

		for (const type of ['timeupdate', 'durationchange', 'loadedmetadata', 'seeked', 'emptied']) audio.addEventListener(type, sync);
		for (const type of ['play', 'pause', 'ended']) audio.addEventListener(type, syncPlay);
		audio.addEventListener('volumechange', syncVolume);

		audio.controls = false;
		audio.hidden = true;
		audio.after(bar);
		container.classList.add('has-player');
		sync();
		syncPlay();
		syncVolume();
	}

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
		// نوار پخش پادکست + سرعت ذخیره‌شده (دکمه سرعت بدون JS پنهان است)
		for (const audio of document.querySelectorAll('.hook-voice-container audio.hook-audio-el')) buildPlayer(audio);
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
