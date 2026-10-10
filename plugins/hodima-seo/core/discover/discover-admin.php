<?php
/**
 * ماژول «Google Discover» — کادر ویرایش نوشته، برگه، محصول و دسته محصول
 * Path: core/discover/discover-admin.php
 *
 * چیدمان (SEO 1.17.0؛ نسخه قبلی راهنما را بالای فیلدها و همه را زیر هم داشت):
 *   ۱. فیلدها: عنوان با شمارنده، کارت تصویر، موضوعات به شکل برچسب — و کنارش
 *      پیش‌نمایش کارت Discover در گوشی (با هر تغییر زنده عوض می‌شود).
 *   ۲. زیر فیلدها: «آمادگی برای Discover» با امتیاز و نوار پیشرفت؛ مشکل‌ها
 *      اول و پررنگ، موارد درست کم‌رنگ؛ هر مشکل لینک رفعش را دارد.
 * از SEO 2.1.2: پیشنهاد عنوان (از داده واقعی صفحه)، آمار با مقایسه ۲۸ روز
 * قبل، و به‌روز شدن زنده فهرست با تغییر تصویر شاخص، عنوان و چکیده در
 * ویرایشگر بلوکی و کلاسیک (discover-admin.js).
 * نام فیلدها hodima_discover[…]، nonce جدا؛ کلیدهای متا همان قبلی.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', static function (): void {

	$screen = get_current_screen();

	$is_post = $screen && 'post' === $screen->base && hodima_seo_discover_enabled( 'post' );
	$is_term = $screen && 'term' === $screen->base && hodima_seo_discover_enabled( 'term' );

	if ( ! $is_post && ! $is_term ) {
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
		'titleMin'  => HODIMA_SEO_DISCOVER_TITLE_MIN,
		'titleMax'  => HODIMA_SEO_DISCOVER_TITLE_MAX,
		'clickbait' => hodima_seo_discover_clickbait_phrases(),
	], JSON_UNESCAPED_UNICODE ) . ';', 'before' );
} );

add_action( 'add_meta_boxes', static function ( string $post_type ): void {

	if ( ! in_array( $post_type, hodima_seo_discover_post_types(), true ) ) {
		return;
	}

	add_meta_box( 'hodima_discover_box', 'Google Discover', 'hodima_seo_discover_render_box', $post_type, 'normal', 'high' );
} );

// دسته محصول: کادر در فرم ویرایش دسته (بعد از کادر رسانه) و ذخیره
add_action( 'admin_init', static function (): void {
	foreach ( hodima_seo_discover_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'hodima_seo_discover_render_term_box', 5 );
		add_action( "edited_{$taxonomy}", static fn( int $term_id ) => hodima_seo_discover_save( $term_id, 'term' ) );
	}
} );

/** کادر Discover نوشته/برگه/محصول. */
function hodima_seo_discover_render_box( WP_Post $post ): void {

	if ( ! hodima_seo_discover_for_post( $post->ID ) ) {
		echo '<p>Discover برای این نوع محتوا فعال نیست.</p>';
		return;
	}

	hodima_seo_discover_render_fields( $post );
}

/** کادر Discover دسته محصول (فرم ویرایش ترم، بیرون از جعبه‌های meta-box). */
function hodima_seo_discover_render_term_box( WP_Term $term ): void {

	if ( ! hodima_seo_discover_for_term( (int) $term->term_id ) ) {
		return;
	}
	?>
	<div id="hodima_discover_term_box" class="postbox hodima-dc-postbox">
		<div class="postbox-header"><h2 class="hndle">Google Discover</h2></div>
		<div class="inside"><?php hodima_seo_discover_render_fields( $term ); ?></div>
	</div>
	<?php
}

/** آدرس تصویر پیش‌نمایش کارت Discover (اندازه متوسط)، یا رشته خالی. */
function hodima_seo_discover_preview_image( int $id, string $context ): string {
	$image = hodima_seo_discover_image( $id, $context );
	if ( null === $image ) {
		return '';
	}
	$src = wp_get_attachment_image_src( $image['id'], 'medium_large' );
	return is_array( $src ) ? (string) $src[0] : $image['url'];
}

/** فیلدها + پیش‌نمایش + آمادگی. */
function hodima_seo_discover_render_fields( WP_Post|WP_Term $target ): void {

	$context   = $target instanceof WP_Term ? 'term' : 'post';
	$id        = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;
	$data      = hodima_seo_discover_data( $id, $context );
	$fallback  = hodima_seo_discover_object_title( $id, $context );
	$own_id    = $data['image_id'];
	$own_src   = $own_id ? wp_get_attachment_image_src( $own_id, 'medium_large' ) : false;
	$preview   = hodima_seo_discover_preview_image( $id, $context );
	$def_id    = hodima_seo_discover_default_image_id( $id, $context );
	$def_src   = $def_id ? wp_get_attachment_image_src( $def_id, 'medium_large' ) : false;
	$default   = is_array( $def_src ) ? (string) $def_src[0] : ''; // پیش‌نمایش وقتی تصویر Discover حذف شود
	$checks    = hodima_seo_discover_checks( $target );
	$stats     = ( $target instanceof WP_Term || 'publish' === $target->post_status ) ? hodima_seo_discover_post_stats( $id, $context ) : null;
	$own_label = 'term' === $context ? 'تصویر دسته' : ( 'product' === $target->post_type ? 'تصویر محصول' : 'تصویر شاخص' );
	$host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$icon      = (string) get_site_icon_url( 64 );
	$length    = mb_strlen( '' !== $data['title'] ? $data['title'] : $fallback );
	$ideas     = hodima_seo_discover_title_ideas( $target );
	$meta_desc = 'post' === $context && '' !== trim( (string) get_post_meta( $id, '_seobox_description', true ) );
	$change    = null !== $stats ? hodima_seo_discover_change( $stats['impressions'], $stats['prev_impressions'] ) : null;
	?>
	<div class="hodima-dc" data-hodima-dc data-context="<?php echo esc_attr( $context ); ?>" data-has-meta-desc="<?php echo $meta_desc ? '1' : '0'; ?>">
		<?php wp_nonce_field( 'hodima_discover_save_' . $context . '_' . $id, 'hodima_discover_nonce' ); ?>
		<input type="hidden" name="hodima_discover[present]" value="1">

		<?php if ( null !== $stats ) : ?>
			<p class="hodima-dc__stats"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> در Discover (۲۸ روز، Search Console): <strong><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></strong> کلیک از <strong><?php echo esc_html( number_format_i18n( $stats['impressions'] ) ); ?></strong> نمایش<?php if ( null !== $change ) : ?> <span class="hodima-dc__change"><?php echo esc_html( '(نمایش ' . hodima_seo_discover_change_text( $change ) . ' نسبت به ۲۸ روز قبل)' ); ?></span><?php elseif ( 0 === $stats['prev_impressions'] && $stats['impressions'] > 0 ) : ?> <span class="hodima-dc__change">(۲۸ روز قبل نمایش نداشت)</span><?php endif; ?></p>
		<?php endif; ?>

		<div class="hodima-dc__layout" data-hodima-dc-default-img="<?php echo esc_url( $default ); ?>">
			<div class="hodima-dc__main">

				<div class="hodima-dc__field">
					<div class="hodima-dc__label-row">
						<label class="hodima-dc__label" for="hodima-dc-title">عنوان Discover</label>
						<span class="hodima-dc__counter" data-hodima-dc-counter aria-live="polite"><?php echo esc_html( number_format_i18n( $length ) . ' / ' . number_format_i18n( HODIMA_SEO_DISCOVER_TITLE_MAX ) ); ?></span>
					</div>
					<input type="text" id="hodima-dc-title" name="hodima_discover[title]" value="<?php echo esc_attr( $data['title'] ); ?>" maxlength="200" placeholder="<?php echo esc_attr( $fallback ); ?>" data-hodima-dc-title data-hodima-dc-fallback="<?php echo esc_attr( $fallback ); ?>">
					<?php if ( $ideas ) : ?>
						<div class="hodima-dc__ideas" role="group" aria-label="پیشنهاد عنوان">
							<span class="hodima-dc__ideas-label">پیشنهاد:</span>
							<?php foreach ( $ideas as $idea ) : ?>
								<button type="button" class="hodima-dc__idea" data-hodima-dc-idea="<?php echo esc_attr( $idea ); ?>"><?php echo esc_html( $idea ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<p class="hodima-dc__help">فقط کارت Discover و اشتراک‌گذاری (og:title)؛ عنوان صفحه و h1 عوض نمی‌شوند. خالی = همان عنوان. جذاب ولی صادق؛ «طعمه کلیک» جریمه دارد. پیشنهادها از اطلاعات خود صفحه ساخته می‌شوند؛ با کلیک در فیلد می‌نشینند.</p>
				</div>

				<div class="hodima-dc__field" data-hodima-dc-image>
					<span class="hodima-dc__label" id="hodima-dc-image-label">تصویر Discover</span>
					<input type="hidden" name="hodima_discover[image_id]" value="<?php echo $own_id ? (int) $own_id : ''; ?>" data-hodima-dc-image-id>
					<div class="hodima-dc__image<?php echo is_array( $own_src ) ? ' has-image' : ''; ?>" data-hodima-dc-image-card>
						<img src="<?php echo esc_url( is_array( $own_src ) ? (string) $own_src[0] : '' ); ?>" alt="" data-hodima-dc-image-preview <?php echo is_array( $own_src ) ? '' : 'hidden'; ?>>
						<div class="hodima-dc__image-empty" <?php echo is_array( $own_src ) ? 'hidden' : ''; ?> data-hodima-dc-image-empty>
							<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
							<span><?php echo esc_html( $own_label ); ?> استفاده می‌شود</span>
						</div>
						<div class="hodima-dc__image-actions">
							<button type="button" class="button" data-hodima-dc-pick aria-describedby="hodima-dc-image-label"><?php echo is_array( $own_src ) ? 'تغییر تصویر' : 'انتخاب تصویر'; ?></button>
							<button type="button" class="button-link hodima-dc__clear" data-hodima-dc-clear <?php echo $own_id ? '' : 'hidden'; ?>>حذف</button>
						</div>
					</div>
					<p class="hodima-dc__status" data-hodima-dc-status aria-live="polite"></p>
					<p class="hodima-dc__help">حداقل <strong>۱۲۰۰ پیکسل عرض</strong>، ترجیحا افقی ۱۶:۹؛ بدون لوگو و متن زیاد روی تصویر. سه برش ۱۶:۹، ۴:۳ و ۱:۱ هنگام ذخیره خودکار ساخته می‌شود.</p>
				</div>

				<div class="hodima-dc__field" data-hodima-dc-topics>
					<label class="hodima-dc__label" for="hodima-dc-topic-input">موضوعات اصلی</label>
					<div class="hodima-dc__chips" data-hodima-dc-chips>
						<?php foreach ( $data['entity_items'] as $item ) : ?>
							<span class="hodima-dc__chip" data-value="<?php echo esc_attr( trim( $item['name'] . ' ' . $item['url'] ) ); ?>">
								<span><?php echo esc_html( $item['name'] ); ?></span>
								<?php if ( '' !== $item['url'] ) : ?><span class="hodima-dc__chip-link" title="<?php echo esc_attr( $item['url'] ); ?>">ویکی</span><?php endif; ?>
								<button type="button" class="hodima-dc__chip-x" data-hodima-dc-chip-remove aria-label="<?php echo esc_attr( 'حذف ' . $item['name'] ); ?>">×</button>
							</span>
						<?php endforeach; ?>
						<input type="text" id="hodima-dc-topic-input" class="hodima-dc__chip-input" placeholder="موضوع را بنویسید و Enter بزنید" data-hodima-dc-topic-input>
					</div>
					<textarea name="hodima_discover[entities]" hidden data-hodima-dc-entities><?php echo esc_textarea( hodima_seo_discover_format_entities( $data['entity_items'], "\n" ) ); ?></textarea>
					<p class="hodima-dc__help">به گوگل می‌گوید صفحه درباره چیست. برای دقت بیشتر بعد از نام، آدرس ویکی‌داده یا شناسه‌اش را بنویسید: <code>کلیپس Q1234</code></p>
				</div>
			</div>

			<aside class="hodima-dc__phone" aria-label="پیش‌نمایش کارت Discover">
				<span class="hodima-dc__phone-label">پیش‌نمایش در گوشی</span>
				<div class="hodima-dc__card">
					<div class="hodima-dc__card-img">
						<img src="<?php echo esc_url( $preview ); ?>" alt="" data-hodima-dc-card-img <?php echo '' === $preview ? 'hidden' : ''; ?>>
						<span data-hodima-dc-card-noimg <?php echo '' === $preview ? '' : 'hidden'; ?>>بدون تصویر</span>
					</div>
					<div class="hodima-dc__card-body">
						<span class="hodima-dc__card-site">
							<?php if ( '' !== $icon ) : ?><img src="<?php echo esc_url( $icon ); ?>" alt="" width="16" height="16"><?php endif; ?>
							<?php echo esc_html( $host ); ?>
						</span>
						<strong class="hodima-dc__card-title" data-hodima-dc-card-title><?php echo esc_html( '' !== $data['title'] ? $data['title'] : $fallback ); ?></strong>
					</div>
				</div>
			</aside>
		</div>

		<?php hodima_seo_discover_render_checks( $checks ); ?>
	</div>
	<?php
}

/**
 * «آمادگی برای Discover»: امتیاز، نوار پیشرفت و فهرست (مشکل‌ها اول).
 *
 * @param list<array{key: string, status: string, label: string, detail: string, link?: string}> $checks
 */
function hodima_seo_discover_render_checks( array $checks ): void {

	[ $ok, $total ] = hodima_seo_discover_score( $checks );
	$icons          = [ 'ok' => 'dashicons-yes-alt', 'warn' => 'dashicons-warning', 'error' => 'dashicons-dismiss' ];
	$order          = [ 'error' => 0, 'warn' => 1, 'ok' => 2 ];
	$level          = match ( true ) {
		$ok === $total        => 'ok',
		$ok >= $total * 0.6   => 'warn',
		default               => 'error',
	};

	usort( $checks, static fn( array $a, array $b ): int => $order[ $a['status'] ] <=> $order[ $b['status'] ] );
	?>
	<section class="hodima-dc__guide" data-hodima-dc-guide>
		<header class="hodima-dc__guide-head">
			<strong>آمادگی برای Discover</strong>
			<span class="hodima-dc__score is-<?php echo esc_attr( $level ); ?>" data-hodima-dc-score><?php echo esc_html( sprintf( '%s از %s', number_format_i18n( $ok ), number_format_i18n( $total ) ) ); ?></span>
		</header>
		<div class="hodima-dc__bar is-<?php echo esc_attr( $level ); ?>" role="progressbar" aria-label="آمادگی برای Discover" aria-valuemin="0" aria-valuemax="<?php echo (int) $total; ?>" aria-valuenow="<?php echo (int) $ok; ?>" data-hodima-dc-bar>
			<span style="inline-size: <?php echo esc_attr( (string) ( $total ? round( 100 * $ok / $total ) : 0 ) ); ?>%"></span>
		</div>
		<ul class="hodima-dc__checks">
			<?php foreach ( $checks as $check ) : ?>
				<li class="is-<?php echo esc_attr( $check['status'] ); ?>" data-hodima-dc-check="<?php echo esc_attr( $check['key'] ); ?>">
					<span class="dashicons <?php echo esc_attr( $icons[ $check['status'] ] ); ?>" aria-hidden="true"></span>
					<span class="hodima-dc__check-text"><strong><?php echo esc_html( $check['label'] ); ?></strong> <span data-hodima-dc-check-text><?php echo esc_html( $check['detail'] ); ?></span></span>
					<?php if ( 'ok' !== $check['status'] && '' !== ( $check['link'] ?? '' ) ) : ?>
						<a class="hodima-dc__fix" href="<?php echo esc_url( (string) $check['link'] ); ?>">رفع</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="hodima-dc__help">Discover فید پیشنهادی گوگل در موبایل است؛ گوگل خودش صفحه‌ها را انتخاب می‌کند. این فهرست چیزهایی است که شانس انتخاب را بالا می‌برد. گزارش همه صفحه‌ها: «ابزارهای هدیما ← Google Discover».</p>
	</section>
	<?php
}

/** ذخیره کادر (نوشته/برگه/محصول یا دسته). */
function hodima_seo_discover_save( int $object_id, string $context ): void {

	$context = hodima_seo_discover_context( $context );
	$nonce   = isset( $_POST['hodima_discover_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_discover_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_discover_save_' . $context . '_' . $object_id ) ) {
		return;
	}

	$allowed = 'term' === $context
		? hodima_seo_discover_for_term( $object_id ) && current_user_can( 'edit_term', $object_id )
		: hodima_seo_discover_for_post( $object_id ) && current_user_can( 'edit_post', $object_id );

	if ( ! $allowed ) {
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
		'title'    => sanitize_text_field( $get( 'title' ) ),
		'entities' => hodima_seo_discover_format_entities( hodima_seo_discover_entity_items( sanitize_textarea_field( $get( 'entities' ) ) ) ),
		'image_id' => $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0,
	];

	foreach ( $values as $field => $value ) {
		$key = hodima_seo_discover_meta_key( $field, $context );
		( '' === $value || 0 === $value )
			? delete_metadata( $context, $object_id, $key )
			: update_metadata( $context, $object_id, $key, $value );
	}
}

add_action( 'save_post', static function ( int $post_id ): void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	hodima_seo_discover_save( $post_id, 'post' );
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
