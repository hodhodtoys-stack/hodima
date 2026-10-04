<?php
/**
 * لینک‌های مرتبط دستی — تنظیمات، ذخیره، مهاجرت و «حل» لینک‌ها
 * Path: plugins/hodima-seo/inc/manual_related_link/includes/store.php
 *
 * ساختار متای جدید (_hodima_rl_groups):
 *   [ 'products' => [ item, … ], 'article' => [ … ] ]
 *   (کلید 'categories' از نسخه ۲.۰–۲.۱ اگر هست دست‌نخورده می‌ماند؛ RETIRED)
 *   item = [ 'kind' => 'post'|'term'|'url', 'id' => int, 'url' => string,
 *            'title' => string (عنوان دلخواه؛ خالی = عنوان مقصد),
 *            'img_id' => int (تصویر دلخواه؛ صفر = تصویر شاخص مقصد) ]
 *
 * نسخه ۱ آدرس را به شکل متن ثابت ذخیره می‌کرد؛ با تغییر نامک، حذف یا
 * پیش‌نویس شدن مقصد، لینک شکسته در صفحه می‌ماند. حالا شناسه ذخیره می‌شود و
 * آدرس، عنوان و تصویر هر بار از خود مقصد خوانده می‌شود.
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Store {

	public const META        = '_hodima_rl_groups';
	public const LEGACY_META = '_hodima_mrl_data';      // نسخه ۱.۸
	public const OPTION      = 'hodima_rl_settings';
	public const MAX_SLOTS   = 6;
	public const HEADINGS    = [ 'h2', 'h3', 'h4', 'p' ];

	/** گروه‌های حذف‌شده: داده‌شان در ذخیره بعدی حفظ می‌شود ولی نمایش/ویرایش ندارند. */
	public const RETIRED     = [ 'categories' ];

	/** مشکلاتی که کارت را از سایت پنهان می‌کنند (بقیه فقط هشدارند). */
	public const HIDING = [ 'missing', 'unpublished', 'self', 'gone', 'no_title', 'empty_url', 'noindex' ];

	/** @var array<string, mixed>|null */
	private static ?array $settings = null;

	/** @var array<string, array<string, list<array<string, mixed>>>> */
	private static array $cache = [];

	/* =====================================================================
	 * تنظیمات
	 * ===================================================================== */

	/** @return array<string, mixed> */
	public static function defaults(): array {
		$groups = [];
		foreach ( Group::cases() as $group ) {
			$groups[ $group->value ] = [
				'count' => $group->default_count(),
				'title' => $group->default_title(),
				'auto'  => false,
			];
		}
		return [
			'groups'            => $groups,
			'article_paragraph' => 3,
			'article_label'     => 'مطالب مرتبط',
			'heading'           => 'h3',
			'hide_noindex'      => true,
			'track_clicks'      => false,
		];
	}

	/** @return array<string, mixed> */
	public static function settings(): array {

		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$saved    = get_option( self::OPTION, [] );
		$saved    = is_array( $saved ) ? $saved : [];
		$defaults = self::defaults();
		$settings = array_merge( $defaults, array_intersect_key( $saved, $defaults ) );

		foreach ( Group::cases() as $group ) {
			$settings['groups'][ $group->value ] = array_merge(
				$defaults['groups'][ $group->value ],
				(array) ( $saved['groups'][ $group->value ] ?? [] )
			);
		}

		/*
		 * ۲.۴ (خواسته کاربر): عنوان پیش‌فرض کادر محصولات در سایت از «محصولات
		 * مکمل» به «پیشنهاد خرید» تغییر کرد. اگر صفحه تنظیمات یک بار ذخیره شده
		 * باشد، همان عنوان پیش‌فرض قدیمی در گزینه مانده؛ آن هم عوض می‌شود.
		 * عنوانی که مدیر خودش نوشته دست نمی‌خورد.
		 */
		if ( 'محصولات مکمل' === ( $settings['groups'][ Group::Products->value ]['title'] ?? null ) ) {
			$settings['groups'][ Group::Products->value ]['title'] = Group::Products->default_title();
		}

		return self::$settings = self::sanitize_settings( $settings );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( array $input ): array {

		$out = self::defaults();

		foreach ( Group::cases() as $group ) {
			$row = (array) ( $input['groups'][ $group->value ] ?? [] );
			$out['groups'][ $group->value ] = [
				'count' => min( self::MAX_SLOTS, max( 1, (int) ( $row['count'] ?? $group->default_count() ) ) ),
				// عنوان خالی مجاز است (کادر بدون عنوان)؛ مقاله هیچ‌وقت عنوان ندارد.
				'title' => $group->has_title() ? sanitize_text_field( (string) ( $row['title'] ?? $group->default_title() ) ) : '',
				'auto'  => ! empty( $row['auto'] ),
			];
		}

		$out['article_paragraph'] = min( 50, max( 0, (int) ( $input['article_paragraph'] ?? 3 ) ) );
		$out['article_label']     = sanitize_text_field( (string) ( $input['article_label'] ?? 'مطالب مرتبط' ) );
		$heading                  = (string) ( $input['heading'] ?? 'h3' );
		$out['heading']           = in_array( $heading, self::HEADINGS, true ) ? $heading : 'h3';
		$out['hide_noindex']      = ! empty( $input['hide_noindex'] );
		$out['track_clicks']      = ! empty( $input['track_clicks'] );

		return $out;
	}

	/** @param array<string, mixed> $settings */
	public static function save_settings( array $settings ): void {
		update_option( self::OPTION, self::sanitize_settings( $settings ), false );
		self::$settings = null;
	}

	public static function count( Group $group ): int {
		return (int) apply_filters( 'hodima_related_links_count', self::settings()['groups'][ $group->value ]['count'], $group->value );
	}

	public static function title( Group $group ): string {
		return $group->has_title() ? (string) self::settings()['groups'][ $group->value ]['title'] : '';
	}

	public static function auto( Group $group ): bool {
		return (bool) self::settings()['groups'][ $group->value ]['auto'];
	}

	/** @return list<string> پست‌تایپ‌هایی که کادر ویرایشگر را دارند (نام فیلتر نسخه ۱ حفظ شده). */
	public static function post_types(): array {
		return array_values( array_filter( (array) apply_filters( 'hodima_internal_links_post_types', [ 'product', 'post', 'page' ] ), 'post_type_exists' ) );
	}

	/** @return list<string> تکسونومی‌هایی که کادر ویرایش ترم را دارند. */
	public static function taxonomies(): array {
		return array_values( array_filter( (array) apply_filters( 'hodima_internal_links_taxonomies', [ 'category', 'product_cat', 'post_tag', 'product_tag' ] ), 'taxonomy_exists' ) );
	}

	/** @return list<string> نوع مقصدهای قابل جستجو برای هر گروه. */
	public static function search_scope( Group $group ): array {
		$scope = match ( $group ) {
			Group::Products => [ 'product' ],
			Group::Article  => [ 'post', 'page' ],
		};
		$scope = (array) apply_filters( 'hodima_related_links_search_scope', $scope, $group->value );
		return array_values( array_filter( $scope, 'post_type_exists' ) );
	}

	/* =====================================================================
	 * خواندن و نوشتن
	 * ===================================================================== */

	/**
	 * لینک‌های ذخیره‌شده یک نوشته/ترم (همه خانه‌ها، شامل خانه‌های ذخیره).
	 * اگر هنوز داده جدید ندارد، داده نسخه ۱ همین‌جا به گروه‌ها تبدیل می‌شود
	 * (بدون نوشتن؛ نوشتن در مهاجرت یا اولین ذخیره).
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	public static function get( int $object_id, string $context ): array {

		$key = $context . ':' . $object_id;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$groups = array_fill_keys( array_map( static fn( Group $g ) => $g->value, Group::cases() ), [] );

		if ( $object_id <= 0 ) {
			return $groups;
		}

		$meta = get_metadata( self::meta_type( $context ), $object_id, self::META, true );

		if ( is_array( $meta ) ) {
			foreach ( $groups as $name => $unused ) {
				$items = array_filter( array_map( [ self::class, 'normalize_item' ], array_values( (array) ( $meta[ $name ] ?? [] ) ) ) );
				$groups[ $name ] = array_slice( array_values( $items ), 0, self::MAX_SLOTS );
			}
		} else {
			$groups = self::classify_legacy( self::legacy_items( $object_id, $context ), $groups );
		}

		return self::$cache[ $key ] = $groups;
	}

	/**
	 * @param array<string, list<array<string, mixed>>> $groups
	 */
	public static function save( int $object_id, string $context, array $groups ): void {

		$type  = self::meta_type( $context );
		$clean = [];
		$any   = false;

		// داده گروه‌های حذف‌شده (دسته‌بندی‌های مرتبط) پاک نمی‌شود.
		$stored = get_metadata( $type, $object_id, self::META, true );
		foreach ( self::RETIRED as $key ) {
			if ( is_array( $stored ) && ! empty( $stored[ $key ] ) ) {
				$clean[ $key ] = $stored[ $key ];
				$any           = true;
			}
		}

		foreach ( Group::cases() as $group ) {
			$items = array_filter( array_map( [ self::class, 'normalize_item' ], array_values( (array) ( $groups[ $group->value ] ?? [] ) ) ) );
			$items = array_slice( array_values( $items ), 0, self::MAX_SLOTS );
			$any   = $any || [] !== $items;
			$clean[ $group->value ] = $items;
		}

		// بدون هیچ لینکی ردیف متا ساخته نمی‌شود (مثل نسخه ۱.۸).
		$any
			? update_metadata( $type, $object_id, self::META, $clean )
			: delete_metadata( $type, $object_id, self::META );

		// مهاجرت کامل شد: داده نسخه ۱ در فرم نمایش داده و همراه آن ذخیره شده است.
		self::delete_legacy( $object_id, $context );

		unset( self::$cache[ $context . ':' . $object_id ] );
	}

	public static function has_legacy( int $object_id, string $context ): bool {
		return [] !== self::legacy_items( $object_id, $context );
	}

	/**
	 * یک آیتم معتبر یا null (خانه خالی).
	 *
	 * @param mixed $raw
	 * @return array{kind:string, id:int, url:string, title:string, img_id:int}|null
	 */
	public static function normalize_item( mixed $raw ): ?array {

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$kind = (string) ( $raw['kind'] ?? 'url' );
		$item = [
			'kind'   => in_array( $kind, [ 'post', 'term', 'url' ], true ) ? $kind : 'url',
			'id'     => absint( $raw['id'] ?? 0 ),
			'url'    => trim( (string) ( $raw['url'] ?? '' ) ),
			'title'  => trim( (string) ( $raw['title'] ?? '' ) ),
			'img_id' => absint( $raw['img_id'] ?? 0 ),
		];

		if ( 'url' === $item['kind'] ) {
			$item['id'] = 0;
			if ( '' === $item['url'] ) {
				return null;
			}
		} else {
			$item['url'] = '';
			if ( ! $item['id'] ) {
				return null;
			}
		}

		return $item;
	}

	private static function meta_type( string $context ): string {
		return 'term' === $context ? 'term' : 'post';
	}

	/* =====================================================================
	 * داده نسخه ۱ و مهاجرت
	 * ===================================================================== */

	/**
	 * آیتم‌های نسخه ۱: متای _hodima_mrl_data، یا قدیمی‌تر _internal_link_{i}_*.
	 * همه متاهای شیء در یک فراخوانی (از کش) خوانده می‌شوند؛ نسخه ۱ برای هر
	 * صفحه بی‌داده ۹ بار get_meta صدا می‌زد.
	 *
	 * @return list<array{title:string, url:string, img_id:int}>
	 */
	private static function legacy_items( int $object_id, string $context ): array {

		$all = get_metadata( self::meta_type( $context ), $object_id );
		if ( ! is_array( $all ) || ! $all ) {
			return [];
		}

		$rows = [];

		if ( isset( $all[ self::LEGACY_META ][0] ) ) {
			$data = maybe_unserialize( $all[ self::LEGACY_META ][0] );
			foreach ( is_array( $data ) ? array_values( $data ) : [] as $row ) {
				$rows[] = is_array( $row ) ? $row : [];
			}
		} else {
			for ( $i = 1; $i <= 10; $i++ ) {
				$rows[] = [
					'title'  => $all[ "_internal_link_{$i}_title" ][0] ?? '',
					'url'    => $all[ "_internal_link_{$i}_url" ][0] ?? '',
					'img_id' => $all[ "_internal_link_{$i}_img_id" ][0] ?? 0,
				];
			}
		}

		$items = [];
		foreach ( $rows as $row ) {
			$url = trim( (string) ( $row['url'] ?? '' ) );
			// خانه بدون آدرس در نسخه ۱ هم هیچ‌وقت نمایش داده نمی‌شد.
			if ( '' === $url ) {
				continue;
			}
			$items[] = [
				'title'  => trim( (string) ( $row['title'] ?? '' ) ),
				'url'    => $url,
				'img_id' => absint( $row['img_id'] ?? 0 ),
			];
		}

		return $items;
	}

	/**
	 * لینک‌های «ویترین پیشنهادی» قدیمی بر اساس نوع مقصد پخش می‌شوند:
	 * نوشته/برگه ← مقاله؛ محصول، دسته و آدرس ناشناخته ← محصولات مکمل (همان
	 * شورت‌کد قدیمی manual_related_products، پس چیزی که روی صفحه بود می‌ماند).
	 * (تا نسخه ۲.۱ ترم به گروه «دسته‌بندی‌های مرتبط» می‌رفت که حذف شد.)
	 * هیچ آیتمی دور ریخته نمی‌شود: بیشتر از تعداد نمایش، «خانه ذخیره» می‌شود.
	 *
	 * @param list<array{title:string, url:string, img_id:int}> $legacy
	 * @param array<string, list<array<string, mixed>>>         $groups
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function classify_legacy( array $legacy, array $groups ): array {

		foreach ( $legacy as $row ) {

			$target = self::resolve_url( $row['url'] );
			$item   = [ 'kind' => 'url', 'id' => 0, 'url' => $row['url'], 'title' => $row['title'], 'img_id' => $row['img_id'] ];
			$group  = Group::Products;

			if ( $target ) {
				$item['kind'] = $target['kind'];
				$item['id']   = $target['id'];
				$item['url']  = '';
				if ( 'post' === $target['kind'] && 'product' !== get_post_type( $target['id'] ) ) {
					$group = Group::Article;
				}
			}

			if ( count( $groups[ $group->value ] ) < self::MAX_SLOTS ) {
				$groups[ $group->value ][] = $item;
			}
		}

		return $groups;
	}

	private static function delete_legacy( int $object_id, string $context ): void {
		$type = self::meta_type( $context );
		delete_metadata( $type, $object_id, self::LEGACY_META );
		for ( $i = 1; $i <= 10; $i++ ) {
			foreach ( [ 'title', 'url', 'img_id' ] as $field ) {
				delete_metadata( $type, $object_id, "_internal_link_{$i}_{$field}" );
			}
		}
	}

	/**
	 * مهاجرت یک‌باره در پس‌زمینه پیشخوان: هر بار حداکثر $limit شیء.
	 * خروجی: تعداد شیءهای تبدیل‌شده (صفر = چیزی باقی نمانده).
	 */
	public static function migrate_batch( int $limit = 25 ): int {

		global $wpdb;

		$keys = [ self::LEGACY_META, '_internal_link_1_url' ];
		$done = 0;

		foreach ( [ 'post' => [ $wpdb->postmeta, 'post_id' ], 'term' => [ $wpdb->termmeta, 'term_id' ] ] as $context => [ $table, $column ] ) {

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام جدول و ستون ثابت‌اند
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$column} FROM {$table} WHERE meta_key IN (%s, %s) LIMIT %d", $keys[0], $keys[1], $limit ) );

			foreach ( array_map( 'intval', (array) $ids ) as $id ) {
				unset( self::$cache[ $context . ':' . $id ] );
				$current = get_metadata( self::meta_type( $context ), $id, self::META, true );
				// اگر داده جدید دارد، داده قدیمی فقط پاک می‌شود (چیزی از دست نمی‌رود).
				self::save( $id, $context, is_array( $current ) ? $current : self::get( $id, $context ) );
				++$done;
			}
		}

		return $done;
	}

	/* =====================================================================
	 * حل لینک: آدرس، عنوان و تصویر زنده + مشکلات
	 * ===================================================================== */

	/**
	 * @param array{kind:string, id:int, url:string, title:string, img_id:int} $item
	 * @return array{kind:string, id:int, url:string, title:string, img_id:int, type_label:string,
	 *               problems:list<string>, visible:bool, redirect_from:string, noindex:bool}
	 */
	public static function resolve( array $item, int $self_id = 0, string $self_context = 'post' ): array {

		$out = [
			'kind'          => $item['kind'],
			'id'            => $item['id'],
			'url'           => '',
			'title'         => $item['title'],
			'img_id'        => $item['img_id'],
			'type_label'    => '',
			'problems'      => [],
			'visible'       => true,
			'redirect_from' => '',
			'noindex'       => false,
		];

		// آدرس دلخواه ذخیره‌شده (مثلا از نسخه ۱) که حالا به یک نوشته/ترم می‌رسد
		if ( 'url' === $item['kind'] && '' !== $item['url'] ) {
			$url = self::follow_redirect( $item['url'], $out );
			if ( null === $url ) {
				$out['url'] = $item['url'];
				return self::finish( $out, $self_id, $self_context );
			}
			$target = self::resolve_url( $url );
			if ( $target ) {
				$out['kind'] = $target['kind'];
				$out['id']   = $target['id'];
			} else {
				$out['url']        = $url;
				$out['type_label'] = self::is_internal( $url ) ? 'آدرس دلخواه' : 'آدرس خارجی';
				$out['problems'][] = self::is_internal( $url ) ? 'unresolved' : 'external';
			}
		} elseif ( 'url' === $item['kind'] ) {
			$out['problems'][] = 'empty_url';
		}

		if ( 'post' === $out['kind'] ) {
			$post = get_post( $out['id'] );
			// پست‌تایپ ثبت‌نشده (مثلا ووکامرس غیرفعال) آدرس «?p=…» می‌گیرد: مثل حذف‌شده
			if ( ! $post instanceof WP_Post || ! is_post_type_viewable( $post->post_type ) ) {
				$out['problems'][] = 'missing';
			} else {
				$type              = get_post_type_object( $post->post_type );
				$out['type_label'] = $type ? (string) $type->labels->singular_name : $post->post_type;
				if ( 'publish' !== $post->post_status || '' !== $post->post_password ) {
					$out['problems'][] = 'unpublished';
				}
				$out['url']     = (string) get_permalink( $post );
				$out['title']   = '' !== $out['title'] ? $out['title'] : wp_strip_all_tags( get_the_title( $post ) );
				$out['img_id']  = $out['img_id'] ?: (int) get_post_thumbnail_id( $post );
				$out['noindex'] = self::is_noindex( $post->ID, 'post' );
			}
		} elseif ( 'term' === $out['kind'] ) {
			$term = get_term( $out['id'] );
			$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
			if ( ! $term instanceof WP_Term || ! is_string( $link ) ) {
				$out['problems'][] = 'missing';
			} else {
				$tax               = get_taxonomy( $term->taxonomy );
				$out['type_label'] = $tax ? (string) $tax->labels->singular_name : $term->taxonomy;
				$out['url']        = $link;
				$out['title']      = '' !== $out['title'] ? $out['title'] : $term->name;
				$out['img_id']     = $out['img_id'] ?: (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				$out['noindex']    = self::is_noindex( $term->term_id, 'term' );
			}
		}

		return self::finish( $out, $self_id, $self_context );
	}

	/**
	 * @param array<string, mixed> $out
	 * @return array<string, mixed>
	 */
	private static function finish( array $out, int $self_id, string $self_context ): array {

		if ( $out['noindex'] ) {
			$out['problems'][] = 'noindex';
		}

		if ( '' === trim( (string) $out['title'] ) && ! array_intersect( [ 'missing', 'empty_url' ], $out['problems'] ) ) {
			$out['problems'][] = 'no_title';
		}

		if ( $self_id && $out['id'] === $self_id && ( 'term' === $out['kind'] ) === ( 'term' === $self_context ) ) {
			$out['problems'][] = 'self';
		}

		if ( $out['img_id'] && ! wp_attachment_is_image( $out['img_id'] ) ) {
			$out['img_id'] = 0;
		}
		if ( ! $out['img_id'] && ! array_intersect( self::HIDING, $out['problems'] ) ) {
			$out['problems'][] = 'no_image';
		}

		$hiding = self::HIDING;
		if ( ! self::settings()['hide_noindex'] ) {
			$hiding = array_diff( $hiding, [ 'noindex' ] );
		}

		$out['problems'] = array_values( array_unique( $out['problems'] ) );
		$out['visible']  = ! array_intersect( $hiding, $out['problems'] );

		return $out;
	}

	/**
	 * لینک‌های قابل نمایش یک گروه (همان ترتیب؛ اگر خانه‌ای قابل نمایش نیست،
	 * خانه ذخیره بعدی جایش می‌آید).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function visible( Group $group, int $object_id, string $context, ?int $limit = null ): array {

		$limit ??= self::count( $group );
		$items   = [];
		$seen    = [];

		foreach ( self::get( $object_id, $context )[ $group->value ] as $item ) {
			$resolved = self::resolve( $item, $object_id, $context );
			if ( ! $resolved['visible'] || isset( $seen[ $resolved['url'] ] ) ) {
				continue;
			}
			$seen[ $resolved['url'] ] = true;
			$items[]                  = $resolved;
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return $items;
	}

	/* =====================================================================
	 * ابزارهای آدرس
	 * ===================================================================== */

	public static function is_internal( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' );
		}
		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$trim = static fn( string $h ): string => preg_replace( '/^www\./i', '', strtolower( $h ) ) ?? $h;
		return $trim( (string) $host ) === $trim( $home );
	}

	/** مسیر یکسان برای مقایسه (رمزگشایی‌شده، بدون اسلش دو طرف). */
	private static function path_of( string $url ): string {
		return trim( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );
	}

	/**
	 * آدرس داخلی ← نوشته یا ترم (برای تبدیل آدرس‌های چسبانده‌شده و داده نسخه ۱).
	 * url_to_postid آدرس‌های تمیز ماژول router (بدون /product/) و ترم‌ها را
	 * نمی‌شناسد؛ برای همین با نامک هم جستجو و با آدرس واقعی مقصد تطبیق می‌شود.
	 *
	 * @return array{kind:string, id:int}|null
	 */
	public static function resolve_url( string $url ): ?array {

		static $memo = [];

		$url = trim( $url );
		if ( '' === $url || ! self::is_internal( $url ) ) {
			return null;
		}
		if ( ! wp_parse_url( $url, PHP_URL_HOST ) ) {
			$url = home_url( $url );
		}
		if ( array_key_exists( $url, $memo ) ) {
			return $memo[ $url ];
		}

		$path = self::path_of( $url );
		if ( '' === $path ) {
			return $memo[ $url ] = null;
		}

		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			return $memo[ $url ] = [ 'kind' => 'post', 'id' => $post_id ];
		}

		$parts = explode( '/', $path );
		$slug  = sanitize_title( (string) end( $parts ) );

		$types = array_values( array_unique( array_merge( self::post_types(), self::search_scope( Group::Products ), self::search_scope( Group::Article ) ) ) );
		$posts = get_posts( [
			'name'             => $slug,
			'post_type'        => $types,
			'post_status'      => 'any',
			'posts_per_page'   => 5,
			'suppress_filters' => false,
		] );
		foreach ( $posts as $post ) {
			if ( self::path_of( (string) get_permalink( $post ) ) === $path ) {
				return $memo[ $url ] = [ 'kind' => 'post', 'id' => $post->ID ];
			}
		}

		$taxonomies = array_values( array_unique( array_merge( self::taxonomies(), [ 'product_cat', 'category' ] ) ) );
		foreach ( $taxonomies as $taxonomy ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) && self::path_of( $link ) === $path ) {
					return $memo[ $url ] = [ 'kind' => 'term', 'id' => $term->term_id ];
				}
			}
		}

		return $memo[ $url ] = null;
	}

	/**
	 * اگر آدرس در ماژول ریدایرکت‌ها قانون دارد: مقصد نهایی، یا null برای
	 * ۴۰۴/۴۱۰ (مشکل gone ثبت می‌شود).
	 *
	 * @param array<string, mixed> $out
	 */
	private static function follow_redirect( string $url, array &$out ): ?string {

		if ( ! self::is_internal( $url ) || ! function_exists( '\Hodima\Redirects\get_rules' ) ) {
			return $url;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$rule = \Hodima\Redirects\get_rules()[ \Hodima\Redirects\normalize_path( '' !== $path ? $path : '/' ) ] ?? null;

		if ( ! is_array( $rule ) ) {
			return $url;
		}

		$status = (int) ( $rule['status'] ?? 301 );
		if ( in_array( $status, \Hodima\Redirects\REDIRECTS, true ) && '' !== (string) ( $rule['target'] ?? '' ) ) {
			$out['redirect_from'] = $url;
			$out['problems'][]    = 'redirect';
			return \Hodima\Redirects\build_location( (string) $rule['target'] );
		}

		$out['problems'][] = 'gone';
		return null;
	}

	/** noindex بودن مقصد از متاباکس سئو (همان کلید _seobox_robots). */
	public static function is_noindex( int $id, string $context ): bool {
		// تشخیص واحد Core (سئوباکس + یواست/AIOSEO)؛ بدنه زیر فالبک بدون Core
		if ( function_exists( 'hodima_is_noindex' ) ) {
			return hodima_is_noindex( $id, 'term' === $context ? 'term' : 'post' );
		}
		$robots = get_metadata( self::meta_type( $context ), $id, '_seobox_robots', true );
		if ( is_array( $robots ) ) {
			return in_array( 'noindex', $robots, true );
		}
		return is_string( $robots ) && str_contains( strtolower( $robots ), 'noindex' );
	}

	/** توضیح فارسی هر مشکل (پیشخوان و گزارش). */
	public static function problem_label( string $code ): string {
		return match ( $code ) {
			'missing'     => 'مقصد حذف شده است',
			'unpublished' => 'مقصد منتشر نشده (پیش‌نویس، خصوصی یا رمزدار)',
			'self'        => 'لینک به همین صفحه',
			'gone'        => 'این آدرس در ریدایرکت‌ها ۴۰۴/۴۱۰ است',
			'no_title'    => 'عنوان ندارد (برای آدرس دلخواه عنوان لازم است)',
			'empty_url'   => 'آدرس خالی است',
			'noindex'     => 'مقصد noindex است',
			'redirect'    => 'آدرس ریدایرکت می‌شود؛ مقصد نهایی نمایش داده می‌شود',
			'unresolved'  => 'آدرس داخلی به هیچ نوشته یا دسته‌ای نمی‌رسد (احتمال ۴۰۴)',
			'external'    => 'لینک خارجی',
			'no_image'    => 'تصویر ندارد؛ کارت بدون عکس نمایش داده می‌شود',
			default       => $code,
		};
	}

	/** آیا این مشکل کارت را پنهان می‌کند؟ (با در نظر گرفتن تنظیم noindex) */
	public static function is_hiding( string $code ): bool {
		if ( 'noindex' === $code ) {
			return (bool) self::settings()['hide_noindex'];
		}
		return in_array( $code, self::HIDING, true );
	}
}
