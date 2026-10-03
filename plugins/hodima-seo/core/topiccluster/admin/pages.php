<?php
/**
 * خوشه موضوعی — «ابزارهای هدیما ← خوشه‌بندی»
 * Path: core/topiccluster/admin/pages.php
 *
 * چهار صفحه با تب زیر هدر (فقط اولی در منو؛ آدرس دو صفحه قدیمی همان است):
 *   hodima-tc-orphans   محتوای یتیم: خوشه‌ای (بدون والد) و لینکی (بدون لینک
 *                       ورودی از متن)، با فیلتر نوع، جستجو و کار سریع
 *                       (والد، پیلار، خارج از خوشه) بدون باز کردن ویرایشگر
 *   hodima-tc-map       نقشه درختی: پیلارهای ریشه، زیرپیلارها و فرزندان
 *   hodima-tc-health    سلامت هر خوشه + فهرست لینک‌های داخلی + والدهای نامعتبر
 *   hodima-tc-settings  والد خودکار، نمایش خودکار، هم‌خوشه‌ها، برچسب‌ها
 */

declare(strict_types=1);

namespace Hodima\TopicCluster\Admin;

use Hodima\TopicCluster\Graph;
use Hodima\TopicCluster\Health;
use Hodima\TopicCluster\Kind;
use Hodima\TopicCluster\Links;
use Hodima\TopicCluster\Ref;
use Hodima\TopicCluster\Render;
use Hodima\TopicCluster\Settings;

defined( 'ABSPATH' ) || exit;

final class Pages {

	private const CAP      = 'manage_options';
	private const PER_PAGE = 30;

	private const TABS = [
		'hodima-tc-orphans'  => [ 'محتوای یتیم', 'dashicons-warning' ],
		'hodima-tc-map'      => [ 'نقشه خوشه‌ها', 'dashicons-networking' ],
		'hodima-tc-health'   => [ 'سلامت خوشه‌ها', 'dashicons-heart' ],
		'hodima-tc-settings' => [ 'تنظیمات', 'dashicons-admin-settings' ],
	];

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_head', [ self::class, 'hide_submenus' ] );
		add_filter( 'submenu_file', [ self::class, 'submenu_file' ] );
		add_action( 'admin_init', [ self::class, 'handle_post' ] );
		add_action( 'wp_dashboard_setup', [ self::class, 'dashboard' ] );
	}

	/* =================================================================
	 * منو
	 * ================================================================= */

	/** بدون Hodima Core: منوی سطح اول قبلی «خوشه‌بندی». */
	private static function parent(): string {
		$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';
		return '' !== $parent ? $parent : 'hodima-tc-orphans';
	}

	public static function menu(): void {

		$parent = self::parent();

		if ( 'hodima-tc-orphans' === $parent ) {
			add_menu_page( 'مدیریت خوشه‌های محتوایی', 'خوشه‌بندی', self::CAP, 'hodima-tc-orphans', [ self::class, 'render' ], 'dashicons-networking', 25 );
		}

		foreach ( self::TABS as $slug => [ $label ] ) {
			$menu = ( 'hodima-tc-orphans' === $slug && 'hodima-tc-orphans' !== $parent ) ? 'خوشه‌بندی' : $label;
			add_submenu_page( $parent, $label, $menu, self::CAP, $slug, [ self::class, 'render' ] );
		}
	}

	/** زیر «ابزارهای هدیما» فقط «خوشه‌بندی» در منو بماند (صفحه‌ها در دسترس می‌مانند). */
	public static function hide_submenus(): void {
		if ( 'hodima-tc-orphans' === self::parent() ) {
			return;
		}
		foreach ( array_keys( self::TABS ) as $slug ) {
			if ( 'hodima-tc-orphans' !== $slug ) {
				remove_submenu_page( self::parent(), $slug );
			}
		}
	}

	public static function submenu_file( $file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
		$page = sanitize_key( wp_unslash( (string) ( $_GET['page'] ?? '' ) ) );
		return ( isset( self::TABS[ $page ] ) && 'hodima-tc-orphans' !== self::parent() ) ? 'hodima-tc-orphans' : $file;
	}

	private static function url( string $slug, array $args = [] ): string {
		return add_query_arg( array_merge( [ 'page' => $slug ], $args ), admin_url( 'admin.php' ) );
	}

	/* =================================================================
	 * ذخیره‌ها (Post/Redirect/Get)
	 * ================================================================= */

	public static function handle_post(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer پایین
		if ( isset( $_POST['hodima_tc_settings_submit'] ) ) {

			if ( ! current_user_can( self::CAP ) ) {
				wp_die( 'دسترسی ندارید.', 403 );
			}
			check_admin_referer( 'hodima_tc_settings' );

			Settings::save( is_array( $_POST['hodima_tc'] ?? null ) ? (array) wp_unslash( $_POST['hodima_tc'] ) : [] );
			self::flash( 'تنظیمات خوشه‌بندی ذخیره شد.' );
			wp_safe_redirect( self::url( 'hodima-tc-settings' ) );
			exit;
		}

		if ( isset( $_POST['hodima_tc_links_rebuild'] ) ) {

			if ( ! current_user_can( self::CAP ) ) {
				wp_die( 'دسترسی ندارید.', 403 );
			}
			check_admin_referer( 'hodima_tc_links_rebuild' );

			Links::install();
			Links::rebuild();
			// دسته اول همین الان (بقیه در پس‌زمینه)
			Links::batch();
			self::flash( 'ساخت دوباره فهرست لینک‌های داخلی شروع شد و در پس‌زمینه ادامه دارد.' );
			wp_safe_redirect( self::url( 'hodima-tc-health' ) );
			exit;
		}
		// phpcs:enable
	}

	private static function flash( string $message, string $type = 'success' ): void {
		if ( function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( $message, $type );
		}
	}

	/* =================================================================
	 * قاب صفحه
	 * ================================================================= */

	public static function render(): void {

		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب صفحه
		$page = sanitize_key( wp_unslash( (string) ( $_GET['page'] ?? '' ) ) );
		$page = isset( self::TABS[ $page ] ) ? $page : 'hodima-tc-orphans';

		$titles = [
			'hodima-tc-orphans'  => [ 'محتوای یتیم', 'صفحه‌هایی که به هیچ خوشه‌ای وصل نیستند یا از متن هیچ صفحه‌ای لینک نگرفته‌اند.' ],
			'hodima-tc-map'      => [ 'نقشه خوشه‌های محتوایی', 'همه پیلارها و زیرمجموعه‌هایشان، همان‌طور که بازدیدکننده و گوگل می‌بینند.' ],
			'hodima-tc-health'   => [ 'سلامت خوشه‌ها', 'مشکلات هر خوشه، از بدترین به بهترین: لینک داخلی، noindex، محتوای قدیمی یا کم‌حجم، عنوان‌های رقیب.' ],
			'hodima-tc-settings' => [ 'تنظیمات خوشه‌بندی', 'والد خودکار، نمایش خودکار کادر خوشه و ظاهر آن.' ],
		];

		$tabs = [];
		foreach ( self::TABS as $slug => [ $label, $icon ] ) {
			$tabs[ $slug ] = [ 'label' => $label, 'url' => self::url( $slug ), 'icon' => $icon ];
		}

		echo '<div class="wrap hd-wrap htc-wrap">';

		if ( function_exists( 'hodima_admin_header' ) ) {
			hodima_admin_header( [
				'title'       => $titles[ $page ][0],
				'description' => $titles[ $page ][1],
				'icon'        => 'dashicons-networking',
				'current'     => $page,
				'tabs_label'  => 'بخش‌های خوشه‌بندی',
				'tabs'        => $tabs,
			] );
		} else {
			echo '<h1>' . esc_html( $titles[ $page ][0] ) . '</h1>';
		}

		match ( $page ) {
			'hodima-tc-map'      => self::render_map(),
			'hodima-tc-health'   => self::render_health(),
			'hodima-tc-settings' => self::render_settings(),
			default              => self::render_orphans(),
		};

		echo '</div>';
	}

	private static function icon( string $icon ): string {
		return function_exists( 'hodima_admin_icon' )
			? hodima_admin_icon( $icon )
			: '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
	}

	/** @param array<string, int|string> $stats برچسب ← مقدار */
	private static function stats( array $stats ): void {
		echo '<div class="hd-grid hd-grid--stats htc-stats">';
		foreach ( $stats as $label => $value ) {
			printf(
				'<div class="hd-stat"><span class="hd-stat__label">%s</span><span class="hd-stat__value">%s</span></div>',
				esc_html( $label ),
				esc_html( is_int( $value ) ? number_format_i18n( $value ) : (string) $value )
			);
		}
		echo '</div>';
	}

	/* =================================================================
	 * محتوای یتیم
	 * ================================================================= */

	private static function render_orphans(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نمایش
		$mode   = 'links' === sanitize_key( wp_unslash( (string) ( $_GET['mode'] ?? '' ) ) ) ? 'links' : 'cluster';
		$type   = sanitize_key( wp_unslash( (string) ( $_GET['type'] ?? '' ) ) );
		$search = sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable

		if ( 'links' === $mode ) {
			$result = Health::unlinked( $paged, self::PER_PAGE, $type );
			$rows   = $result['rows'];
			$total  = $result['total'];
		} else {
			$all = Health::orphans();
			if ( '' !== $type ) {
				$all = array_values( array_filter( $all, static fn( array $o ): bool => $o['type'] === $type ) );
			}
			if ( '' !== $search ) {
				$all = array_values( array_filter( $all, static fn( array $o ): bool => false !== mb_stripos( $o['title'], $search ) ) );
			}
			$total = count( $all );
			$rows  = array_slice( $all, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		}

		$refs    = array_map( static fn( array $r ): Ref => new Ref( Kind::from( $r['kind'] ), (int) $r['id'] ), $rows );
		$inbound = Links::inbound_counts( $refs );

		self::stats( [
			'یتیم خوشه‌ای'          => Health::orphan_count(),
			'پیلارها'               => count( Graph::pillars() ),
			'لینک‌های داخلی متن'    => Links::total(),
		] );

		$types = [ '' => 'همه انواع' ];
		foreach ( Graph::post_types() as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$types[ $pt ] = Health::type_label( 'post', $pt );
			}
		}
		if ( 'cluster' === $mode ) {
			foreach ( Graph::taxonomies() as $tax ) {
				if ( taxonomy_exists( $tax ) ) {
					$types[ $tax ] = Health::type_label( 'term', $tax );
				}
			}
		}
		?>
		<div class="hd-card htc-toolbar">
			<nav class="htc-segment" aria-label="نوع گزارش">
				<a href="<?php echo esc_url( self::url( 'hodima-tc-orphans' ) ); ?>" class="htc-segment__item" <?php echo 'cluster' === $mode ? 'aria-current="page"' : ''; ?>>بدون خوشه</a>
				<a href="<?php echo esc_url( self::url( 'hodima-tc-orphans', [ 'mode' => 'links' ] ) ); ?>" class="htc-segment__item" <?php echo 'links' === $mode ? 'aria-current="page"' : ''; ?>>بدون لینک ورودی از متن</a>
			</nav>
			<form method="get" class="htc-filters">
				<input type="hidden" name="page" value="hodima-tc-orphans">
				<?php if ( 'links' === $mode ) : ?><input type="hidden" name="mode" value="links"><?php endif; ?>
				<label class="screen-reader-text" for="htc-type">نوع محتوا</label>
				<select id="htc-type" name="type">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( 'cluster' === $mode ) : ?>
					<label class="screen-reader-text" for="htc-search">جستجوی عنوان</label>
					<input type="search" id="htc-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی عنوان">
				<?php endif; ?>
				<button type="submit" class="button">اعمال</button>
			</form>
			<p class="htc-help">
				<?php if ( 'links' === $mode ) : ?>
					صفحه‌هایی که از متن هیچ نوشته یا دسته دیگری لینک نگرفته‌اند (منو، فوتر و کادرهای خودکار حساب نمی‌شوند). گوگل این صفحه‌ها را کم‌اهمیت می‌بیند.
					<?php if ( ! Links::complete() ) : ?><strong>فهرست لینک‌ها هنوز کامل ساخته نشده؛ نتیجه ناقص است.</strong><?php endif; ?>
				<?php else : ?>
					نه پیلارند، نه «خارج از خوشه» و نه والدی (دستی یا خودکار) دارند.<?php echo Settings::get( 'auto_parent' ) ? ' نوشته‌ای که دسته اصلی‌اش زیر یک دسته پیلار است خودکار عضو خوشه آن است.' : ''; ?>
				<?php endif; ?>
			</p>
		</div>

		<div class="hd-table-wrap htc-table-wrap">
			<table class="wp-list-table widefat striped htc-table" data-hodima-tc-quick>
				<thead>
					<tr>
						<th scope="col">عنوان</th>
						<th scope="col" class="htc-col-type">نوع</th>
						<th scope="col" class="htc-col-num">لینک ورودی</th>
						<th scope="col" class="htc-col-action">افزودن به خوشه</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="4" class="htc-empty">موردی یافت نشد.</td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $i => $row ) :
						$ref     = $refs[ $i ];
						$edit    = Health::edit_link( $row['kind'], (int) $row['id'] );
						$options = self::options_for( $ref, (string) $row['type'] );
						?>
						<tr data-ref="<?php echo esc_attr( $ref->key() ); ?>">
							<td>
								<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( '' !== $row['title'] ? $row['title'] : '(بدون عنوان)' ); ?></a></strong>
								<span class="htc-row-msg" role="status"></span>
							</td>
							<td class="htc-col-type"><span class="htc-pill htc-pill--muted"><?php echo esc_html( Health::type_label( $row['kind'], (string) $row['type'] ) ); ?></span></td>
							<td class="htc-col-num"><?php echo esc_html( number_format_i18n( (int) ( $inbound[ $ref->key() ] ?? 0 ) ) ); ?></td>
							<td class="htc-col-action">
								<div class="htc-quick">
									<?php if ( $options ) : ?>
										<label class="screen-reader-text" for="htc-q-<?php echo esc_attr( (string) $i ); ?>">والد</label>
										<select id="htc-q-<?php echo esc_attr( (string) $i ); ?>" class="htc-quick__parent">
											<option value="">انتخاب پیلار والد…</option>
											<?php foreach ( $options as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="button" class="button button-small" data-do="parent">افزودن</button>
									<?php endif; ?>
									<button type="button" class="button button-small" data-do="pillar">پیلار شود</button>
									<button type="button" class="button-link htc-quick__muted" data-do="exclude">خارج از خوشه</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		self::pagination( $total, $paged );
	}

	/** @return array<string, string> پیلارهای مجاز برای والد این گره */
	private static function options_for( Ref $ref, string $type ): array {

		static $cache = [];

		$key = $ref->kind->value . ':' . $type;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		if ( $ref->is_post() ) {
			return $cache[ $key ] = Lists::pillar_options( $type );
		}

		$options = [];
		foreach ( Graph::pillars() as $pillar ) {
			$term = $pillar->is_term() ? get_term( $pillar->id ) : null;
			if ( $term && ! is_wp_error( $term ) && $term->taxonomy === $type ) {
				$options[ $pillar->key() ] = Editor::label_for( $pillar );
			}
		}
		asort( $options );

		return $cache[ $key ] = $options;
	}

	private static function pagination( int $total, int $current ): void {

		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages < 2 ) {
			return;
		}

		$links = paginate_links( [
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => 'قبلی',
			'next_text' => 'بعدی',
			'total'     => $pages,
			'current'   => $current,
			'type'      => 'array',
		] );

		if ( is_array( $links ) ) {
			echo '<nav class="htc-pagination" aria-label="صفحه‌بندی">' . wp_kses_post( implode( '', $links ) ) . '</nav>';
		}
	}

	/* =================================================================
	 * نقشه درختی
	 * ================================================================= */

	private static function render_map(): void {

		$pillars = Graph::pillars();
		$roots   = array_values( array_filter( $pillars, static fn( Ref $p ): bool => ! Graph::parents( $p ) ) );
		// پیلاری که در حلقه یا زیر پیلار دیگر است ولی ریشه‌ای نرسیده هم دیده شود
		$shown   = [];
		$members = 0;

		foreach ( $pillars as $pillar ) {
			$members += count( Graph::children( $pillar ) );
		}

		self::stats( [
			'پیلارها'          => count( $pillars ),
			'خوشه‌های اصلی'    => count( $roots ),
			'عضو خوشه‌ها'      => $members,
			'یتیم خوشه‌ای'     => Health::orphan_count(),
		] );

		if ( ! $pillars ) {
			echo '<div class="hd-card hd-empty htc-map-empty">' . self::icon( 'dashicons-networking' ) . '<p>هنوز هیچ پیلاری تعریف نشده است. در ویرایشگر یک دسته یا مقاله راهنما، «پیلار (هسته خوشه)» را تیک بزنید.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		echo '<div class="htc-map">';
		foreach ( $roots as $root ) {
			self::tree_card( $root, $shown );
		}
		// باقی‌مانده: پیلارهایی که از هیچ ریشه‌ای دیده نشدند (مثلا حلقه قدیمی)
		foreach ( $pillars as $pillar ) {
			if ( ! isset( $shown[ $pillar->key() ] ) ) {
				self::tree_card( $pillar, $shown );
			}
		}
		echo '</div>';
	}

	/** @param array<string, bool> $shown */
	private static function tree_card( Ref $root, array &$shown ): void {

		$node = Graph::node( $root );
		if ( null === $node ) {
			$shown[ $root->key() ] = true;
			return;
		}

		$count = count( Graph::children( $root ) );
		?>
		<details class="hd-card htc-tree-card" open>
			<summary class="htc-tree-card__head">
				<span class="htc-tree-card__title"><?php echo esc_html( $node['title'] ); ?></span>
				<span class="htc-pill htc-pill--muted"><?php echo esc_html( Health::type_label( $node['kind'], $node['type'] ) ); ?></span>
				<span class="htc-pill htc-pill--ok"><?php echo esc_html( number_format_i18n( $count ) . ' زیرمجموعه' ); ?></span>
				<?php if ( ! Render::has_box( $root ) ) : ?><span class="htc-pill htc-pill--warn">کادر خوشه نمایش داده نمی‌شود</span><?php endif; ?>
				<a class="htc-tree-card__edit" href="<?php echo esc_url( Health::edit_link( $node['kind'], (int) $node['id'] ) ); ?>">ویرایش</a>
			</summary>
			<?php self::tree( $root, $shown, 0 ); ?>
		</details>
		<?php
	}

	/** @param array<string, bool> $shown */
	private static function tree( Ref $pillar, array &$shown, int $depth ): void {

		$shown[ $pillar->key() ] = true;
		$children                = Graph::children( $pillar );

		if ( ! $children ) {
			echo '<p class="htc-empty">زیرمجموعه‌ای ندارد.</p>';
			return;
		}

		echo '<ul class="htc-tree">';
		foreach ( $children as $child ) {

			$ref       = new Ref( Kind::from( $child['kind'] ), (int) $child['id'] );
			$is_pillar = Graph::is_pillar( $ref );
			?>
			<li class="htc-tree__item <?php echo $is_pillar ? 'is-pillar' : ''; ?>">
				<a href="<?php echo esc_url( Health::edit_link( $child['kind'], (int) $child['id'] ) ); ?>"><?php echo esc_html( $child['title'] ); ?></a>
				<span class="htc-pill htc-pill--muted"><?php echo esc_html( Health::type_label( $child['kind'], $child['type'] ) ); ?></span>
				<?php if ( 'auto' === ( $child['source'] ?? '' ) ) : ?><span class="htc-pill htc-pill--muted">خودکار</span><?php endif; ?>
				<?php if ( $child['noindex'] ) : ?><span class="htc-pill htc-pill--warn">noindex</span><?php endif; ?>
				<?php if ( $is_pillar ) : ?><span class="htc-pill htc-pill--ok">پیلار</span><?php endif; ?>
				<?php
				if ( $is_pillar && $depth < 6 && ! isset( $shown[ $ref->key() ] ) ) {
					self::tree( $ref, $shown, $depth + 1 );
				}
				?>
			</li>
			<?php
		}
		echo '</ul>';
	}

	/* =================================================================
	 * سلامت
	 * ================================================================= */

	private static function render_health(): void {

		$report = Health::report();
		$state  = Links::state();
		$totals = $report['totals'];

		$errors = 0;
		$warns  = 0;
		foreach ( $totals as $code => $n ) {
			if ( 'error' === Health::LEVELS[ $code ] ) {
				$errors += $n;
			} elseif ( 'warn' === Health::LEVELS[ $code ] ) {
				$warns += $n;
			}
		}

		$avg = $report['clusters'] ? (int) round( array_sum( array_column( $report['clusters'], 'score' ) ) / count( $report['clusters'] ) ) : 0;

		self::stats( [
			'خوشه‌ها'           => count( $report['clusters'] ),
			'میانگین امتیاز'    => $report['clusters'] ? $avg . '٪' : '—',
			'مشکل جدی'          => $errors,
			'هشدار'             => $warns,
		] );
		?>
		<section class="hd-card htc-links-card">
			<header class="hd-card__head">
				<?php echo self::icon( 'dashicons-admin-links' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2 class="hd-card__title">فهرست لینک‌های داخلی متن</h2>
			</header>
			<p class="hd-card__desc">
				<?php if ( Links::complete() ) : ?>
					کامل — <?php echo esc_html( number_format_i18n( Links::total() ) ); ?> لینک داخلی از <?php echo esc_html( number_format_i18n( (int) $state['done'] ) ); ?> صفحه؛ آخرین ساخت کامل: <?php echo esc_html( wp_date( 'j F Y، H:i', (int) $state['built'] ) ); ?>. بعد از هر ذخیره نوشته یا دسته خودکار به‌روز می‌شود.
				<?php elseif ( in_array( $state['phase'], [ 'post', 'term' ], true ) ) : ?>
					در حال ساخت در پس‌زمینه (<?php echo esc_html( number_format_i18n( (int) $state['done'] ) ); ?> صفحه تا اینجا). بررسی «لینک متن به پیلار» بعد از اتمام فعال می‌شود.
				<?php else : ?>
					هنوز ساخته نشده است.
				<?php endif; ?>
			</p>
			<form method="post" class="hd-actions">
				<?php wp_nonce_field( 'hodima_tc_links_rebuild' ); ?>
				<button type="submit" name="hodima_tc_links_rebuild" value="1" class="button">ساخت دوباره فهرست</button>
			</form>
		</section>

		<?php if ( ! $report['clusters'] ) : ?>
			<div class="hd-card hd-empty"><?php echo self::icon( 'dashicons-heart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><p>هنوز خوشه‌ای برای بررسی نیست.</p></div>
		<?php endif; ?>

		<?php foreach ( $report['clusters'] as $cluster ) :
			$pillar = $cluster['pillar'];
			$level  = $cluster['score'] >= 85 ? 'ok' : ( $cluster['score'] >= 60 ? 'warn' : 'error' );
			$bad    = array_values( array_filter( $cluster['children'], static fn( array $c ): bool => (bool) $c['issues'] ) );
			?>
			<details class="hd-card htc-health-card" <?php echo 'ok' !== $level ? 'open' : ''; ?>>
				<summary class="htc-tree-card__head">
					<span class="htc-score htc-score--<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $cluster['score'] . '٪' ); ?></span>
					<span class="htc-tree-card__title"><?php echo esc_html( $pillar['title'] ); ?></span>
					<span class="htc-pill htc-pill--muted"><?php echo esc_html( number_format_i18n( count( $cluster['children'] ) ) . ' زیرمجموعه' ); ?></span>
					<a class="htc-tree-card__edit" href="<?php echo esc_url( Health::edit_link( $pillar['kind'], (int) $pillar['id'] ) ); ?>">ویرایش پیلار</a>
				</summary>

				<?php if ( $cluster['issues'] ) : ?>
					<ul class="htc-issues">
						<?php foreach ( $cluster['issues'] as $code ) : ?>
							<li class="htc-issue htc-issue--<?php echo esc_attr( Health::LEVELS[ $code ] ); ?>"><?php echo esc_html( Health::label( $code ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $bad ) : ?>
					<div class="hd-table-wrap">
						<table class="widefat striped htc-table">
							<thead><tr><th scope="col">زیرمجموعه</th><th scope="col">مشکل</th></tr></thead>
							<tbody>
								<?php foreach ( $bad as $row ) : ?>
									<tr>
										<td><a href="<?php echo esc_url( Health::edit_link( $row['node']['kind'], (int) $row['node']['id'] ) ); ?>"><?php echo esc_html( $row['node']['title'] ); ?></a></td>
										<td>
											<ul class="htc-issues htc-issues--inline">
												<?php foreach ( $row['issues'] as $code ) : ?>
													<li class="htc-issue htc-issue--<?php echo esc_attr( Health::LEVELS[ $code ] ); ?>">
														<?php echo esc_html( Health::label( $code ) ); ?>
														<?php if ( 'similar_titles' === $code && ! empty( $row['similar'] ) ) : ?>
															<small>(«<?php echo esc_html( (string) $row['similar'] ); ?>»)</small>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php elseif ( ! $cluster['issues'] ) : ?>
					<p class="htc-ok"><?php echo self::icon( 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> بدون مشکل.</p>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>

		<?php if ( $report['invalid'] ) : ?>
			<section class="hd-card hd-card--danger">
				<header class="hd-card__head">
					<?php echo self::icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">والدهای دستی نامعتبر (<?php echo esc_html( number_format_i18n( count( $report['invalid'] ) ) ); ?>)</h2>
				</header>
				<p class="hd-card__desc">این والدها نادیده گرفته می‌شوند (و اگر «والد خودکار» روشن باشد، والد خودکار جایشان می‌آید). در ویرایشگر همان صفحه والد را اصلاح یا حذف کنید.</p>
				<div class="hd-table-wrap">
					<table class="widefat striped htc-table">
						<thead><tr><th scope="col">صفحه</th><th scope="col">شناسه والد</th><th scope="col">مشکل</th></tr></thead>
						<tbody>
							<?php foreach ( array_slice( $report['invalid'], 0, 200 ) as $row ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( Health::edit_link( $row['child']['kind'], (int) $row['child']['id'] ) ); ?>"><?php echo esc_html( $row['child']['title'] ); ?></a></td>
									<td><?php echo esc_html( ( 'term' === $row['parent_kind'] ? 'دسته ' : 'نوشته ' ) . '#' . $row['parent_id'] ); ?></td>
									<td><?php echo esc_html( Graph::problem_label( $row['problem'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>
		<?php
	}

	/* =================================================================
	 * تنظیمات
	 * ================================================================= */

	private static function render_settings(): void {

		$s = Settings::all();
		?>
		<form method="post" action="<?php echo esc_url( self::url( 'hodima-tc-settings' ) ); ?>" class="hd-body">
			<?php wp_nonce_field( 'hodima_tc_settings' ); ?>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo self::icon( 'dashicons-networking' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">ساختار خوشه</h2>
				</header>
				<div class="hd-fields">
					<div class="hd-field hd-field--wide">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_tc[auto_parent]" value="1" <?php checked( $s['auto_parent'] ); ?>>
							<span>والد خودکار از سلسله‌مراتب وردپرس</span>
						</label>
						<p class="hd-field__help">صفحه‌ای که والد دستی ندارد، خودکار زیر نزدیک‌ترین پیلار می‌رود: نوشته و محصول ← دسته اصلی‌شان (یا والد آن دسته)، زیردسته ← دسته والد، زیربرگه ← برگه والد. والد دستی همیشه مقدم است و «خارج از خوشه» هم والد خودکار ندارد.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo self::icon( 'dashicons-visibility' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">نمایش کادر خوشه</h2>
				</header>
				<div class="hd-fields">
					<div class="hd-field hd-field--wide">
						<span class="hd-field__label">نمایش خودکار (بدون شورت‌کد)</span>
						<div class="hd-choices">
							<?php foreach ( Settings::AUTO_PLACES as $place => $label ) : ?>
								<label class="hd-toggle">
									<input type="checkbox" class="hd-switch" role="switch" name="hodima_tc[auto_insert][<?php echo esc_attr( $place ); ?>]" value="1" <?php checked( ! empty( $s['auto_insert'][ $place ] ) ); ?>>
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="hd-field__help">اگر شورت‌کد <code>[hodima_topic_cluster]</code> در متن باشد، کادر همان‌جا و فقط یک بار نمایش داده می‌شود.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-paragraph">جایگاه در نوشته‌ها: بعد از پاراگراف</label>
						<input type="number" min="0" max="50" id="htc-paragraph" name="hodima_tc[paragraph]" value="<?php echo esc_attr( (string) $s['paragraph'] ); ?>">
						<p class="hd-field__help">۰ یعنی انتهای محتوا.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-heading">تگ عنوان بخش‌ها</label>
						<select id="htc-heading" name="hodima_tc[heading]">
							<?php foreach ( Settings::HEADINGS as $tag ) : ?>
								<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $s['heading'], $tag ); ?>><?php echo esc_html( 'p' === $tag ? 'متن ساده (بدون سرتیتر)' : strtoupper( $tag ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-layout">چیدمان زیرمجموعه‌ها</label>
						<select id="htc-layout" name="hodima_tc[layout]">
							<option value="grid" <?php selected( $s['layout'], 'grid' ); ?>>شبکه‌ای (کارت)</option>
							<option value="list" <?php selected( $s['layout'], 'list' ); ?>>فهرست ساده</option>
						</select>
					</div>
					<div class="hd-field hd-field--wide">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_tc[show_children]" value="1" <?php checked( $s['show_children'] ); ?>>
							<span>روی پیلار: فهرست زیرمجموعه‌ها</span>
						</label>
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_tc[show_siblings]" value="1" <?php checked( $s['show_siblings'] ); ?>>
							<span>روی زیرمجموعه‌ها: «مطالب هم‌خوشه» (لینک مقاله‌های یک خوشه به هم)</span>
						</label>
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_tc[hide_noindex]" value="1" <?php checked( $s['hide_noindex'] ); ?>>
							<span>لینک به صفحه‌های noindex در کادر نمایش داده نشود</span>
						</label>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-climit">حداکثر زیرمجموعه در کادر</label>
						<input type="number" min="0" max="<?php echo esc_attr( (string) Graph::MAX_CHILDREN ); ?>" id="htc-climit" name="hodima_tc[children_limit]" value="<?php echo esc_attr( (string) $s['children_limit'] ); ?>">
						<p class="hd-field__help">۰ یعنی همه (تا <?php echo esc_html( number_format_i18n( Graph::MAX_CHILDREN ) ); ?>). اسکیما و سایت‌مپ همیشه همه را دارند.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-slimit">تعداد مطالب هم‌خوشه</label>
						<input type="number" min="1" max="24" id="htc-slimit" name="hodima_tc[siblings_limit]" value="<?php echo esc_attr( (string) $s['siblings_limit'] ); ?>">
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-lp">عنوان بخش والد</label>
						<input type="text" id="htc-lp" name="hodima_tc[label_parents]" value="<?php echo esc_attr( (string) $s['label_parents'] ); ?>" placeholder="خودکار: «دسته‌بندی مرجع» / «راهنمای مرجع»">
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-lc">عنوان بخش زیرمجموعه‌ها</label>
						<input type="text" id="htc-lc" name="hodima_tc[label_children]" value="<?php echo esc_attr( (string) $s['label_children'] ); ?>">
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-ls">عنوان بخش هم‌خوشه‌ها</label>
						<input type="text" id="htc-ls" name="hodima_tc[label_siblings]" value="<?php echo esc_attr( (string) $s['label_siblings'] ); ?>">
					</div>
				</div>
			</section>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo self::icon( 'dashicons-heart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">معیارهای گزارش سلامت</h2>
				</header>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="htc-stale">«قدیمی» یعنی به‌روزنشده بیش از (ماه)</label>
						<input type="number" min="1" max="60" id="htc-stale" name="hodima_tc[stale_months]" value="<?php echo esc_attr( (string) $s['stale_months'] ); ?>">
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="htc-thin">«کم‌حجم» یعنی کمتر از (کلمه)</label>
						<input type="number" min="0" max="5000" id="htc-thin" name="hodima_tc[thin_words]" value="<?php echo esc_attr( (string) $s['thin_words'] ); ?>">
						<p class="hd-field__help">۰ = بررسی نشود.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo self::icon( 'dashicons-editor-help' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">راهنمای شورت‌کد</h2>
				</header>
				<ul class="hd-list">
					<li><code>[hodima_topic_cluster]</code> کادر خوشه همین صفحه: مرجع (پیلار)، زیرمجموعه‌ها یا مطالب هم‌خوشه.</li>
					<li><code>show="parents,children,siblings"</code> فقط بخش‌های دلخواه · <code>limit="8"</code> حداکثر لینک هر بخش.</li>
					<li><code>heading="h3"</code> تگ عنوان · <code>layout="list"</code> فهرست ساده به جای کارت.</li>
					<li><code>id="12" type="term"</code> نمایش خوشه دسته یا صفحه‌ای دیگر.</li>
				</ul>
			</section>

			<div class="hd-actions">
				<button type="submit" name="hodima_tc_settings_submit" value="1" class="button button-primary">ذخیره تنظیمات</button>
			</div>
		</form>
		<?php
	}

	/* =================================================================
	 * ابزارک پیشخوان
	 * ================================================================= */

	public static function dashboard(): void {
		if ( current_user_can( self::CAP ) ) {
			wp_add_dashboard_widget( 'hodima_tc_dashboard_widget', 'وضعیت خوشه‌بندی محتوا', [ self::class, 'dashboard_render' ] );
		}
	}

	public static function dashboard_render(): void {
		?>
		<div class="htc-widget">
			<p class="htc-widget__count"><?php echo esc_html( number_format_i18n( Health::orphan_count() ) ); ?></p>
			<p class="htc-widget__text">محتوای یتیم (بدون خوشه)</p>
			<p class="htc-widget__links">
				<a href="<?php echo esc_url( self::url( 'hodima-tc-orphans' ) ); ?>" class="button button-primary">ساماندهی محتوا</a>
				<a href="<?php echo esc_url( self::url( 'hodima-tc-health' ) ); ?>" class="button">سلامت خوشه‌ها</a>
			</p>
		</div>
		<?php
	}
}
