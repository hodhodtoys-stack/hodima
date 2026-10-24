<?php
/**
 * کادر «تنظیمات ویدیو» در ویرایش ویدیو (پیشخوان)
 * Path: components/video-watch/meta-boxes.php
 *
 * استاندارد طراحی پیشخوان، نوع «ورود محتوا» (اسکیل hodima-admin-ui؛ HODIMA-AUDIT.md بخش ۲۲۰ و ۲۲۴):
 * نوار وضعیت، سه تب (ویدیو و کاور / زمان و بخش‌ها و متن / زیر ویدیو)، ستون کناری
 * پیش‌نمایش و چک‌لیست آمادگی. اجزا (تب، نوار وضعیت، چیدمان، چک‌لیست، جمع‌شونده،
 * انتخاب‌گر رسانه، لیست تکرارشونده) در video-watch.ui.css/js (کیت محلی این ماژول؛ تصمیم کاربر:
 * فعلا به Core نمی‌رود)؛ کارت و کارت گزینه از Core قدیمی؛ اینجا فقط محتوای ویدیو در video-watch.admin.css و رفتار ویدیو (وضعیت آدرس، «آنچه گوگل
 * می‌بیند»، چک‌لیست، پیش‌نمایش) در video-watch.admin.js. بدون JS همه فیلدها پشت‌سرهم
 * دیده می‌شوند و همان فیلدهای معمولی‌اند.
 *
 * داده: نام و مقدار همه فیلدها همان نسخه‌های قبل است (آزمون رفت‌وبرگشت در
 * video-watch-tests.php). فیلد «آدرس ویدئو» به خواست کاربر همان جعبه متن
 * لینک ماند (بدون انتخاب از کتابخانه رسانه).
 *
 * زیر PHPCS/PHPStan بدون baseline. نام‌های قدیمی hod_video_* (توابع سراسری،
 * کلید متاها) برای سازگاری عوض نشده‌اند.
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- نام‌های قدیمی hod_video_* (کد بیرونی و آزمون‌ها صدایشان می‌زنند)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** بیشترین کارت «ویدئوهای مرتبط» (همان سقف ذخیره). */
const HOD_VIDEO_MAX_RELATED = 99;

function hod_video_watch_register_meta_boxes(): void {
	add_meta_box( 'hod-video-watch-meta', 'تنظیمات ویدیو', 'hod_video_watch_render_meta_box', 'video', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'hod_video_watch_register_meta_boxes' );

/** ISO 8601 → «۲:۳۵» برای نمایش در فرم (ISO اعشاری همان ISO می‌ماند تا ذخیره دوباره اعشار را نبرد). */
function hod_video_iso_to_clock( string $iso ): string {
	if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m ) ) {
		return $iso;
	}
	$h = (int) ( $m[1] ?? 0 );
	$i = (int) ( $m[2] ?? 0 );
	$s = (int) ( $m[3] ?? 0 );
	return $h > 0 ? sprintf( '%d:%02d:%02d', $h, $i, $s ) : sprintf( '%d:%02d', $i, $s );
}

/**
 * «۲:۳۵» یا «PT2M35S» → ISO 8601، یا رشته خالی.
 * نسخه خیلی قدیمی فقط ISO خام را می‌پذیرفت و بقیه را بی‌صدا دور می‌ریخت.
 */
function hod_video_sanitize_duration( string $value ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	// تابع مشترک سیستم رسانه با نام تازه (نام قدیمی hook_format_duration_iso فقط برای کد بیرونی است)
	if ( function_exists( 'hodima_media_duration_iso' ) ) {
		return hodima_media_duration_iso( $value );
	}

	if ( preg_match( '/^PT(?:\d+H)?(?:\d+M)?(?:\d+S)?$/i', $value ) && strlen( $value ) > 2 ) {
		return strtoupper( $value );
	}

	$seconds = function_exists( 'hod_video_time_to_seconds' ) ? hod_video_time_to_seconds( $value ) : null;
	return null === $seconds ? '' : sprintf( 'PT%dH%dM%dS', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
}

/** آیکون Dashicons تزئینی. */
function hod_video_watch_icon( string $name, string $extra_class = '' ): string {
	return sprintf( '<span class="dashicons dashicons-%1$s %2$s" aria-hidden="true"></span>', esc_attr( $name ), esc_attr( $extra_class ) );
}

/** چاپ آیکون (همه بخش‌ها escape شده‌اند). */
function hod_video_watch_e_icon( string $name, string $extra_class = '' ): void {
	echo hod_video_watch_icon( $name, $extra_class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape شده
}

/** آغاز کارت با یک سؤال (`.hd-card` هسته). */
function hod_video_watch_card_open( string $id, string $icon, string $title, string $desc = '', string $extra_class = '' ): void {
	?>
	<section class="hd-card hvw-card <?php echo esc_attr( $extra_class ); ?>" aria-labelledby="hvw-h-<?php echo esc_attr( $id ); ?>">
		<div class="hd-card__head">
			<?php hod_video_watch_e_icon( $icon ); ?>
			<h3 class="hd-card__title" id="hvw-h-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $desc ) : ?>
				<p class="hd-card__desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
	<?php
}

/**
 * یک کارت «ویدئوی مرتبط» (ردیف فشرده `.hd-repeat__row` هسته). $i خالی = الگوی JS («__i__»/«__n__» جایگزین می‌شود).
 * برچسب فیلدهای بدون عنوان دیدنی `screen-reader-text` است (ذخیره همان ترتیب و نام قبلی).
 *
 * @param array{title?: string, url?: string, cover?: string} $link داده کارت.
 */
function hod_video_watch_related_row( array $link, string $i = '__i__', string $n = '__n__' ): void {
	$cover = (string) ( $link['cover'] ?? '' );
	$id    = 'hvw-link-' . $i;
	$name  = 'hod_related_links[' . $i . ']';
	?>
	<div class="hd-repeat__row<?php echo '' === $cover ? ' is-empty' : ''; ?>" data-hd-row data-hd-media>
		<?php // خود بندانگشتی دکمه انتخاب عکس است (ردیف یک‌خطی بماند) ?>
		<button type="button" class="hd-media__preview hd-media__preview--s hd-media__pick" data-hd-media-pick data-hd-label="انتخاب عکس کارت %s" aria-label="انتخاب عکس کارت <?php echo esc_attr( $n ); ?>">
			<?php hod_video_watch_e_icon( 'format-image' ); ?>
			<img<?php echo '' !== $cover ? ' src="' . esc_url( $cover ) . '"' : ' hidden'; ?> alt="" data-hd-media-img>
		</button>
		<div class="hd-repeat__fields">
			<div class="hd-field">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-title">عنوان کارت <span data-hd-n><?php echo esc_html( $n ); ?></span></label>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-title" name="<?php echo esc_attr( $name ); ?>[title]" placeholder="عنوان کارت" value="<?php echo esc_attr( (string) ( $link['title'] ?? '' ) ); ?>" data-hd-row-title>
			</div>
			<div class="hd-field">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-url">لینک کارت <span data-hd-n><?php echo esc_html( $n ); ?></span></label>
				<input type="url" id="<?php echo esc_attr( $id ); ?>-url" name="<?php echo esc_attr( $name ); ?>[url]" placeholder="لینک" dir="ltr" value="<?php echo esc_attr( (string) ( $link['url'] ?? '' ) ); ?>">
			</div>
		</div>
		<div class="hd-repeat__actions">
			<button type="button" class="button hd-icon-btn" data-hd-move="-1" data-hd-label="کارت %s یکی بالاتر" aria-label="کارت <?php echo esc_attr( $n ); ?> یکی بالاتر"><?php hod_video_watch_e_icon( 'arrow-up-alt2' ); ?></button>
			<button type="button" class="button hd-icon-btn" data-hd-move="1" data-hd-label="کارت %s یکی پایین‌تر" aria-label="کارت <?php echo esc_attr( $n ); ?> یکی پایین‌تر"><?php hod_video_watch_e_icon( 'arrow-down-alt2' ); ?></button>
			<button type="button" class="button hd-icon-btn hd-icon-btn--danger" data-hd-remove data-hd-label="حذف کارت %s" aria-label="حذف کارت <?php echo esc_attr( $n ); ?>"><?php hod_video_watch_e_icon( 'trash' ); ?></button>
		</div>
		<details class="hvw-row__more">
			<summary>آدرس عکس کارت</summary>
			<div class="hd-field">
				<label class="hd-field__label" for="<?php echo esc_attr( $id ); ?>-cover">آدرس عکس کارت <span data-hd-n><?php echo esc_html( $n ); ?></span></label>
				<input type="url" id="<?php echo esc_attr( $id ); ?>-cover" name="<?php echo esc_attr( $name ); ?>[cover]" dir="ltr" value="<?php echo esc_attr( $cover ); ?>" data-hd-media-input>
			</div>
		</details>
	</div>
	<?php
}

/** یک ردیف چک‌لیست آمادگی (حالت، برچسب و توضیح را video-watch.admin.js می‌نویسد). */
function hod_video_watch_check_item( string $key, string $label ): void {
	?>
	<li class="hd-checklist__item" data-hvw-check="<?php echo esc_attr( $key ); ?>" data-state="opt">
		<span class="hd-checklist__icon" aria-hidden="true"></span>
		<span class="hd-checklist__label"><?php echo esc_html( $label ); ?></span>
		<span class="hd-checklist__tag"></span>
		<p class="hd-checklist__note"></p>
	</li>
	<?php
}

function hod_video_watch_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'hod_video_watch_save_meta', 'hod_video_watch_meta_nonce' );

	$video_url           = (string) get_post_meta( $post->ID, '_hod_video_url', true );
	$video_thumbnail     = (string) get_post_meta( $post->ID, '_hod_video_thumbnail', true );
	$video_duration      = hod_video_iso_to_clock( (string) get_post_meta( $post->ID, '_hod_video_duration', true ) );
	$chapters            = (string) get_post_meta( $post->ID, '_hod_video_chapters', true );
	$transcript          = (string) get_post_meta( $post->ID, '_hod_video_transcript', true );
	$related_product_url = (string) get_post_meta( $post->ID, '_hod_related_product_url', true );
	$related_links       = get_post_meta( $post->ID, '_hod_related_links', true );
	$related_links       = is_array( $related_links ) ? array_values( array_filter( $related_links, 'is_array' ) ) : [];
	$preview_title       = '' !== trim( $post->post_title ) ? $post->post_title : 'عنوان ویدیو';
	?>
	<div class="hd-wrap hd-metabox hvw" data-hvw>

		<?php // نوار وضعیت: یک جمله + نشان (متن را video-watch.admin.js می‌سازد؛ بدون JS پنهان) ?>
		<header class="hd-statusbar">
			<?php hod_video_watch_e_icon( 'format-video', 'hd-statusbar__icon' ); ?>
			<div class="hd-statusbar__body">
				<h3 class="hd-statusbar__title">وضعیت ویدیو</h3>
				<p class="hd-statusbar__text" data-hvw-summary aria-live="polite" hidden></p>
			</div>
			<div class="hd-statusbar__aside" hidden data-hvw-pill-wrap>
				<span class="hd-pill" data-hvw-pill></span>
			</div>
		</header>

		<div class="hd-tabset" data-hd-tabset>
			<div class="hd-tablist" role="tablist" aria-label="بخش‌های تنظیمات ویدیو" hidden>
				<button type="button" class="hd-tab" role="tab" id="hvw-tab-video" aria-controls="hvw-panel-video" aria-selected="true">ویدیو و کاور</button>
				<button type="button" class="hd-tab" role="tab" id="hvw-tab-time" aria-controls="hvw-panel-time" aria-selected="false">زمان، بخش‌ها و متن</button>
				<button type="button" class="hd-tab" role="tab" id="hvw-tab-below" aria-controls="hvw-panel-below" aria-selected="false">زیر ویدیو <span class="hd-tab__count" data-hvw-tab-count hidden></span></button>
			</div>

			<div class="hd-layout">
				<div class="hd-layout__grid">

					<?php // پیش‌نمایش: آنچه در سایت دیده می‌شود (بدون JS پنهان) ?>
					<section class="hd-side hd-layout__preview hvw-pvcard" aria-labelledby="hvw-pv-title" data-hvw-pv hidden>
						<h3 class="hd-side__title" id="hvw-pv-title">همان‌طور که در سایت دیده می‌شود</h3>
						<div class="hvw-pv__thumb">
							<?php hod_video_watch_e_icon( 'format-image', 'hvw-pv__ph' ); ?>
							<img alt="" hidden data-hvw-pv-img>
							<span class="hvw-pv__play" aria-hidden="true"><?php hod_video_watch_e_icon( 'controls-play' ); ?></span>
							<span class="hvw-pv__dur" data-hvw-pv-dur hidden></span>
						</div>
						<div class="hvw-pv__info">
							<p class="hvw-pv__title"><?php echo esc_html( $preview_title ); ?></p>
							<div class="hvw-pv__chips" data-hvw-pv-chips></div>
							<span class="hvw-pv__btn" data-hvw-pv-product hidden><?php hod_video_watch_e_icon( 'cart' ); ?> مشاهده محصول</span>
						</div>
					</section>

					<div class="hd-layout__main">

						<div class="hd-tabpanel" role="tabpanel" id="hvw-panel-video" aria-labelledby="hvw-tab-video">
							<?php hod_video_watch_card_open( 'video', 'video-alt3', 'کدام ویدیو پخش شود؟', 'لینک فایل ویدیو را مثل همیشه همین‌جا بگذارید.' ); ?>
								<div class="hd-field">
									<label class="hd-field__label" for="hod_video_url">آدرس ویدئو (فرمت MP4)</label>
									<input type="url" id="hod_video_url" name="hod_video_url" dir="ltr" value="<?php echo esc_attr( $video_url ); ?>" aria-describedby="hvw-url-status">
									<p class="hd-field__status" id="hvw-url-status" data-hvw-url-status>بدون آدرس، به‌جای پلیر «ویدیو هنوز آماده نیست» نمایش داده می‌شود.</p>
								</div>
							</section>

							<?php hod_video_watch_card_open( 'cover', 'format-image', 'پیش از پخش چه عکسی دیده شود؟', 'بدون کاور، گوگل ویدیو را در نتایج ویدیویی نشان نمی‌دهد.' ); ?>
								<div class="hd-media<?php echo '' === $video_thumbnail ? ' is-empty' : ''; ?>" data-hd-media>
									<div class="hd-media__preview" aria-hidden="true">
										<?php hod_video_watch_e_icon( 'format-image' ); ?>
										<img<?php echo '' !== $video_thumbnail ? ' src="' . esc_url( $video_thumbnail ) . '"' : ' hidden'; ?> alt="" data-hd-media-img>
									</div>
									<div class="hd-media__side">
										<div class="hd-media__actions">
											<button type="button" class="button button-primary" data-hd-media-pick><?php hod_video_watch_e_icon( 'upload' ); ?> انتخاب از کتابخانه رسانه</button>
											<button type="button" class="button hd-button--danger" data-hd-media-clear>برداشتن کاور</button>
										</div>
										<div class="hd-field">
											<label class="hd-field__label" for="hod_video_thumbnail">یا آدرس عکس</label>
											<input type="url" id="hod_video_thumbnail" name="hod_video_thumbnail" dir="ltr" value="<?php echo esc_attr( $video_thumbnail ); ?>" data-hd-media-input>
										</div>
									</div>
								</div>
							</section>
						</div>

						<div class="hd-tabpanel" role="tabpanel" id="hvw-panel-time" aria-labelledby="hvw-tab-time">
							<?php hod_video_watch_card_open( 'time', 'clock', 'چقدر طول می‌کشد و چه بخش‌هایی دارد؟', 'گوگل بخش‌ها را «لحظه‌های کلیدی» زیر ویدیو نشان می‌دهد و هر کدام از همان ثانیه پخش می‌شود.' ); ?>
								<div class="hvw-time">
									<div class="hvw-time__fields">
										<div class="hd-field">
											<label class="hd-field__label" for="hod_video_duration">مدت ویدیو</label>
											<div class="hvw-unit">
												<input type="text" id="hod_video_duration" name="hod_video_duration" dir="ltr" inputmode="numeric" value="<?php echo esc_attr( $video_duration ); ?>" placeholder="2:35" aria-describedby="hvw-duration-help">
												<span class="hvw-unit__label" aria-hidden="true">دقیقه:ثانیه</span>
											</div>
											<p class="hd-field__help" id="hvw-duration-help">مثلا ۲:۳۵ یا ۱:۰۵:۲۰ (ساعت:دقیقه:ثانیه).</p>
										</div>
										<div class="hd-field">
											<label class="hd-field__label" for="hod_video_chapters">بخش‌ها (هر خط: زمان و عنوان)</label>
											<textarea id="hod_video_chapters" name="hod_video_chapters" rows="5" placeholder="0:00 مقدمه&#10;1:25 معرفی رنگ‌بندی&#10;3:10 نحوه سفارش عمده"><?php echo esc_textarea( $chapters ); ?></textarea>
										</div>
									</div>
									<div class="hvw-chapters" data-hvw-chapters hidden>
										<span class="hvw-chapters__label">آنچه گوگل می‌بیند</span>
										<ol class="hvw-chapters__list" data-hvw-chapters-list></ol>
										<p class="hd-field__status" data-hvw-chapters-status aria-live="polite"></p>
									</div>
								</div>
							</section>

							<details class="hd-more"<?php echo '' !== trim( $transcript ) ? ' open' : ''; ?>>
								<summary class="hd-more__summary">
									<?php hod_video_watch_e_icon( 'text-page' ); ?>
									متن کامل ویدیو
									<span class="hd-more__hint">اختیاری<span data-hvw-transcript-count></span></span>
								</summary>
								<div class="hd-more__body">
									<p class="hd-field__help">زیر پلیر در جعبه «نمایش بیشتر / کمتر» دیده می‌شود و برای گوگل هم فرستاده می‌شود.</p>
									<div class="hd-field">
										<label class="hd-field__label" for="hod_video_transcript">متن گفته‌شده در ویدیو</label>
										<textarea id="hod_video_transcript" name="hod_video_transcript" rows="7"><?php echo esc_textarea( $transcript ); ?></textarea>
									</div>
								</div>
							</details>
						</div>

						<div class="hd-tabpanel" role="tabpanel" id="hvw-panel-below" aria-labelledby="hvw-tab-below">
							<?php hod_video_watch_card_open( 'below', 'cart', 'زیر ویدیو چه چیزی پیشنهاد شود؟', 'دکمه خرید و کارت‌های «ویدئوهای مرتبط».' ); ?>
								<div class="hd-opts">
									<?php // کلید بدون name: فیلد لینک را خالی/پر می‌کند (admin-ui.js هسته)؛ ذخیره همان لینک است ?>
									<div class="hd-opt">
										<div class="hd-opt__head">
											<?php hod_video_watch_e_icon( 'cart', 'hd-opt__icon' ); ?>
											<label class="hd-opt__title" for="hvw-product-switch">دکمه «مشاهده محصول» زیر پلیر</label>
											<input type="checkbox" class="hd-switch hd-opt__switch" id="hvw-product-switch" role="switch" data-hd-opt-switch <?php checked( '' !== $related_product_url ); ?>>
										</div>
										<div class="hd-opt__body">
											<div class="hd-field">
												<label class="hd-field__label" for="hod_related_product_url">لینک محصول</label>
												<input type="url" id="hod_related_product_url" name="hod_related_product_url" dir="ltr" value="<?php echo esc_attr( $related_product_url ); ?>" data-hd-opt-value data-hd-opt-off="">
											</div>
										</div>
									</div>
								</div>

								<div class="hd-repeat" data-hd-repeat data-hd-max="<?php echo (int) HOD_VIDEO_MAX_RELATED; ?>" data-hd-name="hod_related_links" data-hd-id="hvw-link" data-hd-item="کارت" data-hd-count-text="%s کارت">
									<div class="hd-repeat__head" id="hvw-related-title">کارت‌های «ویدئوهای مرتبط» <span class="hd-pill" data-hd-count><?php echo esc_html( number_format_i18n( count( $related_links ) ) ); ?> کارت</span></div>
									<p class="hd-field__help">عنوان و لینک لازم است؛ کارت بدون این دو ذخیره نمی‌شود. ترتیب همین ترتیب سایت است.</p>
									<div class="hd-repeat__rows" data-hd-rows aria-labelledby="hvw-related-title" role="group">
										<?php
										foreach ( $related_links as $index => $link ) {
											hod_video_watch_related_row( $link, (string) $index, (string) number_format_i18n( $index + 1 ) );
										}
										?>
									</div>
									<p class="hd-repeat__empty" data-hd-empty<?php echo $related_links ? ' hidden' : ''; ?>>هنوز کارتی اضافه نشده؛ بخش «ویدئوهای مرتبط» زیر ویدیو نمایش داده نمی‌شود.</p>
									<div class="hd-repeat__undo" data-hd-undo aria-live="polite"></div>
									<button type="button" class="button hd-repeat__add" id="hvw-add-link-btn" data-hd-add><?php hod_video_watch_e_icon( 'plus-alt2' ); ?> افزودن کارت</button>
									<template data-hd-row-template><?php hod_video_watch_related_row( [] ); ?></template>
								</div>
							</section>
						</div>

					</div>

					<?php // چک‌لیست آمادگی (بدون JS پنهان) ?>
					<section class="hd-side hd-layout__aside" aria-labelledby="hvw-ck-title" data-hvw-ck hidden>
						<h3 class="hd-side__title" id="hvw-ck-title">آمادگی ویدیو</h3>
						<ul class="hd-checklist">
							<?php
							hod_video_watch_check_item( 'url', 'آدرس ویدیو' );
							hod_video_watch_check_item( 'cover', 'کاور' );
							hod_video_watch_check_item( 'duration', 'مدت ویدیو' );
							hod_video_watch_check_item( 'chapters', 'بخش‌ها' );
							hod_video_watch_check_item( 'transcript', 'متن کامل' );
							hod_video_watch_check_item( 'product', 'دکمه محصول' );
							hod_video_watch_check_item( 'related', 'ویدیوهای مرتبط' );
							?>
						</ul>
					</section>

				</div>
			</div>
		</div>
	</div>
	<?php
}

function hod_video_watch_save_meta_boxes( int $post_id ): void {
	if ( ! isset( $_POST['hod_video_watch_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hod_video_watch_meta_nonce'] ) ), 'hod_video_watch_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// بازنگری‌ها شناسه جدا دارند؛ نسخه قبلی متا را روی آن‌ها هم می‌نوشت
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'video' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر فیلد پایین جدا پاک‌سازی می‌شود
	$post = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';

	$fields = [
		'_hod_video_url'           => esc_url_raw( trim( $post( 'hod_video_url' ) ), [ 'http', 'https' ] ),
		'_hod_video_thumbnail'     => esc_url_raw( trim( $post( 'hod_video_thumbnail' ) ), [ 'http', 'https' ] ),
		'_hod_video_duration'      => hod_video_sanitize_duration( $post( 'hod_video_duration' ) ),
		'_hod_video_chapters'      => sanitize_textarea_field( $post( 'hod_video_chapters' ) ),
		'_hod_video_transcript'    => sanitize_textarea_field( $post( 'hod_video_transcript' ) ),
		'_hod_related_product_url' => esc_url_raw( trim( $post( 'hod_related_product_url' ) ), [ 'http', 'https' ] ),
	];

	foreach ( $fields as $meta_key => $meta_value ) {
		if ( '' === $meta_value || '0' === $meta_value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}

	// صدای انگلیسی و عربی حذف شد؛ داده باقی‌مانده این ویدئو پاک شود
	delete_post_meta( $post_id, '_hod_audio_url_en' );
	delete_post_meta( $post_id, '_hod_audio_url_ar' );

	$links = [];
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر بخش کارت پایین جدا پاک‌سازی می‌شود
	$raw = isset( $_POST['hod_related_links'] ) && is_array( $_POST['hod_related_links'] ) ? wp_unslash( $_POST['hod_related_links'] ) : [];
	foreach ( (array) $raw as $link ) {
		if ( ! is_array( $link ) || count( $links ) >= HOD_VIDEO_MAX_RELATED ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $link['title'] ?? '' ) );
		$url   = esc_url_raw( trim( (string) ( $link['url'] ?? '' ) ), [ 'http', 'https' ] );
		if ( '' !== $title && '' !== $url ) {
			$links[] = [
				'title' => $title,
				'url'   => $url,
				'cover' => esc_url_raw( trim( (string) ( $link['cover'] ?? '' ) ), [ 'http', 'https' ] ),
			];
		}
	}

	if ( empty( $links ) ) {
		delete_post_meta( $post_id, '_hod_related_links' );
	} else {
		update_post_meta( $post_id, '_hod_related_links', $links );
	}

	if ( class_exists( 'Hodima_Video_Watch_Analytics' ) ) {
		Hodima_Video_Watch_Analytics::forget_views( $post_id );
	}
}
add_action( 'save_post', 'hod_video_watch_save_meta_boxes' );

/**
 * پاکسازی یک‌باره داده صدای انگلیسی و عربی از همه ویدئوها.
 */
add_action(
	'admin_init',
	static function (): void {
		if ( get_option( 'hod_video_audio_meta_purged' ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_hod_audio_url_en', '_hod_audio_url_ar')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- پاک‌سازی یک‌باره
		update_option( 'hod_video_audio_meta_purged', 1, false );
	}
);
