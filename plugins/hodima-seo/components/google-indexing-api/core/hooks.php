<?php
/**
 * Google Indexing — WordPress hooks
 * Path: components/google-indexing-api/core/hooks.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_GI_Hooks {

	/** @var array<int, string> */
	private static array $deleted_urls = [];

	public static function init(): void {
		add_action( 'before_delete_post', [ __CLASS__, 'before_delete' ] );
		add_action( 'deleted_post', [ __CLASS__, 'after_delete' ], 10, 2 );
		add_action( 'transition_post_status', [ __CLASS__, 'status_transition' ], 10, 3 );
		add_action( 'woocommerce_product_set_stock_status', [ __CLASS__, 'stock_ping' ], 10, 2 );
		add_action( 'update_post_meta', [ __CLASS__, 'price_drop_ping' ], 10, 4 );
		add_action( 'save_post', [ __CLASS__, 'auto_cf_purge' ], 99, 2 );
		add_action( 'hodima_gi_daily_pruning', [ __CLASS__, 'daily_maintenance' ] );
	}

	/**
	 * کار روزانه کرون.
	 *
	 * باگ نسخه قبلی: prune_old_records مستقیم به هوک وصل بود. do_action
	 * بدون آرگومان یک رشته خالی به کال‌بک می‌دهد و پارامتر int $days با
	 * آن TypeError می‌داد — هرس شبانه هرگز اجرا نشده بود و جدول‌ها فقط
	 * با دکمه دستی کوچک می‌شدند.
	 */
	public static function daily_maintenance(): void {
		Hodima_Crawler_DB_Queries::prune_old_records();

		if ( class_exists( 'Hodima_Bot_Detector' ) ) {
			Hodima_Bot_Detector::maybe_refresh_ranges();
		}
	}

	private static function tracked( string $post_type ): bool {
		return in_array( $post_type, (array) ( Hodima_GI_Helper::get_settings()['google_post_types'] ?? [] ), true );
	}

	/**
	 * آدرس عمومی واقعی یک پست — حتی اگر در زباله‌دان باشد.
	 *
	 * باگ نسخه قبلی: وردپرس هنگام انتقال به زباله‌دان نامک را به
	 * «slug__trashed» تغییر می‌دهد، و این کار *قبل از* اجرای
	 * transition_post_status انجام می‌شود. پس get_permalink() آدرسی
	 * برمی‌گرداند که هرگز منتشر نشده بود؛ گوگل درخواست حذف یک آدرس
	 * ناموجود را می‌گرفت و آدرس واقعی هرگز حذف نمی‌شد.
	 *
	 * اولویت: آخرین آدرس ثبت‌شده در جدول ماژول (دقیقا همان چیزی که گوگل
	 * دیده)، سپس پیوند یکتا با حذف پسوند __trashed.
	 */
	private static function public_url( WP_Post $post ): string {

		$known = Hodima_Crawler_DB_Queries::get_last_known_url( (int) $post->ID, $post->post_type );

		if ( $known ) {
			return $known;
		}

		$url = get_permalink( $post );

		if ( ! $url ) {
			return '';
		}

		$url = str_replace( '__trashed', '', (string) $url );

		return Hodima_GI_Helper::clean_url( $url );
	}

	/**
	 * واریاسیون محصول آدرس مستقل ندارد؛ get_permalink() برایش آدرس والد
	 * را با رشته کوئری ویژگی‌ها می‌سازد (?attribute_pa_color=...) که
	 * نباید به گوگل ارسال شود. سیگنال باید به محصول والد برود.
	 */
	private static function resolve_product_id( int $id ): int {
		if ( 'product_variation' === get_post_type( $id ) ) {
			return (int) wp_get_post_parent_id( $id );
		}
		return $id;
	}

	/* =================================================================
	 * حذف
	 * ================================================================= */

	public static function before_delete( int $post_id ): void {

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		/*
		 * اگر پست از زباله‌دان حذف می‌شود، درخواست حذف قبلا هنگام انتقال
		 * به زباله‌دان ارسال شده. نسخه قبلی دوباره ارسال می‌کرد — آن هم با
		 * آدرس slug__trashed — و سهمیه را هدر می‌داد.
		 */
		if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
			return;
		}

		$url = self::public_url( $post );

		if ( '' !== $url ) {
			self::$deleted_urls[ $post_id ] = esc_url_raw( $url );
		}
	}

	public static function after_delete( int $post_id, $post = null ): void {

		if ( ! isset( self::$deleted_urls[ $post_id ] ) ) {
			return;
		}

		if ( $post instanceof WP_Post && self::tracked( $post->post_type ) ) {
			Hodima_GI_Queue::push( self::$deleted_urls[ $post_id ], 'URL_DELETED', 'auto_delete' );
		}

		unset( self::$deleted_urls[ $post_id ] );
	}

	/* =================================================================
	 * تغییر وضعیت
	 * ================================================================= */

	public static function status_transition( string $new, string $old, WP_Post $post ): void {

		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// نوع پست *قبل از* ثبت قفل بررسی می‌شود؛ نسخه قبلی برای هر
		// تغییر وضعیت هر نوع پستی یک ترنزینت می‌نوشت.
		if ( ! self::tracked( $post->post_type ) ) {
			return;
		}

		$lock = 'hodima_ping_lock_' . $post->ID . '_' . $new;
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, 10 );

		// انتشار جدید
		if ( 'publish' === $new && 'publish' !== $old ) {
			$url = get_permalink( $post );
			if ( ! $url ) {
				return;
			}
			$url = Hodima_GI_Helper::clean_url( (string) $url );
			Hodima_GI_Queue::push( $url, 'URL_UPDATED', 'auto_publish' );
			Hodima_Crawler_DB_Queries::upsert_url_data( $url, [ 'object_id' => (int) $post->ID, 'object_type' => $post->post_type ] );
			return;
		}

		// خروج از انتشار
		if ( 'publish' === $old && 'publish' !== $new ) {
			$url = self::public_url( $post );
			if ( '' !== $url ) {
				Hodima_GI_Queue::push( $url, 'URL_DELETED', 'auto_unpublish' );
			}
			return;
		}

		// ویرایش پست منتشرشده
		if ( 'publish' === $new && 'publish' === $old ) {

			$url = get_permalink( $post );
			if ( ! $url ) {
				return;
			}
			$url = Hodima_GI_Helper::clean_url( (string) $url );

			$previous_url = Hodima_Crawler_DB_Queries::get_last_known_url( (int) $post->ID, $post->post_type );

			if ( $previous_url && Hodima_GI_Helper::url_hash( $previous_url ) !== Hodima_GI_Helper::url_hash( $url ) ) {
				Hodima_GI_Queue::push( $previous_url, 'URL_DELETED', 'auto_link_change' );
				Hodima_GI_Queue::push( $url, 'URL_UPDATED', 'auto_link_change' );
				Hodima_Crawler_DB_Queries::upsert_url_data( $url, [ 'object_id' => (int) $post->ID, 'object_type' => $post->post_type ] );
				return;
			}

			$nonce = isset( $_POST['hodima_ping_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_ping_nonce'] ) ) : '';

			if ( ! empty( $_POST['hodima_manual_ping'] ) && wp_verify_nonce( $nonce, 'hodima_manual_ping_' . $post->ID ) ) {
				Hodima_GI_Queue::push( $url, 'URL_UPDATED', 'manual_admin_tick' );
			}

			if ( ! $previous_url ) {
				Hodima_Crawler_DB_Queries::upsert_url_data( $url, [ 'object_id' => (int) $post->ID, 'object_type' => $post->post_type ] );
			}
		}
	}

	/* =================================================================
	 * ووکامرس
	 * ================================================================= */

	private static function ping_parent_categories( int $product_id ): void {

		$terms = wp_get_post_terms( $product_id, [ 'category', 'product_cat' ] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) && $link ) {
				// نسخه قبلی clean_url نمی‌زد و آدرس دارای بیس
				// (/product-category/x/) می‌فرستاد که با ۳۰۱ به آدرس تمیز می‌رود.
				Hodima_GI_Queue::push( esc_url_raw( Hodima_GI_Helper::clean_url( (string) $link ) ), 'URL_UPDATED', 'cluster_sync' );
			}
		}
	}

	private static function ping_product( int $product_id, string $source ): void {

		$product_id = self::resolve_product_id( $product_id );

		if ( $product_id <= 0 || 'publish' !== get_post_status( $product_id ) || ! self::tracked( 'product' ) ) {
			return;
		}

		$url = get_permalink( $product_id );
		if ( ! $url ) {
			return;
		}

		Hodima_GI_Queue::push( Hodima_GI_Helper::clean_url( (string) $url ), 'URL_UPDATED', $source );
		self::ping_parent_categories( $product_id );
	}

	public static function stock_ping( int $product_id, string $stock_status ): void {
		if ( in_array( $stock_status, [ 'outofstock', 'instock' ], true ) ) {
			self::ping_product( $product_id, 'stock_sync' );
		}
	}

	public static function price_drop_ping( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {

		if ( '_price' !== $meta_key ) {
			return;
		}

		// هوک update_post_meta *قبل از* نوشتن اجرا می‌شود، پس این مقدار قبلی است
		$old_price = (float) get_post_meta( $object_id, '_price', true );
		$new_price = is_numeric( $meta_value ) ? (float) $meta_value : 0.0;

		if ( $old_price <= 0 || $new_price <= 0 || $new_price >= $old_price ) {
			return;
		}

		if ( ( ( $old_price - $new_price ) / $old_price ) * 100 >= 1 ) {
			self::ping_product( $object_id, 'price_drop' );
		}
	}

	/* =================================================================
	 * پاکسازی کش
	 * ================================================================= */

	/**
	 * پاکسازی Cloudflare و گرم کردن کش پس از ذخیره.
	 *
	 * وقتی لایت‌اسپید فعال است کنار می‌کشد: لایت‌اسپید خودش صفحه را پاک
	 * می‌کند و خزنده خودش را برای گرم کردن دارد. نسخه قبلی در هر ذخیره
	 * یک درخواست HTTP اضافه به خود سایت می‌فرستاد — که گاهی *قبل از*
	 * پاکسازی لایت‌اسپید اجرا می‌شد و کش را با نسخه قدیمی گرم می‌کرد.
	 */
	public static function auto_cf_purge( int $post_id, WP_Post $post ): void {

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status || ! self::tracked( $post->post_type ) ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( ! $url || ! class_exists( 'Hodima_GI_Tools' ) ) {
			return;
		}

		$url = Hodima_GI_Helper::clean_url( (string) $url );

		Hodima_GI_Tools::cloudflare_purge( $url );

		if ( ! Hodima_GI_Helper::litespeed_active() ) {
			Hodima_GI_Tools::preload_cache( $url );
		}
	}
}
