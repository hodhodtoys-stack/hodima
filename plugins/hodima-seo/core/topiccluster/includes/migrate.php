<?php
/**
 * خوشه موضوعی — مهاجرت داده (یک‌باره، دسته‌ای، فقط در پیشخوان مدیر)
 * Path: core/topiccluster/includes/migrate.php
 *
 * گزینه hodima_tc_storage_version:
 *   ''  → ۳  ردیف‌های سریالایزشده (a:1:{…}) به یک ردیف به ازای هر والد؛
 *           حذف ردیف‌های بی‌مصرف «a:0:{}» و «_hodima_is_pillar = 0»
 *   ۳  → ۴  والد نوشته‌ها از «نوشته» به «دسته اصلی پیلار قبلی»
 *   ۴  → ۵  (جدید) هر صفحه/دسته‌ای که والد کسی انتخاب شده ولی پیلار نیست،
 *           پیلار می‌شود. از این نسخه فقط پیلار والد معتبر است (لینک
 *           دوطرفه)؛ بدون این مرحله لینک «دسته‌بندی‌های مرجع» این صفحه‌ها
 *           از روی سایت حذف می‌شد. حالا همان لینک می‌ماند و صفحه والد هم
 *           فرزندانش را نشان می‌دهد.
 *
 * باگ رفع‌شده در ۳ → ۴: نسخه قبلی فقط ۲۰۰۰ نوشته اول را تبدیل می‌کرد و بعد
 * علامت «تمام» می‌زد؛ در سایتی با نوشته بیشتر، بقیه با شناسه نوشته‌ای که
 * حالا شناسه دسته خوانده می‌شود می‌ماندند. حالا دسته‌ای (۲۰۰ تا در هر
 * بارگذاری) با نشانگر پیشرفت ادامه می‌دهد تا واقعا تمام شود. روی سایتی که
 * قبلا به ۴ رسیده، والدهای نامعتبر در «گزارش سلامت» دیده می‌شوند.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

defined( 'ABSPATH' ) || exit;

final class Migrate {

	private const VERSION_OPTION = 'hodima_tc_storage_version';
	private const CURRENT        = '5';
	private const BATCH          = 200;

	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'run' ] );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	public static function run(): void {

		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version = (string) get_option( self::VERSION_OPTION, '' );

		match ( $version ) {
			self::CURRENT => null,
			'4'           => self::to_5(),
			'3'           => self::to_4(),
			default       => self::to_3(),
		};
	}

	/** مرحله ۳: قالب ذخیره چندردیفی. */
	private static function to_3(): void {

		global $wpdb;

		$did = 0;

		foreach ( [ 'post' => $wpdb->postmeta, 'term' => $wpdb->termmeta ] as $type => $table ) {

			$id_col = ( 'post' === $type ) ? 'post_id' : 'term_id';

			$did += (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE meta_key = %s AND meta_value IN ('', '0', 'a:0:{}') LIMIT %d",
				Graph::META_PARENT,
				self::BATCH
			) );

			$did += (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE meta_key = %s AND meta_value IN ('', '0') LIMIT %d",
				Graph::META_PILLAR,
				self::BATCH
			) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_id, {$id_col} AS object_id, meta_value FROM {$table}
				 WHERE meta_key = %s AND meta_value LIKE %s LIMIT %d",
				Graph::META_PARENT,
				'a:%',
				self::BATCH
			) );

			foreach ( (array) $rows as $row ) {

				$values = maybe_unserialize( $row->meta_value );
				$wpdb->delete( $table, [ 'meta_id' => (int) $row->meta_id ] );
				++$did;

				foreach ( (array) $values as $value ) {
					$value = (int) $value;
					if ( $value > 0 && $value !== (int) $row->object_id ) {
						add_metadata( $type, (int) $row->object_id, Graph::META_PARENT, $value );
					}
				}

				wp_cache_delete( (int) $row->object_id, "{$type}_meta" );
			}
		}

		Graph::touch();

		if ( 0 === $did ) {
			update_option( self::VERSION_OPTION, '3', true );
		}
	}

	/** مرحله ۴: والد نوشته از نوشته به دسته — دسته‌ای و کامل. */
	private static function to_4(): void {

		global $wpdb;

		$default_cat = (int) get_option( 'default_category' );
		$after       = (int) get_option( 'hodima_tc_v4_cursor', 0 );

		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND p.post_type = 'post' AND pm.post_id > %d
			 ORDER BY pm.post_id ASC
			 LIMIT %d",
			Graph::META_PARENT,
			$after,
			self::BATCH
		) );

		$report = get_option( 'hodima_tc_v4_progress', [ 'converted' => 0, 'orphaned' => 0 ] );
		$report = is_array( $report ) ? $report : [ 'converted' => 0, 'orphaned' => 0 ];

		foreach ( (array) $post_ids as $post_id ) {

			$post_id = (int) $post_id;
			$after   = $post_id;

			// مقدار خام (اینجا: شناسه نوشته‌های پیلار قبلی)
			$old = array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, Graph::META_PARENT, false ) ) ) );
			if ( ! $old ) {
				continue;
			}

			if ( ! metadata_exists( 'post', $post_id, '_hodima_pillar_id_legacy' ) ) {
				foreach ( $old as $old_id ) {
					add_post_meta( $post_id, '_hodima_pillar_id_legacy', $old_id );
				}
			}

			$categories = [];
			foreach ( $old as $parent_post_id ) {
				if ( ! in_array( get_post_type( $parent_post_id ), [ 'post', 'page' ], true ) ) {
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

			delete_post_meta( $post_id, Graph::META_PARENT );
			foreach ( $categories as $cat_id ) {
				add_post_meta( $post_id, Graph::META_PARENT, $cat_id );
			}

			$categories ? ++$report['converted'] : ++$report['orphaned'];
		}

		Graph::touch();

		if ( count( (array) $post_ids ) >= self::BATCH ) {
			update_option( 'hodima_tc_v4_cursor', $after, false );
			update_option( 'hodima_tc_v4_progress', $report, false );
			return;
		}

		delete_option( 'hodima_tc_v4_cursor' );
		delete_option( 'hodima_tc_v4_progress' );
		update_option( self::VERSION_OPTION, '4', true );
		update_option( 'hodima_tc_migration_v4_report', $report + [ 'time' => time() ], false );
	}

	/** مرحله ۵: والدهای غیرپیلار پیلار می‌شوند. */
	private static function to_5(): void {

		global $wpdb;

		$promoted = (int) get_option( 'hodima_tc_v5_promoted', 0 );
		$cursor   = (array) get_option( 'hodima_tc_v5_cursor', [ 'post' => 0, 'term' => 0 ] );
		$more     = false;

		foreach ( [ 'post' => $wpdb->postmeta, 'term' => $wpdb->termmeta ] as $type => $table ) {

			$id_col = ( 'post' === $type ) ? 'post_id' : 'term_id';

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_id, {$id_col} AS object_id, meta_value FROM {$table}
				 WHERE meta_key = %s AND meta_id > %d ORDER BY meta_id ASC LIMIT %d",
				Graph::META_PARENT,
				(int) ( $cursor[ $type ] ?? 0 ),
				self::BATCH
			) );

			foreach ( (array) $rows as $row ) {

				$cursor[ $type ] = (int) $row->meta_id;

				$child = new Ref( Kind::from( $type ), (int) $row->object_id );

				// ردیف یتیم یک نوشته حذف‌شده: نوع والدش معلوم نیست
				if ( ! Graph::is_member( $child ) ) {
					continue;
				}

				$parent = new Ref( Graph::parent_kind( $child ), (int) $row->meta_value );

				if ( 'not_pillar' === Graph::parent_problem( $child, $parent ) && ! Graph::would_cycle( $child, $parent ) ) {
					update_metadata( $parent->kind->meta_type(), $parent->id, Graph::META_PILLAR, '1' );
					Graph::reset_memo();
					++$promoted;
				}
			}

			if ( count( (array) $rows ) >= self::BATCH ) {
				$more = true;
			}
		}

		if ( $more ) {
			update_option( 'hodima_tc_v5_cursor', $cursor, false );
			update_option( 'hodima_tc_v5_promoted', $promoted, false );
			return;
		}

		delete_option( 'hodima_tc_v5_cursor' );
		delete_option( 'hodima_tc_v5_promoted' );
		update_option( self::VERSION_OPTION, self::CURRENT, true );

		if ( $promoted > 0 ) {
			update_option( 'hodima_tc_migration_v5_report', [ 'promoted' => $promoted, 'time' => time() ], false );
		}
	}

	/** نتیجه هر مهاجرت یک بار به مدیر گزارش می‌شود. */
	public static function notice(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$map_url = esc_url( admin_url( 'admin.php?page=hodima-tc-map' ) );

		$v4 = get_option( 'hodima_tc_migration_v4_report' );
		if ( is_array( $v4 ) ) {
			delete_option( 'hodima_tc_migration_v4_report' );
			if ( (int) ( $v4['converted'] ?? 0 ) || (int) ( $v4['orphaned'] ?? 0 ) ) {
				printf(
					'<div class="notice notice-info is-dismissible"><p><strong>خوشه‌بندی محتوا:</strong> والد نوشته‌ها از «نوشته» به «دسته‌بندی» منتقل شد. %s نوشته به دسته‌بندی اصلیِ پیلار قبلی‌اش متصل شد و %s نوشته بدون والد ماند. <a href="%s">بررسی محتوای یتیم</a></p></div>',
					esc_html( number_format_i18n( (int) ( $v4['converted'] ?? 0 ) ) ),
					esc_html( number_format_i18n( (int) ( $v4['orphaned'] ?? 0 ) ) ),
					esc_url( admin_url( 'admin.php?page=hodima-tc-orphans' ) )
				);
			}
		}

		$v5 = get_option( 'hodima_tc_migration_v5_report' );
		if ( is_array( $v5 ) ) {
			delete_option( 'hodima_tc_migration_v5_report' );
			printf(
				'<div class="notice notice-info is-dismissible"><p><strong>خوشه‌بندی محتوا:</strong> %s صفحه یا دسته که والد محتوای دیگری انتخاب شده بود ولی «پیلار» نبود، پیلار شد؛ حالا این صفحه‌ها هم فرزندانشان را نشان می‌دهند و لینک خوشه دوطرفه است. <a href="%s">نقشه خوشه‌ها</a></p></div>',
				esc_html( number_format_i18n( (int) $v5['promoted'] ) ),
				$map_url // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url بالا
			);
		}
	}
}
