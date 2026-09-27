<?php
/**
 * Media System — Admin UI
 * Path: media-system/media-admin.php
 * Version: 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نوع پستی که فیلد «متن کامل معرفی» برایش غیرفعال است.
 * منبع واحد برای رندر و ذخیره.
 */
function hook_content_field_is_disabled( string $context, int|string $object_id ): bool {

	if ( 'post' !== $context ) {
		return false;
	}

	if ( 'new' !== $object_id && (int) $object_id > 0 ) {
		return get_post_type( (int) $object_id ) === 'product';
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return ( $screen && 'product' === $screen->post_type );
}

add_action( 'admin_enqueue_scripts', 'hook_enqueue_admin_media_assets' );

function hook_enqueue_admin_media_assets( string $hook ): void {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$is_post = ( 'post' === $screen->base && in_array( $screen->post_type, hook_get_supported_post_types(), true ) );
	$is_term = ( 'term' === $screen->base && in_array( $screen->taxonomy, hook_get_supported_taxonomies(), true ) );

	/*
	 * edit-tags.php (فهرست ترم‌ها و فرم افزودن) حذف شد: فیلدهای رسانه
	 * فقط در فرم *ویرایش* ترم رندر می‌شوند، ولی نسخه قبلی کتابخانه رسانه
	 * وردپرس را روی صفحه فهرست هم بارگذاری می‌کرد.
	 */
	if ( ! $is_post && ! $is_term ) {
		return;
	}

	wp_enqueue_media();

	$url = HODIMA_MEDIA_URL . '/media-system';

	wp_enqueue_style( 'hook-media-admin-css', $url . '/css/media-admin.css', [], hook_media_asset_version( 'css/media-admin.css' ) );
	wp_enqueue_script( 'hook-media-admin-js', $url . '/js/media-admin.js', [ 'jquery' ], hook_media_asset_version( 'js/media-admin.js' ), true );
}

/* =====================================================================
 * فرم
 * ===================================================================== */

function hook_render_media_fields_core( array $data, string $context, int|string $object_id = 0 ): void {

	$unique_id = $object_id ?: 'new';
	$field_id  = esc_attr( (string) $unique_id );

	wp_nonce_field( "hook_media_save_{$context}_{$unique_id}", 'hook_media_nonce' );

	$content_disabled = hook_content_field_is_disabled( $context, $unique_id );

	// موجودیت‌ها هر کدام در یک خط نمایش داده می‌شوند
	$entities_text = implode( "\n", hook_parse_key_entities( $data['key_entities'] ?? '' ) );
	$cover         = (string) ( $data['video_cover'] ?? '' );
	?>
	<div class="hook-admin-box hook-admin-box-primary">
		<label>
			<input type="checkbox" name="hook_enabled" value="yes" <?php checked( $data['enabled'] ?? 'no', 'yes' ); ?>>
			<strong>فعال‌سازی سیستم رسانه (ویدیو، پادکست، سوالات متداول) &mdash; نسخه <?php echo esc_html( HOOK_MEDIA_VERSION ); ?></strong>
		</label>
	</div>

	<?php if ( hook_modern_seo_enabled( $context, $unique_id ) ) : ?>
	<div class="hook-admin-box">
		<p class="hook-section-title">سئو مدرن (AI &amp; Discover)</p>
		<?php // نشانه‌ای که ذخیره فقط وقتی این سه فیلد را بنویسد که واقعا در فرم بوده‌اند ?>
		<input type="hidden" name="hook_modern_seo_present" value="1">

		<div class="hook-field-group">
			<label for="hook_discover_title_<?php echo $field_id; ?>"><strong>عنوان قلاب (Discover Title):</strong></label>
			<input type="text" id="hook_discover_title_<?php echo $field_id; ?>" name="hook_discover_title" value="<?php echo esc_attr( (string) ( $data['discover_title'] ?? '' ) ); ?>" placeholder="عنوانی جذاب و کنجکاوکننده...">
		</div>

		<div class="hook-field-group">
			<label for="hook_key_entities_<?php echo $field_id; ?>"><strong>موجودیت‌های کلیدی (Key Entities):</strong></label>
			<?php
			/*
			 * textarea به جای input تک‌خطی.
			 * چسباندن متن چندخطی در input تک‌خطی، خط‌های جدید را به فاصله
			 * تبدیل می‌کرد و هیچ جداکننده‌ای باقی نمی‌ماند؛ کل متن یک
			 * موجودیت می‌شد (همان «مرتبط با» طولانی در اسکیمای محصول).
			 */
			?>
			<textarea id="hook_key_entities_<?php echo $field_id; ?>" name="hook_key_entities" rows="4" class="hook-textarea" placeholder="هر موجودیت در یک خط، یا با ویرگول (, یا ،) جدا شود&#10;مثلا:&#10;کش مو&#10;واردات از چین"><?php echo esc_textarea( $entities_text ); ?></textarea>
			<p class="description">مفاهیم اصلی صفحه که به موتورهای جستجو و هوش مصنوعی کمک می‌کند موضوع را دقیق درک کنند.</p>
		</div>

		<div class="hook-field-group">
			<label><strong>خلاصه برای هوش مصنوعی (AI TL;DR):</strong></label>
			<?php
			if ( 'new' === $unique_id ) {
				echo '<textarea name="hook_ai_summary" rows="5" class="hook-textarea">' . esc_textarea( (string) ( $data['ai_summary'] ?? '' ) ) . '</textarea>';
			} else {
				wp_editor( (string) ( $data['ai_summary'] ?? '' ), 'hookaisummary', [
					'textarea_name' => 'hook_ai_summary',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'quicktags'     => true,
					'tinymce'       => true,
				] );
			}
			?>
			<p class="description"><strong>شورت‌کد نمایش:</strong> <code>[hook_ai_box title="خلاصه هوش مصنوعی"]</code></p>
		</div>

	</div>
	<?php endif; ?>

	<div class="hook-admin-box">
		<p class="hook-section-title">متن معرفی</p>

		<div class="hook-field-group">
			<div class="hook-label-title"><strong>متن کامل معرفی (HOOK):</strong></div>
			<?php
			if ( $content_disabled ) {
				// مقدار فعلی در فیلد مخفی تا ذخیره محصول آن را پاک نکند
				printf( '<input type="hidden" name="hook_content" value="%s">', esc_attr( (string) ( $data['content'] ?? '' ) ) );
				echo '<div class="hook-notice hook-notice-muted"><em>این قسمت برای صفحه محصول غیرفعال است. مقدار قبلی دست‌نخورده باقی می‌ماند.</em></div>';
			} elseif ( 'new' === $unique_id ) {
				echo '<textarea name="hook_content" rows="6" class="hook-textarea">' . esc_textarea( (string) ( $data['content'] ?? '' ) ) . '</textarea>';
			} else {
				wp_editor( (string) ( $data['content'] ?? '' ), 'hookcontent', [ 'textarea_name' => 'hook_content', 'textarea_rows' => 8 ] );
			}
			?>
		</div>
	</div>

	<div class="hook-admin-box">
		<p class="hook-section-title">تنظیمات ویدیو</p>
		<input type="text" name="hook_video_title" value="<?php echo esc_attr( (string) ( $data['video_title'] ?? '' ) ); ?>" placeholder="عنوان ویدیو">
		<input type="url" name="hook_video_url" value="<?php echo esc_url( (string) ( $data['video_url'] ?? '' ) ); ?>" placeholder="لینک مستقیم فایل (MP4) یا لینک صفحه آپارات / یوتیوب" dir="ltr">
		<input type="text" name="hook_video_duration" value="<?php echo esc_attr( (string) ( $data['video_duration'] ?? '' ) ); ?>" placeholder="مدت زمان — مثلا ۲:۳۵ یا ۱:۰۵:۲۰" class="hook-duration" inputmode="numeric">

		<div class="hook-video-cover-wrap">
			<input type="url" id="hook_video_cover_<?php echo $field_id; ?>" class="hook-video-cover-input" name="hook_video_cover" value="<?php echo esc_url( $cover ); ?>" placeholder="لینک تصویر کاور ویدیو..." dir="ltr">
			<button type="button" class="button hook-upload-video-cover">انتخاب تصویر از گالری</button>
		</div>
		<img class="hook-video-cover-preview" src="<?php echo esc_url( $cover ); ?>" alt="" <?php echo '' === $cover ? 'hidden' : ''; ?>>
		<p class="description">کاور هم در پلیر نمایش داده می‌شود و هم به عنوان تصویر ویدیو به گوگل ارسال می‌شود.</p>
	</div>

	<div class="hook-admin-box">
		<p class="hook-section-title">تنظیمات صوت (پادکست)</p>
		<input type="text" name="hook_voice_title" value="<?php echo esc_attr( (string) ( $data['voice_title'] ?? '' ) ); ?>" placeholder="عنوان پادکست">
		<input type="url" name="hook_voice_url" value="<?php echo esc_url( (string) ( $data['voice_url'] ?? '' ) ); ?>" placeholder="لینک مستقیم فایل صوتی (MP3)" dir="ltr">
		<input type="text" name="hook_voice_duration" value="<?php echo esc_attr( (string) ( $data['voice_duration'] ?? '' ) ); ?>" placeholder="مدت زمان — مثلا ۱۲:۴۰" class="hook-duration" inputmode="numeric">
	</div>

	<div class="hook-admin-box">
		<p class="hook-section-title">سوالات متداول (FAQ)</p>
		<div class="hook-faq-container">
			<?php foreach ( array_values( (array) ( $data['faq'] ?? [] ) ) as $index => $faq ) : ?>
				<div class="hook-faq-item">
					<input type="text" name="hook_faq[<?php echo (int) $index; ?>][q]" value="<?php echo esc_attr( (string) ( $faq['q'] ?? '' ) ); ?>" placeholder="سؤال (پرسش)...">
					<textarea name="hook_faq[<?php echo (int) $index; ?>][a]" placeholder="پاسخ..."><?php echo esc_textarea( (string) ( $faq['a'] ?? '' ) ); ?></textarea>
					<button type="button" class="button-link button-link-delete hook-remove-faq">حذف این سؤال</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button hook-add-faq">افزودن سؤال جدید</button>
	</div>
	<?php
}

/* =====================================================================
 * ثبت در نوشته‌ها و ترم‌ها
 * ===================================================================== */

add_action( 'add_meta_boxes', 'hook_add_post_media_meta_box' );

function hook_add_post_media_meta_box(): void {
	foreach ( hook_get_supported_post_types() as $post_type ) {
		add_meta_box( 'hook_media_box', 'تنظیمات رسانه', 'hook_render_post_media_box', $post_type, 'normal', 'high' );
	}
}

function hook_render_post_media_box( WP_Post $post ): void {
	hook_render_media_fields_core( hook_get_media_data( $post->ID, 'post' ), 'post', $post->ID );
}

add_action( 'save_post', 'hook_save_all_post_types_media_box', 10, 2 );

function hook_save_all_post_types_media_box( int $post_id, WP_Post $post ): void {

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, hook_get_supported_post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	hook_save_media_fields_core( $post_id, 'post' );
}

add_action( 'admin_init', 'hook_register_taxonomy_media_hooks' );

function hook_register_taxonomy_media_hooks(): void {
	foreach ( hook_get_supported_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'hook_render_term_media_fields', 2 );
		add_action( "edited_{$taxonomy}", 'hook_save_term_media_fields' );
		// هوک created_{$taxonomy} حذف شد: فیلدها در فرم *افزودن* رندر نمی‌شوند،
		// پس nonce وجود نداشت و آن کالبک همیشه در خط اول برمی‌گشت.
	}
}

function hook_render_term_media_fields( WP_Term $term ): void {
	?>
	<div id="hook_term_media_box" class="postbox hook-term-postbox">
		<div class="postbox-header"><h2 class="hndle"><span>تنظیمات رسانه دسته‌بندی</span></h2></div>
		<div class="inside hook-term-wrapper">
			<?php hook_render_media_fields_core( hook_get_media_data( $term->term_id, 'term' ), 'term', $term->term_id ); ?>
		</div>
	</div>
	<?php
}

function hook_save_term_media_fields( int $term_id ): void {
	hook_save_media_fields_core( $term_id, 'term' );
}

/* =====================================================================
 * ذخیره
 * ===================================================================== */

function hook_save_media_fields_core( int $object_id, string $context ): void {

	$nonce = isset( $_POST['hook_media_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hook_media_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, "hook_media_save_{$context}_{$object_id}" ) ) {
		return;
	}

	$is_post = ( 'post' === $context );

	if ( ! $is_post ) {
		$term = get_term( $object_id );
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}
		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return;
		}
	}

	$prefix = $is_post ? '_hook_' : 'hook_';
	$get    = $is_post ? 'get_post_meta' : 'get_term_meta';
	$update = $is_post ? 'update_post_meta' : 'update_term_meta';
	$delete = $is_post ? 'delete_post_meta' : 'delete_term_meta';

	// مقادیر فعلی (با فالبک کلید قدیمی) — برای تشخیص تغییر آدرس ویدیو/صوت
	$before = hook_get_media_data( $object_id, $context, true );

	/*
	 * نوشتن هر فیلد.
	 *
	 *   - مقدار خالی حذف می‌شود، نه ذخیره (جلوگیری از ردیف‌های بی‌مصرف).
	 *   - کلید قدیمیِ بدون پیشوند هم حذف می‌شود. نسخه قبلی فقط کلید جدید
	 *     را حذف می‌کرد؛ خواندن به کلید قدیمی برمی‌گشت و فیلدی که مدیر
	 *     عمدا خالی کرده بود، در بارگذاری بعدی دوباره ظاهر می‌شد. داده
	 *     قدیمی را هرگز نمی‌شد پاک کرد.
	 */
	$write = static function ( string $key, $value ) use ( $object_id, $prefix, $get, $update, $delete ): void {

		( '' === $value || [] === $value )
			? $delete( $object_id, $prefix . $key )
			: $update( $object_id, $prefix . $key, $value );

		if ( '' !== $get( $object_id, $key, true ) ) {
			$delete( $object_id, $key );
		}
	};

	$post = static fn( string $key ): string => isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '';

	$write( 'enabled', isset( $_POST['hook_enabled'] ) ? 'yes' : '' );

	/*
	 * این سه فیلد فقط وقتی نوشته می‌شوند که بخش «سئو مدرن» در فرم بوده.
	 * برای محصول و دسته‌بندی بخش نمایش داده نمی‌شود؛ بدون این شرط، ذخیره
	 * بعدی مقدار خالی می‌فرستاد و داده قبلی پاک می‌شد.
	 */
	if ( isset( $_POST['hook_modern_seo_present'] ) ) {
		$write( 'discover_title', sanitize_text_field( $post( 'hook_discover_title' ) ) );
		$write( 'key_entities', implode( ', ', hook_parse_key_entities( sanitize_textarea_field( $post( 'hook_key_entities' ) ) ) ) );
		$write( 'ai_summary', wp_kses_post( $post( 'hook_ai_summary' ) ) );
	}
	$write( 'content', wp_kses_post( $post( 'hook_content' ) ) );

	$video_url = esc_url_raw( trim( $post( 'hook_video_url' ) ), [ 'http', 'https' ] );
	$voice_url = esc_url_raw( trim( $post( 'hook_voice_url' ) ), [ 'http', 'https' ] );

	$write( 'video_title', sanitize_text_field( $post( 'hook_video_title' ) ) );
	$write( 'video_url', $video_url );
	$write( 'video_cover', esc_url_raw( trim( $post( 'hook_video_cover' ) ), [ 'http', 'https' ] ) );
	$write( 'video_duration', hook_sanitize_duration( $post( 'hook_video_duration' ) ) );

	$write( 'voice_title', sanitize_text_field( $post( 'hook_voice_title' ) ) );
	$write( 'voice_url', $voice_url );
	$write( 'voice_duration', hook_sanitize_duration( $post( 'hook_voice_duration' ) ) );

	/*
	 * تاریخ انتشار ویدیو و صوت (uploadDate در اسکیما).
	 *
	 * فرم قبلا این تاریخ را نمی‌نوشت. اسکیما برای نوشته‌ها به تاریخ *آخرین
	 * ویرایش* برمی‌گشت (هر ویرایش متن = ویدیوی «تازه آپلودشده») و برای
	 * دسته‌ها به «همین الان» — یعنی در هر بازدید یک تاریخ جدید. حالا
	 * تاریخ فقط وقتی ثبت می‌شود که آدرس فایل *واقعا* عوض شود.
	 */
	foreach ( [ 'video' => $video_url, 'voice' => $voice_url ] as $kind => $url ) {

		if ( '' === $url ) {
			$write( "{$kind}_date", '' );
		} elseif ( $url !== (string) $before[ "{$kind}_url" ] || '' === (string) $before[ "{$kind}_date" ] ) {
			$write( "{$kind}_date", gmdate( 'c' ) );
		}
	}

	$faq = [];
	foreach ( (array) ( $_POST['hook_faq'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item     = wp_unslash( $item );
		$question = sanitize_text_field( (string) ( $item['q'] ?? '' ) );
		$answer   = wp_kses_post( (string) ( $item['a'] ?? '' ) );
		if ( '' !== $question && '' !== trim( wp_strip_all_tags( $answer ) ) ) {
			$faq[] = [ 'q' => $question, 'a' => $answer ];
		}
	}
	$write( 'faq', $faq );

	hook_get_media_data( $object_id, $context, true );
}

/** «۲:۳۵» → «2:35»؛ مقدار نامعتبر → رشته خالی. */
function hook_sanitize_duration( string $value ): string {
	$value = trim( hook_normalize_digits( $value ) );
	return ( '' !== hook_format_duration_iso( $value ) ) ? $value : '';
}
