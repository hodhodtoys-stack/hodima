<?php
/**
 * ماژول «دستار سئو» — پیش‌نویس عنوان و توضیح متا برای ایرادهای فنی (SEO 3.3.6)
 * Path: core/advisor/advisor-drafts.php
 *
 * برای «بدون عنوان»، «عنوان کوتاه/بلند» و «بدون توضیح متا» یک پیش‌نویس می‌سازد تا مدیر به‌جای «بنویسید»
 * متن آماده بخواند، اصلاح و کپی کند. با الگو و داده خود سایت (نام نوشته/محصول/دسته، دسته اصلی، خلاصه یا ابتدای متن،
 * نام سایت) ساخته می‌شود؛ مدل زبانی در کار نیست و چیزی در سایت ذخیره یا عوض نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** طول هدف عنوان و توضیح متا (همان عددهای دستور ایراد). */
const HODIMA_SEO_ADVISOR_DRAFT_TITLE_MAX = 62;
const HODIMA_SEO_ADVISOR_DRAFT_META_MAX  = 155;

/** HTML یا متن ← متن ساده یک‌خطی (بدون شورت‌کد، تگ و موجودیت). */
function hodima_seo_advisor_draft_clean( string $html ): string {

	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * کوتاه‌کردن تمیز: جایی که جمله تمام می‌شود (بعد از `$floor` نویسه)، وگرنه آخرین فاصله؛ هرگز وسط کلمه.
 */
function hodima_seo_advisor_draft_cut( string $text, int $max, int $floor = 0 ): string {

	$text = trim( $text );

	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}

	$head = mb_substr( $text, 0, $max + 1 );
	$best = -1;

	foreach ( [ '.', '؟', '!', '؛', '?' ] as $mark ) {
		$pos = mb_strrpos( $head, $mark );
		if ( false !== $pos && $pos >= $floor && $pos <= $max - 1 ) {
			$best = max( $best, $pos );
		}
	}

	if ( $best >= 0 ) {
		return rtrim( mb_substr( $head, 0, $best + 1 ) );
	}

	$space = mb_strrpos( mb_substr( $text, 0, $max + 1 ), ' ' );
	$cut   = false !== $space && $space > 0 ? mb_substr( $text, 0, $space ) : mb_substr( $text, 0, $max );

	return rtrim( $cut, " \t\n\r،,:؛-–—|" );
}

/**
 * داده سازنده پیش‌نویس از خود صفحه: نام، دسته اصلی (یا دسته والد)، متن کوتاه و نام سایت.
 *
 * @return array{name: string, group: string, text: string, site: string}
 */
function hodima_seo_advisor_draft_context( string $path ): array {

	$ctx   = [ 'name' => '', 'group' => '', 'text' => '', 'site' => hodima_seo_advisor_draft_clean( get_bloginfo( 'name' ) ) ];
	$class = hodima_seo_advisor_classify( $path );
	$kind  = $class['kind'];
	$id    = $class['id'];

	if ( 'home' === $kind ) {
		$ctx['name'] = $ctx['site'];
		$ctx['text'] = hodima_seo_advisor_draft_clean( get_bloginfo( 'description' ) );
		return $ctx;
	}

	if ( $id > 0 && taxonomy_exists( $kind ) ) {
		$term = get_term( $id, $kind );
		if ( $term instanceof WP_Term ) {
			$ctx['name'] = hodima_seo_advisor_draft_clean( $term->name );
			$ctx['text'] = hodima_seo_advisor_draft_clean( $term->description );
			$parent      = $term->parent > 0 ? get_term( $term->parent, $kind ) : null;
			$ctx['group'] = $parent instanceof WP_Term ? hodima_seo_advisor_draft_clean( $parent->name ) : '';
		}
		return $ctx;
	}

	if ( $id > 0 && post_type_exists( $kind ) ) {
		$post = get_post( $id );
		if ( $post instanceof WP_Post ) {
			$ctx['name'] = hodima_seo_advisor_draft_clean( $post->post_title );
			$source      = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
			$ctx['text'] = mb_substr( hodima_seo_advisor_draft_clean( $source ), 0, 400 );

			foreach ( [ 'product_cat', 'category' ] as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $kind, $taxonomy ) ) {
					continue;
				}
				$terms = get_the_terms( $id, $taxonomy );
				foreach ( is_array( $terms ) ? $terms : [] as $term ) {
					if ( 'uncategorized' === $term->slug || ( 'category' === $taxonomy && (int) get_option( 'default_category' ) === $term->term_id ) ) {
						continue; // دسته پیش‌فرض بی‌معنی است
					}
					$ctx['group'] = hodima_seo_advisor_draft_clean( $term->name );
					break 2;
				}
			}
		}
	}

	return $ctx;
}

/**
 * پیش‌نویس عنوان سئو. «عنوان بلند» = کوتاه‌شده عنوان فعلی از آخر (جداکننده | – — -)؛ بقیه = نام صفحه + دسته/والد + نام سایت،
 * تا جایی که در ۶۲ نویسه جا شود. رشته خالی = داده کافی نیست.
 */
function hodima_seo_advisor_draft_title( string $path, string $code, string $current = '' ): string {

	$max = HODIMA_SEO_ADVISOR_DRAFT_TITLE_MAX;

	if ( 'title_long' === $code ) {
		$current = hodima_seo_advisor_draft_clean( $current );
		if ( '' === $current ) {
			return '';
		}
		if ( preg_match_all( '/\s+[|–—-]\s+/u', $current, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( array_reverse( $found[0] ) as [ , $offset ] ) {
				$candidate = substr( $current, 0, (int) $offset );
				if ( mb_strlen( $candidate ) <= $max && mb_strlen( $candidate ) >= 20 ) {
					return $candidate;
				}
			}
		}
		return hodima_seo_advisor_draft_cut( $current, $max );
	}

	$ctx  = hodima_seo_advisor_draft_context( $path );
	$name = '' !== $ctx['name'] ? $ctx['name'] : hodima_seo_advisor_draft_clean( $current );

	if ( '' === $name ) {
		return '';
	}

	$title = mb_strlen( $name ) > $max ? hodima_seo_advisor_draft_cut( $name, $max - 2 ) : $name;

	foreach ( [ $ctx['group'], $ctx['site'] ] as $extra ) {
		if ( '' !== $extra && false === mb_stripos( $title, $extra ) && mb_strlen( $title . ' | ' . $extra ) <= $max ) {
			$title .= ' | ' . $extra;
		}
	}

	return $title;
}

/**
 * پیش‌نویس توضیح متا (تا ۱۵۵ نویسه): خلاصه یا ابتدای متن صفحه با نام صفحه در آغاز؛ بدون متن، نام + دسته + نام سایت.
 */
function hodima_seo_advisor_draft_meta( string $path ): string {

	$ctx  = hodima_seo_advisor_draft_context( $path );
	$name = $ctx['name'];

	if ( '' === $name ) {
		return '';
	}

	if ( '' !== $ctx['text'] ) {
		$body = false === mb_stripos( mb_substr( $ctx['text'], 0, 80 ), $name ) ? $name . '؛ ' . $ctx['text'] : $ctx['text'];
	} else {
		$body = $name . ( '' !== $ctx['group'] ? '؛ ' . $ctx['group'] : '' ) . '.';
	}

	if ( mb_strlen( $body ) < 110 && '' !== $ctx['site'] && false === mb_stripos( $body, $ctx['site'] ) ) {
		$body .= ( 1 === preg_match( '/[.؟!؛?]$/u', $body ) ? '' : '.' ) . ' خرید از ' . $ctx['site'] . '.';
	}

	return hodima_seo_advisor_draft_cut( $body, HODIMA_SEO_ADVISOR_DRAFT_META_MAX, 90 );
}
