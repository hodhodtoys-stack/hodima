<?php
/**
 * آزمون خودکار حریم خصوصی (Commerce): خروجی/حذف داده لیدهای تلفنی و کد ملی، متن سیاست، یادداشت فرم.
 *   HARNESS=1 php tools/wp-harness/privacy-tests.php                                   (ابزار اصلی؛ بدون ووکامرس)
 *   HARNESS=1 HODIMA_WP=/tmp/hodima-harness-wc/wp php tools/wp-harness/privacy-tests.php   (ووکامرس واقعی)
 * بخش‌های ووکامرس فقط وقتی ووکامرس واقعی بارگذاری شده باشد اجرا می‌شوند (وگرنه «رد شد» چاپ می‌شود).
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. هر اجرا داده آزمایشی خودش را می‌سازد و پاک می‌کند.
 * (بخش ۱۷۱ HODIMA-AUDIT.md)
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
// بقایای اجرای نیمه‌کاره قبلی
delete_option( 'wp_page_for_privacy_policy' );

$failed = 0;
$passed = 0;

function hodima_pt( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! function_exists( 'hodima_phone_privacy_export' ) ) {
	echo "✘ ماژول فرم تلفن (phone-privacy.php) لود نشده است\n";
	exit( 1 );
}

/** یک لید آزمایشی؛ شناسه پست. */
function hodima_pt_lead( string $phone, array $meta ): int {
	$id = wp_insert_post( [ 'post_type' => 'hodima_phone_lead', 'post_title' => $phone, 'post_status' => 'publish' ] );
	foreach ( $meta as $key => $value ) {
		update_post_meta( (int) $id, $key, $value );
	}
	return (int) $id;
}

$email  = 'privacy-test@example.com';
$phone  = '09123456789';
$other  = '09351112233';
$wc     = class_exists( 'WooCommerce' ) && class_exists( 'WC_Privacy_Exporters' ) && function_exists( 'wc_create_order' );
$ids    = [];
$cleanup = [];

echo "=== ثبت ابزارهای حریم خصوصی وردپرس\n";
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', [] );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', [] );
hodima_pt( 'exporter لیدها ثبت شده', is_callable( $exporters['hodima-phone-leads']['callback'] ?? null ), true );
hodima_pt( 'eraser لیدها ثبت شده', is_callable( $erasers['hodima-phone-leads']['callback'] ?? null ), true );

echo "=== پیدا کردن شماره‌ها از ایمیل (حساب کاربری)\n";
$user_id = wp_insert_user( [ 'user_login' => 'privacy_test_user', 'user_pass' => wp_generate_password(), 'user_email' => $email ] );
$cleanup[] = static function () use ( $user_id ): void {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $user_id );
};
update_user_meta( (int) $user_id, 'billing_phone', '۰۹۱۲-۳۴۵-۶۷۸۹' ); // رقم فارسی و خط تیره
hodima_pt( 'شماره حساب یکدست می‌شود', hodima_phone_privacy_phones( $email ), [ $phone ] );
hodima_pt( 'ایمیل ناشناس: بدون شماره', hodima_phone_privacy_phones( 'nobody@example.com' ), [] );
hodima_pt( 'ایمیل خالی: بدون شماره', hodima_phone_privacy_phones( '' ), [] );

$lead_a = hodima_pt_lead( $phone, [ '_hodima_lead_data' => [
	'count'      => 3,
	'last_url'   => 'https://hodima.test/hair/',
	'last_title' => 'کلیپس',
	'last_time'  => '2026-10-17 10:00',
	'history'    => [ [ 'url' => 'https://hodima.test/', 'title' => 'خانه', 'time' => '2026-10-16 09:00' ] ],
] ] );
$lead_b = hodima_pt_lead( $other, [ 'hodima_request_count' => 2, 'hodima_page_url' => 'https://hodima.test/x/', 'hodima_submit_time' => '2026-10-01 08:00' ] );
$cleanup[] = static function () use ( $lead_a, $lead_b ): void {
	wp_delete_post( $lead_a, true );
	wp_delete_post( $lead_b, true );
};
hodima_pt( 'لید شماره حساب پیدا می‌شود', hodima_phone_privacy_lead_ids( $phone ), [ $lead_a ] );
$lead_trash = hodima_pt_lead( $phone, [] );
wp_trash_post( $lead_trash );
$found = hodima_phone_privacy_lead_ids( $phone );
sort( $found );
hodima_pt( 'لید داخل زباله‌دان هم پیدا می‌شود', $found, [ $lead_a, $lead_trash ] );

echo "=== خروجی داده شخصی لید\n";
$export = hodima_phone_privacy_export( $email );
hodima_pt( 'لیدهای همین شماره (حتی زباله‌دان؛ نه شماره دیگر)', count( $export['data'] ), 2 );
$item_a = array_values( array_filter( $export['data'], static fn( array $item ): bool => ( $item['item_id'] ?? '' ) === 'hodima-phone-lead-' . $lead_a ) )[0] ?? [];
hodima_pt( 'خروجی کامل است (done)', $export['done'], true );
$values = array_column( $item_a['data'] ?? [], 'value', 'name' );
hodima_pt( 'شماره در خروجی', $values['شماره تماس'] ?? null, $phone );
hodima_pt( 'تعداد دفعات در خروجی', $values['تعداد دفعات ثبت'] ?? null, '3' );
hodima_pt( 'تاریخچه در خروجی', str_contains( (string) ( $values['تاریخچه ثبت‌ها'] ?? '' ), 'خانه' ), true );
hodima_pt( 'گروه و شناسه آیتم', [ $item_a['group_id'] ?? '', $item_a['item_id'] ?? '' ], [ 'hodima-phone-leads', 'hodima-phone-lead-' . $lead_a ] );
hodima_pt( 'ایمیل ناشناس: خروجی خالی', hodima_phone_privacy_export( 'nobody@example.com' ), [ 'data' => [], 'done' => true ] );

echo "=== لید با متاهای قدیمی (قبل از _hodima_lead_data)\n";
$legacy = hodima_phone_lead_data( $lead_b );
hodima_pt( 'داده لید قدیمی خوانده می‌شود', [ $legacy['count'], $legacy['last_url'], $legacy['last_time'] ], [ 2, 'https://hodima.test/x/', '2026-10-01 08:00' ] );

echo "=== حذف داده شخصی لید\n";
$erase = hodima_phone_privacy_erase( $email );
hodima_pt( 'حذف انجام شد', [ $erase['items_removed'], $erase['items_retained'], $erase['done'] ], [ true, false, true ] );
hodima_pt( 'لید شماره حساب پاک شد', get_post( $lead_a ), null );
hodima_pt( 'لید داخل زباله‌دان هم پاک شد', get_post( $lead_trash ), null );
hodima_pt( 'لید شماره دیگر دست‌نخورده', get_post( $lead_b ) instanceof WP_Post, true );
hodima_pt( 'حذف دوباره: چیزی نمانده', hodima_phone_privacy_erase( $email )['items_removed'], false );

echo "=== یادداشت زیر فرم و لینک سیاست حریم خصوصی\n";
$html = do_shortcode( '[hodima_phone_form]' );
hodima_pt( 'پیش‌فرض: جمله موافقت چاپ نمی‌شود', str_contains( $html, 'موافقت' ), false );
hodima_pt( 'بدون صفحه سیاست و بدون متن: یادداشتی نیست', str_contains( $html, 'hodima-phone-note' ), false );
hodima_pt( 'بدون یادداشت: aria-describedby به یادداشت اشاره نمی‌کند', str_contains( $html, 'hodima-phone-1-note' ), false );
$policy_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'سیاست حریم خصوصی', 'post_status' => 'publish', 'post_name' => 'privacy-test' ] );
update_option( 'wp_page_for_privacy_policy', (int) $policy_page );
$cleanup[] = static function () use ( $policy_page ): void {
	delete_option( 'wp_page_for_privacy_policy' );
	wp_delete_post( (int) $policy_page, true );
};
// شمارنده static نمونه فرم همین فرایند ادامه دارد؛ نمونه‌ها hodima-phone-2، 3، … هستند
$html = do_shortcode( '[hodima_phone_form]' );
hodima_pt( 'با صفحه سیاست: فقط لینک به آن (بدون جمله موافقت)', str_contains( $html, 'href="' . esc_url( (string) get_permalink( $policy_page ) ) . '">سیاست حریم خصوصی</a>' ) && ! str_contains( $html, 'موافقت' ), true );
hodima_pt( 'لینک سیاست در aria-describedby ورودی است', (bool) preg_match( '/aria-describedby="[^"]*hodima-phone-2-note/', $html ), true );
add_filter( 'hodima_phone_privacy_note', static fn(): string => 'متن دلخواه مدیر' );
hodima_pt( 'فیلتر: متن دلخواه چاپ می‌شود', str_contains( do_shortcode( '[hodima_phone_form]' ), 'متن دلخواه مدیر' ), true );
remove_all_filters( 'hodima_phone_privacy_note' );

echo "=== متن پیشنهادی سیاست حریم خصوصی\n";
define( 'WP_ADMIN', true ); // wp_add_privacy_policy_content فقط در پیشخوان و بعد از admin_init کار می‌کند
$GLOBALS['wp_actions']['admin_init'] = 1; // did_action('admin_init'): خود do_action('admin_init') در ابزار تست کامل اجرا نمی‌شود
require_once ABSPATH . 'wp-admin/includes/plugin.php'; // wp_add_privacy_policy_content
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
hodima_phone_privacy_policy_text();
if ( class_exists( 'Hodima\\Core\\Time_Jalali\\Modules\\Checkout_Optimizer' ) ) {
	( new Hodima\Core\Time_Jalali\Modules\Checkout_Optimizer() )->privacy_policy_text();
}
if ( class_exists( 'Hodima_Bot_Shield' ) ) {
	Hodima_Bot_Shield::privacy_policy_text();
}
if ( function_exists( 'hod_video_watch_privacy_policy_text' ) ) {
	hod_video_watch_privacy_policy_text();
}
if ( class_exists( 'Hodima\\Stories\\Analytics\\WP_Story_Setup' ) ) {
	Hodima\Stories\Analytics\WP_Story_Setup::privacy_policy_text();
}
$prop = ( new ReflectionClass( 'WP_Privacy_Policy_Content' ) )->getProperty( 'policy_content' );
$all  = (string) wp_json_encode( $prop->getValue(), JSON_UNESCAPED_UNICODE );
hodima_pt( 'نگهداری خاموش: متن سیاست «تا حذف مدیر» می‌گوید', str_contains( $all, 'تا زمانی که مدیر سایت آن شماره را' ), true );
$prop->setValue( null, [] );
hodima_phone_privacy_policy_text();
if ( class_exists( 'Hodima\\Core\\Time_Jalali\\Modules\\Checkout_Optimizer' ) ) {
	( new Hodima\Core\Time_Jalali\Modules\Checkout_Optimizer() )->privacy_policy_text();
}
if ( class_exists( 'Hodima_Bot_Shield' ) ) {
	Hodima_Bot_Shield::privacy_policy_text();
}
if ( function_exists( 'hod_video_watch_privacy_policy_text' ) ) {
	hod_video_watch_privacy_policy_text();
}
if ( class_exists( 'Hodima\\Stories\\Analytics\\WP_Story_Setup' ) ) {
	Hodima\Stories\Analytics\WP_Story_Setup::privacy_policy_text();
}
$all = (string) wp_json_encode( $prop->getValue(), JSON_UNESCAPED_UNICODE );
foreach ( [ 'هدیما — فرم شماره تماس', 'هدیما — کد ملی', 'هدیما — ربات‌های هوش مصنوعی', 'هدیما — ویدیو', 'هدیما — استوری' ] as $needle ) {
	hodima_pt( "متن شامل «{$needle}»", str_contains( $all, $needle ), true );
}

if ( $wc ) {
	echo "=== ووکامرس واقعی: شماره‌های سفارش + کد ملی\n";
	$order = wc_create_order( [ 'status' => 'completed' ] );
	$order->set_billing_email( 'order-test@example.com' );
	$order->set_billing_phone( '+98 935 111 2233' );
	$order->update_meta_data( '_billing_national_id', '0499370899' );
	$order->save();
	$cleanup[] = static function () use ( $order ): void {
		$order->delete( true );
	};
	hodima_pt( 'شماره سفارش پیدا می‌شود', hodima_phone_privacy_phones( 'order-test@example.com' ), [ $other ] );
	hodima_pt( 'خروجی لید از روی سفارش', count( hodima_phone_privacy_export( 'order-test@example.com' )['data'] ), 1 );

	$exp = apply_filters( 'woocommerce_privacy_export_order_personal_data_meta', [] );
	hodima_pt( 'کد ملی سفارش در فهرست متای خروجی ووکامرس', $exp['_billing_national_id'] ?? null, 'کد ملی' );
	$wc_export = WC_Privacy_Exporters::order_data_exporter( 'order-test@example.com', 1 );
	$flat      = wp_json_encode( $wc_export['data'], JSON_UNESCAPED_UNICODE );
	hodima_pt( 'کد ملی در خروجی سفارش ووکامرس', str_contains( (string) $flat, '0499370899' ), true );

	$customer_id = wp_insert_user( [ 'user_login' => 'privacy_test_customer', 'user_pass' => wp_generate_password(), 'user_email' => 'customer-test@example.com', 'role' => 'customer' ] );
	$cleanup[]   = static function () use ( $customer_id ): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $customer_id );
	};
	update_user_meta( (int) $customer_id, 'billing_national_id', '0499370899' );
	$cust = WC_Privacy_Exporters::customer_data_exporter( 'customer-test@example.com', 1 );
	hodima_pt( 'کد ملی در خروجی مشتری ووکامرس', str_contains( (string) wp_json_encode( $cust['data'], JSON_UNESCAPED_UNICODE ), '0499370899' ), true );
	$gone = WC_Privacy_Erasers::customer_data_eraser( 'customer-test@example.com', 1 );
	hodima_pt( 'حذف مشتری: کد ملی پاک شد', [ (string) get_user_meta( (int) $customer_id, 'billing_national_id', true ), $gone['items_removed'] ], [ '', true ] );
	hodima_pt( 'حذف مشتری: پیام کد ملی', in_array( 'کد ملی مشتری حذف شد.', $gone['messages'], true ), true );

	$rm = apply_filters( 'woocommerce_privacy_remove_order_personal_data_meta', [] );
	hodima_pt( 'کد ملی در فهرست ناشناس‌سازی سفارش', $rm['_billing_national_id'] ?? null, 'text' );
	update_option( 'woocommerce_erasure_request_removes_order_data', 'yes' );
	WC_Privacy_Erasers::order_data_eraser( 'order-test@example.com', 1 );
	$order = wc_get_order( $order->get_id() );
	hodima_pt( 'ناشناس‌سازی سفارش: کد ملی دیگر نیست', $order->get_meta( '_billing_national_id' ) !== '0499370899', true );
	delete_option( 'woocommerce_erasure_request_removes_order_data' );
} else {
	echo "=== ووکامرس واقعی بارگذاری نشده؛ بخش کد ملی/سفارش رد شد (HODIMA_WP=/tmp/hodima-harness-wc/wp)\n";
}

foreach ( array_reverse( $cleanup ) as $fn ) {
	$fn();
}

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون حریم خصوصی قبول" ) . "\n";
exit( $failed ? 1 : 0 );
