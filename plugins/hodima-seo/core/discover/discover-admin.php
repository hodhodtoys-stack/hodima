<?php
/**
 * ماژول «Google Discover» — کادر ویرایش نوشته و برگه
 * Path: core/discover/discover-admin.php
 *
 * قبلا بخشی از کادر «تنظیمات رسانه» (Hodima Media) بود؛ حالا کادر جدای
 * «Google Discover» که فقط با همین ماژول دیده می‌شود. نام فیلدها
 * hodima_discover[…]، nonce جدا؛ کلیدهای متا همان قبلی.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', static function (): void {

	$screen = get_current_screen();

	if ( ! $screen || 'post' !== $screen->base || ! hodima_seo_discover_enabled( 'post' ) ) {
		return;
	}

	wp_enqueue_media();

	$url = HODIMA_SEO_URL . '/core/discover/assets';
	// نسخه دارایی = زمان تغییر فایل (به‌روزرسانی از کش مرورگر پنهان نماند)
	$ver = static function ( string $file ): string {
		$path = __DIR__ . '/assets/' . $file;
		return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_SEO_VERSION;
	};

	wp_enqueue_style( 'hodima-discover-admin', $url . '/discover-admin.css', [ 'dashicons' ], $ver( 'discover-admin.css' ) );
	wp_enqueue_script( 'hodima-discover-admin', $url . '/discover-admin.js', [], $ver( 'discover-admin.js' ), [
		'in_footer' => true,
		'strategy'  => 'defer',
	] );

	wp_add_inline_script( 'hodima-discover-admin', 'window.hodimaDiscover = ' . wp_json_encode( [
		'minWidth'  => HODIMA_SEO_DISCOVER_MIN_WIDTH,
		'clickbait' => HODIMA_SEO_DISCOVER_CLICKBAIT,
	], JSON_UNESCAPED_UNICODE ) . ';', 'before' );
} );

add_action( 'add_meta_boxes', static function ( string $post_type ): void {

	if ( ! in_array( $post_type, (array) apply_filters( 'hook_modern_seo_post_types', [ 'post', 'page' ] ), true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- نام فیلتر قبلی سیستم رسانه
		return;
	}

	add_meta_box( 'hodima_discover_box', 'Google Discover', 'hodima_seo_discover_render_box', $post_type, 'normal', 'high' );
} );

/** فهرست بررسی آمادگی. */
function hodima_seo_discover_render_checks( WP_Post $post ): void {

	$icons = [ 'ok' => 'dashicons-yes-alt', 'warn' => 'dashicons-warning', 'error' => 'dashicons-dismiss' ];

	echo '<ul class="hodima-dc__checks" data-hodima-dc-checks>';
	foreach ( hodima_seo_discover_checks( $post ) as $check ) {
		printf(
			'<li class="is-%1$s" data-hodima-dc-check="%2$s"><span class="dashicons %3$s" aria-hidden="true"></span><strong>%4$s:</strong> <span data-hodima-dc-check-text>%5$s</span></li>',
			esc_attr( $check['status'] ),
			esc_attr( $check['key'] ),
			esc_attr( $icons[ $check['status'] ] ),
			esc_html( $check['label'] ),
			esc_html( $check['detail'] )
		);
	}
	echo '</ul>';
}

function hodima_seo_discover_render_box( WP_Post $post ): void {

	if ( ! hodima_seo_discover_for_post( $post->ID ) ) {
		echo '<p>Discover برای این نوع محتوا فعال نیست.</p>';
		return;
	}

	$data      = hodima_seo_discover_data( $post->ID );
	$image_id  = $data['image_id'];
	$image_src = $image_id ? wp_get_attachment_image_src( $image_id, 'medium' ) : false;
	?>
	<div class="hodima-dc" data-hodima-dc>
		<?php wp_nonce_field( 'hodima_discover_save_' . $post->ID, 'hodima_discover_nonce' ); ?>
		<input type="hidden" name="hodima_discover[present]" value="1">

		<p class="hodima-dc__intro">Discover فید پیشنهادی گوگل در موبایل است و ورودی زیادی برای مقاله‌ها می‌آورد. فهرست زیر آمادگی همین نوشته را نشان می‌دهد.</p>

		<?php $stats = 'publish' === $post->post_status ? hodima_seo_discover_post_stats( $post->ID ) : null; ?>
		<?php if ( null !== $stats ) : ?>
			<p class="hodima-dc__stats"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> در Discover (۲۸ روز، Search Console): <strong><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></strong> کلیک از <strong><?php echo esc_html( number_format_i18n( $stats['impressions'] ) ); ?></strong> نمایش</p>
		<?php endif; ?>

		<?php hodima_seo_discover_render_checks( $post ); ?>

		<div class="hodima-dc__field">
			<label class="hodima-dc__label" for="hodima-dc-title">عنوان Discover (اختیاری)</label>
			<input type="text" id="hodima-dc-title" name="hodima_discover[title]" value="<?php echo esc_attr( $data['title'] ); ?>" maxlength="200" data-hodima-dc-title data-hodima-dc-fallback="<?php echo esc_attr( $post->post_title ); ?>">
			<p class="hodima-dc__status" data-hodima-dc-status aria-live="polite"></p>
			<p class="hodima-dc__help">فقط در کارت Discover و اشتراک‌گذاری (og:title) استفاده می‌شود؛ عنوان صفحه و h1 عوض نمی‌شوند. جذاب ولی صادق: اغراق و «طعمه کلیک» در Discover جریمه دارد. خالی = عنوان نوشته.</p>
		</div>

		<div class="hodima-dc__field" data-hodima-dc-image>
			<span class="hodima-dc__label" id="hodima-dc-image-label">تصویر Discover (اختیاری)</span>
			<input type="hidden" name="hodima_discover[image_id]" value="<?php echo $image_id ? (int) $image_id : ''; ?>" data-hodima-dc-image-id>
			<div class="hodima-dc__row">
				<button type="button" class="button" data-hodima-dc-pick aria-describedby="hodima-dc-image-label"><span class="dashicons dashicons-format-image" aria-hidden="true"></span> انتخاب تصویر</button>
				<button type="button" class="button-link hodima-dc__clear" data-hodima-dc-clear <?php echo $image_id ? '' : 'hidden'; ?>>حذف (تصویر شاخص استفاده شود)</button>
			</div>
			<img class="hodima-dc__preview" src="<?php echo esc_url( is_array( $image_src ) ? (string) $image_src[0] : '' ); ?>" alt="" data-hodima-dc-preview <?php echo is_array( $image_src ) ? '' : 'hidden'; ?>>
			<p class="hodima-dc__status" data-hodima-dc-status aria-live="polite"></p>
			<p class="hodima-dc__help">حداقل <strong>۱۲۰۰ پیکسل عرض</strong>، ترجیحا افقی ۱۶:۹؛ بدون لوگو و بدون متن زیاد روی تصویر. خالی = تصویر شاخص. سه برش ۱۶:۹، ۴:۳ و ۱:۱ هنگام ذخیره خودکار ساخته می‌شود.</p>
		</div>

		<div class="hodima-dc__field">
			<label class="hodima-dc__label" for="hodima-dc-entities">موضوعات اصلی (اختیاری)</label>
			<textarea id="hodima-dc-entities" name="hodima_discover[entities]" rows="3" dir="auto" placeholder="<?php echo esc_attr( "کش مو\nکلیپس https://www.wikidata.org/wiki/Q…" ); ?>"><?php echo esc_textarea( hodima_seo_discover_format_entities( $data['entity_items'], "\n" ) ); ?></textarea>
			<p class="hodima-dc__help">هر موضوع در یک خط (یا جدا با ویرگول). به گوگل کمک می‌کند بداند نوشته درباره چیست و به علاقه‌مندان همان موضوع در Discover نشانش دهد. برای دقت بیشتر، بعد از نام آدرس صفحه آن در <strong>ویکی‌داده</strong> یا ویکی‌پدیا (یا فقط شناسه ویکی‌داده مثل <code>Q1234</code>) را بنویسید تا گوگل موضوع را بی‌ابهام بشناسد.</p>
		</div>
	</div>
	<?php
}

add_action( 'save_post', static function ( int $post_id ): void {

	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$nonce = isset( $_POST['hodima_discover_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_discover_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_discover_save_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! hodima_seo_discover_for_post( $post_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- هر فیلد پایین جداگانه پاک‌سازی می‌شود
	$in  = isset( $_POST['hodima_discover'] ) && is_array( $_POST['hodima_discover'] ) ? wp_unslash( $_POST['hodima_discover'] ) : [];
	$get = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : '';

	// نبودن فیلدها = دست نزدن به داده (مثلا فرمی که کادر را نداشت)
	if ( '1' !== $get( 'present' ) ) {
		return;
	}

	$image_id = absint( $get( 'image_id' ) );

	$values = [
		HODIMA_SEO_DISCOVER_META['title']    => sanitize_text_field( $get( 'title' ) ),
		HODIMA_SEO_DISCOVER_META['entities'] => hodima_seo_discover_format_entities( hodima_seo_discover_entity_items( sanitize_textarea_field( $get( 'entities' ) ) ) ),
		HODIMA_SEO_DISCOVER_META['image_id'] => $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0,
	];

	foreach ( $values as $key => $value ) {
		( '' === $value || 0 === $value )
			? delete_post_meta( $post_id, $key )
			: update_post_meta( $post_id, $key, $value );
	}
}, 10 );

/* =====================================================================
 * نمایه کاربر: «نویسنده در گوگل» (E-E-A-T)
 * ===================================================================== */

/** فیلدهای معرفی نویسنده در «کاربران ← نمایه». */
function hodima_seo_discover_profile_fields( WP_User $user ): void {

	$author = hodima_seo_discover_author( (int) $user->ID );
	wp_nonce_field( 'hodima_discover_author_' . $user->ID, 'hodima_discover_author_nonce' );
	?>
	<h2>نویسنده در گوگل (Google Discover)</h2>
	<p class="description">گوگل برای نمایش مقاله در Discover و نتایج جستجو به «چه کسی نوشته و چرا قابل اعتماد است» وزن می‌دهد. این اطلاعات در اسکیمای نویسنده (Person) همه مقاله‌هایش می‌رود. بیوگرافی همان «اطلاعات زندگی‌نامه» بالاست.</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="hodima-author-job">سمت و تخصص</label></th>
			<td>
				<input type="text" class="regular-text" id="hodima-author-job" name="hodima_author[job_title]" value="<?php echo esc_attr( $author['job_title'] ); ?>" placeholder="کارشناس اکسسوری مو و فروش عمده">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="hodima-author-knows">حوزه‌های تخصص</label></th>
			<td>
				<textarea class="large-text" rows="3" id="hodima-author-knows" name="hodima_author[knows_about]" placeholder="<?php echo esc_attr( "اکسسوری مو\nواردات و پخش عمده" ); ?>"><?php echo esc_textarea( implode( "\n", $author['knows_about'] ) ); ?></textarea>
				<p class="description">هر مورد در یک خط.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="hodima-author-sameas">پروفایل‌های معتبر</label></th>
			<td>
				<textarea class="large-text" rows="3" id="hodima-author-sameas" name="hodima_author[same_as]" dir="ltr" placeholder="https://www.instagram.com/…"><?php echo esc_textarea( implode( "\n", $author['same_as'] ) ); ?></textarea>
				<p class="description">هر آدرس در یک خط: اینستاگرام، لینکدین، آپارات، صفحه ویکی‌پدیا، مصاحبه‌ها. فقط صفحه‌هایی که واقعا مال همین شخص است.</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'show_user_profile', 'hodima_seo_discover_profile_fields' );
add_action( 'edit_user_profile', 'hodima_seo_discover_profile_fields' );

/** ذخیره فیلدهای معرفی نویسنده. */
function hodima_seo_discover_profile_save( int $user_id ): void {

	$nonce = isset( $_POST['hodima_discover_author_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_discover_author_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_discover_author_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- هر فیلد پایین جداگانه پاک‌سازی می‌شود
	$in  = isset( $_POST['hodima_author'] ) && is_array( $_POST['hodima_author'] ) ? wp_unslash( $_POST['hodima_author'] ) : [];
	$get = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? (string) $in[ $key ] : '';

	$lines = static fn( string $raw ): array => array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ?: [] ) ) );

	$values = [
		HODIMA_SEO_DISCOVER_AUTHOR_META['job_title']   => sanitize_text_field( $get( 'job_title' ) ),
		HODIMA_SEO_DISCOVER_AUTHOR_META['knows_about'] => implode( "\n", array_map( 'sanitize_text_field', $lines( $get( 'knows_about' ) ) ) ),
		HODIMA_SEO_DISCOVER_AUTHOR_META['same_as']     => implode( "\n", array_filter( array_map( 'hodima_seo_discover_clean_url', $lines( $get( 'same_as' ) ) ) ) ),
	];

	foreach ( $values as $key => $value ) {
		'' === $value ? delete_user_meta( $user_id, $key ) : update_user_meta( $user_id, $key, $value );
	}
}

add_action( 'personal_options_update', 'hodima_seo_discover_profile_save' );
add_action( 'edit_user_profile_update', 'hodima_seo_discover_profile_save' );
