<?php
/**
 * آزمون خودکار اسکیمای AEO (plugins/hodima-seo/components/indexnow-sync/includes/aeo): FAQPage پنل AEO به‌طور پیش‌فرض در JSON-LD
 * صفحه نمی‌آید (SEO 3.9.7؛ پرسش‌ها در متن صفحه دیده نمی‌شدند)، با فیلتر برمی‌گردد، و متن AEO به نود #webpage می‌چسبد.
 *   HARNESS=1 php tools/wp-harness/aeo-tests.php      ci-check.sh هم اجرا می‌کند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این آزمون داده می‌سازد و پاک می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}
if ( ! class_exists( 'Hodima_AEO_Schema' ) ) {
	echo "✘ ماژول AEO لود نشده است\n";
	exit( 1 );
}

$failed = 0;
$passed = 0;

function hodima_ae( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

/** نودهای گراف AEO که register_schemas برای صفحه ساخته (خصوصیت خصوصی کلاس؛ render آن را به گراف واحد می‌دهد و خالی می‌کند). */
$graph_of = static function ( int $post_id ): array {
	global $wp_query;
	$wp_query = new WP_Query( [ 'p' => $post_id, 'post_type' => 'post' ] );
	$GLOBALS['post'] = $wp_query->posts[0] ?? null;
	$prop = new ReflectionProperty( Hodima_AEO_Schema::class, 'graph' );
	$prop->setValue( null, [] );
	Hodima_AEO_Schema::register_schemas();
	return (array) $prop->getValue();
};

$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'آزمون AEO', 'post_name' => 'aeo-test', 'post_content' => 'متن صفحه' ] );
update_post_meta( $id, '_h_ai_faqs', (string) wp_json_encode( [ [ 'q' => 'جنس چیست؟', 'a' => 'اسفنج.' ] ], JSON_UNESCAPED_UNICODE ) );
update_post_meta( $id, '_h_ai_en_faqs', (string) wp_json_encode( [ [ 'q' => 'What material?', 'a' => 'Foam.' ] ] ) );
update_post_meta( $id, '_h_ai_en_context', 'English context for AI' );

$types = static fn( array $graph ): array => array_values( array_map( static fn( array $n ): string => (string) ( $n['@type'] ?? '(بی‌نوع)' ), $graph ) );

$default = $graph_of( $id );
hodima_ae( 'پیش‌فرض: FAQPage پنل AEO در اسکیمای صفحه نیست', in_array( 'FAQPage', $types( $default ), true ), false );
hodima_ae( 'پیش‌فرض: متن AEO به نود #webpage می‌چسبد (مثل صفحه بی‌FAQ)', array_values( array_filter( $default, static fn( array $n ): bool => str_ends_with( (string) ( $n['@id'] ?? '' ), '#webpage' ) ) )[0]['text'] ?? '', 'English context for AI' );

add_filter( 'hodima_seo_aeo_faq_schema', '__return_true' );
$on  = $graph_of( $id );
$faq = array_values( array_filter( $on, static fn( array $n ): bool => 'FAQPage' === ( $n['@type'] ?? '' ) ) )[0] ?? [];
hodima_ae( 'با فیلتر روشن: FAQPage با پرسش انگلیسی و فارسی برمی‌گردد (رفتار قبلی)', [ count( $faq['mainEntity'] ?? [] ), $faq['description'] ?? '' ], [ 2, 'English context for AI' ] );
remove_filter( 'hodima_seo_aeo_faq_schema', '__return_true' );

hodima_ae( 'با خاموش‌کردن فیلتر دوباره بی FAQPage', in_array( 'FAQPage', $types( $graph_of( $id ) ), true ), false );
hodima_ae( 'داده پنل (متای پرسش‌ها) دست‌نخورده می‌ماند: فایل‌های هوش مصنوعی از همان می‌خوانند', [ json_decode( (string) get_post_meta( $id, '_h_ai_faqs', true ), true )[0]['q'] ?? '', json_decode( (string) get_post_meta( $id, '_h_ai_en_faqs', true ), true )[0]['q'] ?? '' ], [ 'جنس چیست؟', 'What material?' ] );

delete_post_meta( $id, '_h_ai_en_context' );
hodima_ae( 'بدون FAQ و بدون متن AEO: هیچ نودی نمی‌آید', $graph_of( $id ), [] );

wp_delete_post( $id, true );

echo $failed ? "\n✘ {$failed} آزمون رد شد ({$passed} قبول)\n" : "\n✔ همه {$passed} آزمون AEO قبول\n";
exit( $failed ? 1 : 0 );
