<?php
/**
 * قالب صفحه تک ویدیو (/video/…)
 * Path: components/video-watch/template-video.php
 *
 * فقط وقتی لود می‌شود که قالب سایت single-video.php خودش را ندارد (video-watch.php).
 * کلاس hvw-page روی <main> دامنه CSS این صفحه است: video-watch.front.css فقط
 * داخل آن اثر دارد تا با کلاس‌های هم‌نام پلیر استوری (hs-player، hs-loader…)
 * که روی همین صفحه هم لود می‌شود تداخل نکند (HODIMA-AUDIT.md بخش ۲۲۱).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	// پیشوند hodima_vw_: متغیرهای این فایل سراسری‌اند؛ تا Media 2.1.3 «$post_id» همان متغیر سراسری وردپرس را بازنویسی می‌کرد
	$hodima_vw_id    = (int) get_the_ID();
	$hodima_vw_url   = (string) get_post_meta( $hodima_vw_id, '_hod_video_url', true );
	$hodima_vw_thumb = function_exists( 'hodima_video_watch_poster_url' ) // کاور در اندازه مناسب، نه اصلی (includes/poster.php)
		? hodima_video_watch_poster_url( $hodima_vw_id )
		: (string) ( get_post_meta( $hodima_vw_id, '_hod_video_thumbnail', true ) ?: get_the_post_thumbnail_url( $hodima_vw_id, 'full' ) );
	$hodima_vw_views = number_format_i18n( class_exists( 'Hodima_Video_Watch_Analytics' ) ? Hodima_Video_Watch_Analytics::get_total_views( $hodima_vw_id ) : 0 );
	$hodima_vw_product = (string) get_post_meta( $hodima_vw_id, '_hod_related_product_url', true );
	$hodima_vw_cards   = get_post_meta( $hodima_vw_id, '_hod_related_links', true );
	$hodima_vw_cards   = is_array( $hodima_vw_cards ) ? $hodima_vw_cards : [];
	$hodima_vw_uid     = 'hvw-' . $hodima_vw_id;

	// متن کامل ویدیو (ترنسکریپت، بخش ۲۱۹/۲۲۲): دکمه «متن» در نوار زیر پلیر و باکس زیر همان نوار
	$hodima_vw_transcript    = trim( (string) get_post_meta( $hodima_vw_id, '_hod_video_transcript', true ) );
	$hodima_vw_transcript_id = 'hvw-transcript-' . $hodima_vw_id;

	/*
	 * مسیر راهنما: همان آدرس پله «ویدئوها» در اسکیمای مسیر راهنما.
	 * اگر برگه فهرست ویدیوها نیست، پله حذف می‌شود (تا Media 2.1.3 لینک با href خالی
	 * به خود صفحه چاپ می‌شد؛ اسکیما هم در این حالت آن پله را ندارد).
	 */
	$hodima_vw_archive = function_exists( 'hod_video_archive_url' ) ? hod_video_archive_url() : '';
	?>

		<main class="hodima-page-wrapper hvw-page">

			<?php // فقط یک h1 (عنوان اصلی)؛ عنوان در مسیر راهنما عنصر معمولی با aria-current است ?>
			<nav class="section-breadcrumb" aria-label="<?php esc_attr_e( 'مسیر راهنما', 'hodima' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'hodima' ); ?></a> /
				<?php if ( '' !== $hodima_vw_archive ) : ?>
				<a href="<?php echo esc_url( $hodima_vw_archive ); ?>"><?php echo esc_html( hod_video_archive_label() ); ?></a> /
				<?php endif; ?>
				<span class="hs-breadcrumb-current" aria-current="page"><?php the_title(); ?></span>
			</nav>

			<section class="hodima-section-box hs-intro-section">

				<div class="hs-content-col">
					<h1 class="hs-title"><?php the_title(); ?></h1>
					<div class="hs-content-box">
						<?php the_content(); ?>
					</div>
				</div>

				<div class="hs-video-col">
					<?php
					/*
					 * پلیر (HODIMA-AUDIT.md بخش ۲۱۸).
					 * - بدون آدرس ویدیو پلیر ساخته نمی‌شود: تا Media 2.1.0 همیشه
					 *   <video src=""> چاپ می‌شد که مرورگر خود صفحه را دوباره به‌عنوان
					 *   ویدیو دانلود می‌کرد و «دانلود ویدئو» هم به خود صفحه می‌رفت.
					 * - نام دکمه‌ها وضعیت را می‌گوید («پخش»/«توقف»، «قطع صدا»/«وصل صدا»)
					 *   و JS آن را به‌روز می‌کند؛ منوها aria-expanded دارند و با Esc بسته می‌شوند.
					 * - نشانگر بارگذاری و پیام خطا (role="status") در خود صحنه.
					 */
					if ( '' === trim( $hodima_vw_url ) ) :
						?>
					<div class="hs-player hs-player--empty">
						<div class="hs-stage hs-flex-center">
							<?php if ( '' !== $hodima_vw_thumb ) : ?>
								<img class="hs-empty-poster" src="<?php echo esc_url( $hodima_vw_thumb ); ?>" alt="" width="1280" height="720" decoding="async">
							<?php endif; ?>
							<p class="hs-player-message is-visible"><?php esc_html_e( 'ویدیو هنوز آماده نیست.', 'hodima' ); ?></p>
						</div>
					</div>
					<?php else : ?>
					<div class="hs-player" data-player-instance>
						<div class="hs-stage hs-flex-center">
							<video class="hs-main-video" src="<?php echo esc_url( $hodima_vw_url ); ?>" data-id="<?php echo esc_attr( (string) $hodima_vw_id ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" playsinline preload="metadata"<?php echo '' !== $hodima_vw_thumb ? ' poster="' . esc_url( $hodima_vw_thumb ) . '"' : ''; ?>></video>

							<div class="hs-loader" aria-hidden="true"></div>
							<p class="hs-player-message" role="status" aria-live="polite" data-error="<?php esc_attr_e( 'پخش ویدیو ممکن نشد. اتصال اینترنت را بررسی کنید و صفحه را دوباره باز کنید.', 'hodima' ); ?>"></p>

							<div class="hs-center-control hs-flex-center">
								<button type="button" class="hs-play-toggle hs-play-btn hs-flex-center" aria-label="<?php esc_attr_e( 'پخش', 'hodima' ); ?>" data-label-play="<?php esc_attr_e( 'پخش', 'hodima' ); ?>" data-label-pause="<?php esc_attr_e( 'توقف', 'hodima' ); ?>">
									<svg class="hs-play-icon" width="34" height="34" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>
								</button>
							</div>
						</div>

						<div class="hs-bottombar">
							<div class="hs-progress-wrap" tabindex="0" role="slider" aria-valuemin="0" aria-valuemax="0" aria-valuenow="0" aria-valuetext="0:00" aria-label="<?php esc_attr_e( 'نوار پیشرفت ویدئو', 'hodima' ); ?>">
								<div class="hs-progress-item"><div class="hs-progress-fill"></div></div>
							</div>

							<div class="hs-bottom-controls">
								<button type="button" class="hs-control-btn hs-play-pause-small" aria-label="<?php esc_attr_e( 'پخش', 'hodima' ); ?>" data-label-play="<?php esc_attr_e( 'پخش', 'hodima' ); ?>" data-label-pause="<?php esc_attr_e( 'توقف', 'hodima' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path class="hs-play-icon-path" d="M8 5v14l11-7z"/></svg>
								</button>

								<div class="hs-volume-container">
									<button type="button" class="hs-control-btn hs-mute-btn" aria-label="<?php esc_attr_e( 'قطع صدا', 'hodima' ); ?>" data-label-mute="<?php esc_attr_e( 'قطع صدا', 'hodima' ); ?>" data-label-unmute="<?php esc_attr_e( 'وصل صدا', 'hodima' ); ?>">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
									</button>
									<input type="range" class="hs-volume-slider" min="0" max="1" step="0.05" value="1" aria-label="<?php esc_attr_e( 'تنظیم صدا', 'hodima' ); ?>">
								</div>

								<div class="hs-time-display">
									<span class="hs-current-time">0:00</span><span class="hs-time-total"> / <span class="hs-duration">0:00</span></span>
								</div>

								<div class="hs-spacer"></div>

								<button type="button" class="hs-control-btn hs-pip-btn" aria-label="<?php esc_attr_e( 'تصویر در تصویر', 'hodima' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="11" y="11" width="8" height="6"></rect></svg>
								</button>

								<div class="hs-more-options-container">
									<button type="button" class="hs-control-btn hs-speed-btn" aria-label="1x <?php esc_attr_e( 'سرعت پخش', 'hodima' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $hodima_vw_uid ); ?>-speed">1x</button>
									<div class="hs-more-menu hs-speed-menu" id="<?php echo esc_attr( $hodima_vw_uid ); ?>-speed">
										<button type="button" class="hs-menu-option" data-speed="0.5" aria-pressed="false">0.5x</button>
										<button type="button" class="hs-menu-option is-active" data-speed="1" aria-pressed="true">1x (<?php esc_html_e( 'عادی', 'hodima' ); ?>)</button>
										<button type="button" class="hs-menu-option" data-speed="1.5" aria-pressed="false">1.5x</button>
										<button type="button" class="hs-menu-option" data-speed="2" aria-pressed="false">2x</button>
									</div>
								</div>

								<button type="button" class="hs-control-btn hs-fullscreen-btn" aria-label="<?php esc_attr_e( 'تمام‌صفحه', 'hodima' ); ?>" data-label-enter="<?php esc_attr_e( 'تمام‌صفحه', 'hodima' ); ?>" data-label-exit="<?php esc_attr_e( 'خروج از تمام‌صفحه', 'hodima' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
								</button>

								<div class="hs-more-options-container">
									<button type="button" class="hs-control-btn hs-more-options-btn" aria-label="<?php esc_attr_e( 'گزینه‌های بیشتر', 'hodima' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $hodima_vw_uid ); ?>-more">
										<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
									</button>
									<div class="hs-more-menu" id="<?php echo esc_attr( $hodima_vw_uid ); ?>-more">
										<a href="<?php echo esc_url( $hodima_vw_url ); ?>" download class="hs-menu-option" target="_blank" rel="noopener noreferrer">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hs-menu-icon" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
											<?php esc_html_e( 'دانلود ویدئو', 'hodima' ); ?>
										</a>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php endif; ?>

					<div class="hs-video-action-bar">
						<?php // محصول (فقط با لینک)، بازدید، متن (فقط با متن کامل)، اشتراک ?>
						<?php if ( '' !== $hodima_vw_product ) : ?>
						<div class="hs-action-item hs-action-product">
							<a href="<?php echo esc_url( $hodima_vw_product ); ?>" class="hs-btn-primary" title="<?php esc_attr_e( 'مشاهده محصول', 'hodima' ); ?>">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
								<span class="hs-action-label"><?php esc_html_e( 'محصول', 'hodima' ); ?></span>
							</a>
						</div>
						<?php endif; ?>

						<div class="hs-action-item hs-action-stats">
							<div class="hs-view-count" title="<?php esc_attr_e( 'بازدید', 'hodima' ); ?>">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<span class="hs-live-view-count"><?php echo esc_html( $hodima_vw_views ); ?></span>
								<span class="hs-action-label"><?php esc_html_e( 'بازدید', 'hodima' ); ?></span>
							</div>
						</div>

						<?php if ( '' !== $hodima_vw_transcript ) : ?>
						<div class="hs-action-item hs-action-transcript">
							<?php // نام دسترس‌پذیر با کلمه دیدنی «متن» شروع می‌شود (WCAG 2.5.3) ?>
							<button type="button" class="hs-btn-outline hs-transcript-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $hodima_vw_transcript_id ); ?>" aria-label="<?php esc_attr_e( 'متن کامل ویدیو', 'hodima' ); ?>" title="<?php esc_attr_e( 'متن کامل ویدیو', 'hodima' ); ?>">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><polyline points="14 3 14 8 19 8"></polyline><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line></svg>
								<span class="hs-transcript-btn-text"><?php esc_html_e( 'متن', 'hodima' ); ?></span>
							</button>
						</div>
						<?php endif; ?>

						<div class="hs-action-item hs-action-share">
							<button type="button" class="hs-btn-outline hs-share-btn" aria-label="<?php esc_attr_e( 'اشتراک‌گذاری', 'hodima' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $hodima_vw_uid ); ?>-share">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
							</button>

							<div class="hs-action-menu hs-share-menu" id="<?php echo esc_attr( $hodima_vw_uid ); ?>-share">
								<button type="button" class="hs-share-link hs-copy-link" data-label-done="<?php esc_attr_e( 'لینک کپی شد', 'hodima' ); ?>"><?php esc_html_e( 'کپی لینک', 'hodima' ); ?></button>
							</div>
							<span class="hs-action-label hs-share-status" role="status" aria-live="polite"></span>
						</div>
					</div>

					<?php
					/*
					 * باکس متن کامل: باز/بسته با دکمه «متن» (video-watch.front.js).
					 * متن از اول در HTML است (گوگل و اسکیمای VideoObject.transcript)؛
					 * hidden="until-found": جستجوی مرورگر (Ctrl+F) هم بازش می‌کند. قاب و
					 * فاصله روی فرزند است، چون جعبه until-found خودش رسم می‌شود.
					 * تا Media 2.1.4 بخش جدا زیر کادر معرفی با جعبه «نمایش بیشتر» بود.
					 */
					if ( '' !== $hodima_vw_transcript ) :
						?>
					<div class="hs-transcript-panel" id="<?php echo esc_attr( $hodima_vw_transcript_id ); ?>" hidden="until-found">
						<section class="hs-transcript-card" aria-labelledby="<?php echo esc_attr( $hodima_vw_transcript_id ); ?>-title">
							<h2 class="hs-transcript-title" id="<?php echo esc_attr( $hodima_vw_transcript_id ); ?>-title" tabindex="-1"><?php esc_html_e( 'متن کامل ویدیو', 'hodima' ); ?></h2>
							<div class="hs-transcript">
								<?php echo wp_kses_post( wpautop( esc_html( $hodima_vw_transcript ) ) ); ?>
							</div>
							<button type="button" class="hs-transcript-close"><?php esc_html_e( 'بستن متن', 'hodima' ); ?></button>
						</section>
					</div>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( $hodima_vw_cards ) : ?>
				<section class="hodima-section-box section-related-videos">
					<?php // h2: تیتر بعد از h1 صفحه (تا Media 2.1.0 h3 و یک سطح جا افتاده بود) ?>
					<h2 class="hs-related-section-title"><?php esc_html_e( 'ویدئوهای مرتبط', 'hodima' ); ?></h2>
					<div class="hs-related-cards-wrapper">
						<?php
						foreach ( $hodima_vw_cards as $hodima_vw_card ) :
							// کارت ناقص (داده دستی قدیمی) هشدار PHP نمی‌دهد
							$hodima_vw_card_url   = is_array( $hodima_vw_card ) ? (string) ( $hodima_vw_card['url'] ?? '' ) : '';
							$hodima_vw_card_title = is_array( $hodima_vw_card ) ? (string) ( $hodima_vw_card['title'] ?? '' ) : '';
							$hodima_vw_card_cover = is_array( $hodima_vw_card ) ? (string) ( $hodima_vw_card['cover'] ?? '' ) : '';
							if ( '' === $hodima_vw_card_url ) {
								continue;
							}
							?>
							<a href="<?php echo esc_url( $hodima_vw_card_url ); ?>" class="hs-related-card">
								<div class="hs-related-cover-container">
									<?php if ( '' !== $hodima_vw_card_cover ) : ?>
										<img src="<?php echo esc_url( $hodima_vw_card_cover ); ?>" alt="<?php echo esc_attr( $hodima_vw_card_title ); ?>" class="hs-card-cover-img" loading="lazy" decoding="async" width="300" height="300">
									<?php else : ?>
										<div class="hs-related-icon-placeholder">
											<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
										</div>
									<?php endif; ?>
								</div>
								<span class="hs-related-title" title="<?php echo esc_attr( $hodima_vw_card_title ); ?>"><?php echo esc_html( $hodima_vw_card_title ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

		</main>
	<?php
endwhile;

get_footer();
