<?php
/**
 * Topic Cluster — Core Helper
 * Path: core/topiccluster/helper.php
 * Version: 3.0.0
 *
 * ─────────────────────────────────────────────────────────────────────
 * مدل داده
 * ─────────────────────────────────────────────────────────────────────
 * هر گره (نوشته، برگه، محصول یا ترم) دو متا دارد:
 *
 *   _hodima_is_pillar   → '1' اگر پیلار است (در غیر این صورت وجود ندارد)
 *   _hodima_pillar_id   → *یک ردیف به ازای هر والد*، هر ردیف یک عدد صحیح
 *
 * نسخه‌های قبلی کل فهرست والدها را به صورت یک آرایه سریالایزشده
 * (a:1:{i:0;i:42;}) در یک ردیف ذخیره می‌کردند. سه پیامد داشت:
 *   ۱. پیدا کردن فرزندان فقط با LIKE روی متن سریالایزشده ممکن بود —
 *      بدون ایندکس، اسکن کامل postmeta.
 *   ۲. ماژول سایت‌مپ خوشه‌ها (google-indexing-api/modules/cluster-sitemap.php)
 *      با «meta_value IN (…)» دنبال عدد خالص می‌گشت و هیچ‌وقت فرزندی
 *      پیدا نمی‌کرد.
 *   ۳. هر ذخیره، حتی بدون والد، ردیف «a:0:{}» می‌ساخت که گزارش محتوای
 *      یتیم آن را «دارای والد» حساب می‌کرد.
 *
 * خواندن با هر دو قالب سازگار است؛ هر گره در اولین ذخیره بعدی خودبه‌خود
 * به قالب جدید منتقل می‌شود.
 *
 * ─────────────────────────────────────────────────────────────────────
 * نوع والد
 * ─────────────────────────────────────────────────────────────────────
 * شناسه نوشته‌ها و شناسه ترم‌ها دو دنباله شماره مستقل‌اند؛ «۴۲» می‌تواند
 * همزمان یک نوشته و یک ترم باشد. پس هر شناسه والد فقط همراه با *نوعش*
 * معنا دارد:
 *
 *   نوشته / برگه   → والدش نوشته یا برگه است
 *   محصول          → والدش دسته‌بندی محصول (ترم) است
 *   ترم            → والدش ترم است
 *
 * نسخه قبلی این قانون را فقط در متاباکس رعایت می‌کرد و بقیه کد (یافتن
 * فرزندان، بررسی noindex) آن را نادیده می‌گرفت.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_TC_Helper {

	public const META_PILLAR = '_hodima_is_pillar';
	public const META_PARENT = '_hodima_pillar_id';

	public const KIND_POST = 'post';
	public const KIND_TERM = 'term';

	private const CACHE_GEN_OPTION = 'hodima_tc_cache_gen';
	private const CACHE_TTL        = 12 * HOUR_IN_SECONDS;

	/** نوع‌های پستی که در خوشه‌بندی شرکت می‌کنند. */
	public static function post_types(): array {
		return (array) apply_filters( 'hodima_tc_post_types', [ 'post', 'page', 'product' ] );
	}

	/** تکسونومی‌هایی که در خوشه‌بندی شرکت می‌کنند. */
	public static function taxonomies(): array {
		return (array) apply_filters( 'hodima_tc_taxonomies', [ 'category', 'post_tag', 'product_cat' ] );
	}

	/* =================================================================
	 * نوع والد
	 * ================================================================= */

	/**
	 * نوع پست → تکسونومی‌ای که والدهایش از آن انتخاب می‌شوند.
	 *
	 * نوشته و محصول والدشان را از دسته‌بندی انتخاب می‌کنند. برگه در
	 * وردپرس به صورت پیش‌فرض تکسونومی ندارد، پس والدش برگه دیگری است
	 * (خوشه سلسله‌مراتبی برگه‌ها).
	 *
	 * نسخه قبلی فقط محصول را در این نقشه داشت؛ نوشته‌ها والد را از میان
	 * نوشته‌های دیگر انتخاب می‌کردند، در حالی که خوشه محتوایی وبلاگ
	 * طبیعتا حول دسته‌بندی شکل می‌گیرد.
	 *
	 * @return array<string, string>
	 */
	public static function parent_taxonomy_map(): array {
		return (array) apply_filters( 'hodima_tc_parent_taxonomy_map', [
			'post'    => 'category',
			'product' => 'product_cat',
		] );
	}

	/** تکسونومی والد برای یک نوع پست، یا رشته خالی اگر والدش پست است. */
	public static function parent_taxonomy_for( string $post_type ): string {
		$map = self::parent_taxonomy_map();
		return isset( $map[ $post_type ] ) && taxonomy_exists( $map[ $post_type ] ) ? $map[ $post_type ] : '';
	}

	/**
	 * والدهای این گره از چه نوعی هستند؟
	 *
	 * @param string $context 'post' یا 'term' — نوع خود گره.
	 */
	public static function parent_kind( int $id, string $context ): string {

		if ( self::KIND_TERM === $context ) {
			return self::KIND_TERM;
		}

		return '' !== self::parent_taxonomy_for( (string) get_post_type( $id ) )
			? self::KIND_TERM
			: self::KIND_POST;
	}

	/** نوع‌های پستی که فرزندانشان در باکس خوشه نمایش داده نمی‌شوند. */
	private static function hidden_child_post_types(): array {
		// تصمیم اصلی ماژول: محصولات در خوشه‌ها نمایش داده نشوند
		return (array) apply_filters( 'hodima_tc_hidden_child_post_types', [ 'product' ] );
	}

	/* =================================================================
	 * خواندن
	 * ================================================================= */

	/**
	 * شناسه والدها — با هر دو قالب ذخیره (چندردیفی جدید، سریالایزشده قدیمی).
	 *
	 * @return int[]
	 */
	public static function get_parents( int $id, string $context ): array {

		if ( $id <= 0 ) {
			return [];
		}

		$rows = ( self::KIND_POST === $context )
			? get_post_meta( $id, self::META_PARENT, false )
			: get_term_meta( $id, self::META_PARENT, false );

		$ids = [];

		foreach ( (array) $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $value ) {
				$value = (int) $value;
				if ( $value > 0 && $value !== $id ) {
					$ids[ $value ] = $value;
				}
			}
		}

		return array_values( $ids );
	}

	public static function is_pillar( int $id, string $context ): bool {

		$value = ( self::KIND_POST === $context )
			? get_post_meta( $id, self::META_PILLAR, true )
			: get_term_meta( $id, self::META_PILLAR, true );

		return 1 === (int) $value;
	}

	/* =================================================================
	 * نوشتن
	 * ================================================================= */

	/**
	 * ذخیره تنظیمات خوشه یک گره.
	 *
	 * مقادیر خالی *حذف* می‌شوند، نه ذخیره. نسخه قبلی برای هر نوشته‌ای که
	 * ذخیره می‌شد دو ردیف بی‌مصرف (_hodima_is_pillar=0 و a:0:{}) می‌ساخت.
	 *
	 * @param int[] $parent_ids
	 */
	public static function save( int $id, string $context, bool $is_pillar, array $parent_ids ): void {

		$is_post = ( self::KIND_POST === $context );

		$old_parents    = self::get_parents( $id, $context );
		$was_pillar     = self::is_pillar( $id, $context );

		$parent_ids = array_values( array_unique( array_filter(
			array_map( 'absint', $parent_ids ),
			static fn( int $pid ): bool => $pid > 0 && $pid !== $id
		) ) );

		/*
		 * فقط شناسه‌هایی پذیرفته می‌شوند که واقعا از نوع درست باشند.
		 *
		 * بدون این، یک تب ویرایشگر که *قبل از* به‌روزرسانی باز شده بود
		 * (و هنوز نوشته‌ها را برای انتخاب نشان می‌داد) می‌توانست شناسه
		 * نوشته را به عنوان والد ذخیره کند — که در مدل جدید به عنوان
		 * شناسه دسته‌بندی تفسیر می‌شد.
		 */
		$parent_ids = self::filter_valid_parents( $id, $context, $parent_ids );

		// پیلار
		if ( $is_pillar ) {
			$is_post ? update_post_meta( $id, self::META_PILLAR, '1' ) : update_term_meta( $id, self::META_PILLAR, '1' );
		} else {
			$is_post ? delete_post_meta( $id, self::META_PILLAR ) : delete_term_meta( $id, self::META_PILLAR );
		}

		// والدها: همه ردیف‌های قبلی (هر قالبی) حذف، سپس یک ردیف به ازای هر والد
		$is_post ? delete_post_meta( $id, self::META_PARENT ) : delete_term_meta( $id, self::META_PARENT );

		foreach ( $parent_ids as $pid ) {
			$is_post
				? add_post_meta( $id, self::META_PARENT, $pid )
				: add_term_meta( $id, self::META_PARENT, $pid );
		}

		$changed = ( $was_pillar !== $is_pillar )
			|| ( array_diff( $old_parents, $parent_ids ) !== [] )
			|| ( array_diff( $parent_ids, $old_parents ) !== [] );

		if ( $changed ) {
			self::bump_cache();
		}
	}

	/** @param int[] $ids */
	private static function filter_valid_parents( int $id, string $context, array $ids ): array {

		if ( empty( $ids ) ) {
			return [];
		}

		if ( self::KIND_TERM === $context ) {
			// ترم → والد ترم از همان تکسونومی
			$term = get_term( $id );
			$tax  = ( $term instanceof WP_Term ) ? $term->taxonomy : '';
			return array_values( array_filter( $ids, static function ( int $pid ) use ( $tax ): bool {
				$parent = get_term( $pid );
				return $parent instanceof WP_Term && ( '' === $tax || $parent->taxonomy === $tax );
			} ) );
		}

		$taxonomy = self::parent_taxonomy_for( (string) get_post_type( $id ) );

		if ( '' !== $taxonomy ) {
			return array_values( array_filter( $ids, static function ( int $pid ) use ( $taxonomy ): bool {
				$parent = get_term( $pid );
				return $parent instanceof WP_Term && $parent->taxonomy === $taxonomy;
			} ) );
		}

		$post_type = (string) get_post_type( $id );
		return array_values( array_filter( $ids, static function ( int $pid ) use ( $post_type ): bool {
			return get_post_type( $pid ) === $post_type;
		} ) );
	}

	/* =================================================================
	 * فرزندان
	 * ================================================================= */

	/**
	 * فرزندان یک پیلار، حل‌شده و آماده نمایش.
	 *
	 * نتیجه کش می‌شود و هم شورت‌کد و هم اسکیما از *همین* خروجی استفاده
	 * می‌کنند. نسخه قبلی شورت‌کد را بدون کش اجرا می‌کرد (یعنی کوئری LIKE
	 * روی هر بازدید) و اسکیما را با کلیدی کش می‌کرد که تابع پاکسازی هرگز
	 * پاکش نمی‌کرد — آن تابع کلید «…_children_» را حذف می‌کرد در حالی که
	 * اسکیما «…_children_v2_» می‌نوشت، و تازه خود تابع پاکسازی هیچ‌جا
	 * صدا زده نمی‌شد.
	 *
	 * @param string $pillar_kind نوع خود پیلار: 'post' یا 'term'.
	 * @return array<int, array{id:int, kind:string, title:string, url:string, noindex:bool}>
	 */
	public static function get_children( int $pillar_id, string $pillar_kind = self::KIND_POST ): array {

		if ( $pillar_id <= 0 ) {
			return [];
		}

		$cache_key = sprintf( 'hodima_tc_children_%s_%d_%d', $pillar_kind, $pillar_id, self::cache_gen() );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$children = ( self::KIND_TERM === $pillar_kind )
			? self::find_term_children( $pillar_id )
			: self::find_post_children( $pillar_id );

		set_transient( $cache_key, $children, self::CACHE_TTL );

		return $children;
	}

	/**
	 * شرط متا برای یافتن *نامزدهایی* که شاید $pillar_id را والد خود داشته باشند.
	 *
	 * قالب جدید با «=» و ایندکس جستجو می‌شود. الگوهای LIKE فقط برای
	 * ردیف‌های سریالایزشده قدیمی است که هنوز دوباره ذخیره نشده‌اند.
	 *
	 * این الگوها دقیق نیستند: «;i:1;» با *کلید* آرایه‌ای مثل
	 * a:2:{i:0;i:42;i:1;i:7;} هم تطبیق می‌دهد. به همین دلیل خروجی این
	 * کوئری فقط فهرست نامزد است و is_child_of() در PHP تأییدش می‌کند.
	 */
	private static function parent_meta_query( int $pillar_id ): array {
		return [
			'relation' => 'OR',
			[ 'key' => self::META_PARENT, 'value' => (string) $pillar_id, 'compare' => '=' ],
			[ 'key' => self::META_PARENT, 'value' => ';i:' . $pillar_id . ';', 'compare' => 'LIKE' ],
			[ 'key' => self::META_PARENT, 'value' => '"' . $pillar_id . '"', 'compare' => 'LIKE' ],
		];
	}

	/** پیلار نوشته/برگه → فرزندان نوشته/برگه. */
	private static function find_post_children( int $pillar_id ): array {

		/*
		 * فقط نوع‌هایی که والدشان *پست* است (یعنی در نقشه تکسونومی نیستند).
		 * نوشته و محصول والدشان را از دسته‌بندی انتخاب می‌کنند، پس هرگز
		 * نمی‌توانند فرزند یک پیلار پستی باشند؛ عدد ذخیره‌شده در متای آن‌ها
		 * شناسه یک ترم است و ممکن است تصادفا با شناسه این پیلار برابر باشد.
		 */
		$post_types = array_values( array_filter(
			self::post_types(),
			static fn( string $pt ): bool => '' === self::parent_taxonomy_for( $pt )
		) );

		$post_types = array_values( array_diff( $post_types, self::hidden_child_post_types() ) );

		if ( empty( $post_types ) ) {
			return [];
		}

		$ids = get_posts( [
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => 100,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => self::parent_meta_query( $pillar_id ),
		] );

		if ( empty( $ids ) ) {
			return [];
		}

		// یک کوئری به جای دو کوئری (عنوان + متا) به ازای هر فرزند
		_prime_post_caches( array_map( 'intval', $ids ), false, true );

		$children = [];

		foreach ( $ids as $child_id ) {
			if ( ! self::is_child_of( (int) $child_id, self::KIND_POST, $pillar_id ) ) {
				continue;
			}
			$node = self::resolve_node( (int) $child_id, self::KIND_POST );
			if ( null !== $node ) {
				$children[] = $node;
			}
		}

		return $children;
	}

	/**
	 * پیلار ترم → زیردسته‌ها + نوشته‌هایی که این دسته را والد خود دارند.
	 *
	 * با تغییر مدل، نوشته‌ها حالا والدشان را از category انتخاب می‌کنند،
	 * پس یک پیلار دسته‌بندی باید مقاله‌هایش را هم در فهرست فرزندان ببیند —
	 * این اصل خوشه محتوایی است: صفحه هسته به مقاله‌های پشتیبانش لینک
	 * می‌دهد.
	 */
	private static function find_term_children( int $pillar_id ): array {

		$children = self::find_term_term_children( $pillar_id );

		$pillar = get_term( $pillar_id );

		if ( $pillar instanceof WP_Term ) {

			$post_types = [];
			foreach ( self::parent_taxonomy_map() as $post_type => $taxonomy ) {
				if ( $taxonomy === $pillar->taxonomy && in_array( $post_type, self::post_types(), true ) ) {
					$post_types[] = $post_type;
				}
			}

			$post_types = array_values( array_diff( $post_types, self::hidden_child_post_types() ) );

			if ( ! empty( $post_types ) ) {

				$ids = get_posts( [
					'post_type'              => $post_types,
					'post_status'            => 'publish',
					'posts_per_page'         => 100,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'meta_query'             => self::parent_meta_query( $pillar_id ),
				] );

				if ( ! empty( $ids ) ) {
					_prime_post_caches( array_map( 'intval', $ids ), false, true );

					foreach ( $ids as $child_id ) {
						if ( ! self::is_child_of( (int) $child_id, self::KIND_POST, $pillar_id ) ) {
							continue;
						}
						$node = self::resolve_node( (int) $child_id, self::KIND_POST );
						if ( null !== $node ) {
							$children[] = $node;
						}
					}
				}
			}
		}

		return $children;
	}

	/** زیردسته‌هایی که این ترم را والد خود دارند. */
	private static function find_term_term_children( int $pillar_id ): array {

		$terms = get_terms( [
			'taxonomy'               => self::taxonomies(),
			'hide_empty'             => false,
			'number'                 => 100,
			'update_term_meta_cache' => true,
			'meta_query'             => self::parent_meta_query( $pillar_id ),
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$children = [];

		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}
			if ( ! self::is_child_of( (int) $term->term_id, self::KIND_TERM, $pillar_id ) ) {
				continue;
			}
			$node = self::resolve_node( (int) $term->term_id, self::KIND_TERM );
			if ( null !== $node ) {
				$children[] = $node;
			}
		}

		return $children;
	}

	/** تأیید دقیق: آیا $pillar_id واقعا در فهرست والدهای این گره هست؟ */
	private static function is_child_of( int $id, string $context, int $pillar_id ): bool {
		return in_array( $pillar_id, self::get_parents( $id, $context ), true );
	}

	/* =================================================================
	 * حل یک گره
	 * ================================================================= */

	/**
	 * عنوان، آدرس و وضعیت noindex یک گره.
	 *
	 * null یعنی گره قابل نمایش نیست (حذف‌شده، منتشرنشده، یا آدرس عمومی
	 * ندارد). نسخه قبلی خروجی get_term_link() را بدون بررسی WP_Error
	 * مستقیم در esc_url() و الحاق رشته استفاده می‌کرد — که روی
	 * تکسونومی غیرعمومی خطای کشنده می‌داد.
	 *
	 * @return array{id:int, kind:string, title:string, url:string, noindex:bool}|null
	 */
	public static function resolve_node( int $id, string $kind ): ?array {

		if ( $id <= 0 ) {
			return null;
		}

		if ( self::KIND_POST === $kind ) {

			$post = get_post( $id );

			if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
				return null;
			}

			$url   = get_permalink( $post );
			$title = get_the_title( $post );

		} else {

			$term = get_term( $id );

			if ( ! ( $term instanceof WP_Term ) ) {
				return null;
			}

			$link  = get_term_link( $term );
			$url   = is_wp_error( $link ) ? '' : $link;
			$title = $term->name;
		}

		if ( ! is_string( $url ) || '' === $url || '' === trim( (string) $title ) ) {
			return null;
		}

		return [
			'id'      => $id,
			'kind'    => $kind,
			'title'   => wp_strip_all_tags( (string) $title ),
			'url'     => $url,
			'noindex' => self::node_is_noindex( $id, $kind ),
		];
	}

	/**
	 * آیا گره noindex است؟
	 *
	 * باگ نسخه قبلی: نوع جدول از context *گره جاری* گرفته می‌شد، نه از
	 * نوع خود والد/فرزند. مثلا برای یک محصول، والدها ترم‌اند ولی بررسی
	 * روی postmeta با همان شناسه انجام می‌شد — یعنی روی یک نوشته کاملا
	 * نامرتبط.
	 */
	public static function node_is_noindex( int $id, string $kind ): bool {

		$robots = ( self::KIND_POST === $kind )
			? get_post_meta( $id, '_seobox_robots', true )
			: get_term_meta( $id, '_seobox_robots', true );

		if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
			return true;
		}

		if ( is_string( $robots ) && str_contains( strtolower( $robots ), 'noindex' ) ) {
			return true;
		}

		// سازگاری با افزونه‌ها/ماژول‌هایی که کلیدی حاوی «noindex» دارند
		$all = ( self::KIND_POST === $kind ) ? get_post_meta( $id ) : get_term_meta( $id );

		foreach ( (array) $all as $key => $values ) {
			if ( ! str_contains( strtolower( (string) $key ), 'noindex' ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				if ( in_array( strtolower( (string) $value ), [ '1', 'yes', 'true', 'on' ], true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/* =================================================================
	 * کش
	 * ================================================================= */

	/**
	 * شماره نسل کش.
	 *
	 * به جای ردیابی تک‌تک کلیدها، هر تغییری در گراف خوشه شماره را بالا
	 * می‌برد و همه کلیدهای قدیمی خودبه‌خود از دسترس خارج می‌شوند. تعداد
	 * پیلارها کم است، پس بازسازی تنبل آن‌ها هزینه‌ای ندارد — ولی تضمین
	 * می‌کند هیچ‌وقت داده کهنه نمایش داده نشود.
	 */
	public static function cache_gen(): int {
		return (int) get_option( self::CACHE_GEN_OPTION, 1 );
	}

	public static function bump_cache(): void {
		update_option( self::CACHE_GEN_OPTION, self::cache_gen() + 1, false );
		delete_transient( 'hodima_tc_orphan_count' );
	}

	/**
	 * آیا این گره در گراف خوشه حضور دارد؟ (برای تصمیم‌گیری درباره
	 * پاکسازی کش هنگام ذخیره یا حذف یک نوشته بدون تغییر تنظیمات خوشه —
	 * مثلا وقتی فقط عنوان یا نامک عوض شده.)
	 */
	public static function in_graph( int $id, string $context ): bool {
		return self::is_pillar( $id, $context ) || [] !== self::get_parents( $id, $context );
	}

	/**
	 * @deprecated 3.0.0 از bump_cache() استفاده کنید.
	 *             نگه داشته شده تا کد خارجی که آن را صدا می‌زند نشکند.
	 */
	public static function clear_schema_cache( int $object_id, string $context ): void {
		self::bump_cache();
	}
}

/* =====================================================================
 * پاکسازی کش هنگام تغییر عنوان، نامک، وضعیت انتشار یا حذف
 * ---------------------------------------------------------------------
 * تغییر تنظیمات خوشه در Hodima_TC_Helper::save() کش را پاک می‌کند.
 * ولی عنوان یا نامک یک فرزند هم در فهرست پیلار نمایش داده می‌شود؛
 * بدون این هوک‌ها، ویرایش عنوان یک مقاله تا ۱۲ ساعت در باکس خوشه
 * منعکس نمی‌شد.
 * ===================================================================== */
add_action( 'post_updated', static function ( int $post_id, WP_Post $after, WP_Post $before ): void {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$visible_change = $after->post_title !== $before->post_title
		|| $after->post_name !== $before->post_name
		|| $after->post_status !== $before->post_status;

	if ( $visible_change && Hodima_TC_Helper::in_graph( $post_id, Hodima_TC_Helper::KIND_POST ) ) {
		Hodima_TC_Helper::bump_cache();
	}
}, 10, 3 );

add_action( 'before_delete_post', static function ( int $post_id ): void {
	if ( Hodima_TC_Helper::in_graph( $post_id, Hodima_TC_Helper::KIND_POST ) ) {
		Hodima_TC_Helper::bump_cache();
	}
} );

add_action( 'trashed_post', static function ( int $post_id ): void {
	if ( Hodima_TC_Helper::in_graph( $post_id, Hodima_TC_Helper::KIND_POST ) ) {
		Hodima_TC_Helper::bump_cache();
	}
} );

add_action( 'edited_term', static function ( int $term_id ): void {
	if ( Hodima_TC_Helper::in_graph( $term_id, Hodima_TC_Helper::KIND_TERM ) ) {
		Hodima_TC_Helper::bump_cache();
	}
} );

add_action( 'pre_delete_term', static function ( int $term_id ): void {
	if ( Hodima_TC_Helper::in_graph( $term_id, Hodima_TC_Helper::KIND_TERM ) ) {
		Hodima_TC_Helper::bump_cache();
	}
} );

/* =====================================================================
 * مهاجرت یک‌باره داده‌های قدیمی
 * ---------------------------------------------------------------------
 * بدون این، ساختار جدید فقط برای محتوایی اعمال می‌شد که دستی دوباره
 * ذخیره شود. تا آن زمان سایت‌مپ خوشه‌ها هنوز فرزندی پیدا نمی‌کرد و
 * ردیف‌های بی‌مصرف «a:0:{}» و «_hodima_is_pillar = 0» در دیتابیس
 * می‌ماندند.
 *
 * ویژگی‌ها:
 *   - دسته‌ای: هر بار حداکثر ۲۰۰ ردیف، تا هیچ بارگذاری پیشخوانی کند نشود
 *   - تکرارپذیر: اجرای دوباره روی داده منتقل‌شده هیچ اثری ندارد
 *   - فقط برای مدیر و فقط در پیشخوان اجرا می‌شود
 *   - پس از اتمام، با یک گزینه علامت می‌خورد و دیگر اجرا نمی‌شود
 * ===================================================================== */
add_action( 'admin_init', 'hodima_tc_migrate_storage' );

function hodima_tc_migrate_storage(): void {

	if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$version = (string) get_option( 'hodima_tc_storage_version', '' );

	if ( '4' === $version ) {
		return;
	}

	// مرحله ۴ فقط بعد از اتمام کامل مرحله ۳ اجرا می‌شود
	if ( '3' === $version ) {
		hodima_tc_migrate_post_parents_to_categories();
		return;
	}

	global $wpdb;

	$batch = 200;
	$did   = 0;

	foreach ( [ 'post' => $wpdb->postmeta, 'term' => $wpdb->termmeta ] as $type => $table ) {

		$id_col = ( 'post' === $type ) ? 'post_id' : 'term_id';

		// ۱. ردیف‌های بی‌مصرف
		$did += (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE meta_key = %s AND meta_value IN ('', '0', 'a:0:{}') LIMIT %d",
			Hodima_TC_Helper::META_PARENT,
			$batch
		) );

		$did += (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE meta_key = %s AND meta_value IN ('', '0') LIMIT %d",
			Hodima_TC_Helper::META_PILLAR,
			$batch
		) );

		// ۲. ردیف‌های سریالایزشده → یک ردیف به ازای هر والد
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_id, {$id_col} AS object_id, meta_value FROM {$table}
			 WHERE meta_key = %s AND meta_value LIKE %s LIMIT %d",
			Hodima_TC_Helper::META_PARENT,
			'a:%',
			$batch
		) );

		foreach ( (array) $rows as $row ) {

			$values = maybe_unserialize( $row->meta_value );
			$wpdb->delete( $table, [ 'meta_id' => (int) $row->meta_id ] );
			$did++;

			foreach ( (array) $values as $value ) {
				$value = (int) $value;
				if ( $value > 0 && $value !== (int) $row->object_id ) {
					( 'post' === $type )
						? add_post_meta( (int) $row->object_id, Hodima_TC_Helper::META_PARENT, $value )
						: add_term_meta( (int) $row->object_id, Hodima_TC_Helper::META_PARENT, $value );
				}
			}

			( 'post' === $type )
				? wp_cache_delete( (int) $row->object_id, 'post_meta' )
				: wp_cache_delete( (int) $row->object_id, 'term_meta' );
		}
	}

	if ( $did > 0 ) {
		Hodima_TC_Helper::bump_cache();
		// سایت‌مپ خوشه‌ها هم باید با داده جدید بازسازی شود
		delete_transient( 'hodima_cluster_sitemap_xml_cache_v1' );
		return; // دسته بعدی در بارگذاری بعدی پیشخوان
	}

	update_option( 'hodima_tc_storage_version', '3', true );
}

/* =====================================================================
 * مهاجرت مرحله ۴: والد نوشته‌ها از «نوشته» به «دسته‌بندی»
 * ---------------------------------------------------------------------
 * تا این نسخه، والد یک نوشته، نوشته دیگری بود و شناسه *نوشته* ذخیره
 * می‌شد. از این نسخه والد نوشته یک دسته‌بندی است و همان عدد به عنوان
 * شناسه *ترم* خوانده می‌شود. بدون تبدیل، هر نوشته موجود به دسته‌ای
 * متصل می‌شد که تصادفا همان شماره را دارد.
 *
 * تبدیل: اگر مقاله الف فرزند مقاله پیلار ب بود، حالا فرزند دسته‌بندی
 * اصلی مقاله ب می‌شود — نزدیک‌ترین معادل خوشه‌ای که مدیر ساخته بود.
 *
 * هیچ داده‌ای از دست نمی‌رود: شناسه‌های قبلی در کلید
 * _hodima_pillar_id_legacy نگه داشته می‌شوند تا در صورت نیاز قابل
 * بازبینی یا برگرداندن باشند.
 *
 * دسته «بدون دسته‌بندی» نادیده گرفته می‌شود؛ اتصال به آن معنای خوشه‌ای
 * ندارد. اگر نوشته پیلار فقط همان دسته را داشت، فرزندش بدون والد
 * می‌ماند و در گزارش محتوای یتیم ظاهر می‌شود.
 * ===================================================================== */
function hodima_tc_migrate_post_parents_to_categories(): void {

	global $wpdb;

	$default_cat = (int) get_option( 'default_category' );

	// فقط نوع «post» — محصول از ابتدا دسته‌بندی ذخیره می‌کرد و برگه تغییر نکرده
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT pm.post_id
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = %s AND p.post_type = 'post'
		 LIMIT 2000",
		Hodima_TC_Helper::META_PARENT
	) );

	$converted = 0;
	$orphaned  = 0;

	foreach ( (array) $post_ids as $post_id ) {

		$post_id = (int) $post_id;

		// get_parents هنوز مقادیر خام را برمی‌گرداند (اینجا: شناسه‌های نوشته)
		$old_parent_posts = Hodima_TC_Helper::get_parents( $post_id, Hodima_TC_Helper::KIND_POST );

		if ( empty( $old_parent_posts ) ) {
			continue;
		}

		// پشتیبان — فقط یک بار
		if ( ! metadata_exists( 'post', $post_id, '_hodima_pillar_id_legacy' ) ) {
			foreach ( $old_parent_posts as $old_id ) {
				add_post_meta( $post_id, '_hodima_pillar_id_legacy', $old_id );
			}
		}

		$categories = [];

		foreach ( $old_parent_posts as $parent_post_id ) {

			if ( 'post' !== get_post_type( $parent_post_id ) && 'page' !== get_post_type( $parent_post_id ) ) {
				continue;
			}

			foreach ( wp_get_post_categories( $parent_post_id ) as $cat_id ) {
				$cat_id = (int) $cat_id;
				if ( $cat_id > 0 && $cat_id !== $default_cat ) {
					$categories[ $cat_id ] = $cat_id;
					break; // دسته اول = دسته اصلی
				}
			}
		}

		delete_post_meta( $post_id, Hodima_TC_Helper::META_PARENT );

		foreach ( $categories as $cat_id ) {
			add_post_meta( $post_id, Hodima_TC_Helper::META_PARENT, $cat_id );
		}

		empty( $categories ) ? $orphaned++ : $converted++;
	}

	update_option( 'hodima_tc_storage_version', '4', true );
	update_option( 'hodima_tc_migration_v4_report', [
		'converted' => $converted,
		'orphaned'  => $orphaned,
		'time'      => time(),
	], false );

	Hodima_TC_Helper::bump_cache();
	delete_transient( 'hodima_cluster_sitemap_xml_cache_v1' );
}

/**
 * یک بار پس از مهاجرت، نتیجه را به مدیر گزارش بده.
 */
add_action( 'admin_notices', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$report = get_option( 'hodima_tc_migration_v4_report' );

	if ( ! is_array( $report ) ) {
		return;
	}

	delete_option( 'hodima_tc_migration_v4_report' );

	if ( 0 === (int) $report['converted'] && 0 === (int) $report['orphaned'] ) {
		return;
	}

	printf(
		'<div class="notice notice-info is-dismissible"><p><strong>خوشه‌بندی محتوا:</strong> والد نوشته‌ها از «نوشته» به «دسته‌بندی» منتقل شد. %s نوشته به دسته‌بندی اصلیِ پیلار قبلی‌اش متصل شد و %s نوشته بدون والد ماند (پیلارش دسته‌بندی معتبری نداشت). <a href="%s">بررسی محتوای یتیم</a></p></div>',
		esc_html( number_format_i18n( (int) $report['converted'] ) ),
		esc_html( number_format_i18n( (int) $report['orphaned'] ) ),
		esc_url( admin_url( 'admin.php?page=hodima-tc-orphans' ) )
	);
} );
