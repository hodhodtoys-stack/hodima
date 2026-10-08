<?php
/**
 * Media System — کادر «تنظیمات رسانه» در ویرایش نوشته، محصول، برگه و دسته
 * Path: media-system/media-admin.php
 *
 * نسخه ۴ (بازطراحی):
 *   - هر فیلد برچسب واقعی دارد (قبلا فقط متن راهنمای داخل کادر که با تایپ محو می‌شد).
 *   - انتخاب ویدیو، صوت و کاور از کتابخانه رسانه؛ مدت و
 *     نسبت تصویر از خود فایل خوانده می‌شود.
 *   - بررسی زنده: سرویس لینک ویدیو (آپارات/یوتیوب/فایل)، قالب مدت، فصل‌ها.
 *     مدت نامعتبر دیگر بی‌صدا پاک نمی‌شود: مقدار قبلی می‌ماند و پیام داده می‌شود.
 *   - جابه‌جایی ترتیب سوالات FAQ؛ حذف بدون پنجره confirm مرورگر.
 *   - بخش Discover از نسخه 1.5.0 کادر جدای ماژول «Google Discover» افزونه سئو است.
 *   - جاوااسکریپت خالص (بدون jQuery)؛ CSS فقط زیر .hodima-mb (بدون :root).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** «متن معرفی» برای محصول غیرفعال است (توضیحات خود محصول کافی است). */
function hodima_media_content_disabled( string $context, int $object_id ): bool {

	if ( 'post' !== $context ) {
		return false;
	}

	if ( $object_id > 0 ) {
		return 'product' === get_post_type( $object_id );
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return $screen && 'product' === $screen->post_type;
}

add_action( 'admin_enqueue_scripts', 'hodima_media_admin_assets' );

function hodima_media_admin_assets(): void {

	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$is_post = 'post' === $screen->base && in_array( $screen->post_type, hodima_media_post_types(), true );
	$is_term = 'term' === $screen->base && in_array( $screen->taxonomy, hodima_media_taxonomies(), true );

	// فقط فرم ویرایش؛ نه فهرست ترم‌ها (فیلدها آنجا رندر نمی‌شوند)
	if ( ! $is_post && ! $is_term ) {
		return;
	}

	wp_enqueue_media();

	$url = HODIMA_MEDIA_URL . '/media-system';

	wp_enqueue_style( 'hodima-media-admin', $url . '/css/media-admin.css', [ 'dashicons' ], hodima_media_asset_version( 'css/media-admin.css' ) );
	wp_enqueue_script( 'hodima-media-admin', $url . '/js/media-admin.js', [], hodima_media_asset_version( 'js/media-admin.js' ), [
		'in_footer' => true,
		'strategy'  => 'defer',
	] );

	wp_add_inline_script( 'hodima-media-admin', 'window.hodimaMediaAdmin = ' . wp_json_encode( [
		'ratios' => array_values( array_diff( array_keys( HODIMA_MEDIA_RATIOS ), [ 'auto' ] ) ),
	], JSON_UNESCAPED_UNICODE ) . ';', 'before' );
}

/* =====================================================================
 * فرم
 * ===================================================================== */

/** یک فیلد آدرس + دکمه انتخاب از کتابخانه رسانه (+ شناسه پیوست اختیاری). */
function hodima_media_field_picker( string $key, string $label, string $value, string $type, string $help = '', int $attachment_id = -1, string $placeholder = '' ): void {
	$id = 'hodima-mb-' . str_replace( '_', '-', $key );
	?>
	<div class="hodima-mb__field" data-hodima-picker="<?php echo esc_attr( $type ); ?>">
		<label class="hodima-mb__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<div class="hodima-mb__row">
			<input type="url" id="<?php echo esc_attr( $id ); ?>" name="hodima_media[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_url( $value ); ?>" dir="ltr" class="hodima-mb__grow" data-hodima-url<?php echo '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>>
			<?php if ( $attachment_id >= 0 ) : ?>
				<input type="hidden" name="hodima_media[<?php echo esc_attr( $key ); ?>_id]" value="<?php echo (int) $attachment_id ?: ''; ?>" data-hodima-id>
			<?php endif; ?>
			<button type="button" class="button" data-hodima-pick><span class="dashicons dashicons-admin-media" aria-hidden="true"></span> انتخاب از کتابخانه</button>
			<button type="button" class="button-link hodima-mb__clear" data-hodima-clear <?php echo '' === $value ? 'hidden' : ''; ?>>حذف</button>
		</div>
		<?php if ( 'image' === $type ) : ?>
			<img class="hodima-mb__preview" src="<?php echo esc_url( $value ); ?>" alt="" data-hodima-preview <?php echo '' === $value ? 'hidden' : ''; ?>>
		<?php endif; ?>
		<p class="hodima-mb__status" data-hodima-status aria-live="polite"></p>
		<?php if ( '' !== $help ) : ?>
			<p class="hodima-mb__help"><?php echo wp_kses( $help, [ 'code' => [], 'strong' => [] ] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** فیلد متنی ساده با برچسب. */
function hodima_media_field_text( string $key, string $label, string $value, string $help = '', array $attrs = [] ): void {
	$id   = 'hodima-mb-' . str_replace( '_', '-', $key );
	$html = '';
	foreach ( $attrs as $name => $attr ) {
		$html .= sprintf( ' %s="%s"', esc_attr( (string) $name ), esc_attr( (string) $attr ) );
	}
	?>
	<div class="hodima-mb__field">
		<label class="hodima-mb__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<input type="text" id="<?php echo esc_attr( $id ); ?>" name="hodima_media[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"<?php echo $html; // phpcs:ignore -- بالا escape شده ?>>
		<p class="hodima-mb__status" data-hodima-status aria-live="polite"></p>
		<?php if ( '' !== $help ) : ?>
			<p class="hodima-mb__help"><?php echo wp_kses( $help, [ 'code' => [], 'strong' => [] ] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** فیلد چندخطی با برچسب. */
function hodima_media_field_textarea( string $key, string $label, string $value, string $help = '', array $attrs = [] ): void {
	$id   = 'hodima-mb-' . str_replace( '_', '-', $key );
	$html = '';
	foreach ( $attrs + [ 'rows' => 4 ] as $name => $attr ) {
		$html .= sprintf( ' %s="%s"', esc_attr( (string) $name ), esc_attr( (string) $attr ) );
	}
	?>
	<div class="hodima-mb__field">
		<label class="hodima-mb__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="hodima_media[<?php echo esc_attr( $key ); ?>]"<?php echo $html; // phpcs:ignore -- بالا escape شده ?>><?php echo esc_textarea( $value ); ?></textarea>
		<p class="hodima-mb__status" data-hodima-status aria-live="polite"></p>
		<?php if ( '' !== $help ) : ?>
			<p class="hodima-mb__help"><?php echo wp_kses( $help, [ 'code' => [], 'strong' => [] ] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** سرتیتر یک بخش تاشو، با برچسب وضعیت («تنظیم شده»). */
function hodima_media_section_open( string $key, string $icon, string $title, bool $filled, bool $open ): void {
	printf(
		'<details class="hodima-mb__section" data-hodima-section="%1$s"%2$s><summary class="hodima-mb__summary"><span class="dashicons %3$s" aria-hidden="true"></span><span class="hodima-mb__title">%4$s</span>%5$s</summary><div class="hodima-mb__body">',
		esc_attr( $key ),
		$open ? ' open' : '',
		esc_attr( $icon ),
		esc_html( $title ),
		$filled ? '<span class="hodima-mb__badge">تنظیم شده</span>' : ''
	);
}

function hodima_media_section_close(): void {
	echo '</div></details>';
}

/** یک ردیف FAQ (در PHP و قالب <template> برای ردیف تازه). */
function hodima_media_faq_row( int|string $index, string $question, string $answer ): string {
	return sprintf(
		'<li class="hodima-mb__faq" data-hodima-faq>
			<div class="hodima-mb__faq-fields">
				<label class="screen-reader-text" for="hodima-mb-faq-q-%1$s">سوال</label>
				<input type="text" id="hodima-mb-faq-q-%1$s" name="hodima_media[faq][%1$s][q]" value="%2$s" placeholder="سوال">
				<label class="screen-reader-text" for="hodima-mb-faq-a-%1$s">پاسخ</label>
				<textarea id="hodima-mb-faq-a-%1$s" name="hodima_media[faq][%1$s][a]" rows="3" placeholder="پاسخ">%3$s</textarea>
			</div>
			<div class="hodima-mb__faq-tools">
				<button type="button" class="button-link" data-hodima-move="-1" aria-label="انتقال به بالا"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="button-link" data-hodima-move="1" aria-label="انتقال به پایین"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<button type="button" class="button-link hodima-mb__delete" data-hodima-remove aria-label="حذف این سوال"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			</div>
		</li>',
		esc_attr( (string) $index ),
		esc_attr( $question ),
		esc_textarea( $answer )
	);
}

/**
 * فهرست بررسی (ok / warn / error).
 *
 * @param list<array{key: string, status: string, label: string, detail: string}> $checks
 */
function hodima_media_render_checks( array $checks ): void {

	if ( ! $checks ) {
		return;
	}

	$icons = [ 'ok' => 'dashicons-yes-alt', 'warn' => 'dashicons-warning', 'error' => 'dashicons-dismiss' ];

	echo '<ul class="hodima-mb__checks">';
	foreach ( $checks as $check ) {
		printf(
			'<li class="is-%1$s"><span class="dashicons %2$s" aria-hidden="true"></span><strong>%3$s:</strong> <span>%4$s</span></li>',
			esc_attr( $check['status'] ),
			esc_attr( $icons[ $check['status'] ] ),
			esc_html( $check['label'] ),
			esc_html( $check['detail'] )
		);
	}
	echo '</ul>';
}

function hodima_media_render_fields( array $data, string $context, int $object_id ): void {

	$context = hodima_media_context( $context );

	$video_set = '' !== (string) ( $data['video_url'] ?? '' );
	$voice_set = '' !== (string) ( $data['voice_url'] ?? '' );
	$faq       = array_values( (array) ( $data['faq'] ?? [] ) );
	$cover_id  = (int) ( $data['video_cover_id'] ?? 0 );
	$cover     = (string) ( $data['video_cover'] ?? '' );
	?>
	<div class="hodima-mb" data-hodima-mb>
		<?php wp_nonce_field( "hodima_media_save_{$context}_{$object_id}", 'hodima_media_nonce' ); ?>

		<div class="hodima-mb__head">
			<label class="hodima-mb__toggle">
				<input type="checkbox" class="hodima-mb__switch" role="switch" name="hodima_media[enabled]" value="yes" <?php checked( $data['enabled'] ?? '', 'yes' ); ?>>
				<span>
					<strong>نمایش ویدیو، پادکست، سوالات متداول و متن معرفی</strong>
					<small>خاموش: هیچ‌کدام در سایت نمایش داده نمی‌شوند و به گوگل اعلام نمی‌شوند. اطلاعات پایین پاک نمی‌شود.</small>
				</span>
			</label>
		</div>

		<?php hodima_media_section_open( 'intro', 'dashicons-text-page', 'متن معرفی', '' !== trim( (string) ( $data['content'] ?? '' ) ), false ); ?>
			<?php if ( hodima_media_content_disabled( $context, $object_id ) ) : ?>
				<input type="hidden" name="hodima_media[content_locked]" value="1">
				<p class="hodima-mb__intro">برای محصول غیرفعال است (توضیحات خود محصول نمایش داده می‌شود). مقدار قبلی دست‌نخورده می‌ماند.</p>
			<?php else : ?>
				<div class="hodima-mb__field">
					<span class="hodima-mb__label">متن کامل معرفی <code>[hook_intro]</code></span>
					<?php wp_editor( (string) ( $data['content'] ?? '' ), 'hookcontent', [ 'textarea_name' => 'hodima_media[content]', 'textarea_rows' => 8 ] ); ?>
				</div>
			<?php endif; ?>
		<?php hodima_media_section_close(); ?>

		<?php hodima_media_section_open( 'video', 'dashicons-video-alt3', 'ویدیو', $video_set, $video_set ); ?>
			<?php if ( $video_set && $object_id > 0 ) : ?>
				<?php hodima_media_render_checks( hodima_media_video_checks( $object_id, $context ) ); ?>
				<p class="hodima-mb__help">گوگل ویدیو را در نتایج ویدیویی بیشتر وقتی نشان می‌دهد که موضوع اصلی صفحه باشد (مثل صفحه هر ویدیو)؛ در محصول و مقاله، اسکیما و کاور خوب شانس را بالا می‌برند.</p>
			<?php endif; ?>
			<?php
			hodima_media_field_picker(
				'video_url',
				'لینک ویدیو',
				(string) ( $data['video_url'] ?? '' ),
				'video',
				'لینک صفحه آپارات یا یوتیوب (مثل <code>aparat.com/v/…</code>)، یا فایل MP4 از کتابخانه رسانه.',
				-1,
				'https://www.aparat.com/v/…'
			);
			hodima_media_field_text( 'video_title', 'عنوان ویدیو', (string) ( $data['video_title'] ?? '' ), 'بالای پلیر و در نتایج ویدیویی گوگل. خالی = «ویدیوی معرفی: عنوان صفحه» (برای آپارات، یوتیوب و ویمئو هنگام ذخیره از خود ویدیو پر می‌شود).' );
			hodima_media_field_textarea(
				'video_description',
				'توضیح ویدیو (اختیاری)',
				(string) ( $data['video_description'] ?? '' ),
				'یکی دو جمله درباره همین ویدیو (نه کل صفحه) برای نتایج ویدیویی گوگل. خالی = خلاصه صفحه.',
				[ 'rows' => 2, 'maxlength' => 500 ]
			);
			?>
			<div class="hodima-mb__grid">
				<div class="hodima-mb__field">
					<label class="hodima-mb__label" for="hodima-mb-video-ratio">نسبت تصویر</label>
					<select id="hodima-mb-video-ratio" name="hodima_media[video_ratio]" data-hodima-ratio>
						<?php foreach ( HODIMA_MEDIA_RATIOS as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $data['video_ratio'] ?? 'auto', $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="hodima-mb__help">خودکار: شورتز یوتیوب عمودی، فایل آپلودشده از ابعاد واقعی، بقیه افقی.</p>
				</div>
				<?php
				hodima_media_field_text( 'video_duration', 'مدت', (string) ( $data['video_duration'] ?? '' ), 'مثلا <code>2:35</code> یا <code>1:05:20</code>. برای فایل کتابخانه خودکار پر می‌شود.', [ 'dir' => 'ltr', 'inputmode' => 'numeric', 'placeholder' => '2:35', 'data-hodima-duration' => '' ] );
				?>
			</div>
			<?php
			hodima_media_field_picker(
				'video_cover',
				'کاور ویدیو',
				$cover,
				'image',
				'قبل از پخش نمایش داده می‌شود و تصویر ویدیو در گوگل است. خالی = تصویر شاخص.',
				$cover_id
			);
			hodima_media_field_textarea(
				'video_chapters',
				'فصل‌های ویدیو (اختیاری)',
				(string) ( $data['video_chapters'] ?? '' ),
				'هر خط: زمان و عنوان. زیر پلیر دکمه پرش ساخته می‌شود و گوگل «لحظه‌های کلیدی» را در نتایج نشان می‌دهد.',
				[ 'rows' => 4, 'dir' => 'auto', 'placeholder' => "0:00 معرفی\n0:45 رنگ‌بندی\n1:30 نحوه استفاده", 'data-hodima-chapters' => '' ]
			);
			hodima_media_field_textarea(
				'video_transcript',
				'متن کامل ویدیو (اختیاری)',
				(string) ( $data['video_transcript'] ?? '' ),
				'زیر پلیر به صورت بسته («متن کامل ویدیو») نمایش داده می‌شود و در اسکیما (transcript) به گوگل می‌رسد.',
				[ 'rows' => 4 ]
			);
			?>
		<?php hodima_media_section_close(); ?>

		<?php hodima_media_section_open( 'voice', 'dashicons-microphone', 'پادکست (صوت)', $voice_set, $voice_set ); ?>
			<?php
			hodima_media_field_picker( 'voice_url', 'لینک فایل صوتی', (string) ( $data['voice_url'] ?? '' ), 'audio', 'فایل MP3 یا M4A؛ از کتابخانه رسانه یا لینک مستقیم.', -1, 'https://…/podcast.mp3' );
			?>
			<div class="hodima-mb__grid">
				<?php
				hodima_media_field_text( 'voice_title', 'عنوان پادکست', (string) ( $data['voice_title'] ?? '' ) );
				hodima_media_field_text( 'voice_duration', 'مدت', (string) ( $data['voice_duration'] ?? '' ), 'مثلا <code>12:40</code>. برای فایل کتابخانه خودکار پر می‌شود.', [ 'dir' => 'ltr', 'inputmode' => 'numeric', 'placeholder' => '12:40', 'data-hodima-duration' => '' ] );
				?>
			</div>
			<?php
			hodima_media_field_textarea( 'voice_transcript', 'متن کامل پادکست (اختیاری)', (string) ( $data['voice_transcript'] ?? '' ), 'زیر پلیر به صورت بسته نمایش داده می‌شود و در اسکیما به گوگل می‌رسد.', [ 'rows' => 4 ] );
			?>
		<?php hodima_media_section_close(); ?>

		<?php hodima_media_section_open( 'faq', 'dashicons-editor-help', 'سوالات متداول (FAQ)', (bool) $faq, (bool) $faq ); ?>
			<ol class="hodima-mb__faqs" data-hodima-faqs>
				<?php
				foreach ( $faq as $index => $item ) {
					echo hodima_media_faq_row( (int) $index, (string) ( $item['q'] ?? '' ), (string) ( $item['a'] ?? '' ) ); // phpcs:ignore -- داخل تابع escape شده
				}
				?>
			</ol>
			<template data-hodima-faq-template><?php echo hodima_media_faq_row( '__i__', '', '' ); // phpcs:ignore ?></template>
			<button type="button" class="button" data-hodima-faq-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن سوال</button>
			<p class="hodima-mb__help">سوال بدون پاسخ ذخیره نمی‌شود. شورت‌کد: <code>[hook_faq]</code></p>
		<?php hodima_media_section_close(); ?>
	</div>
	<?php
}

/* =====================================================================
 * ثبت در نوشته‌ها و ترم‌ها
 * ===================================================================== */

add_action( 'add_meta_boxes', static function (): void {
	foreach ( hodima_media_post_types() as $post_type ) {
		add_meta_box( 'hook_media_box', 'تنظیمات رسانه', static function ( WP_Post $post ): void {
			hodima_media_render_fields( hodima_media_get_data( $post->ID, 'post' ), 'post', $post->ID );
		}, $post_type, 'normal', 'high' );
	}
} );

add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, hodima_media_post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	hodima_media_save_fields( $post_id, 'post' );
}, 10, 2 );

add_action( 'admin_init', static function (): void {
	foreach ( hodima_media_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'hodima_media_render_term_box', 2 );
		add_action( "edited_{$taxonomy}", static fn( int $term_id ) => hodima_media_save_fields( $term_id, 'term' ) );
	}
} );

function hodima_media_render_term_box( WP_Term $term ): void {
	?>
	<div id="hook_term_media_box" class="postbox hodima-mb-postbox">
		<div class="postbox-header"><h2 class="hndle">تنظیمات رسانه دسته‌بندی</h2></div>
		<div class="inside">
			<?php if ( ! hodima_media_is_displayed( $term->term_id, 'term' ) ) : ?>
				<p class="hodima-mb__intro"><span class="dashicons dashicons-info" aria-hidden="true"></span> قالب فعلی بخش‌های رسانه را در صفحه این نوع دسته نمایش نمی‌دهد؛ برای همین به گوگل هم اعلام نمی‌شوند. اطلاعات ذخیره‌شده پاک نمی‌شود.</p>
			<?php endif; ?>
			<?php hodima_media_render_fields( hodima_media_get_data( $term->term_id, 'term' ), 'term', $term->term_id ); ?>
		</div>
	</div>
	<?php
}

/* =====================================================================
 * ذخیره
 * ===================================================================== */

/**
 * اطلاعات فایل کتابخانه رسانه از روی آدرس: [ مدت (ثانیه), عرض, ارتفاع ].
 *
 * @return array{0: int, 1: int, 2: int}
 */
function hodima_media_attachment_info( string $url ): array {

	$id   = '' !== $url ? (int) attachment_url_to_postid( $url ) : 0;
	$meta = $id ? wp_get_attachment_metadata( $id ) : false;

	return is_array( $meta )
		? [ (int) ( $meta['length'] ?? 0 ), (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ) ]
		: [ 0, 0, 0 ];
}

function hodima_media_save_fields( int $object_id, string $context ): void {

	$context = hodima_media_context( $context );
	$nonce   = isset( $_POST['hodima_media_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_media_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, "hodima_media_save_{$context}_{$object_id}" ) ) {
		return;
	}

	if ( 'term' === $context ) {
		$term     = get_term( $object_id );
		$taxonomy = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return;
		}
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- هر فیلد پایین جداگانه پاک‌سازی می‌شود
	$in     = isset( $_POST['hodima_media'] ) && is_array( $_POST['hodima_media'] ) ? wp_unslash( $_POST['hodima_media'] ) : [];
	$get    = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : '';
	$prefix = hodima_media_meta_prefix( $context );
	$before = hodima_media_get_data( $object_id, $context, true );
	$errors = [];

	/*
	 * نوشتن هر فیلد: مقدار خالی حذف می‌شود. هیچ کلید بی‌پیشوندی حذف
	 * نمی‌شود (باگ قبلی: داده افزونه‌های دیگر با همان نام عمومی پاک می‌شد).
	 */
	$write = static function ( string $key, mixed $value ) use ( $object_id, $context, $prefix ): void {
		( '' === $value || [] === $value || 0 === $value )
			? delete_metadata( $context, $object_id, $prefix . $key )
			: update_metadata( $context, $object_id, $prefix . $key, $value );
	};

	// همیشه yes/no (نه حذف): نشانه اینکه این شیء با نسخه ۴ ذخیره شده و
	// کلیدهای بی‌پیشوند نسخه خیلی قدیمی دیگر خوانده نشوند.
	update_metadata( $context, $object_id, $prefix . 'enabled', 'yes' === $get( 'enabled' ) ? 'yes' : 'no' );

	if ( '1' !== $get( 'content_locked' ) && array_key_exists( 'content', $in ) ) {
		$write( 'content', wp_kses_post( $get( 'content' ) ) );
	}

	/* ── ویدیو ── */
	$video_url = esc_url_raw( $get( 'video_url' ), [ 'http', 'https' ] );
	[ $v_length, $v_width, $v_height ] = hodima_media_attachment_info( $video_url );

	$write( 'video_url', $video_url );
	$write( 'video_title', sanitize_text_field( $get( 'video_title' ) ) );
	$write( 'video_description', sanitize_textarea_field( $get( 'video_description' ) ) );

	$ratio = $get( 'video_ratio' );
	$ratio = isset( HODIMA_MEDIA_RATIOS[ $ratio ] ) ? $ratio : 'auto';
	if ( 'auto' === $ratio && $v_width > 0 ) {
		$ratio = hodima_media_nearest_ratio( $v_width, $v_height ); // نسبت واقعی فایل آپلودشده
	}
	$write( 'video_ratio', 'auto' === $ratio ? '' : $ratio );

	$cover_id  = absint( $get( 'video_cover_id' ) );
	$cover_url = esc_url_raw( $get( 'video_cover' ), [ 'http', 'https' ] );
	if ( $cover_id && ! wp_attachment_is_image( $cover_id ) ) {
		$cover_id = 0;
	}
	if ( ! $cover_id && '' !== $cover_url ) {
		$cover_id = (int) attachment_url_to_postid( $cover_url );
	}
	if ( '' === $cover_url ) {
		$cover_id = 0;
	}
	$write( 'video_cover', $cover_url );
	$write( 'video_cover_id', $cover_id );

	$write( 'video_chapters', sanitize_textarea_field( $get( 'video_chapters' ) ) );
	$write( 'video_transcript', sanitize_textarea_field( $get( 'video_transcript' ) ) );

	/* ── صوت ── */
	$voice_url = esc_url_raw( $get( 'voice_url' ), [ 'http', 'https' ] );
	[ $a_length ] = hodima_media_attachment_info( $voice_url );

	$write( 'voice_url', $voice_url );
	$write( 'voice_title', sanitize_text_field( $get( 'voice_title' ) ) );
	$write( 'voice_transcript', sanitize_textarea_field( $get( 'voice_transcript' ) ) );

	/*
	 * مدت: خالی + فایل کتابخانه = از اطلاعات فایل. نامعتبر («۵ دقیقه») دیگر
	 * بی‌صدا پاک نمی‌شود: مقدار قبلی می‌ماند و پیام داده می‌شود.
	 */
	foreach ( [ 'video' => [ $video_url, $v_length, 'ویدیو' ], 'voice' => [ $voice_url, $a_length, 'پادکست' ] ] as $kind => [ $url, $length, $label ] ) {
		$raw   = $get( "{$kind}_duration" );
		$clean = hodima_media_sanitize_duration( $raw );

		if ( '' !== $raw && '' === $clean ) {
			$errors[] = sprintf( 'مدت %1$s («%2$s») قابل فهم نبود؛ مقدار قبلی نگه داشته شد. قالب درست: 2:35 یا 1:05:20.', $label, $raw );
			continue;
		}
		if ( '' === $clean && '' !== $url && $length > 0 ) {
			$clean = hodima_media_clock( $length );
		}
		$write( "{$kind}_duration", '' === $url ? '' : $clean );
	}

	/*
	 * آپارات، یوتیوب، ویمئو: عنوان و مدت خالی از خود ویدیو؛ کاور فقط برای
	 * لینک تازه و وقتی کاوری انتخاب نشده (تصویر واقعی ویدیو در کتابخانه سایت،
	 * به‌جای تصویر شاخص صفحه). فیلدی که مدیر پر کرده دست نمی‌خورد.
	 */
	$video_changed = '' !== $video_url && $video_url !== (string) ( $before['video_url'] ?? '' );
	$need          = [
		'title'    => '' === $get( 'video_title' ),
		'duration' => '' === (string) get_metadata( $context, $object_id, $prefix . 'video_duration', true ),
		'cover'    => $video_changed && '' === $cover_url,
	];

	if ( '' !== $video_url && in_array( hodima_media_parse_video_url( $video_url )['provider'], [ 'aparat', 'youtube', 'vimeo' ], true ) && in_array( true, $need, true ) ) {

		$info   = hodima_media_fetch_video_info( $video_url );
		$filled = [];

		if ( null !== $info ) {
			if ( $need['title'] && '' !== $info['title'] ) {
				$write( 'video_title', $info['title'] );
				$filled[] = 'عنوان';
			}
			if ( $need['duration'] && $info['seconds'] > 0 ) {
				$write( 'video_duration', hodima_media_clock( $info['seconds'] ) );
				$filled[] = 'مدت';
			}
			if ( $need['cover'] && '' !== $info['thumbnail'] ) {
				$cover_new = hodima_media_sideload_cover( $info['thumbnail'], 'post' === $context ? $object_id : 0, $info['title'] );
				if ( $cover_new ) {
					$write( 'video_cover_id', $cover_new );
					$write( 'video_cover', (string) wp_get_attachment_url( $cover_new ) );
					$filled[] = 'کاور';
				}
			}
		}

		if ( $filled && function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( 'از خود ویدیو پر شد: ' . implode( '، ', $filled ) . '. در کادر «تنظیمات رسانه» می‌توانید عوضش کنید.', 'success' );
		}
	}

	/*
	 * تاریخ انتشار ویدیو و صوت (uploadDate): فقط وقتی آدرس فایل *واقعا*
	 * عوض شود ثبت می‌شود؛ ویرایش متن، ویدیو را «تازه» نمی‌کند.
	 */
	foreach ( [ 'video' => $video_url, 'voice' => $voice_url ] as $kind => $url ) {
		if ( '' === $url ) {
			$write( "{$kind}_date", '' );
		} elseif ( $url !== (string) ( $before[ "{$kind}_url" ] ?? '' ) || '' === (string) ( $before[ "{$kind}_date" ] ?? '' ) ) {
			$write( "{$kind}_date", gmdate( 'c' ) );
		}
	}

	/* ── سوالات متداول (به ترتیب فرم) ── */
	$faq = [];
	foreach ( (array) ( $in['faq'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$question = sanitize_text_field( (string) ( $item['q'] ?? '' ) );
		$answer   = wp_kses_post( (string) ( $item['a'] ?? '' ) );
		if ( '' !== $question && '' !== trim( wp_strip_all_tags( $answer ) ) ) {
			$faq[] = [ 'q' => $question, 'a' => $answer ];
		}
	}
	$write( 'faq', $faq );

	hodima_media_get_data( $object_id, $context, true );

	// پیام بعد از بارگذاری دوباره (ویرایشگر کلاسیک و دسته؛ ویرایشگر بلوکی همان پیام را زنده زیر فیلد نشان می‌دهد)
	if ( $errors && function_exists( 'hodima_admin_flash' ) ) {
		foreach ( $errors as $error ) {
			hodima_admin_flash( $error, 'warning' );
		}
	}
}
