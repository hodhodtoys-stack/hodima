/**
 * Hodima Stories — نوار استوری سایت.
 *
 * - علامت «دیده‌شده» (حلقه کم‌رنگ) از کلیدهای hs_view_* که پخش‌کننده می‌نویسد؛
 *   اگر گزینه‌اش در پنل روشن باشد، دیده‌شده‌ها به انتهای ردیف می‌روند.
 * - سایه لبه (is-scrolled) وقتی به انتهای ردیف رسیدیم پنهان می‌شود.
 *   نشانگر فلش «استوری‌های بیشتر» به خواست کاربر حذف شد.
 */
( () => {
	'use strict';

	function throttle( func, limit ) {
		let waiting = false;
		return ( ...args ) => {
			if ( ! waiting ) {
				func( ...args );
				waiting = true;
				setTimeout( () => {
					waiting = false;
				}, limit );
			}
		};
	}

	function handleScrollEdge( wrapper, container ) {
		// scrollLeft در راست‌به‌چپ صفر یا منفی است؛ فاصله از ابتدا = قدر مطلق
		const checkScroll = () => {
			const maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
			container.classList.toggle( 'is-scrolled', Math.abs( wrapper.scrollLeft ) >= maxScroll - 5 );
		};

		checkScroll();
		const throttled = throttle( checkScroll, 100 );
		wrapper.addEventListener( 'scroll', throttled, { passive: true } );
		window.addEventListener( 'resize', throttled, { passive: true } );
	}

	// شناسه استوری‌هایی که این بازدیدکننده دیده (حالت خصوصی: هیچ)
	function getSeenStoryIds() {
		const seen = new Set();
		try {
			for ( let i = 0; i < localStorage.length; i++ ) {
				const key = localStorage.key( i );
				if ( key && key.startsWith( 'hs_view_' ) ) {
					seen.add( key.slice( 'hs_view_'.length ) );
				}
			}
		} catch { /* حافظه در دسترس نیست */ }
		return seen;
	}

	// علامت دیده‌شده؛ و اگر moveToEnd، دیده‌شده‌ها به انتهای ردیف
	function markSeen( wrapper, moveToEnd ) {
		const seen = getSeenStoryIds();
		if ( ! seen.size ) {
			return;
		}

		const unseen = [];
		const watched = [];
		wrapper.querySelectorAll( '.story-item' ).forEach( ( item ) => {
			const link = item.querySelector( '.story-link' );
			if ( link && seen.has( link.dataset.storyId || '' ) ) {
				item.classList.add( 'is-seen' );
				watched.push( item );
			} else {
				unseen.push( item );
			}
		} );

		if ( moveToEnd && watched.length ) {
			const frag = document.createDocumentFragment();
			unseen.concat( watched ).forEach( ( item ) => frag.appendChild( item ) );
			wrapper.appendChild( frag );
		}
	}

	function setupContainer( container ) {
		const wrapper = container.querySelector( '.story-wrapper' );
		if ( wrapper ) {
			markSeen( wrapper, container.dataset.seenToEnd === '1' );
			handleScrollEdge( wrapper, container );
		}
		container.classList.remove( 'hs-loading' );
		container.classList.add( 'is-ready' );
	}

	/*
	 * راه‌اندازی فوری، بدون انتظار برای ورود به دید: با حالت Delay JS
	 * لایت‌اسپید DOMContentLoaded پیش از اجرای اسکریپت رخ داده است.
	 */
	function processStories() {
		document.querySelectorAll( '.section-stories' ).forEach( setupContainer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', processStories );
	} else {
		processStories();
	}
} )();
