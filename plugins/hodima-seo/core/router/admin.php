<?php
/**
 * Hodima Router — صفحه پیشخوان «آدرس تمیز»
 * Path: core/router/admin.php
 *
 * وضعیت روتر، بررسی یک آدرس و بررسی گروهی آدرس‌ها (مثلا خروجی سرچ کنسول)،
 * آدرس کوتاه قدیمی زیردسته‌ها، و فهرست آدرس‌هایی که بیش از یک شیء دارند
 * (نامک تکراری قدیمی). منطق گزارش‌ها در report.php؛ هیچ‌کدام چیزی ذخیره نمی‌کند. پیش از این لینک «تنظیمات» ماژول به «پیوندهای یکتا»
 * می‌رفت که هیچ چیزی از روتر نداشت.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_ROUTER_PAGE = 'hodima-router';

add_action( 'admin_menu', 'hodima_router_admin_menu', 30 );

function hodima_router_admin_menu(): void {

	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	$hook = '' !== $parent
		? add_submenu_page( $parent, 'آدرس تمیز', 'آدرس تمیز', 'manage_options', HODIMA_ROUTER_PAGE, 'hodima_router_render_admin' )
		: add_menu_page( 'آدرس تمیز', 'آدرس تمیز', 'manage_options', HODIMA_ROUTER_PAGE, 'hodima_router_render_admin', 'dashicons-admin-site-alt3', 31 );

	if ( is_string( $hook ) && '' !== $hook ) {
		// CSS همین صفحه فقط روی همین صفحه (بعد از ثبت admin-ui.css در اولویت ۵ Core)
		add_action( 'load-' . $hook, static fn() => add_action( 'admin_enqueue_scripts', 'hodima_router_admin_assets', 20 ) );
	}
}

/** نام فارسی نوع شیء. */
function hodima_router_type_label( string $type ): string {
	return match ( $type ) {
		'product'     => 'محصول',
		'post'        => 'نوشته',
		'page'        => 'برگه',
		'product_cat' => 'دسته محصول',
		'category'    => 'دسته نوشته',
		default       => $type,
	};
}

/** عنوان و لینک ویرایش یک شیء. */
function hodima_router_object_label( WP_Post|WP_Term $object ): string {

	if ( $object instanceof WP_Post ) {
		$title = '' !== $object->post_title ? $object->post_title : '#' . $object->ID;
		$edit  = (string) get_edit_post_link( $object->ID, 'raw' );
		$type  = $object->post_type;
	} else {
		$title = $object->name;
		$edit  = (string) get_edit_term_link( $object->term_id, $object->taxonomy );
		$type  = $object->taxonomy;
	}

	$label = sprintf( '<span class="hd-pill hd-pill--info">%s</span> ', esc_html( hodima_router_type_label( $type ) ) );

	return $label . ( '' !== $edit
		? sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html( $title ) )
		: esc_html( $title ) );
}


/**
 * CSS همین صفحه، فقط روی همین صفحه و زیر .hodima-router.
 * پایه‌ها: کد «/product-category/» در کارت وضعیت سر خط تیره دو تکه می‌شد و در
 * متن راست‌به‌چپ مثل «/product-» و «/product/» خوانده می‌شد.
 */
function hodima_router_admin_assets(): void {

	// جستجوی دسته‌ای لینک‌ها (links.php)
	$file = HODIMA_ROUTER_DIR . '/assets/router-links.js';
	wp_enqueue_script( 'hodima-router-links', HODIMA_SEO_URL . '/core/router/assets/router-links.js', [], is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_add_inline_script( 'hodima-router-links', 'window.hodimaRouterLinks = ' . wp_json_encode( [
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'hodima_router_links' ),
	] ) . ';', 'before' );

	if ( ! defined( 'HODIMA_ADMIN_UI_HANDLE' ) ) {
		return; // بدون Hodima Core هدر ساده فالبک؛ ظاهر پیش‌فرض وردپرس کافی است
	}

	wp_add_inline_style( HODIMA_ADMIN_UI_HANDLE, '
.hodima-router .hodima-router__status { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; }
.hodima-router .hodima-router__bases { display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem; font-size: 0.875rem; }
.hodima-router .hodima-router__ok { margin: 0; color: var(--hd-muted); }
.hodima-router .hodima-router__ok span { color: var(--hd-ok); font-weight: 700; margin-inline-end: 0.25rem; }
.hodima-router .hodima-router__urls { inline-size: 100%; }
.hodima-router .hodima-router__bases code { white-space: nowrap; }
.hodima-router .hodima-router__urls { min-block-size: 6rem; font-family: inherit; }
.hodima-router .hodima-router__url { min-inline-size: 14rem; direction: ltr; text-align: left; overflow-wrap: anywhere; }
.hodima-router .hd-table-wrap > table { min-inline-size: 44rem; }
.hodima-router .hodima-router__summary { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-block: 1rem; }
.hodima-router .hodima-router__more { margin-block-start: 1rem; }
.hodima-router .hodima-router__more > summary { cursor: pointer; color: var(--hd-primary); font-weight: 700; }
.hodima-router .hodima-router__more[open] > summary { margin-block-end: 0.75rem; }
.hodima-router .hodima-router__progress { display: block; inline-size: 100%; block-size: 0.5rem; margin-block: 0.75rem; accent-color: var(--hd-primary); }
.hodima-router .hodima-router__progress[hidden] { display: none; }
.hodima-router .hodima-router__field { display: block; color: var(--hd-muted); font-size: 0.75rem; }
' );
}

/**
 * نشان وضعیت یک آدرس: کلید hodima_router_check_key() (بررسی آدرس) یا وضعیت
 * گزارش زیردسته‌ها (ok، rule، taken، lost).
 */
function hodima_router_status_pill( string $key, ?int $count = null ): string {

	[ $class, $text ] = match ( $key ) {
		'main'          => [ 'ok', 'آدرس اصلی' ],
		'shared'        => [ 'warn', 'آدرس اصلی، نامک مشترک' ],
		'redirect'      => [ 'ok', '۳۰۱ به آدرس اصلی' ],
		'rule_ok'       => [ 'ok', 'ریدایرکت سالم (قانون دستی)' ],
		'rule_chain'    => [ 'warn', 'قانون به ریدایرکت دوباره' ],
		'rule_broken'   => [ 'error', 'قانون به صفحه ۴۰۴' ],
		'rule_gone'     => [ 'info', 'حذف عمدی (قانون ۴۰۴/۴۱۰)' ],
		'rule_external' => [ 'info', 'قانون به سایت دیگر' ],
		'rule'          => [ 'info', 'قانون ریدایرکت' ],
		'other'         => [ 'info', 'بخش دیگر وردپرس' ],
		'home'          => [ 'ok', 'صفحه اصلی' ],
		'external'      => [ 'warn', 'دامنه دیگر' ],
		'ok'            => [ 'ok', 'ریدایرکت سالم' ],
		'taken'         => [ 'error', 'گرفته شده' ],
		'lost'          => [ 'error', 'به دسته نمی‌رسد' ],
		default         => [ 'error', 'پیدا نشد (۴۰۴)' ],
	};

	if ( null !== $count ) {
		$text .= ': ' . number_format_i18n( $count );
	}

	return sprintf( '<span class="hd-pill hd-pill--%s">%s</span>', esc_attr( $class ), esc_html( $text ) );
}

/** آدرس خوانا (فارسی رمزگشایی‌شده) در یک code چپ‌به‌راست. */
function hodima_router_url_code( string $url ): string {
	return '<code dir="ltr">' . esc_html( rawurldecode( $url ) ) . '</code>';
}

/**
 * توضیح وضعیت یک آدرس، به زبان مدیر سایت.
 *
 * @param array<string, mixed> $check خروجی hodima_router_inspect_url()
 */
function hodima_router_status_text( array $check ): string {

	$object = $check['object'];
	$label  = null !== $object ? hodima_router_object_label( $object ) : '';

	return match ( $check['status'] ) {
		'main'     => 'آدرس اصلی ' . $label . ' است.',
		'shared'   => 'آدرس اصلی ' . $label . ' است. ولی این‌ها هم همین نامک را دارند: '
			. implode( '، ', array_map( 'hodima_router_object_label', $check['others'] ) )
			. ' — اگر این آدرس قبلا مال یکی از آن‌ها بوده (مثلا در سرچ کنسول هست)، حالا صفحه دیگری نشان داده می‌شود.',
		'redirect' => 'با ۳۰۱ به آدرس اصلی ' . $label . ' می‌رود: ' . hodima_router_url_code( $check['target'] ),
		'rule'     => hodima_router_rule_text( $check ),
		'other'    => 'مال بخش دیگری از وردپرس است (برچسب، نویسنده، بایگانی تاریخ، فید، فروشگاه و…)؛ روتر به آن دست نمی‌زند و باز شدن یا ۴۰۴ بودنش را خود وردپرس تعیین می‌کند.',
		'home'     => 'صفحه اصلی سایت است.',
		'external' => 'آدرس سایت دیگری است.',
		default    => 'هیچ محصول، نوشته، برگه یا دسته‌ای با این آدرس پیدا نمی‌شود؛ بازدیدکننده صفحه ۴۰۴ می‌بیند.',
	};
}

/**
 * توضیح یک قانون ماژول «ریدایرکت‌ها» و سلامت مقصدش.
 *
 * @param array<string, mixed> $check خروجی hodima_router_inspect_url() با status=rule
 */
function hodima_router_rule_text( array $check ): string {

	$key  = hodima_router_check_key( $check );
	$base = 'در «ابزارهای هدیما ← ریدایرکت‌ها» قانونی دارد';

	if ( 'rule_gone' === $key ) {
		return $base . ' که کد ' . number_format_i18n( (int) $check['code'] ) . ' برمی‌گرداند (به گوگل می‌گوید صفحه عمدا حذف شده).';
	}

	$text = $base . ' که به این آدرس می‌برد: ' . hodima_router_url_code( (string) $check['target'] );
	$next = is_array( $check['next'] ?? null ) ? $check['next'] : [];

	return $text . match ( $key ) {
		'rule_ok'       => ' — مقصد سالم است' . ( ( $next['object'] ?? null ) instanceof WP_Post || ( $next['object'] ?? null ) instanceof WP_Term ? ' (' . hodima_router_object_label( $next['object'] ) . ')' : '' ) . '.',
		'rule_chain'    => ' — ولی مقصد خودش دوباره ریدایرکت می‌شود؛ مقصد قانون را این بگذارید: ' . hodima_router_url_code( hodima_router_final_url( $check ) ),
		'rule_external' => ' (سایت دیگر).',
		default         => ' — ولی مقصد ۴۰۴ است؛ در «ریدایرکت‌ها» مقصد قانون را به صفحه موجودی عوض کنید.',
	};
}

function hodima_router_render_admin(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active    = hodima_router_active();
	$bases     = hodima_router_bases();
	$conflicts = $active ? hodima_router_conflicts() : [];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط خواندن
	$check     = isset( $_GET['check'] ) ? trim( (string) wp_unslash( $_GET['check'] ) ) : '';
	$nested    = $active ? hodima_router_nested_terms_report() : [ 'rows' => [], 'total' => 0, 'capped' => false ];
	$counts    = array_count_values( array_column( $nested['rows'], 'state' ) );
	$problems  = ( $counts['taken'] ?? 0 ) + ( $counts['lost'] ?? 0 ) + ( $counts['rule'] ?? 0 );

	// بررسی گروهی: POST (فهرست بلند در آدرس GET جا نمی‌شود)، فقط خواندن
	$bulk_text = '';
	$bulk      = [];
	if ( isset( $_POST['hodima_router_urls'] ) && wp_verify_nonce( (string) wp_unslash( $_POST['_hodima_router_bulk'] ?? '' ), 'hodima_router_bulk' ) ) {
		$bulk_text = (string) wp_unslash( $_POST['hodima_router_urls'] );
		$bulk      = array_map( 'hodima_router_inspect_url', hodima_router_extract_urls( $bulk_text ) );
	}

	echo '<div class="wrap hd-wrap hodima-router">';

	hodima_admin_header( [
		'title'       => 'آدرس تمیز',
		'description' => 'محصول، دسته محصول و دسته نوشته بدون /product/، /product-category/ و /category/؛ هر صفحه فقط یک آدرس دارد و شکل‌های دیگر با ۳۰۱ به آن می‌روند.',
		'icon'        => 'dashicons-admin-site-alt3',
	] );

	if ( ! $active ) {
		hodima_admin_notice( 'پیوندهای یکتا روی «ساده» است؛ آدرس تمیز فقط با پیوندهای یکتای زیبا (مثل «نام نوشته») کار می‌کند. <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">تنظیمات پیوندهای یکتا</a>', 'warning' );
	}

	if ( isset( $_POST['hodima_router_urls'] ) && '' === $bulk_text ) {
		hodima_admin_notice( 'فرم منقضی شده بود؛ صفحه را تازه کنید و دوباره بررسی کنید.', 'warning' );
	}

	echo '<div class="hd-body">';

	// وضعیت
	?>
	<section class="hd-card hd-card--accent">
		<div class="hodima-router__status">
			<?php echo $active ? '<span class="hd-pill hd-pill--ok">روتر فعال</span>' : '<span class="hd-pill hd-pill--warn">روتر غیرفعال</span>'; ?>
			<span class="hodima-router__bases"><span class="hd-muted">پایه‌های حذف‌شده:</span> <code dir="ltr">/<?php echo esc_html( $bases['product'] ); ?>/</code> <code dir="ltr">/<?php echo esc_html( $bases['product_cat'] ); ?>/</code> <code dir="ltr">/<?php echo esc_html( $bases['category'] ); ?>/</code></span>
			<span class="hd-pill hd-pill--<?php echo $conflicts ? 'warn' : 'ok'; ?>">آدرس مشترک: <?php echo esc_html( number_format_i18n( count( $conflicts ) ) ); ?></span>
			<span class="hd-pill hd-pill--<?php echo $problems ? 'warn' : 'ok'; ?>">زیردسته با مشکل: <?php echo esc_html( number_format_i18n( $problems ) ); ?></span>
		</div>
	</section>

	<section class="hd-card">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-search' ); // phpcs:ignore ?> بررسی آدرس</h2>
		<form method="get" class="hd-inline">
			<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_ROUTER_PAGE ); ?>">
			<label class="screen-reader-text" for="hodima-router-check">آدرس</label>
			<input type="text" id="hodima-router-check" name="check" class="regular-text" dir="ltr" value="<?php echo esc_attr( $check ); ?>" placeholder="<?php echo esc_attr( home_url( '/product/...' ) ); ?>">
			<button type="submit" class="button button-primary">بررسی</button>
		</form>
		<?php
		if ( '' !== $check ) {
			$result = hodima_router_inspect_url( $check );
			$tone   = in_array( hodima_router_check_key( $result ), [ 'missing', 'external', 'shared', 'rule_chain', 'rule_broken' ], true ) ? 'warning' : 'success';
			echo '<div class="hd-callout hd-callout--' . esc_attr( $tone ) . '">' . hodima_admin_icon( 'success' === $tone ? 'dashicons-yes-alt' : 'dashicons-warning' ) . '<p>' . wp_kses_post( hodima_router_status_text( $result ) ) . '</p></div>'; // phpcs:ignore
		}
		?>
		<details class="hodima-router__more"<?php echo '' !== $bulk_text ? ' open' : ''; ?>>
		<summary>بررسی گروهی آدرس‌ها (تا ۳۰۰ آدرس)</summary>
		<p class="hd-text">هر خط یک آدرس. می‌توانید فهرست صفحه‌ها را از سرچ کنسول کپی کنید: «Pages» (مثلا گروه «Page with redirect» یا «Not found (404)») یا «Performance ← Pages»، دکمه «Export» و بعد ستون آدرس‌ها. چیزی ذخیره یا عوض نمی‌شود.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . HODIMA_ROUTER_PAGE ) ); ?>">
			<?php wp_nonce_field( 'hodima_router_bulk', '_hodima_router_bulk' ); ?>
			<label class="screen-reader-text" for="hodima-router-urls">آدرس‌ها</label>
			<textarea id="hodima-router-urls" name="hodima_router_urls" class="hodima-router__urls" dir="ltr" rows="8" placeholder="<?php echo esc_attr( home_url( '/plumeria-hair-clips/' ) ); ?>"><?php echo esc_textarea( $bulk_text ); ?></textarea>
			<p><button type="submit" class="button button-primary">بررسی همه</button></p>
		</form>
		<?php
		if ( '' !== $bulk_text && ! $bulk ) {
			echo '<div class="hd-callout hd-callout--warning">' . hodima_admin_icon( 'dashicons-warning' ) . '<p>آدرسی پیدا نشد؛ هر خط باید با https:// یا / شروع شود.</p></div>'; // phpcs:ignore
		}

		if ( $bulk ) {
			// مشکل‌دارها اول؛ ترتیب ورودی در هر گروه حفظ می‌شود
			$keys     = array_map( 'hodima_router_check_key', $bulk );
			$severity = [ 'missing' => 0, 'rule_broken' => 1, 'shared' => 2, 'rule_chain' => 3, 'external' => 4, 'rule_external' => 5, 'rule_gone' => 6, 'other' => 7, 'rule_ok' => 8, 'redirect' => 9, 'main' => 10, 'home' => 11 ];
			$indexed  = array_keys( $bulk );
			usort( $indexed, static fn( int $a, int $b ): int => [ $severity[ $keys[ $a ] ] ?? 0, $a ] <=> [ $severity[ $keys[ $b ] ] ?? 0, $b ] );

			echo '<div class="hodima-router__summary">';
			foreach ( array_count_values( $keys ) as $key => $count ) {
				echo hodima_router_status_pill( (string) $key, (int) $count ); // phpcs:ignore
			}
			echo '</div>';
			?>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead><tr><th scope="col">آدرس</th><th scope="col">وضعیت</th><th scope="col">توضیح</th></tr></thead>
					<tbody>
					<?php foreach ( $indexed as $i ) : ?>
						<tr>
							<td class="hodima-router__url"><?php echo esc_html( rawurldecode( $bulk[ $i ]['url'] ) ); ?></td>
							<td><?php echo hodima_router_status_pill( $keys[ $i ] ); // phpcs:ignore ?></td>
							<td><?php echo wp_kses_post( hodima_router_status_text( $bulk[ $i ] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
		?>
		</details>
	</section>

	<section class="hd-card" data-hodima-router-links>
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-admin-links' ); // phpcs:ignore ?> لینک‌های داخلی قدیمی و خراب</h2>
		<p class="hd-text">لینک‌های داخل سایت را که به آدرس قدیمی (ریدایرکت‌شونده)، صفحه ۴۰۴ یا آدرس اشتباه می‌روند پیدا می‌کند؛ اصلاح با شماست.</p>
		<details class="hodima-router__more"><summary>جاهایی که جستجو می‌شود و توضیح بیشتر</summary>
		<p class="hd-text">لینک‌هایی داخل خود سایت را پیدا می‌کند که به آدرس قدیمی (ریدایرکت‌شونده)، صفحه ۴۰۴ یا آدرس اشتباه‌نوشته‌شده می‌روند: متن و توضیح کوتاه نوشته‌ها، برگه‌ها و محصولات منتشرشده، توضیح دسته‌ها، «پیوند دلخواه» منوها و تنظیمات قالب. برای هر لینک جای آن (با دکمه ویرایش) و آدرس درست را می‌گوید؛ اصلاح با شماست و چیزی خودکار عوض نمی‌شود. جستجو دسته‌دسته انجام می‌شود و ممکن است چند دقیقه طول بکشد؛ تا پایان، صفحه را نبندید.</p>
		</details>
		<p class="hd-inline">
			<button type="button" class="button button-primary" data-hodima-router-links-start>جستجوی لینک‌ها</button>
			<span class="hd-muted" data-hodima-router-links-msg role="status" aria-live="polite"></span>
		</p>
		<progress class="hodima-router__progress" max="100" value="0" hidden data-hodima-router-links-progress></progress>
		<div class="hodima-router__summary" hidden data-hodima-router-links-summary></div>
		<div class="hd-table-wrap" hidden data-hodima-router-links-table>
			<table class="widefat striped">
				<thead><tr><th scope="col">کجا</th><th scope="col">لینک</th><th scope="col">مشکل</th><th scope="col">چه کنید</th></tr></thead>
				<tbody></tbody>
			</table>
		</div>
		<noscript><p class="hd-text">این جستجو به جاوااسکریپت نیاز دارد.</p></noscript>
	</section>

	<section class="hd-card">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-category' ); // phpcs:ignore ?> آدرس کوتاه قدیمی زیردسته‌ها</h2>
		<details class="hodima-router__more"><summary>این فهرست چه می‌کند؟</summary>
		<p class="hd-text">آدرس اصلی زیردسته‌ها مسیر دسته‌های والد را دارد (مثل <code dir="ltr">/accessory/hair-clips/plumeria-hair-clips/</code>). اگر آدرس کوتاه همان دسته (<code dir="ltr">/plumeria-hair-clips/</code>) قبلا در گوگل بوده، باید با ۳۰۱ به آدرس اصلی برسد. این فهرست برای هر زیردسته همین را بررسی می‌کند، همان‌طور که بازدیدکننده می‌بیند.</p>
		</details>
		<?php if ( ! $nested['total'] ) : ?>
			<p class="hodima-router__ok"><span aria-hidden="true">✓</span> زیردسته‌ای با آدرس سلسله‌مراتبی نیست.</p>
		<?php else : ?>
			<div class="hodima-router__summary">
				<span class="hd-muted"><?php echo esc_html( number_format_i18n( $nested['total'] ) ); ?> زیردسته با آدرس کامل:</span>
				<?php
				foreach ( [ 'ok', 'rule', 'taken', 'lost' ] as $state ) {
					if ( ! empty( $counts[ $state ] ) ) {
						echo hodima_router_status_pill( $state, (int) $counts[ $state ] ); // phpcs:ignore
					}
				}
				?>
			</div>
			<?php if ( $nested['capped'] ) : ?>
				<div class="hd-callout hd-callout--warning"><?php echo hodima_admin_icon( 'dashicons-info' ); // phpcs:ignore ?><p>دسته‌ها بیش از حد زیاد بودند؛ فقط ۲۰۰۰ دسته اول هر نوع بررسی شد.</p></div>
			<?php endif; ?>
			<?php if ( $problems ) : ?>
				<div class="hd-callout hd-callout--warning">
					<?php echo hodima_admin_icon( 'dashicons-info' ); // phpcs:ignore ?>
					<div>
						<p><strong>«گرفته شده»:</strong> آدرس کوتاه دسته الان صفحه دیگری را نشان می‌دهد. اگر آن آدرس در گوگل مال همان صفحه است، کاری لازم نیست. اگر مال دسته بوده (مثلا در سرچ کنسول هست): نامک صفحه‌ای را که آن را گرفته در ویرایش همان صفحه عوض کنید؛ ماژول ریدایرکت‌ها خودکار قانونی از آدرس کوتاه به آدرس تازه آن صفحه می‌سازد و این ردیف «قانون ریدایرکت» می‌شود — آن قانون را در «ابزارهای هدیما ← ریدایرکت‌ها» حذف کنید تا آدرس کوتاه با ۳۰۱ به دسته برسد.</p>
						<p><strong>«به دسته نمی‌رسد»:</strong> آدرس کوتاه ۴۰۴ است یا مال بخش دیگری از وردپرس؛ اگر در گوگل بوده، در «ریدایرکت‌ها» یک ریدایرکت از آن به آدرس اصلی دسته بسازید.</p>
						<p><strong>«قانون ریدایرکت»:</strong> قانونی در «ریدایرکت‌ها» (دستی، یا خودکار بعد از تغییر نامک یک صفحه) آدرس کوتاه را به جای دیگری می‌برد؛ اگر آدرس کوتاه مال این دسته بوده، قانون را حذف کنید یا مقصدش را آدرس اصلی دسته بگذارید.</p>
					</div>
				</div>
				<?php hodima_router_render_nested_table( array_filter( $nested['rows'], static fn( array $row ): bool => 'ok' !== $row['state'] ) ); ?>
			<?php else : ?>
				<div class="hd-callout hd-callout--success"><?php echo hodima_admin_icon( 'dashicons-yes-alt' ); // phpcs:ignore ?><p>آدرس کوتاه قدیمی همه زیردسته‌ها به دسته خودشان می‌رسد.</p></div>
			<?php endif; ?>
			<?php $healthy = array_filter( $nested['rows'], static fn( array $row ): bool => 'ok' === $row['state'] ); ?>
			<?php if ( $healthy ) : ?>
				<details class="hodima-router__more">
					<summary><?php echo esc_html( number_format_i18n( count( $healthy ) ) ); ?> زیردسته بدون مشکل (نمایش فهرست)</summary>
					<?php hodima_router_render_nested_table( $healthy ); ?>
				</details>
			<?php endif; ?>
		<?php endif; ?>
	</section>

	<section class="hd-card">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore ?> آدرس‌های مشترک (نامک تکراری)</h2>
		<?php if ( ! $conflicts ) : ?>
			<p class="hodima-router__ok"><span aria-hidden="true">✓</span> هیچ دو صفحه‌ای آدرس یکسان ندارند.</p>
		<?php else : ?>
			<div class="hd-callout hd-callout--warning">
				<?php echo hodima_admin_icon( 'dashicons-info' ); // phpcs:ignore ?>
				<p><strong>این آدرس‌ها مال بیش از یک صفحه‌اند و فقط اولی نمایش داده می‌شود.</strong> نامک صفحه پنهان‌مانده را در ویرایش آن عوض کنید (نامک تکراری جدید دیگر ساخته نمی‌شود). آدرس فعلی برای صفحه نمایش‌داده‌شده می‌ماند و ریدایرکت خودکار برایش ساخته نمی‌شود.</p>
			</div>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead><tr><th scope="col">آدرس</th><th scope="col">نمایش داده می‌شود</th><th scope="col">پنهان مانده</th></tr></thead>
					<tbody>
					<?php foreach ( $conflicts as $conflict ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $conflict['items'][0]['url'] ); ?>" dir="ltr"><?php echo esc_html( '/' . $conflict['path'] . '/' ); ?></a></td>
							<td><?php echo hodima_router_object_label( $conflict['items'][0]['object'] ); // phpcs:ignore -- escaped inside ?></td>
							<td><?php echo implode( '<br>', array_map( static fn( array $item ): string => hodima_router_object_label( $item['object'] ), array_slice( $conflict['items'], 1 ) ) ); // phpcs:ignore ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php

	echo '</div></div>';
}

/**
 * جدول ردیف‌های گزارش زیردسته‌ها.
 *
 * @param array<int, array{term:WP_Term, old:string, new:string, state:string, detail:array<string, mixed>}> $rows
 */
function hodima_router_render_nested_table( array $rows ): void {
	?>
	<div class="hd-table-wrap">
		<table class="widefat striped">
			<thead><tr><th scope="col">زیردسته</th><th scope="col">آدرس کوتاه قدیمی</th><th scope="col">وضعیت</th><th scope="col">آدرس اصلی / توضیح</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo hodima_router_object_label( $row['term'] ); // phpcs:ignore -- escaped inside ?></td>
					<td class="hodima-router__url"><?php echo esc_html( rawurldecode( $row['old'] ) ); ?></td>
					<td><?php echo hodima_router_status_pill( $row['state'] ); // phpcs:ignore ?></td>
					<td>
						<?php
						echo match ( $row['state'] ) { // phpcs:ignore -- خروجی توابع escape شده
							'ok'    => 'rule' === $row['detail']['status'] ? wp_kses_post( hodima_router_status_text( $row['detail'] ) ) : hodima_router_url_code( $row['new'] ),
							'taken' => wp_kses_post( 'الان ' . ( 'redirect' === $row['detail']['status'] ? 'به ' : '' ) . hodima_router_object_label( $row['detail']['object'] ) . ( 'redirect' === $row['detail']['status'] ? ' می‌رود' : ' را نشان می‌دهد' ) . '؛ آدرس اصلی دسته: ' . hodima_router_url_code( $row['new'] ) ),
							default => wp_kses_post( hodima_router_status_text( $row['detail'] ) ),
						};
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
