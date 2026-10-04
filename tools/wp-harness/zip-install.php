<?php
/*
 * گام‌های zip-install-check.sh (هر گام یک فرایند PHP جدا، مثل درخواست‌های جدای پیشخوان):
 *   php zip-install.php install  <dist>   نصب تازه وردپرس + نصب قالب و ۴ افزونه از ZIP (Theme_Upgrader/Plugin_Upgrader)
 *   php zip-install.php theme             فعال کردن قالب (switch_theme)
 *   php zip-install.php plugins           فعال کردن افزونه‌ها با activate_plugin (فایل افزونه بعد از قالبِ ازپیش‌فعال لود می‌شود: «Cannot redeclare» اینجا دیده می‌شود)
 */
[ , $step, $dist ] = $argv + [ 1 => '', 2 => '' ];
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['REQUEST_URI'] = '/wp-admin/'; $_SERVER['HTTPS'] = 'on';
define( 'FS_METHOD', 'direct' );
if ( 'install' === $step ) {
	define( 'WP_INSTALLING', true );
}
$zip_wp_dir = rtrim( getenv( "HODIMA_WP" ) ?: "", "/" ); // نه $wp: شیء سراسری وردپرس است
require "$zip_wp_dir/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$plugins = [ 'hodima-core', 'hodima-seo', 'hodima-commerce', 'hodima-media' ];

switch ( $step ) {
	case 'install':
		add_filter( 'pre_wp_mail', '__return_false' ); // بدون sendmail در محیط تست
		wp_install( 'هدهدلی', 'admin', 'admin@hodima.test', true, '', 'pass' );
		update_option( 'permalink_structure', '/%postname%/' );
		WP_Filesystem();
		$ok = ( new Theme_Upgrader( new Automatic_Upgrader_Skin() ) )->install( "$dist/hodima.zip" );
		echo 'theme install: ', var_export( $ok, true ), "\n";
		foreach ( $plugins as $p ) {
			$ok = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->install( "$dist/$p.zip" );
			echo "plugin install $p: ", var_export( $ok, true ), "\n";
		}
		break;
	case 'theme':
		switch_theme( 'hodima' );
		echo 'theme: ', get_stylesheet(), "\n";
		break;
	case 'plugins':
		wp_set_current_user( 1 );
		foreach ( $plugins as $p ) {
			$r = activate_plugin( "$p/$p.php" );
			echo "activate $p: ", is_wp_error( $r ) ? 'ERROR ' . $r->get_error_message() : 'ok', "\n";
		}
		break;
}
