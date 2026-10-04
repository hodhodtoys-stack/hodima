<?php
/**
 * سشن خنثی ووکامرس برای ربات‌های موتور جستجو (store-optimizer.php)
 * Path: plugins/hodima-commerce/inc/woocommerce/class-bot-session.php
 *
 * فایل جدا: فقط وقتی WC_Session_Handler بارگذاری شده include می‌شود.
 * نام کلاس با نسخه قالب (WC_Session_Handler_Bot_Dummy) فرق دارد تا با قالب
 * قدیمی تداخل نداشته باشد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class Hodima_Commerce_Bot_Session extends WC_Session_Handler {

	public function init() {
		$this->_customer_id = 'bot';
		$this->_data        = [];
		$this->_dirty       = false;
	}

	public function get_session_cookie() {
		return false;
	}

	public function set_customer_session_cookie( $set ) {}

	public function has_session() {
		return false;
	}

	public function get_session( $customer_id, $default = false ) {
		return [];
	}

	public function save_data( $old_session_key = 0 ) {}

	public function update_session_timestamp( $customer_id, $timestamp ) {}

	public function destroy_session() {}

	public function forget_session() {}

	public function cleanup_sessions() {}
}
