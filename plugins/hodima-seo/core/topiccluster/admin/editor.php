<?php
/**
 * خوشه موضوعی — کادر ویرایشگر (نوشته/برگه/محصول) و فیلدهای دسته
 * Path: core/topiccluster/admin/editor.php
 *
 * در هر ویرایشگر:
 *   - پیلار بودن، «خارج از خوشه»
 *   - والد دستی: دسته پیلار، و برای نوشته‌ها «محتوای ستون» (مقاله/برگه پیلار)
 *     فقط پیلارها پیشنهاد می‌شوند و مسیر کامل دسته («والد › فرزند») دیده
 *     می‌شود؛ قبلا دو دسته هم‌نام از هم قابل تشخیص نبودند.
 *   - والد فعلی و منبعش (دستی/خودکار) و هشدار والد نامعتبر
 *   - روی پیلار: ترتیب دستی زیرمجموعه‌ها و موجودیت موضوع (اسکیما)
 *   - پیشنهاد لینک داخلی: اعضای همین خوشه و اینکه متن به هر کدام لینک
 *     داده یا نه (لینک داخل متن ارزش سئوی بیشتری از کادر خوشه دارد)
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
use WP_Post;
use WP_Term;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Editor {

	private const NONCE      = 'hodima_tc_save';
	private const NONCE_NAME = 'hodima_tc_nonce';
	public const AJAX_NONCE  = 'hodima_tc_secure_nonce';

	public static function init(): void {

		add_action( 'add_meta_boxes', [ self::class, 'meta_boxes' ], 20 );
		add_action( 'save_post', [ self::class, 'save_post' ], 10, 2 );

		add_action( 'init', static function (): void {
			foreach ( Graph::taxonomies() as $taxonomy ) {
				add_action( "{$taxonomy}_edit_form_fields", [ self::class, 'term_edit' ], 10, 2 );
				add_action( "{$taxonomy}_add_form_fields", [ self::class, 'term_add' ], 10, 1 );
				add_action( "edited_{$taxonomy}", [ self::class, 'save_term' ], 10, 1 );
				add_action( "created_{$taxonomy}", [ self::class, 'save_term' ], 10, 1 );
			}
		}, 30 );

		add_action( 'wp_ajax_hodima_tc_search', [ self::class, 'ajax_search' ] );
		add_action( 'wp_ajax_hodima_tc_quick', [ self::class, 'ajax_quick' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	/* =================================================================
	 * دارایی‌ها — فقط صفحه‌هایی که استفاده می‌کنند
	 * ================================================================= */

	public static function assets( string $hook ): void {

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$editor = ( 'post' === $screen->base && in_array( $screen->post_type, Graph::post_types(), true ) )
			|| ( in_array( $screen->base, [ 'term', 'edit-tags' ], true ) && in_array( $screen->taxonomy, Graph::taxonomies(), true ) );
		$lists  = ( 'edit' === $screen->base && in_array( $screen->post_type, Graph::post_types(), true ) );
		$module = str_contains( $hook, 'hodima-tc-' );

		if ( ! $editor && ! $lists && ! $module && 'dashboard' !== $screen->base ) {
			return;
		}

		wp_enqueue_style( 'hodima-tc-admin', TOPICCLUSTER_URL . 'assets/admin.css', [], TOPICCLUSTER_VERSION );

		if ( $editor || $module ) {
			// بدون jQuery (قانون پروژه: JavaScript خالص)
			wp_enqueue_script( 'hodima-tc-admin-js', TOPICCLUSTER_URL . 'assets/admin.js', [], TOPICCLUSTER_VERSION, true );
			wp_add_inline_script( 'hodima-tc-admin-js', 'window.hodimaTcConfig = ' . wp_json_encode( [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE ),
			] ) . ';', 'before' );
		}
	}

	/* =================================================================
	 * کادر ویرایشگر نوشته
	 * ================================================================= */

	public static function meta_boxes(): void {
		foreach ( Graph::post_types() as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				add_meta_box( 'hodima_tc_box', 'خوشه‌بندی محتوا', [ self::class, 'render_post' ], $post_type, 'side', 'high' );
			}
		}
	}

	public static function render_post( WP_Post $post ): void {
		echo '<div class="htc-box htc-box--side">';
		self::fields( Ref::post( $post->ID ) );
		echo '</div>';
	}

	public static function term_edit( WP_Term $term, string $taxonomy ): void {
		?>
		<tr class="form-field htc-term-row">
			<th scope="row">خوشه‌بندی محتوا</th>
			<td>
				<div class="htc-box htc-box--term">
					<?php self::fields( Ref::term( (int) $term->term_id ), $taxonomy ); ?>
				</div>
			</td>
		</tr>
		<?php
	}

	public static function term_add( string $taxonomy ): void {
		?>
		<div class="form-field htc-box htc-box--term">
			<span class="htc-box__title">خوشه‌بندی محتوا</span>
			<?php self::fields( null, $taxonomy ); ?>
		</div>
		<?php
	}

	/**
	 * فیلدهای مشترک. $ref = null یعنی فرم «افزودن دسته» (هنوز شناسه‌ای نیست).
	 */
	private static function fields( ?Ref $ref, string $taxonomy = '' ): void {

		wp_nonce_field( self::NONCE, self::NONCE_NAME );

		$is_pillar = $ref && Graph::is_pillar( $ref );
		$excluded  = $ref && Graph::is_excluded( $ref );
		$explicit  = $ref ? Graph::explicit_parents( $ref ) : [];
		$type      = $ref && $ref->is_post() ? Graph::post_type_of( $ref ) : '';
		$is_term   = null === $ref || $ref->is_term();

		// والد دسته‌ای (ترم) یا والد محتوایی (برگه)
		$term_tax = $is_term ? $taxonomy : Graph::parent_taxonomy_for( $type );
		if ( $is_term && '' === $term_tax && $ref ) {
			$t        = get_term( $ref->id );
			$term_tax = $t instanceof WP_Term ? $t->taxonomy : '';
		}
		$self_key = $ref ? $ref->key() : '';
		?>
		<div class="htc-section htc-flags">
			<label class="htc-check">
				<input type="checkbox" name="hodima_is_pillar" value="1" class="hodima-pillar-toggle" <?php checked( $is_pillar ); ?>>
				<span><strong>پیلار (هسته خوشه)</strong> — صفحه مرجع یک موضوع که به همه زیرمجموعه‌هایش لینک می‌دهد</span>
			</label>
			<label class="htc-check">
				<input type="checkbox" name="hodima_tc_exclude" value="1" class="hodima-tc-exclude" <?php checked( $excluded ); ?>>
				<span>خارج از خوشه (بدون والد خودکار، در گزارش یتیم‌ها نمی‌آید)</span>
			</label>
		</div>

		<div class="htc-section htc-parents" <?php echo $excluded ? 'hidden' : ''; ?>>
			<?php
			if ( '' !== $term_tax ) {
				self::select(
					'hodima_pillar_id[]',
					$is_term ? 'دسته والد' : 'دسته پیلار والد',
					'term',
					$term_tax,
					array_values( array_filter( $explicit, static fn( Ref $p ): bool => $p->is_term() ) ),
					$ref,
					$self_key
				);
			}

			if ( null !== $ref && ( Graph::accepts_post_parents( $ref ) || ( $ref->is_post() && '' === $term_tax ) ) ) {
				self::select(
					Graph::accepts_post_parents( $ref ) ? 'hodima_pillar_post_id[]' : 'hodima_pillar_id[]',
					Graph::accepts_post_parents( $ref ) ? 'محتوای ستون (مقاله یا برگه پیلار — اختیاری)' : 'برگه یا مقاله پیلار والد',
					'post',
					implode( ',', Graph::post_pillar_types() ),
					array_values( array_filter( $explicit, static fn( Ref $p ): bool => $p->is_post() ) ),
					$ref,
					$self_key
				);
			}

			if ( null !== $ref ) {
				self::status( $ref );
			} elseif ( Settings::get( 'auto_parent' ) ) {
				echo '<p class="htc-help">اگر والدی انتخاب نشود، نزدیک‌ترین دسته پیلار بالاتر (والد این دسته در وردپرس) خودکار والد می‌شود.</p>';
			}
			?>
		</div>

		<?php
		if ( null !== $ref && $is_pillar ) {
			self::children_order( $ref );
			self::topic_fields( $ref );
		}

		if ( null !== $ref && ! $excluded ) {
			self::link_suggestions( $ref );
		}
		?>

		<div class="htc-section htc-code">
			<?php if ( null !== $ref && Settings::auto_insert( Render::place( $ref ) ) ) : ?>
				<p class="htc-help">کادر خوشه به صورت خودکار نمایش داده می‌شود. برای نمایش در جای دلخواه: <code>[hodima_topic_cluster]</code></p>
			<?php else : ?>
				<p class="htc-help">برای نمایش کادر خوشه در صفحه این کد را در متن بگذارید (یا «نمایش خودکار» را در تنظیمات خوشه‌بندی روشن کنید): <code>[hodima_topic_cluster]</code></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * انتخابگر چندتایی با جستجوی زنده.
	 *
	 * @param list<Ref> $selected
	 */
	private static function select( string $name, string $label, string $context, string $type, array $selected, ?Ref $ref, string $exclude ): void {

		$id = 'htc-' . md5( $name . $type );
		?>
		<div class="htc-field">
			<label class="htc-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" multiple class="hodima-tc-ajax-select"
				data-context="<?php echo esc_attr( $context ); ?>"
				data-type="<?php echo esc_attr( $type ); ?>"
				data-exclude="<?php echo esc_attr( $exclude ); ?>">
				<?php
				foreach ( $selected as $parent ) {
					$node    = Graph::node( $parent );
					$problem = $ref ? Graph::parent_problem( $ref, $parent ) : '';
					$title   = null !== $node ? self::label_for( $parent, $node['title'] ) : '#' . $parent->id;
					if ( '' !== $problem ) {
						$title .= ' — ' . Graph::problem_label( $problem );
					}
					printf( '<option value="%d" selected>%s</option>', (int) $parent->id, esc_html( $title ) );
				}
				?>
			</select>
		</div>
		<?php
	}

	/** «والد › فرزند» برای دسته‌ها؛ عنوان + نوع برای نوشته‌ها. */
	public static function label_for( Ref $ref, string $title = '' ): string {

		if ( $ref->is_term() ) {
			$term = get_term( $ref->id );
			if ( ! $term instanceof WP_Term ) {
				return $title;
			}
			$names = array_map(
				static fn( $id ): string => (string) get_term_field( 'name', (int) $id, $term->taxonomy ),
				array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) )
			);
			$names[] = $term->name;
			return implode( ' › ', array_filter( $names ) );
		}

		$type = (string) get_post_type( $ref->id );
		return ( '' !== $title ? $title : (string) get_the_title( $ref->id ) ) . ' (' . Health::type_label( 'post', $type ) . ')';
	}

	/** والد فعلی و منبعش. */
	private static function status( Ref $ref ): void {

		$parents = Graph::parents( $ref );
		$source  = Graph::parent_source( $ref );

		echo '<p class="htc-status">';

		if ( Graph::is_excluded( $ref ) ) {
			echo '<span class="htc-pill htc-pill--muted">خارج از خوشه</span>';
		} elseif ( ! $parents ) {
			echo Graph::is_pillar( $ref )
				? '<span class="htc-pill htc-pill--ok">پیلار اصلی (بدون والد)</span>'
				: '<span class="htc-pill htc-pill--warn">یتیم: والدی ندارد</span>';
		} else {
			echo '<span class="htc-status__label">والد فعلی:</span> ';
			$links = [];
			foreach ( $parents as $parent ) {
				$node = Graph::node( $parent );
				if ( null !== $node ) {
					$links[] = '<strong>' . esc_html( self::label_for( $parent, $node['title'] ) ) . '</strong>';
				}
			}
			echo implode( '، ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html بالا
			if ( 'auto' === $source ) {
				echo ' <span class="htc-pill htc-pill--muted">خودکار از دسته اصلی</span>';
			}
		}

		echo '</p>';
	}

	/** ترتیب دستی زیرمجموعه‌ها (فقط روی پیلار). */
	private static function children_order( Ref $ref ): void {

		$children = Graph::children( $ref );
		?>
		<div class="htc-section htc-children">
			<p class="htc-label">زیرمجموعه‌ها (<?php echo esc_html( number_format_i18n( count( $children ) ) ); ?>)</p>
			<?php if ( ! $children ) : ?>
				<p class="htc-help">هنوز زیرمجموعه‌ای ندارد. در ویرایشگر صفحه‌های دیگر این پیلار را والد کنید؛ نوشته‌هایی که دسته اصلی‌شان این دسته است خودکار اضافه می‌شوند.</p>
			<?php else : ?>
				<ol class="htc-order" data-hodima-tc-order>
					<?php foreach ( $children as $child ) : ?>
						<li class="htc-order__item">
							<input type="hidden" name="hodima_tc_order[]" value="<?php echo esc_attr( $child['key'] ); ?>">
							<span class="htc-order__title"><?php echo esc_html( $child['title'] ); ?>
								<?php if ( 'auto' === ( $child['source'] ?? '' ) ) : ?><span class="htc-pill htc-pill--muted">خودکار</span><?php endif; ?>
								<?php if ( $child['noindex'] ) : ?><span class="htc-pill htc-pill--warn">noindex</span><?php endif; ?>
							</span>
							<span class="htc-order__actions">
								<button type="button" class="button-link htc-order__up" aria-label="<?php echo esc_attr( 'بالا: ' . $child['title'] ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
								<button type="button" class="button-link htc-order__down" aria-label="<?php echo esc_attr( 'پایین: ' . $child['title'] ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="htc-help">ترتیب نمایش در کادر خوشه و اسکیما. زیرمجموعه‌های جدید انتهای فهرست می‌آیند.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** موجودیت موضوع پیلار → about در اسکیما (اتصال به گراف دانش گوگل). */
	private static function topic_fields( Ref $ref ): void {

		$topic = Graph::topic( $ref );
		$id    = 'htc-topic-' . $ref->kind->value . '-' . $ref->id;
		?>
		<details class="htc-section htc-topic" <?php echo ( '' !== $topic['name'] || $topic['sameas'] ) ? 'open' : ''; ?>>
			<summary class="htc-label">موضوع این خوشه (اسکیما)</summary>
			<div class="htc-field">
				<label class="htc-label" for="<?php echo esc_attr( $id ); ?>-name">نام موضوع</label>
				<input type="text" class="widefat" id="<?php echo esc_attr( $id ); ?>-name" name="hodima_tc_topic[name]" value="<?php echo esc_attr( $topic['name'] ); ?>" placeholder="مثلا: کلیپس مو">
			</div>
			<div class="htc-field">
				<label class="htc-label" for="<?php echo esc_attr( $id ); ?>-sameas">آدرس همین موضوع در ویکی‌پدیا یا ویکی‌داده (هر خط یک آدرس)</label>
				<textarea class="widefat" rows="2" dir="ltr" id="<?php echo esc_attr( $id ); ?>-sameas" name="hodima_tc_topic[sameas]"><?php echo esc_textarea( implode( "\n", $topic['sameas'] ) ); ?></textarea>
			</div>
			<p class="htc-help">در اسکیما «about» این صفحه و همه زیرمجموعه‌هایش می‌شود؛ گوگل و موتورهای هوش مصنوعی خوشه را به یک موجودیت مشخص وصل می‌کنند.</p>
		</details>
		<?php
	}

	/**
	 * پیشنهاد لینک داخلی: اعضای خوشه و وضعیت لینک متن فعلی به هر کدام.
	 * از متن ذخیره‌شده خوانده می‌شود (نه فهرست پس‌زمینه) تا همیشه تازه باشد.
	 */
	private static function link_suggestions( Ref $ref ): void {

		$targets = [];
		foreach ( Graph::parents( $ref ) as $parent ) {
			$node = Graph::node( $parent );
			if ( null !== $node ) {
				$node['role'] = 'پیلار';
				$targets[]    = $node;
			}
		}

		$extra = Graph::is_pillar( $ref ) ? array_slice( Graph::children( $ref ), 0, 12 ) : Graph::siblings( $ref, 8 );
		foreach ( $extra as $node ) {
			$node['role'] = Graph::is_pillar( $ref ) ? 'زیرمجموعه' : 'هم‌خوشه';
			$targets[]    = $node;
		}

		if ( ! $targets ) {
			return;
		}

		$text = $ref->is_term()
			? (string) get_term_field( 'description', $ref->id, '', 'raw' )
			: (string) get_post_field( 'post_content', $ref->id, 'raw' );

		$linked = [];
		foreach ( Links::extract( $text, $ref ) as [ $target ] ) {
			$linked[ $target->key() ] = true;
		}
		?>
		<details class="htc-section htc-suggest" open>
			<summary class="htc-label">پیشنهاد لینک داخلی در متن</summary>
			<ul class="htc-suggest__list">
				<?php foreach ( $targets as $node ) : $ok = isset( $linked[ $node['key'] ] ); ?>
					<li class="htc-suggest__item <?php echo $ok ? 'is-linked' : ''; ?>">
						<span class="dashicons <?php echo $ok ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
						<span class="htc-suggest__text">
							<?php echo esc_html( $node['title'] ); ?>
							<small class="htc-muted"><?php echo esc_html( $node['role'] . ( $ok ? ' · لینک شده' : ' · لینک نشده' ) ); ?></small>
						</span>
						<?php if ( ! $ok ) : ?>
							<button type="button" class="button-link htc-copy" data-copy="<?php echo esc_attr( $node['url'] ); ?>" aria-label="<?php echo esc_attr( 'کپی آدرس ' . $node['title'] ); ?>"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></button>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="htc-help">لینک داخل متن (با عنوان یا کلمه کلیدی صفحه مقصد) ارزش بیشتری از کادر خوشه دارد. وضعیت از آخرین ذخیره این صفحه است.</p>
		</details>
		<?php
	}

	/* =================================================================
	 * ذخیره
	 * ================================================================= */

	public static function save_post( $post_id, $post ): void {

		if ( ! $post instanceof WP_Post || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Graph::post_types(), true ) || ! self::verify() ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		self::save( Ref::post( $post->ID ) );
	}

	public static function save_term( $term_id ): void {

		if ( ! self::verify() ) {
			return;
		}

		$term = get_term( (int) $term_id );
		$tax  = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
		if ( ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
			return;
		}

		self::save( Ref::term( (int) $term_id ) );
	}

	private static function verify(): bool {
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, self::NONCE );
	}

	private static function save( Ref $ref ): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verify() بالا
		$kind    = Graph::parent_kind( $ref );
		$parents = [];

		foreach ( array_map( 'absint', (array) wp_unslash( $_POST['hodima_pillar_id'] ?? [] ) ) as $id ) {
			if ( $id > 0 ) {
				$parents[] = new Ref( $kind, $id );
			}
		}
		foreach ( array_map( 'absint', (array) wp_unslash( $_POST['hodima_pillar_post_id'] ?? [] ) ) as $id ) {
			if ( $id > 0 ) {
				$parents[] = Ref::post( $id );
			}
		}

		$order = isset( $_POST['hodima_tc_order'] )
			? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['hodima_tc_order'] ) )
			: null;

		$pillar   = isset( $_POST['hodima_is_pillar'] );
		$rejected = Graph::save( $ref, $pillar, $parents, isset( $_POST['hodima_tc_exclude'] ), $order );

		if ( $pillar && isset( $_POST['hodima_tc_topic'] ) && is_array( $_POST['hodima_tc_topic'] ) ) {
			Graph::save_topic( $ref, (array) wp_unslash( $_POST['hodima_tc_topic'] ) );
		}
		// phpcs:enable

		if ( $rejected && function_exists( 'hodima_admin_flash' ) ) {
			$lines = array_map(
				static fn( array $r ): string => '«' . esc_html( self::label_for( $r[0] ) ) . '»: ' . esc_html( Graph::problem_label( $r[1] ) ),
				$rejected
			);
			hodima_admin_flash( '<strong>خوشه‌بندی:</strong> این والدها ذخیره نشدند — ' . implode( '؛ ', $lines ), 'warning' );
		}
	}

	/* =================================================================
	 * AJAX
	 * ================================================================= */

	/** جستجوی والد: فقط پیلارها، با مسیر کامل دسته. */
	public static function ajax_search(): void {

		check_ajax_referer( self::AJAX_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'دسترسی غیرمجاز.', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- check_ajax_referer بالا
		$search  = sanitize_text_field( wp_unslash( (string) ( $_GET['q'] ?? '' ) ) );
		$context = 'term' === sanitize_key( wp_unslash( (string) ( $_GET['context'] ?? '' ) ) ) ? 'term' : 'post';
		$types   = array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( (string) ( $_GET['type'] ?? '' ) ) ) ) );
		$exclude = Ref::parse( sanitize_text_field( wp_unslash( (string) ( $_GET['exclude'] ?? '' ) ) ) );
		// phpcs:enable

		$results = [];

		if ( 'term' === $context ) {

			$taxonomy = (string) reset( $types );
			if ( ! in_array( $taxonomy, Graph::taxonomies(), true ) || ! taxonomy_exists( $taxonomy ) ) {
				wp_send_json_success( [] );
			}

			$terms = get_terms( [
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 40,
				'search'     => $search,
				'exclude'    => $exclude && $exclude->is_term() ? [ $exclude->id ] : [],
				'meta_query' => [ [ 'key' => Graph::META_PILLAR, 'value' => '1' ] ],
			] );

			foreach ( is_array( $terms ) ? $terms : [] as $term ) {
				$results[] = [ 'value' => (string) $term->term_id, 'text' => self::label_for( Ref::term( (int) $term->term_id ) ) ];
			}

		} else {

			// نوع پست محدود به فهرست مجاز (قبلا هر مقداری به WP_Query می‌رفت)
			$types = array_values( array_intersect( $types, Graph::post_pillar_types() ) );
			if ( ! $types ) {
				wp_send_json_success( [] );
			}

			$args = [
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => 40,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [ [ 'key' => Graph::META_PILLAR, 'value' => '1' ] ],
				'post__not_in'           => $exclude && $exclude->is_post() ? [ $exclude->id ] : [],
			];
			if ( '' !== $search ) {
				$args['s']              = $search;
				$args['search_columns'] = [ 'post_title' ]; // فقط عنوان، نه کل متن
			}

			foreach ( ( new WP_Query( $args ) )->posts as $post ) {
				$results[] = [ 'value' => (string) $post->ID, 'text' => self::label_for( Ref::post( $post->ID ), wp_strip_all_tags( get_the_title( $post ) ) ) ];
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * کارهای سریع از گزارش یتیم‌ها: والد، پیلار، خارج از خوشه.
	 */
	public static function ajax_quick(): void {

		check_ajax_referer( self::AJAX_NONCE, 'nonce' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer بالا
		$ref    = Ref::parse( sanitize_text_field( wp_unslash( (string) ( $_POST['ref'] ?? '' ) ) ) );
		$action = sanitize_key( wp_unslash( (string) ( $_POST['do'] ?? '' ) ) );
		$parent = Ref::parse( sanitize_text_field( wp_unslash( (string) ( $_POST['parent'] ?? '' ) ) ) );
		// phpcs:enable

		if ( null === $ref || ! Graph::is_member( $ref ) || ! self::can_edit( $ref ) ) {
			wp_send_json_error( [ 'message' => 'دسترسی ندارید.' ], 403 );
		}

		$parents  = Graph::explicit_parents( $ref );
		$pillar   = Graph::is_pillar( $ref );
		$excluded = Graph::is_excluded( $ref );

		switch ( $action ) {
			case 'parent':
				if ( null === $parent ) {
					wp_send_json_error( [ 'message' => 'والد را انتخاب کنید.' ] );
				}
				$parents[] = $parent;
				$excluded  = false;
				break;
			case 'pillar':
				$pillar = true;
				break;
			case 'exclude':
				$excluded = true;
				break;
			default:
				wp_send_json_error( [ 'message' => 'عملیات نامعتبر.' ] );
		}

		$rejected = Graph::save( $ref, $pillar, $parents, $excluded );

		if ( $rejected ) {
			wp_send_json_error( [ 'message' => Graph::problem_label( $rejected[0][1] ) ] );
		}

		wp_send_json_success( [ 'message' => match ( $action ) {
			'parent'  => 'به خوشه اضافه شد.',
			'pillar'  => 'پیلار شد.',
			default   => 'از خوشه‌بندی خارج شد.',
		} ] );
	}

	public static function can_edit( Ref $ref ): bool {
		if ( $ref->is_post() ) {
			return current_user_can( 'edit_post', $ref->id );
		}
		$term = get_term( $ref->id );
		$tax  = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
		return (bool) ( $tax && current_user_can( $tax->cap->edit_terms ) );
	}
}
