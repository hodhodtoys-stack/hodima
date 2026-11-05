<?php
/**
 * ساخت جدول‌ها و برگه پنل.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * نصب و ارتقای پایگاه داده پنل.
 *
 * طراحی جدول‌ها (تازه؛ پنل قدیمی داده واقعی روی سایت نداشت):
 * - بدون ENUM، FOREIGN KEY و ON UPDATE: dbDelta وردپرس این‌ها را درست مقایسه
 *   نمی‌کند و ارتقای بعدی جدول را خراب می‌کرد؛ رابطه‌ها را کد نگه می‌دارد.
 * - همه زمان‌ها DATETIME به UTC (تابع now())، بدون پیش‌فرض سرور.
 * - تعداد اقلام DECIMAL: واحد «کیلو» اعشاری است (ستون INT قدیمی ۲.۵ را ۳ ذخیره می‌کرد
 *   در حالی که مبلغ با ۲.۵ حساب شده بود).
 * - قیمت و ظرفیت هر قلم هنگام ثبت ذخیره می‌شود تا مبلغ پنل مشتری و پیشخوان یکی باشد
 *   (پیشخوان قدیمی ظرفیت را نادیده می‌گرفت و جمع دیگری نشان می‌داد).
 * - فاکتورها و فیش‌ها در جدول جدا (order_files)، نه رشته آدرس‌های جداشده با ویرگول.
 */
final class Install {

	/** با هر تغییر ساختار جدول یک واحد بالا برود. */
	public const DB_VERSION = '1';

	public const DB_OPTION = 'hodima_panel_db_version';

	/** فعال‌سازی افزونه: جدول‌ها + برگه پنل. */
	public static function activate(): void {
		self::create_tables();
		update_option( self::DB_OPTION, self::DB_VERSION );
		Page::ensure();
	}

	/** ارتقا بعد از به‌روزرسانی افزونه (فعال‌سازی دوباره در به‌روزرسانی اجرا نمی‌شود). */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::DB_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * دستورهای CREATE TABLE (برای dbDelta و آزمون).
	 *
	 * @return array<string, string> نام کوتاه => SQL.
	 */
	public static function schema(): array {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		return [
			'orders'          => 'CREATE TABLE ' . table( 'orders' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  type varchar(20) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  note text NULL,
  total_price decimal(15,2) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_created (user_id,created_at),
  KEY status (status)
) $charset;",
			'order_items'     => 'CREATE TABLE ' . table( 'order_items' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  quantity decimal(12,3) NOT NULL DEFAULT 1,
  unit_price decimal(15,2) NOT NULL DEFAULT 0,
  unit_capacity int(10) unsigned NOT NULL DEFAULT 1,
  sale_unit varchar(20) NOT NULL DEFAULT '',
  line_total decimal(15,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY order_id (order_id)
) $charset;",
			'order_files'     => 'CREATE TABLE ' . table( 'order_files' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  kind varchar(20) NOT NULL,
  file varchar(255) NOT NULL,
  name varchar(255) NOT NULL DEFAULT '',
  mime varchar(100) NOT NULL DEFAULT '',
  uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY order_kind (order_id,kind)
) $charset;",
			'chats'           => 'CREATE TABLE ' . table( 'chats' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  user_unread int(10) unsigned NOT NULL DEFAULT 0,
  admin_unread int(10) unsigned NOT NULL DEFAULT 0,
  last_message_preview varchar(255) NOT NULL DEFAULT '',
  last_message_sender varchar(10) NOT NULL DEFAULT '',
  last_message_at datetime NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_id (order_id),
  KEY user_id (user_id)
) $charset;",
			'chat_messages'   => 'CREATE TABLE ' . table( 'chat_messages' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chat_id bigint(20) unsigned NOT NULL,
  sender_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sender_type varchar(10) NOT NULL,
  message text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY chat_id (chat_id)
) $charset;",
			'tickets'         => 'CREATE TABLE ' . table( 'tickets' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  reference_code varchar(20) NOT NULL DEFAULT '',
  department varchar(20) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  priority varchar(20) NOT NULL DEFAULT 'normal',
  user_unread int(10) unsigned NOT NULL DEFAULT 0,
  admin_unread int(10) unsigned NOT NULL DEFAULT 0,
  last_message_preview varchar(255) NOT NULL DEFAULT '',
  last_message_sender varchar(10) NOT NULL DEFAULT '',
  last_message_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY status (status)
) $charset;",
			'ticket_messages' => 'CREATE TABLE ' . table( 'ticket_messages' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ticket_id bigint(20) unsigned NOT NULL,
  sender_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sender_type varchar(10) NOT NULL,
  message text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY ticket_id (ticket_id)
) $charset;",
		];
	}

	/** ساخت یا به‌روزرسانی همه جدول‌ها با dbDelta. */
	public static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::schema() as $sql ) {
			dbDelta( $sql );
		}
	}
}
