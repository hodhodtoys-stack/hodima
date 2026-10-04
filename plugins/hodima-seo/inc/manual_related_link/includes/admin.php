<?php
/**
 * لینک‌های مرتبط دستی — کادر ویرایشگر (نوشته، محصول، برگه، دسته و برچسب)
 * Path: plugins/hodima-seo/inc/manual_related_link/includes/admin.php
 *
 * نسخه ۱: سه خانه ثابت که آدرس باید دستی کپی و چسبانده می‌شد، بدون هیچ
 * هشداری برای لینک خراب یا کارت بی‌تصویر (که بی‌صدا در سایت حذف می‌شد).
 * حالا: جستجوی زنده مقصد، عنوان و تصویر خودکار از مقصد و هشدار هر خانه
 * (پیش‌نویس، noindex، ریدایرکت، لینک به خود…). از ۲.۳ دو گروه در یک ردیف،
 * بدون شماره، جابه‌جایی و دکمه «افزودن خانه ذخیره».
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

use WP_Post;
use WP_Query;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Admin {

	private const NONCE        = 'hodima_rl_nonce';
	private const NONCE_ACTION = 'hodima_rl_save';
	private const AJAX_NONCE   = 'hodima_rl_search';
	private const FIELD        = 'hodima_rl';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ] );
		add_action( 'save_post', [ self::class, 'save_post' ], 10, 2 );
		add_action( 'init', [ self::class, 'register_term_hooks' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'wp_ajax_hodima_rl_search', [ self::class, 'ajax_search' ] );
		add_action( 'admin_init', [ self::class, 'migrate' ] );
	}

	/* =====================================================================
	 * کادر نوشته و ترم
	 * ===================================================================== */

	public static function add_meta_box(): void {
		add_meta_box( 'hodima_internal_links_box', 'لینک‌های مرتبط', [ self::class, 'render_meta_box' ], Store::post_types(), 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE );
		self::render_box( $post->ID, 'post' );
	}

	public static function register_term_hooks(): void {
		foreach ( Store::taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form", [ self::class, 'render_term_box' ], 9, 2 );
			add_action( "edited_{$taxonomy}", [ self::class, 'save_term' ], 10, 2 );
		}
	}

	public static function render_term_box( mixed $term, string $taxonomy = '' ): void {

		if ( ! $term instanceof WP_Term ) {
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE );
		?>
		<div class="postbox hodima-rl-postbox">
			<div class="postbox-header"><h2 class="hndle">لینک‌های مرتبط</h2></div>
			<div class="inside"><?php self::render_box( $term->term_id, 'term' ); ?></div>
		</div>
		<?php
	}

	private static function render_box( int $object_id, string $context ): void {

		$groups = Store::get( $object_id, $context );

		/*
		 * همه خانه‌ها (۲ پیشنهاد خرید + ۱ مقاله) در یک ردیف با عرض و ارتفاع
		 * یکسان (خواسته کاربر، ۲.۴): ردیف به تعداد کل خانه‌ها ستون دارد و هر
		 * گروه به تعداد خانه‌هایش ستون می‌گیرد (--rl-span).
		 */
		$spans = [];
		foreach ( Group::cases() as $group ) {
			$spans[ $group->value ] = max( Store::count( $group ), count( $groups[ $group->value ] ) );
		}
		?>
		<div class="hodima-rl-box">
			<?php if ( Store::has_legacy( $object_id, $context ) ) : ?>
				<p class="hodima-rl-box__legacy">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					لینک‌های «ویترین پیشنهادی» نسخه قبل بر اساس نوع مقصد در گروه‌های زیر چیده شده‌اند و با ذخیره همین صفحه به ساختار جدید منتقل می‌شوند.
				</p>
			<?php endif; ?>

			<div class="hodima-rl-groups" style="--rl-cols: <?php echo (int) array_sum( $spans ); ?>">
				<?php foreach ( Group::cases() as $group ) : ?>
					<?php self::render_group( $group, $groups[ $group->value ], $object_id, $context, $spans[ $group->value ] ); ?>
				<?php endforeach; ?>
			</div>

			<p class="hodima-rl-box__foot">
				تعداد، عنوان و نمایش خودکار هر گروه:
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminPage::SLUG ) ); ?>">تنظیمات لینک‌های مرتبط</a>
			</p>
		</div>
		<?php
	}

	/** @param list<array<string, mixed>> $items */
	private static function render_group( Group $group, array $items, int $object_id, string $context, int $slots ): void {

		$count = Store::count( $group );
		$auto  = Store::auto( $group );
		// شورت‌کدها مستقل‌اند: گروهی که لینک دارد ولی شورت‌کدش در متن نیست
		// (و نمایش خودکار خاموش است) در سایت هیچ‌جا دیده نمی‌شود.
		$hidden = $items && ! in_array( $group, Front::placed_groups( Front::source_text( $object_id, $context ) ), true );
		$gid    = wp_unique_id( 'hodima-rl-group-' );

		/*
		 * section به جای fieldset (۲.۴): legend در گرید شرکت نمی‌کند و برای
		 * هم‌ارتفاع کردن سر و خانه‌های دو گروه (subgrid) لازم است بخش بالا یک
		 * عنصر معمولی باشد. role="group" همان معنای fieldset را دارد.
		 */
		?>
		<section class="hodima-rl-group" role="group" aria-labelledby="<?php echo esc_attr( $gid ); ?>" data-group="<?php echo esc_attr( $group->value ); ?>" style="--rl-span: <?php echo (int) $slots; ?>">
			<div class="hodima-rl-group__top">
			<div class="hodima-rl-group__head">
				<span class="dashicons <?php echo esc_attr( $group->icon() ); ?>" aria-hidden="true"></span>
				<span class="hodima-rl-group__title" id="<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $group->label() ); ?></span>
				<code class="hodima-rl-group__code">[<?php echo esc_html( $group->shortcode() ); ?>]</code>
				<button type="button" class="button-link hodima-rl-copy" data-copy="[<?php echo esc_attr( $group->shortcode() ); ?>]">کپی شورت‌کد</button>
			</div>
			<?php if ( $auto ) : ?>
				<p class="hodima-rl-group__help">نمایش خودکار روشن است؛ شورت‌کد لازم نیست.</p>
			<?php endif; ?>
			<?php if ( $hidden ) : ?>
				<p class="hodima-rl-group__notplaced">
					<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
					<?php
					printf(
						'این لینک‌ها فعلا در سایت دیده نمی‌شوند: شورت‌کد %s در متن %s نیست و نمایش خودکار این گروه خاموش است. شورت‌کد را هر جای متن که می‌خواهید بگذارید (این پیام بعد از ذخیره به‌روز می‌شود).',
						'<code>[' . esc_html( $group->shortcode() ) . ']</code>',
						'term' === $context ? 'توضیح این دسته' : 'این صفحه'
					);
					?>
				</p>
			<?php endif; ?>
			</div>
			<ol class="hodima-rl-slots">
				<?php
				$seen = [];
				for ( $i = 0; $i < $slots; $i++ ) {
					$item = $items[ $i ] ?? null;
					$key  = $item ? $item['kind'] . ':' . ( $item['id'] ?: $item['url'] ) : '';
					self::render_slot( $group, $i, $item, $count, $object_id, $context, '' !== $key && isset( $seen[ $key ] ) );
					$seen[ $key ] = true;
				}
				?>
			</ol>
		</section>
		<?php
	}

	/** @param array<string, mixed>|null $item */
	private static function render_slot( Group $group, int $index, ?array $item, int $count, int $object_id, string $context, bool $duplicate = false ): void {

		$resolved = $item ? Store::resolve( $item, $object_id, $context ) : null;
		$name     = sprintf( '%s[%s][%d]', self::FIELD, $group->value, $index );
		$thumb    = $resolved && $resolved['img_id'] ? (string) wp_get_attachment_image_url( (int) $resolved['img_id'], 'thumbnail' ) : '';
		$uid      = wp_unique_id( 'hodima-rl-' );
		?>
		<li class="hodima-rl-slot<?php echo $index >= $count ? ' is-reserve' : ''; ?>" data-filled="<?php echo $item ? '1' : '0'; ?>">
			<?php
			/*
			 * نسخه ۲.۳: شماره خانه، دستگیره کشیدن و دکمه‌های بالا/پایین و
			 * «افزودن خانه ذخیره» حذف شد (خواسته کاربر: اضافی بودند). خانه
			 * اضافه فقط وقتی هست که از قبل لینکی بیش از تعداد نمایش ذخیره شده
			 * (مثلا مهاجرت ویترین قدیمی یا کم کردن تعداد)؛ آن‌جا برچسب «ذخیره».
			 */
			if ( $index >= $count ) :
				?>
				<span class="hodima-rl-slot__badge">ذخیره — فقط اگر لینک بالایی نمایش داده نشود</span>
			<?php endif; ?>

			<input type="hidden" data-field="kind" name="<?php echo esc_attr( $name ); ?>[kind]" value="<?php echo esc_attr( $item['kind'] ?? '' ); ?>">
			<input type="hidden" data-field="id" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( (string) ( $item['id'] ?? '' ) ); ?>">
			<input type="hidden" data-field="url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $item['url'] ?? '' ); ?>">

			<div class="hodima-rl-target"<?php echo $item ? '' : ' hidden'; ?>>
				<span class="hodima-rl-target__thumb"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt=""><?php endif; ?></span>
				<span class="hodima-rl-target__body">
					<strong class="hodima-rl-target__title"><?php echo esc_html( $resolved['title'] ?? '' ); ?></strong>
					<span class="hodima-rl-target__meta">
						<span class="hodima-rl-target__type"><?php echo esc_html( $resolved['type_label'] ?? '' ); ?></span>
						<bdi class="hodima-rl-target__url" dir="ltr"><?php echo esc_html( rawurldecode( (string) ( $resolved['url'] ?? '' ) ) ); ?></bdi>
					</span>
				</span>
				<button type="button" class="button-link hodima-rl-clear" aria-label="پاک کردن" title="پاک کردن"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</div>

			<div class="hodima-rl-picker"<?php echo $item ? ' hidden' : ''; ?>>
				<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-q"><?php echo esc_html( $group->search_label() ); ?></label>
				<input type="search" id="<?php echo esc_attr( $uid ); ?>-q" class="hodima-rl-search" autocomplete="off"
					role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-list"
					placeholder="جستجو">
				<ul class="hodima-rl-results" id="<?php echo esc_attr( $uid ); ?>-list" role="listbox" hidden></ul>
				<p class="hodima-rl-status" role="status" aria-live="polite"></p>
			</div>

			<?php
			/*
			 * ۲.۴ (خواسته کاربر): «عنوان دلخواه» همیشه باز است (بدون دکمه
			 * باز/بسته) و انتخاب «تصویر دلخواه» حذف شد. تصویر دلخواهی که از قبل
			 * ذخیره شده (مثلا کاور ویترین نسخه ۱) در فیلد مخفی می‌ماند تا داده
			 * پاک نشود؛ با «پاک کردن» مقصد خالی می‌شود.
			 */
			?>
			<p class="hodima-rl-more">
				<label for="<?php echo esc_attr( $uid ); ?>-title">عنوان روی کارت (متن لینک)</label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-title" data-field="title" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" placeholder="خالی = عنوان خود مقصد">
				<input type="hidden" data-field="img_id" name="<?php echo esc_attr( $name ); ?>[img_id]" value="<?php echo esc_attr( (string) ( $item['img_id'] ?? '' ) ); ?>">
			</p>

			<ul class="hodima-rl-warnings">
				<?php if ( $duplicate ) : ?>
					<li class="is-warning">تکراری در همین گروه؛ در سایت فقط یک بار نمایش داده می‌شود</li>
				<?php endif; ?>
				<?php foreach ( $resolved['problems'] ?? [] as $code ) : ?>
					<li class="<?php echo Store::is_hiding( $code ) ? 'is-error' : 'is-warning'; ?>">
						<?php echo esc_html( Store::problem_label( $code ) . ( Store::is_hiding( $code ) ? ' — در سایت نمایش داده نمی‌شود' : '' ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</li>
		<?php
	}

	/* =====================================================================
	 * ذخیره
	 * ===================================================================== */

	public static function save_post( int $post_id, mixed $post ): void {

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, Store::post_types(), true ) || ! self::verify_nonce() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		Store::save( $post_id, 'post', self::posted_groups() );
	}

	public static function save_term( int $term_id, int $tt_id = 0 ): void {

		if ( ! self::verify_nonce() ) {
			return;
		}

		// تکسونومی از خود ترم خوانده می‌شود، نه از درخواست (مثل نسخه ۱.۸).
		$term     = get_term( $term_id );
		$taxonomy = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return;
		}

		Store::save( $term_id, 'term', self::posted_groups() );
	}

	private static function verify_nonce(): bool {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * فرم ← گروه‌ها. آدرس چسبانده‌شده اگر به نوشته/ترمی برسد به شناسه تبدیل
	 * می‌شود تا با تغییر نامک خراب نشود.
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function posted_groups(): array {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در verify_nonce بررسی شده
		$raw    = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : [];
		$groups = [];

		foreach ( Group::cases() as $group ) {

			$rows = (array) ( $raw[ $group->value ] ?? [] );
			ksort( $rows, SORT_NUMERIC );
			$items = [];

			foreach ( $rows as $row ) {
				$row  = (array) $row;
				$item = [
					'kind'   => sanitize_key( (string) ( $row['kind'] ?? '' ) ),
					'id'     => absint( $row['id'] ?? 0 ),
					'url'    => esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) ),
					'title'  => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
					'img_id' => absint( $row['img_id'] ?? 0 ),
				];

				if ( 'url' === $item['kind'] && '' !== $item['url'] ) {
					$target = Store::resolve_url( $item['url'] );
					if ( $target ) {
						$item = array_merge( $item, $target, [ 'url' => '' ] );
					}
				}

				$item = Store::normalize_item( $item );
				if ( $item ) {
					$items[] = $item;
				}
			}

			$groups[ $group->value ] = $items;
		}

		return $groups;
	}

	/* =====================================================================
	 * جستجوی زنده (AJAX)
	 * ===================================================================== */

	public static function ajax_search(): void {

		check_ajax_referer( self::AJAX_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'دسترسی ندارید.' ], 403 );
		}

		$group = Group::tryFrom( sanitize_key( (string) wp_unslash( $_GET['group'] ?? '' ) ) );
		$query = trim( sanitize_text_field( (string) wp_unslash( $_GET['q'] ?? '' ) ) );
		$self  = [ absint( $_GET['self_id'] ?? 0 ), 'term' === ( $_GET['self_ctx'] ?? '' ) ? 'term' : 'post' ];

		if ( ! $group ) {
			wp_send_json_error( [ 'message' => 'گروه نامعتبر است.' ], 400 );
		}

		$raw_q = trim( (string) wp_unslash( $_GET['q'] ?? '' ) );

		// آدرس چسبانده‌شده
		if ( preg_match( '#^(https?://|/)#i', $raw_q ) ) {
			$url    = esc_url_raw( $raw_q );
			$target = Store::resolve_url( $url );
			$item   = $target
				? self::result( [ 'kind' => $target['kind'], 'id' => $target['id'], 'url' => '', 'title' => '', 'img_id' => 0 ], $self )
				: self::result( [ 'kind' => 'url', 'id' => 0, 'url' => $url, 'title' => '', 'img_id' => 0 ], $self );
			wp_send_json_success( $item ? [ $item ] : [] );
		}

		$items = [];

		$types = Store::search_scope( $group );
		$ids   = [];

		// کد محصول (SKU) — برای فروشگاه عمده رایج‌ترین راه پیدا کردن محصول
		if ( '' !== $query && in_array( 'product', $types, true ) ) {
			$ids = get_posts( [
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'meta_query'     => [ [ 'key' => '_sku', 'value' => $query ] ], // phpcs:ignore WordPress.DB.SlowDBQuery
			] );
		}

		$found = new WP_Query( [
			'post_type'              => $types,
			'post_status'            => 'publish',
			'posts_per_page'         => 12,
			's'                      => $query,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'orderby'                => '' === $query ? 'modified' : 'relevance',
		] );

		foreach ( array_unique( array_merge( $ids, $found->posts ) ) as $id ) {
			$items[] = [ 'kind' => 'post', 'id' => (int) $id ];
		}

		$results = [];
		foreach ( $items as $item ) {
			$result = self::result( $item + [ 'url' => '', 'title' => '', 'img_id' => 0 ], $self );
			if ( $result && ! in_array( 'self', $result['problems'], true ) ) {
				$results[] = $result;
			}
		}

		wp_send_json_success( array_slice( $results, 0, 12 ) );
	}

	/**
	 * @param array<string, mixed>      $item
	 * @param array{0:int, 1:string}    $self
	 * @return array<string, mixed>|null
	 */
	private static function result( array $item, array $self ): ?array {

		$item = Store::normalize_item( $item );
		if ( ! $item ) {
			return null;
		}
		$r = Store::resolve( $item, $self[0], $self[1] );

		return [
			'kind'     => $item['kind'],
			'id'       => $item['id'],
			'url'      => 'url' === $item['kind'] ? $item['url'] : '',
			'title'    => (string) $r['title'],
			'link'     => rawurldecode( (string) $r['url'] ),
			'type'     => (string) $r['type_label'],
			'thumb'    => $r['img_id'] ? (string) wp_get_attachment_image_url( (int) $r['img_id'], 'thumbnail' ) : '',
			'problems' => $r['problems'],
			'warnings' => array_map(
				static fn( string $code ): array => [ 'text' => Store::problem_label( $code ), 'hiding' => Store::is_hiding( $code ) ],
				$r['problems']
			),
		];
	}

	/* =====================================================================
	 * دارایی‌ها و مهاجرت
	 * ===================================================================== */

	public static function enqueue(): void {

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// فقط صفحه ویرایش (نه فهرست نوشته‌ها).
		$is_post = 'post' === $screen->base && in_array( $screen->post_type, Store::post_types(), true );
		$is_term = 'term' === $screen->base && in_array( $screen->taxonomy, Store::taxonomies(), true );
		if ( ! $is_post && ! $is_term ) {
			return;
		}

		$base = HODIMA_SEO_URL . '/inc/manual_related_link/assets/';
		wp_enqueue_style( 'hodima-rl-admin', $base . 'admin.css', [ 'dashicons' ], VERSION );
		// بدون کتابخانه رسانه (wp_enqueue_media): از ۲.۴ انتخاب تصویر دلخواه نیست.
		wp_enqueue_script( 'hodima-rl-admin', $base . 'admin.js', [], VERSION, [ 'in_footer' => true ] );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط شناسه صفحه جاری
		$self_id  = $is_post ? absint( $_GET['post'] ?? get_the_ID() ) : absint( $_GET['tag_ID'] ?? 0 );
		// phpcs:enable

		wp_localize_script( 'hodima-rl-admin', 'hodimaRL', [
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::AJAX_NONCE ),
			'selfId'  => $self_id,
			'selfCtx' => $is_term ? 'term' : 'post',
			'i18n'    => [
				'searching'   => 'در حال جستجو…',
				'noResults'   => 'چیزی پیدا نشد. می‌توانید آدرس کامل را بچسبانید.',
				'error'       => 'جستجو انجام نشد؛ دوباره تلاش کنید.',
				'copied'      => 'کپی شد',
				'hidden'      => ' — در سایت نمایش داده نمی‌شود',
				'unsaved'     => 'برای اعمال، صفحه را ذخیره/به‌روزرسانی کنید.',
			],
		] );
	}

	/**
	 * مهاجرت یک‌باره داده نسخه ۱ در پس‌زمینه پیشخوان (هر بار ۲۵ مورد)، تا
	 * صفحه‌های سایت برای داده قدیمی هر بار آدرس را از نو حل نکنند.
	 */
	public static function migrate(): void {

		if ( wp_doing_ajax() || VERSION === get_option( 'hodima_rl_migrated' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( 0 === Store::migrate_batch( 25 ) ) {
			update_option( 'hodima_rl_migrated', VERSION, false );
		}
	}
}
