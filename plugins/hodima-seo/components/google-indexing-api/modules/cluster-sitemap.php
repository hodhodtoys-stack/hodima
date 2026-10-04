<?php
/**
 * Topic Cluster Sitemap — /cluster-sitemap.xml
 * Path: components/google-indexing-api/modules/cluster-sitemap.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * بازنویسی
 * ─────────────────────────────────────────────────────────────────────
 * نسخه قبلی:
 *   - فقط پیلارهای *پستی* را می‌شناخت. پیلارهای دسته‌بندی — که در یک
 *     فروشگاه و پس از تغییر مدل Topic Cluster (نوشته → دسته‌بندی)
 *     پیلارهای اصلی‌اند — کاملا نادیده گرفته می‌شدند.
 *   - فرزندان را با «meta_value IN (…)» پیدا می‌کرد در حالی که ماژول
 *     Topic Cluster والدها را سریالایزشده ذخیره می‌کرد؛ هیچ فرزندی پیدا
 *     نمی‌شد. (در core/topiccluster نسخه ۳ مدل داده اصلاح شد.)
 *   - get_permalink() خام در <loc> می‌گذاشت؛ آدرس دارای بیس ووکامرس که
 *     با ۳۰۱ به آدرس تمیز می‌رود. سایت‌مپ باید فقط آدرس نهایی داشته باشد.
 *   - فرزندی با دو والد دو بار فهرست می‌شد.
 *   - کش فقط با save_post پاک می‌شد، نه با تغییر ترم‌ها.
 *
 * حالا تمام منطق از Hodima_TC_Helper می‌آید — همان منبعی که باکس خوشه
 * روی صفحات سایت استفاده می‌کند. سایت‌مپ دقیقا همان گرافی را نشان
 * می‌دهد که بازدیدکننده می‌بیند، و کلید کش شامل شماره نسل همان ماژول
 * است، پس با هر تغییر در خوشه‌ها خودکار باطل می‌شود.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_Cluster_Sitemap {

	private static ?self $instance = null;

	private const CACHE_KEY = 'hodima_cluster_sitemap_xml_cache_v1';

	private function __construct() {
		add_action( 'init', [ $this, 'register_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'template_redirect', [ $this, 'render_sitemap' ], 0 );
	}

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	public function register_rewrite(): void {
		add_rewrite_rule( '^cluster-sitemap\.xml$', 'index.php?hodima_cluster_sitemap=1', 'top' );
	}

	public function query_vars( $vars ): array {
		$vars   = (array) $vars;
		$vars[] = 'hodima_cluster_sitemap';
		return $vars;
	}

	/** سازگاری با کد قدیمی — کش حالا با شماره نسل Topic Cluster باطل می‌شود. */
	public function maybe_bust_cache( int $post_id = 0 ): void {
		delete_transient( self::CACHE_KEY );
	}

	private function cache_key(): string {
		$gen = class_exists( 'Hodima_TC_Helper' ) ? Hodima_TC_Helper::cache_gen() : 0;
		return self::CACHE_KEY . '_' . $gen;
	}

	public function render_sitemap(): void {

		if ( ! get_query_var( 'hodima_cluster_sitemap' ) ) {
			return;
		}

		// درخواست سایت‌مپ نباید در کش صفحه ذخیره شود
		if ( Hodima_GI_Helper::litespeed_active() ) {
			do_action( 'litespeed_control_set_nocache', 'hodima: cluster sitemap' );
		}

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8', true );
		header( 'X-Robots-Tag: noindex, follow', true );

		$xml = get_transient( $this->cache_key() );

		if ( ! is_string( $xml ) || '' === $xml ) {
			$xml = $this->build();
			set_transient( $this->cache_key(), $xml, 12 * HOUR_IN_SECONDS );
		}

		echo $xml; // phpcs:ignore — XML ساخته‌شده با مقادیر esc_xml شده
		exit;
	}

	private function build(): string {

		$lines   = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$lines[] = '<!-- Hodima Topic Cluster Semantic Map -->';

		if ( ! class_exists( 'Hodima_TC_Helper' ) ) {
			$lines[] = '</urlset>';
			return implode( "\n", $lines );
		}

		$seen = [];

		foreach ( $this->pillars() as [ $pillar_id, $kind ] ) {

			$pillar = Hodima_TC_Helper::resolve_node( $pillar_id, $kind );

			if ( null === $pillar || $pillar['noindex'] ) {
				continue;
			}

			$children = array_values( array_filter(
				Hodima_TC_Helper::get_children( $pillar_id, $kind ),
				static fn( array $child ): bool => ! $child['noindex']
			) );

			$lines[] = "\t<!-- Pillar: " . esc_xml( str_replace( '--', '—', $pillar['title'] ) ) . ' -->';

			$this->append_url( $lines, $seen, $pillar, '1.0', $this->lastmod( $pillar, $children ) );

			foreach ( $children as $child ) {
				$this->append_url( $lines, $seen, $child, '0.8', $this->lastmod( $child, [] ) );
			}
		}

		$lines[] = '</urlset>';

		return implode( "\n", $lines );
	}

	/**
	 * همه پیلارها از خود ماژول خوشه (نسخه قبلی همان کوئری را جدا تکرار می‌کرد).
	 *
	 * @return array<int, array{0:int, 1:string}>
	 */
	private function pillars(): array {
		return method_exists( 'Hodima_TC_Helper', 'pillars' ) ? Hodima_TC_Helper::pillars() : [];
	}

	private function append_url( array &$lines, array &$seen, array $node, string $priority, string $lastmod ): void {

		$loc = Hodima_GI_Helper::clean_url( (string) $node['url'] );
		$key = Hodima_GI_Helper::url_hash( $loc );

		// یک آدرس فقط یک بار، حتی اگر فرزند چند پیلار باشد
		if ( isset( $seen[ $key ] ) ) {
			return;
		}
		$seen[ $key ] = true;

		$lines[] = "\t<url>";
		$lines[] = "\t\t<loc>" . esc_xml( esc_url_raw( $loc ) ) . '</loc>';
		if ( '' !== $lastmod ) {
			$lines[] = "\t\t<lastmod>" . esc_xml( $lastmod ) . '</lastmod>';
		}
		$lines[] = "\t\t<priority>{$priority}</priority>";
		$lines[] = "\t</url>";
	}

	/**
	 * تاریخ آخرین تغییر.
	 * پست: post_modified خودش. ترم: جدیدترین تاریخ میان فرزندانش (ترم
	 * تاریخ تغییر ندارد؛ نسخه قبلی برای ترم‌ها اصلا lastmod نداشت).
	 */
	private function lastmod( array $node, array $children ): string {

		$stamps = [];

		if ( Hodima_TC_Helper::KIND_POST === $node['kind'] ) {
			$time = get_post_modified_time( 'U', true, (int) $node['id'] );
			if ( $time ) {
				$stamps[] = (int) $time;
			}
		}

		foreach ( $children as $child ) {
			if ( Hodima_TC_Helper::KIND_POST === $child['kind'] ) {
				$time = get_post_modified_time( 'U', true, (int) $child['id'] );
				if ( $time ) {
					$stamps[] = (int) $time;
				}
			}
		}

		return empty( $stamps ) ? '' : gmdate( 'c', max( $stamps ) );
	}
}

Hodima_Cluster_Sitemap::get_instance();
