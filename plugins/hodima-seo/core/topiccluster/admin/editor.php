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
				add_action( "{$taxonomy}_edit_form", [ self::class, 'term_edit' ], 8, 2 );
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
	 * کادر ویرایشگر
	 * -----------------------------------------------------------------
	 * طراحی نسخه ۴.۱: یک کادر با نوار وضعیت، سوییچ‌ها و بخش‌های جدا (کارت)،
	 * مثل کادرهای «تنظیمات رسانه» و «لینک‌های مرتبط». در صفحه دسته قبلا
	 * فیلدها بدون کادر داخل جدول فرم وردپرس بودند؛ حالا یک postbox جدا با
	 * عنوان است. چیدمان با container query: در ستون کناری ویرایشگر یک‌ستونه،
	 * در صفحه دسته دوستونه.
	 * ================================================================= */

	public static function meta_boxes(): void {
		foreach ( Graph::post_types() as $post_type ) {
			if ( post_type_exists( $post_type ) ) {
				add_meta_box( 'hodima_tc_box', 'خوشه‌بندی محتوا', [ self::class, 'render_post' ], $post_type, 'side', 'high' );
			}
		}
	}

	public static function render_post( WP_Post $post ): void {
		self::box( Ref::post( $post->ID ) );
	}

	/** صفحه ویرایش دسته: postbox جدا بعد از فیلدهای اصلی (داخل همان فرم). */
	public static function term_edit( $term, string $taxonomy = '' ): void {

		if ( ! $term instanceof WP_Term ) {
			return;
		}
		?>
		<div class="postbox htc-postbox">
			<div class="postbox-header"><h2 class="hndle">خوشه‌بندی محتوا</h2></div>
			<div class="inside"><?php self::box( Ref::term( (int) $term->term_id ), $term->taxonomy ); ?></div>
		</div>
		<?php
	}

	/** فرم «افزودن دسته» (ستون کناری صفحه دسته‌ها). */
	public static function term_add( string $taxonomy ): void {
		?>
		<div class="htc-add-term">
			<p class="htc-add-term__title"><span class="dashicons dashicons-networking" aria-hidden="true"></span> خوشه‌بندی محتوا</p>
			<?php self::box( null, $taxonomy ); ?>
		</div>
		<?php
	}

	/**
	 * کادر مشترک. $ref = null یعنی فرم «افزودن دسته» (هنوز شناسه‌ای نیست).
	 */
	private static function box( ?Ref $ref, string $taxonomy = '' ): void {

		wp_nonce_field( self::NONCE, self::NONCE_NAME );

		$is_pillar = $ref && Graph::is_pillar( $ref );
		$excluded  = $ref && Graph::is_excluded( $ref );
		$explicit  = $ref ? Graph::explicit_parents( $ref ) : [];
		$type      = $ref && $ref->is_post() ? Graph::post_type_of( $ref ) : '';
		$is_term   = null === $ref || $ref->is_term();
		$self_key  = $ref ? $ref->key() : '';

		// والد دسته‌ای (ترم) یا والد محتوایی (برگه)
		$term_tax = $is_term ? $taxonomy : Graph::parent_taxonomy_for( $type );
		if ( $is_term && '' === $term_tax && $ref ) {
			$t        = get_term( $ref->id );
			$term_tax = $t instanceof WP_Term ? $t->taxonomy : '';
		}

		$post_parents = null !== $ref && ( Graph::accepts_post_parents( $ref ) || ( $ref->is_post() && '' === $term_tax ) );
		?>
		<div class="htc-box" data-hodima-tc-box>

			<?php if ( null !== $ref ) { self::summary( $ref ); } ?>

			<div class="htc-box__grid">
				<div class="htc-box__col">

					<section class="htc-card">
						<h3 class="htc-card__title"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>نقش در خوشه</h3>
						<label class="htc-switch-row">
							<input type="checkbox" name="hodima_is_pillar" value="1" class="htc-switch hodima-pillar-toggle" role="switch" <?php checked( $is_pillar ); ?>>
							<span class="htc-switch-row__text">
								<strong>پیلار (هسته خوشه)</strong>
								<small>صفحه مرجع یک موضوع که به همه زیرمجموعه‌هایش لینک می‌دهد.</small>
							</span>
						</label>
						<label class="htc-switch-row">
							<input type="checkbox" name="hodima_tc_exclude" value="1" class="htc-switch hodima-tc-exclude" role="switch" <?php checked( $excluded ); ?>>
							<span class="htc-switch-row__text">
								<strong>خارج از خوشه</strong>
								<small>والد خودکار نمی‌گیرد و در گزارش محتوای یتیم نمی‌آید.</small>
							</span>
						</label>
					</section>

					<section class="htc-card htc-parents" <?php echo $excluded ? 'hidden' : ''; ?>>
						<h3 class="htc-card__title"><span class="dashicons dashicons-arrow-up-alt" aria-hidden="true"></span>والد</h3>
						<?php
						if ( '' !== $term_tax ) {
							self::select(
								'hodima_pillar_id[]',
								$is_term ? 'دسته پیلار والد' : 'دسته پیلار',
								'term',
								$term_tax,
								array_values( array_filter( $explicit, static fn( Ref $p ): bool => $p->is_term() ) ),
								$ref,
								$self_key
							);
						}

						if ( $post_parents ) {
							$accepts = Graph::accepts_post_parents( $ref );
							self::select(
								$accepts ? 'hodima_pillar_post_id[]' : 'hodima_pillar_id[]',
								$accepts ? 'محتوای ستون (اختیاری)' : 'برگه یا مقاله پیلار',
								'post',
								implode( ',', Graph::post_pillar_types() ),
								array_values( array_filter( $explicit, static fn( Ref $p ): bool => $p->is_post() ) ),
								$ref,
								$self_key
							);
							if ( $accepts ) {
								echo '<p class="htc-help">یک مقاله یا برگه «راهنمای جامع» که پیلار است.</p>';
							}
						}

						if ( Settings::get( 'auto_parent' ) ) {
							echo '<p class="htc-help">';
							echo match ( true ) {
								$is_term          => 'خالی بگذارید تا نزدیک‌ترین دسته پیلار بالاتر (والد این دسته) خودکار والد شود.',
								'' === $term_tax  => 'خالی بگذارید تا نزدیک‌ترین برگه پیلار بالاتر (برگه والد) خودکار والد شود.',
								default           => 'خالی بگذارید تا دسته اصلی (یا نزدیک‌ترین دسته پیلار بالاتر) خودکار والد شود.',
							};
							echo '</p>';
						}
						?>
					</section>

				</div>

				<?php if ( null !== $ref && ( $is_pillar || ! $excluded ) ) : ?>
					<div class="htc-box__col">
						<?php
						if ( $is_pillar ) {
							self::children_order( $ref );
							self::topic_fields( $ref );
						}
						if ( ! $excluded ) {
							self::link_suggestions( $ref );
						}
						?>
					</div>
				<?php endif; ?>
			</div>

			<div class="htc-foot">
				<span class="htc-foot__label">شورت‌کد کادر:</span>
				<code class="htc-foot__code">[hodima_topic_cluster]</code>
				<button type="button" class="htc-icon-btn htc-copy" data-copy="[hodima_topic_cluster]" aria-label="کپی شورت‌کد"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
				<span class="htc-foot__note">
					<?php echo ( null !== $ref && Settings::auto_insert( Render::place( $ref ) ) ) ? 'نمایش خودکار روشن است؛ شورت‌کد فقط برای جای دلخواه.' : 'برای نمایش کادر در صفحه، در متن بگذارید (یا «نمایش خودکار» را در تنظیمات خوشه‌بندی روشن کنید).'; ?>
				</span>
			</div>
		</div>
		<?php
	}

	/** نوار وضعیت بالای کادر. */
	private static function summary( ?Ref $ref ): void {

		[ $state, $title, $text ] = match ( true ) {
			null === $ref               => [ 'new', 'دسته جدید', 'وضعیت خوشه بعد از ذخیره مشخص می‌شود.' ],
			Graph::is_excluded( $ref )  => [ 'muted', 'خارج از خوشه', 'این صفحه عمدا در هیچ خوشه‌ای نیست.' ],
			default                     => self::state_text( $ref ),
		};

		$icon = [ 'pillar' => 'dashicons-star-filled', 'member' => 'dashicons-networking', 'orphan' => 'dashicons-warning', 'muted' => 'dashicons-hidden', 'new' => 'dashicons-plus-alt2' ][ $state ];
		?>
		<div class="htc-summary htc-summary--<?php echo esc_attr( $state ); ?>">
			<span class="htc-summary__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span class="htc-summary__body">
				<strong class="htc-summary__title"><?php echo esc_html( $title ); ?></strong>
				<span class="htc-summary__text"><?php echo esc_html( $text ); ?></span>
			</span>
			<?php if ( null !== $ref && current_user_can( 'manage_options' ) ) : ?>
				<a class="htc-summary__link" href="<?php echo esc_url( admin_url( 'admin.php?page=hodima-tc-map' ) ); ?>">نقشه خوشه‌ها</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return array{0:string, 1:string, 2:string} حالت، عنوان، توضیح */
	private static function state_text( Ref $ref ): array {

		$titles = [];
		foreach ( Graph::parents( $ref ) as $parent ) {
			$node = Graph::node( $parent );
			if ( null !== $node ) {
				$titles[] = '«' . $node['title'] . '»';
			}
		}
		$under = $titles ? 'زیر ' . implode( '، ', $titles ) . ( 'auto' === Graph::parent_source( $ref ) ? ' (خودکار از دسته اصلی)' : '' ) : '';

		if ( Graph::is_pillar( $ref ) ) {
			$count = number_format_i18n( count( Graph::children( $ref ) ) );
			return [ 'pillar', 'پیلار · ' . $count . ' زیرمجموعه', '' !== $under ? 'خودش ' . $under . ' است.' : 'پیلار اصلی؛ والدی ندارد.' ];
		}

		if ( $titles ) {
			return [ 'member', 'عضو خوشه', $under . '.' ];
		}

		return [ 'orphan', 'یتیم', 'به هیچ خوشه‌ای وصل نیست. یک پیلار والد انتخاب کنید یا خودش را پیلار کنید.' ];
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

	/** ترتیب دستی زیرمجموعه‌ها (فقط روی پیلار). */
	private static function children_order( Ref $ref ): void {

		$children = Graph::children( $ref );
		?>
		<section class="htc-card htc-children">
			<h3 class="htc-card__title">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>زیرمجموعه‌ها
				<span class="htc-count"><?php echo esc_html( number_format_i18n( count( $children ) ) ); ?></span>
			</h3>
			<?php if ( ! $children ) : ?>
				<p class="htc-empty-note">هنوز زیرمجموعه‌ای ندارد. در ویرایشگر صفحه‌های دیگر این پیلار را والد کنید؛ نوشته‌هایی که دسته اصلی‌شان این دسته است خودکار اضافه می‌شوند.</p>
			<?php else : ?>
				<ol class="htc-order" data-hodima-tc-order>
					<?php foreach ( $children as $child ) : ?>
						<li class="htc-order__item">
							<input type="hidden" name="hodima_tc_order[]" value="<?php echo esc_attr( $child['key'] ); ?>">
							<span class="htc-order__title">
								<?php echo esc_html( $child['title'] ); ?>
								<span class="htc-order__tags">
									<span class="htc-pill htc-pill--muted"><?php echo esc_html( Health::type_label( $child['kind'], $child['type'] ) ); ?></span>
									<?php if ( 'auto' === ( $child['source'] ?? '' ) ) : ?><span class="htc-pill htc-pill--muted">خودکار</span><?php endif; ?>
									<?php if ( $child['noindex'] ) : ?><span class="htc-pill htc-pill--warn">noindex</span><?php endif; ?>
								</span>
							</span>
							<span class="htc-order__actions">
								<button type="button" class="htc-icon-btn htc-order__up" aria-label="<?php echo esc_attr( 'بالا: ' . $child['title'] ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
								<button type="button" class="htc-icon-btn htc-order__down" aria-label="<?php echo esc_attr( 'پایین: ' . $child['title'] ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="htc-help">ترتیب نمایش در کادر خوشه و اسکیما. زیرمجموعه‌های جدید انتهای فهرست می‌آیند.</p>
			<?php endif; ?>
		</section>
		<?php
	}

	/** موجودیت موضوع پیلار → about در اسکیما (اتصال به گراف دانش گوگل). */
	private static function topic_fields( Ref $ref ): void {

		$topic = Graph::topic( $ref );
		$id    = 'htc-topic-' . $ref->kind->value . '-' . $ref->id;
		$set   = '' !== $topic['name'] || $topic['sameas'];
		?>
		<details class="htc-card htc-card--toggle htc-topic" <?php echo $set ? 'open' : ''; ?>>
			<summary class="htc-card__title">
				<span class="dashicons dashicons-tag" aria-hidden="true"></span>موضوع این خوشه (اسکیما)
				<?php if ( $set ) : ?><span class="htc-pill htc-pill--ok">تنظیم شده</span><?php endif; ?>
			</summary>
			<div class="htc-field">
				<label class="htc-label" for="<?php echo esc_attr( $id ); ?>-name">نام موضوع</label>
				<input type="text" class="htc-input" id="<?php echo esc_attr( $id ); ?>-name" name="hodima_tc_topic[name]" value="<?php echo esc_attr( $topic['name'] ); ?>" placeholder="مثلا: کلیپس مو">
			</div>
			<div class="htc-field">
				<label class="htc-label" for="<?php echo esc_attr( $id ); ?>-sameas">آدرس ویکی‌پدیا یا ویکی‌داده (هر خط یک آدرس)</label>
				<textarea class="htc-input" rows="2" dir="ltr" id="<?php echo esc_attr( $id ); ?>-sameas" name="hodima_tc_topic[sameas]" placeholder="https://fa.wikipedia.org/wiki/..."><?php echo esc_textarea( implode( "\n", $topic['sameas'] ) ); ?></textarea>
			</div>
			<p class="htc-help">در اسکیما «about» این صفحه و همه زیرمجموعه‌هایش می‌شود؛ گوگل و موتورهای هوش مصنوعی خوشه را به یک موضوع مشخص وصل می‌کنند.</p>
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

		$done = count( array_filter( $targets, static fn( array $n ): bool => isset( $linked[ $n['key'] ] ) ) );
		?>
		<details class="htc-card htc-card--toggle htc-suggest" <?php echo $done < count( $targets ) ? 'open' : ''; ?>>
			<summary class="htc-card__title">
				<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>پیشنهاد لینک داخلی در متن
				<span class="htc-pill <?php echo $done === count( $targets ) ? 'htc-pill--ok' : 'htc-pill--warn'; ?>"><?php echo esc_html( number_format_i18n( $done ) . ' از ' . number_format_i18n( count( $targets ) ) ); ?></span>
			</summary>
			<ul class="htc-suggest__list">
				<?php foreach ( $targets as $node ) : $ok = isset( $linked[ $node['key'] ] ); ?>
					<li class="htc-suggest__item <?php echo $ok ? 'is-linked' : ''; ?>">
						<span class="htc-suggest__icon dashicons <?php echo $ok ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
						<span class="htc-suggest__text">
							<?php echo esc_html( $node['title'] ); ?>
							<small><?php echo esc_html( $node['role'] . ( $ok ? ' · در متن لینک شده' : ' · هنوز لینک نشده' ) ); ?></small>
						</span>
						<?php if ( ! $ok ) : ?>
							<button type="button" class="htc-icon-btn htc-copy" data-copy="<?php echo esc_attr( $node['url'] ); ?>" aria-label="<?php echo esc_attr( 'کپی آدرس ' . $node['title'] ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
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
