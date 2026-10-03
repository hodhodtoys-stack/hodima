<?php
/**
 * خوشه موضوعی — فهرست نوشته‌ها/محصولات/دسته‌ها
 * Path: core/topiccluster/admin/lists.php
 *
 *   - ستون «خوشه»: پیلار (تعداد زیرمجموعه)، والد (دستی/خودکار)، یتیم، خارج
 *   - فیلتر فهرست نوشته‌ها: پیلارها، دارای والد دستی، بدون والد دستی، خارج از خوشه
 *   - ویرایش گروهی (Bulk Edit): والد، پیلار و «خارج از خوشه» برای چند نوشته
 *     با هم — قبلا هر نوشته باید جدا باز و ذخیره می‌شد.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster\Admin;

use Hodima\TopicCluster\Graph;
use Hodima\TopicCluster\Ref;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Lists {

	public const COLUMN = 'hodima_tc';

	public static function init(): void {

		add_action( 'admin_init', static function (): void {

			foreach ( Graph::post_types() as $post_type ) {
				add_filter( "manage_{$post_type}_posts_columns", [ self::class, 'columns' ], 20 );
				add_action( "manage_{$post_type}_posts_custom_column", [ self::class, 'post_cell' ], 10, 2 );
			}
			// برگه‌ها هوک نوع سلسله‌مراتبی هم دارند؛ همان هوک بالا برای page کار می‌کند

			foreach ( Graph::taxonomies() as $taxonomy ) {
				add_filter( "manage_edit-{$taxonomy}_columns", [ self::class, 'columns' ], 20 );
				add_filter( "manage_{$taxonomy}_custom_column", [ self::class, 'term_cell' ], 10, 3 );
			}
		} );

		add_action( 'restrict_manage_posts', [ self::class, 'filter_dropdown' ], 20, 1 );
		add_action( 'pre_get_posts', [ self::class, 'apply_filter' ] );
		add_action( 'bulk_edit_custom_box', [ self::class, 'bulk_box' ], 10, 2 );
		add_action( 'bulk_edit_posts', [ self::class, 'bulk_save' ], 10, 2 );
	}

	/** @param array<string, string> $columns */
	public static function columns( array $columns ): array {
		$columns[ self::COLUMN ] = 'خوشه';
		return $columns;
	}

	public static function post_cell( string $column, int $post_id ): void {
		if ( self::COLUMN === $column ) {
			echo self::cell( Ref::post( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- ساخته‌شده با esc_*
		}
	}

	public static function term_cell( $content, $column, $term_id ): string {
		return self::COLUMN === $column ? self::cell( Ref::term( (int) $term_id ) ) : (string) $content;
	}

	private static function cell( Ref $ref ): string {

		if ( Graph::is_excluded( $ref ) ) {
			return '<span class="htc-pill htc-pill--muted">خارج از خوشه</span>';
		}

		$out = '';

		if ( Graph::is_pillar( $ref ) ) {
			$count = count( Graph::children( $ref ) );
			$out  .= sprintf( '<span class="htc-pill htc-pill--ok">پیلار · %s زیرمجموعه</span> ', esc_html( number_format_i18n( $count ) ) );
		}

		$parents = Graph::parents( $ref );

		if ( $parents ) {
			$titles = [];
			foreach ( $parents as $parent ) {
				$node = Graph::node( $parent );
				if ( null !== $node ) {
					$titles[] = esc_html( $node['title'] );
				}
			}
			$out .= '<span class="htc-cell__parent">← ' . implode( '، ', $titles ) . '</span>';
			if ( 'auto' === Graph::parent_source( $ref ) ) {
				$out .= ' <span class="htc-pill htc-pill--muted">خودکار</span>';
			}
		} elseif ( '' === $out ) {
			$out = '<span class="htc-pill htc-pill--warn">یتیم</span>';
		}

		return $out;
	}

	/* =================================================================
	 * فیلتر
	 * ================================================================= */

	private const FILTERS = [
		'pillar'      => 'پیلارها',
		'explicit'    => 'دارای والد دستی',
		'no_explicit' => 'بدون والد دستی',
		'excluded'    => 'خارج از خوشه',
	];

	public static function filter_dropdown( string $post_type ): void {

		if ( ! in_array( $post_type, Graph::post_types(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
		$current = sanitize_key( wp_unslash( (string) ( $_GET['hodima_tc'] ?? '' ) ) );
		?>
		<label class="screen-reader-text" for="hodima-tc-filter">فیلتر خوشه</label>
		<select name="hodima_tc" id="hodima-tc-filter">
			<option value="">همه (خوشه)</option>
			<?php foreach ( self::FILTERS as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function apply_filter( WP_Query $query ): void {

		if ( ! is_admin() || ! $query->is_main_query() || 'edit' !== ( get_current_screen()->base ?? '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
		$filter = sanitize_key( wp_unslash( (string) ( $_GET['hodima_tc'] ?? '' ) ) );
		if ( ! isset( self::FILTERS[ $filter ] ) ) {
			return;
		}

		$meta = (array) $query->get( 'meta_query' );

		$meta[] = match ( $filter ) {
			'pillar'      => [ 'key' => Graph::META_PILLAR, 'value' => '1' ],
			'excluded'    => [ 'key' => Graph::META_EXCLUDE, 'value' => '1' ],
			'explicit'    => [ 'relation' => 'OR', [ 'key' => Graph::META_PARENT, 'compare' => 'EXISTS' ], [ 'key' => Graph::META_PARENT_POST, 'compare' => 'EXISTS' ] ],
			default       => [ 'relation' => 'AND', [ 'key' => Graph::META_PARENT, 'compare' => 'NOT EXISTS' ], [ 'key' => Graph::META_PARENT_POST, 'compare' => 'NOT EXISTS' ] ],
		};

		$query->set( 'meta_query', $meta );
	}

	/* =================================================================
	 * ویرایش گروهی
	 * ================================================================= */

	public static function bulk_box( string $column, string $post_type ): void {

		if ( self::COLUMN !== $column || ! in_array( $post_type, Graph::post_types(), true ) ) {
			return;
		}

		$options = self::pillar_options( $post_type );
		?>
		<fieldset class="inline-edit-col-right htc-bulk">
			<div class="inline-edit-col">
				<span class="title inline-edit-categories-label">خوشه</span>
				<label class="inline-edit-group">
					<span class="title">والد</span>
					<select name="hodima_tc_bulk_parent">
						<option value="">— بدون تغییر —</option>
						<option value="none">حذف والدهای دستی</option>
						<?php foreach ( $options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="inline-edit-group">
					<span class="title">پیلار</span>
					<select name="hodima_tc_bulk_pillar">
						<option value="">— بدون تغییر —</option>
						<option value="1">پیلار شود</option>
						<option value="0">پیلار نباشد</option>
					</select>
				</label>
				<label class="inline-edit-group">
					<span class="title">عضویت</span>
					<select name="hodima_tc_bulk_exclude">
						<option value="">— بدون تغییر —</option>
						<option value="1">خارج از خوشه</option>
						<option value="0">داخل خوشه</option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * پیلارهایی که برای این نوع پست والد مجازند (کلید Ref ← برچسب).
	 *
	 * @return array<string, string>
	 */
	public static function pillar_options( string $post_type ): array {

		$taxonomy = Graph::parent_taxonomy_for( $post_type );
		$options  = [];

		foreach ( Graph::pillars() as $pillar ) {

			if ( $pillar->is_term() ) {
				$term = get_term( $pillar->id );
				if ( '' === $taxonomy || ! $term || $term->taxonomy !== $taxonomy ) {
					continue;
				}
			} else {
				$type    = Graph::post_type_of( $pillar );
				$allowed = ( '' === $taxonomy || ! in_array( $post_type, Graph::hidden_child_post_types(), true ) )
					&& in_array( $type, Graph::post_pillar_types(), true );
				if ( ! $allowed ) {
					continue;
				}
			}

			$options[ $pillar->key() ] = Editor::label_for( $pillar );
		}

		asort( $options );

		return $options;
	}

	/**
	 * @param list<int>            $updated
	 * @param array<string, mixed> $shared
	 */
	public static function bulk_save( array $updated, array $shared = [] ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- bulk-posts بررسی شده (edit.php)
		$parent  = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['hodima_tc_bulk_parent'] ?? '' ) ) );
		$pillar  = sanitize_key( wp_unslash( (string) ( $_REQUEST['hodima_tc_bulk_pillar'] ?? '' ) ) );
		$exclude = sanitize_key( wp_unslash( (string) ( $_REQUEST['hodima_tc_bulk_exclude'] ?? '' ) ) );
		// phpcs:enable

		if ( '' === $parent && '' === $pillar && '' === $exclude ) {
			return;
		}

		$target   = 'none' === $parent ? null : Ref::parse( $parent );
		$problems = 0;

		foreach ( $updated as $post_id ) {

			$ref = Ref::post( (int) $post_id );
			if ( ! Graph::is_member( $ref ) || ! current_user_can( 'edit_post', $ref->id ) ) {
				continue;
			}

			$parents = Graph::explicit_parents( $ref );
			if ( 'none' === $parent ) {
				$parents = [];
			} elseif ( null !== $target ) {
				$parents[] = $target;
			}

			$rejected = Graph::save(
				$ref,
				'' === $pillar ? Graph::is_pillar( $ref ) : '1' === $pillar,
				$parents,
				'' === $exclude ? Graph::is_excluded( $ref ) : '1' === $exclude
			);

			$problems += count( $rejected );
		}

		if ( $problems && function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( sprintf( '<strong>خوشه‌بندی:</strong> والد برای %s مورد ذخیره نشد (نوع نامناسب یا حلقه).', esc_html( number_format_i18n( $problems ) ) ), 'warning' );
		}
	}
}
