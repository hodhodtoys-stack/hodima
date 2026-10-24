<?php
/**
 * Hodima Redirects — admin
 * Path: core/redirects/admin-redirects.php
 * Version: 3.0.0
 */

namespace Hodima\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
add_action( 'admin_init', __NAMESPACE__ . '\\handle_actions' );

function admin_menu(): void {
	// زیر «ابزارهای هدیما» (آدرس admin.php?page=hodima-redirects همان قبلی است)؛
	// بدون Hodima Core مثل قبل منوی سطح اول
	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	if ( '' !== $parent ) {
		add_submenu_page( $parent, 'مدیریت ریدایرکت‌ها', 'ریدایرکت‌ها', 'manage_options', 'hodima-redirects', __NAMESPACE__ . '\\render_page' );
		return;
	}

	add_menu_page( 'مدیریت ریدایرکت‌ها', 'ریدایرکت‌ها', 'manage_options', 'hodima-redirects', __NAMESPACE__ . '\\render_page', 'dashicons-randomize', 30 );
}

function page_url( array $args = [] ): string {
	return add_query_arg( $args, admin_url( 'admin.php?page=hodima-redirects' ) );
}

function flash( string $type, string $text ): void {
	set_transient( 'hodima_redirect_msg_' . get_current_user_id(), [ $type, $text ], 60 );
}

/* =====================================================================
 * عملیات (پیش از ارسال هدرها)
 * ===================================================================== */

function handle_actions(): void {

	if ( ( $_GET['page'] ?? '' ) !== 'hodima-redirects' && ! isset( $_POST['hodima_redirect_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = sanitize_key( (string) ( $_POST['hodima_redirect_action'] ?? $_GET['action'] ?? '' ) );

	switch ( $action ) {

		case 'save':
			check_admin_referer( 'hodima_redirect_save' );

			/*
			 * بدون sanitize_text_field. آن تابع هر %XX را حذف می‌کند و آدرس
			 * فارسی کپی‌شده از مرورگر را نابود می‌کرد. یکسان‌سازی و
			 * اعتبارسنجی در normalize_path() و normalize_target() انجام می‌شود.
			 */
			$result = add_rule(
				(string) wp_unslash( $_POST['old_url'] ?? '' ),
				(string) wp_unslash( $_POST['target_url'] ?? '' ),
				(int) ( $_POST['status_code'] ?? 301 ),
				'',
				normalize_path( (string) wp_unslash( $_POST['original_key'] ?? '' ) )
			);

			flash( $result['ok'] ? 'success' : 'error', $result['message'] );
			wp_safe_redirect( page_url( $result['ok'] ? [] : [ 'edit' => rawurlencode( (string) wp_unslash( $_POST['original_key'] ?? '' ) ) ] ) );
			exit;

		case 'delete':
			check_admin_referer( 'hodima_redirect_delete' );
			/*
			 * کلید با rawurlencode در آدرس، نه base64. base64 شامل «+» است که
			 * PHP در رشته کوئری آن را فاصله می‌خواند؛ کلید درست رمزگشایی
			 * نمی‌شد، قانون حذف نمی‌شد و باز هم پیام «موفق» نمایش داده می‌شد.
			 */
			$deleted = delete_rule( (string) wp_unslash( $_GET['rule'] ?? '' ) );
			flash( $deleted ? 'success' : 'error', $deleted ? 'قانون حذف شد.' : 'قانون پیدا نشد.' );
			wp_safe_redirect( page_url() );
			exit;

		case 'export':
			check_admin_referer( 'hodima_redirect_export' );
			export_csv();
			exit;

		case 'import':
			check_admin_referer( 'hodima_redirect_import' );
			[ $type, $text ] = import_csv();
			flash( $type, $text );
			wp_safe_redirect( page_url() );
			exit;
	}
}

/* =====================================================================
 * CSV
 * ===================================================================== */

function export_csv(): void {

	$hits = (array) get_option( HITS_OPTION, [] );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=hodima-redirects-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, [ 'source', 'target', 'status', 'type', 'last_used' ] );

	$safe = static fn( string $v ): string => ( '' !== $v && in_array( $v[0], [ '=', '+', '-', '@' ], true ) ) ? "'" . $v : $v;

	foreach ( get_rules() as $key => $rule ) {
		fputcsv( $out, [
			$safe( (string) ( $rule['source'] ?? $key ) ),
			$safe( (string) ( $rule['target'] ?? '' ) ),
			(int) $rule['status'],
			(string) ( $rule['auto'] ?? '' ),
			isset( $hits[ $key ] ) ? gmdate( 'Y-m-d', (int) $hits[ $key ] ) : '',
		] );
	}

	fclose( $out );
}

/**
 * ورود CSV: ستون‌ها «مبدا، مقصد، کد» (سرستون اختیاری). هر ردیف از همان
 * add_rule می‌گذرد، پس زنجیره و حلقه در ورود گروهی هم بررسی می‌شوند.
 *
 * @return array{0:string, 1:string}
 */
function import_csv(): array {

	$file = $_FILES['redirects_csv'] ?? null;

	if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? 1 ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
		return [ 'error', 'فایلی بارگذاری نشد.' ];
	}

	if ( (int) $file['size'] > 2 * MB_IN_BYTES ) {
		return [ 'error', 'حجم فایل بیش از ۲ مگابایت است.' ];
	}

	$handle = fopen( (string) $file['tmp_name'], 'r' );
	if ( ! $handle ) {
		return [ 'error', 'فایل خوانده نشد.' ];
	}

	$ok = 0;
	$failed = [];
	$row = 0;

	while ( ( $cols = fgetcsv( $handle ) ) !== false && $row < 5000 ) {

		$row++;
		$cols = array_map( static fn( $c ) => trim( (string) $c ), (array) $cols );

		if ( 1 === $row ) {
			$cols[0] = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $cols[0] ?? '' );
			if ( in_array( strtolower( $cols[0] ), [ 'source', 'from', 'old', 'مبدا' ], true ) ) {
				continue;
			}
		}

		if ( '' === ( $cols[0] ?? '' ) ) {
			continue;
		}

		$status = isset( $cols[2] ) && '' !== $cols[2] ? (int) $cols[2] : 301;
		$result = add_rule( $cols[0], (string) ( $cols[1] ?? '' ), $status );

		$result['ok'] ? $ok++ : ( $failed[] = $row . ': ' . $result['message'] );
	}

	fclose( $handle );

	$text = sprintf( '%s قانون وارد شد.', number_format_i18n( $ok ) );
	if ( $failed ) {
		$text .= ' ' . sprintf( '%s ردیف رد شد — ', number_format_i18n( count( $failed ) ) ) . implode( ' | ', array_slice( $failed, 0, 5 ) );
	}

	return [ $failed && ! $ok ? 'error' : 'success', $text ];
}

/* =====================================================================
 * صفحه
 * ===================================================================== */

/** نمای هر دسته از فهرست: کلید ← [برچسب، شرط روی قانون]. */
function views(): array {
	return [
		'all'        => [ 'همه', static fn( array $rule, string $key ): bool => true ],
		'permanent'  => [ 'دائم', static fn( array $rule, string $key ): bool => in_array( (int) $rule['status'], [ 301, 308 ], true ) ],
		'temporary'  => [ 'موقت', static fn( array $rule, string $key ): bool => in_array( (int) $rule['status'], [ 302, 307 ], true ) ],
		'gone'       => [ 'حذف‌شده', static fn( array $rule, string $key ): bool => in_array( (int) $rule['status'], [ 404, 410 ], true ) ],
		'auto'       => [ 'خودکار', static fn( array $rule, string $key ): bool => ! empty( $rule['auto'] ) ],
		'suspicious' => [ 'مشکوک', static fn( array $rule, string $key ): bool => is_suspicious( $key ) ],
	];
}

function render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$all  = array_reverse( get_rules(), true );
	$hits = (array) get_option( HITS_OPTION, [] );

	$msg = get_transient( 'hodima_redirect_msg_' . get_current_user_id() );
	delete_transient( 'hodima_redirect_msg_' . get_current_user_id() );

	$edit_key  = isset( $_GET['edit'] ) ? normalize_path( rawurldecode( (string) wp_unslash( $_GET['edit'] ) ) ) : '';
	$edit_rule = ( '' !== $edit_key && isset( $all[ $edit_key ] ) ) ? $all[ $edit_key ] : null;

	// شمار هر نما روی کل قانون‌ها (نه فقط نتیجه جستجو)
	$views  = views();
	$counts = [];
	foreach ( $views as $id => [ , $match ] ) {
		$counts[ $id ] = count( array_filter( $all, static fn( $rule, $key ): bool => $match( $rule, (string) $key ), ARRAY_FILTER_USE_BOTH ) );
	}
	// «مشکوک» فقط وقتی هست که قانون مشکوکی وجود داشته باشد
	if ( 0 === $counts['suspicious'] ) {
		unset( $views['suspicious'] );
	}

	$view = sanitize_key( (string) wp_unslash( $_GET['view'] ?? 'all' ) );
	$view = isset( $views[ $view ] ) ? $view : 'all';

	$rules = array_filter( $all, static fn( $rule, $key ): bool => $views[ $view ][1]( $rule, (string) $key ), ARRAY_FILTER_USE_BOTH );

	$search = trim( (string) wp_unslash( $_GET['s'] ?? '' ) );
	if ( '' !== $search ) {
		$needle = mb_strtolower( rawurldecode( $search ) );
		$rules  = array_filter( $rules, static function ( $rule, $key ) use ( $needle ) {
			return str_contains( (string) $key, $needle ) || str_contains( mb_strtolower( (string) ( $rule['target'] ?? '' ) ), $needle );
		}, ARRAY_FILTER_USE_BOTH );
	}

	$per_page     = 25;
	$total        = count( $rules );
	$pages        = max( 1, (int) ceil( $total / $per_page ) );
	$current_page = min( $pages, max( 1, (int) ( $_GET['paged'] ?? 1 ) ) );
	$offset       = ( $current_page - 1 ) * $per_page;
	$page_rules   = array_slice( $rules, $offset, $per_page, true );

	$labels = [ 301 => 'انتقال دائم', 302 => 'موقت', 307 => 'موقت', 308 => 'دائم', 404 => 'پیدا نشد', 410 => 'حذف همیشگی' ];
	$icons  = [ 'edit' => 'dashicons-edit', 'delete' => 'dashicons-trash' ];
	$stats  = [
		[ 'کل قانون‌ها', $counts['all'] ],
		[ 'انتقال دائم (۳۰۱/۳۰۸)', $counts['permanent'] ],
		[ 'انتقال موقت', $counts['temporary'] ],
		[ 'حذف‌شده (۴۱۰/۴۰۴)', $counts['gone'] ],
	];
	?>
	<div class="wrap hd-wrap h-admin">

		<?php
		hodima_admin_header( [
			'title'       => 'مدیریت ریدایرکت‌های سایت',
			'description' => 'ریدایرکت‌های ۳۰۱/۳۰۲/۴۱۰، ورود و خروج CSV و ریدایرکت خودکار هنگام تغییر نامک.',
			'icon'        => 'dashicons-randomize',
		] );
		?>

		<?php
		// پیام استاندارد وردپرس (زیر هدر، با دکمه X)؛ قبلا کادر اختصاصی بدون دکمه بستن بود
		if ( is_array( $msg ) ) {
			hodima_admin_notice( esc_html( (string) $msg[1] ), 'success' === $msg[0] ? 'success' : 'error' );
		}
		?>

		<?php if ( $counts['suspicious'] > 0 && '' === $search && 'all' === $view ) : ?>
			<?php
			hodima_admin_notice(
				esc_html( sprintf( '%s قانون مشکوک پیدا شد (با نشان «مشکوک» در فهرست).', number_format_i18n( $counts['suspicious'] ) ) )
				. ' نسخه قبلی آدرس‌های فارسی کپی‌شده از مرورگر را هنگام ذخیره خراب می‌کرد و این قوانین هرگز اجرا نمی‌شدند. آن‌ها را حذف و با آدرس درست دوباره بسازید.',
				'warning'
			);
			?>
		<?php endif; ?>

		<section class="h-stats" aria-label="خلاصه قانون‌ها">
			<?php foreach ( $stats as [ $label, $number ] ) : ?>
				<div class="h-stat"><span><?php echo esc_html( $label ); ?></span><b><?php echo esc_html( number_format_i18n( $number ) ); ?></b></div>
			<?php endforeach; ?>
		</section>

		<form class="h-card h-add" method="post" action="<?php echo esc_url( page_url() ); ?>" aria-label="<?php echo $edit_rule ? 'ویرایش قانون' : 'افزودن مسیر جدید'; ?>">
			<?php wp_nonce_field( 'hodima_redirect_save' ); ?>
			<input type="hidden" name="hodima_redirect_action" value="save">
			<input type="hidden" name="original_key" value="<?php echo esc_attr( $edit_rule ? $edit_key : '' ); ?>">

			<?php if ( $edit_rule ) : ?>
				<p class="h-add__mode">ویرایش قانون <code><?php echo esc_html( $edit_key ); ?></code></p>
			<?php endif; ?>

			<div class="h-form-grid">
				<div>
					<label for="h-old">آدرس قدیمی (مبدا)</label>
					<input type="text" id="h-old" name="old_url" class="h-input" dir="ltr" required
						value="<?php echo esc_attr( $edit_rule ? (string) ( $edit_rule['source'] ?? $edit_key ) : '' ); ?>"
						placeholder="/old-path/ یا آدرس کامل">
				</div>
				<div>
					<label for="h-target">آدرس جدید (مقصد)</label>
					<input type="text" id="h-target" name="target_url" class="h-input" dir="ltr"
						value="<?php echo esc_attr( $edit_rule ? (string) $edit_rule['target'] : '' ); ?>"
						placeholder="/new-path/ یا https://…">
				</div>
				<div>
					<label for="h-status">نوع</label>
					<select id="h-status" name="status_code" class="h-input">
						<?php
						$current = $edit_rule ? (int) $edit_rule['status'] : 301;
						foreach ( [
							301 => '301 — انتقال دائم (استاندارد سئو)',
							302 => '302 — انتقال موقت',
							307 => '307 — موقت، حفظ متد فرم',
							308 => '308 — دائم، حفظ متد فرم',
							404 => '404 — پیدا نشد',
							410 => '410 — حذف همیشگی',
						] as $code => $text ) {
							printf( '<option value="%d"%s>%s</option>', $code, selected( $current, $code, false ), esc_html( $text ) );
						}
						?>
					</select>
				</div>
				<div class="h-form-actions">
					<button type="submit" class="h-btn h-btn-primary"><?php echo $edit_rule ? 'ذخیره تغییرات' : 'افزودن مسیر'; ?></button>
					<?php if ( $edit_rule ) : ?>
						<a class="h-btn h-btn-outline" href="<?php echo esc_url( page_url() ); ?>">انصراف</a>
					<?php endif; ?>
				</div>
			</div>

			<details class="h-help">
				<summary>راهنما: آدرس فارسی، زنجیره‌ها و تست</summary>
				<p>
					آدرس را می‌توانید مستقیم از نوار آدرس مرورگر کپی کنید (حتی به شکل کدگذاری‌شده %D8%…).
					زنجیره‌ها خودکار صاف می‌شوند (A→B و B→C به A→C) و قانونی که حلقه بسازد ذخیره نمی‌شود.
					با تغییر نامک دسته‌بندی، محصول یا نوشته، ریدایرکت ۳۰۱ خودکار ساخته می‌شود.
					مرورگرها ریدایرکت ۳۰۱ را به خاطر می‌سپارند؛ برای تست قانون تازه از پنجره ناشناس استفاده کنید.
				</p>
			</details>
		</form>

		<section class="h-card h-list" aria-label="قوانین">
			<div class="h-toolbar">
				<nav class="h-views" aria-label="نمای قوانین">
					<?php foreach ( $views as $id => [ $label ] ) : ?>
						<a class="h-view<?php echo $id === $view ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( page_url( 'all' === $id ? [] : [ 'view' => $id ] ) ); ?>"<?php echo $id === $view ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $label ); ?><i><?php echo esc_html( number_format_i18n( $counts[ $id ] ) ); ?></i>
						</a>
					<?php endforeach; ?>
				</nav>

				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="h-search-bar" role="search">
					<input type="hidden" name="page" value="hodima-redirects">
					<?php if ( 'all' !== $view ) : ?>
						<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
					<?php endif; ?>
					<input type="search" name="s" class="h-input" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی مبدا یا مقصد…" aria-label="جستجوی مبدا یا مقصد">
				</form>

				<a class="h-btn h-btn-outline h-btn-sm" href="<?php echo esc_url( wp_nonce_url( page_url( [ 'action' => 'export' ] ), 'hodima_redirect_export' ) ); ?>">خروجی CSV</a>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( page_url() ); ?>" class="h-import">
					<?php wp_nonce_field( 'hodima_redirect_import' ); ?>
					<input type="hidden" name="hodima_redirect_action" value="import">
					<label class="h-btn h-btn-outline h-btn-sm">
						ورود CSV
						<input type="file" name="redirects_csv" accept=".csv,text/csv" class="h-file" onchange="this.form.submit()">
					</label>
				</form>
			</div>

			<div class="h-table-wrapper">
				<table class="h-table">
					<thead>
						<tr>
							<th>مبدا ← مقصد</th>
							<th>کد</th>
							<th>آخرین استفاده</th>
							<th><span class="screen-reader-text">عملیات</span></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $page_rules ) ) : ?>
						<tr><td colspan="4" class="h-empty">
							<?php echo '' !== $search || 'all' !== $view ? 'قانونی با این فیلتر پیدا نشد.' : 'هنوز ریدایرکتی ثبت نشده. اولین مسیر را از کادر بالا اضافه کنید.'; ?>
						</td></tr>
					<?php else : ?>
						<?php foreach ( $page_rules as $key => $rule ) : ?>
							<?php
							$status  = (int) $rule['status'];
							$target  = (string) ( $rule['target'] ?? '' );
							$used_at = isset( $hits[ $key ] ) ? human_time_diff( (int) $hits[ $key ] ) . ' پیش' : 'استفاده نشده';
							?>
							<tr<?php echo is_suspicious( (string) $key ) ? ' class="is-suspicious"' : ''; ?>>
								<td>
									<div class="h-route">
										<a href="<?php echo esc_url( build_location( (string) $key ) ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( (string) $key ); ?></code></a>
										<span class="h-route__arrow" aria-hidden="true">←</span>
										<?php if ( '' !== $target ) : ?>
											<a href="<?php echo esc_url( build_location( $target ) ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $target ); ?></code></a>
											<?php if ( is_external( $target ) ) : ?><span class="h-tag">خارجی</span><?php endif; ?>
										<?php else : ?>
											<span class="h-muted"><?php echo esc_html( $labels[ $status ] ?? '' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $rule['auto'] ) ) : ?><span class="h-tag">خودکار</span><?php endif; ?>
										<?php if ( is_suspicious( (string) $key ) ) : ?><span class="h-tag h-tag--danger">مشکوک</span><?php endif; ?>
									</div>
								</td>
								<td><span class="h-badge bg-<?php echo (int) $status; ?>" title="<?php echo esc_attr( $labels[ $status ] ?? '' ); ?>"><?php echo (int) $status; ?></span></td>
								<td class="h-muted"><?php echo esc_html( $used_at ); ?></td>
								<td class="h-actions">
									<a class="h-icon-btn" href="<?php echo esc_url( page_url( [ 'edit' => rawurlencode( (string) $key ) ] ) ); ?>" aria-label="<?php echo esc_attr( 'ویرایش ' . $key ); ?>" title="ویرایش">
										<span class="dashicons <?php echo esc_attr( $icons['edit'] ); ?>" aria-hidden="true"></span>
									</a>
									<a class="h-icon-btn h-icon-btn--danger js-confirm" data-confirm="این قانون حذف شود؟" aria-label="<?php echo esc_attr( 'حذف ' . $key ); ?>" title="حذف"
										href="<?php echo esc_url( wp_nonce_url( page_url( [ 'action' => 'delete', 'rule' => rawurlencode( (string) $key ) ] ), 'hodima_redirect_delete' ) ); ?>">
										<span class="dashicons <?php echo esc_attr( $icons['delete'] ); ?>" aria-hidden="true"></span>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="h-pager">
				<span class="h-muted">
					<?php
					echo esc_html( $total > 0
						? sprintf( 'نمایش %1$s تا %2$s از %3$s قانون', number_format_i18n( $offset + 1 ), number_format_i18n( $offset + count( $page_rules ) ), number_format_i18n( $total ) )
						: 'بدون قانون' );
					?>
					— «آخرین استفاده» حداکثر ساعتی یک بار به‌روز می‌شود.
				</span>
				<?php if ( $pages > 1 ) : ?>
					<div class="h-pagination">
						<?php
						echo wp_kses_post( (string) paginate_links( [
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&rsaquo;',
							'next_text' => '&lsaquo;',
							'total'     => $pages,
							'current'   => $current_page,
						] ) );
						?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<script>
	document.addEventListener('click', function (e) {
		var a = e.target.closest('.js-confirm');
		if (a && !confirm(a.getAttribute('data-confirm'))) e.preventDefault();
	});
	</script>
	<?php
}
