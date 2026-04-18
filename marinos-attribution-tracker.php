<?php
/**
 * Plugin Name: Marinos Attribution Tracker
 * Plugin URI:  https://marinosajans.com.tr
 * Description: Ziyaretci kaynagi, tiklama hareketleri ve arama kelimesi (mumkun oldugu kadar) takibi yapar.
 * Version:     1.0.0
 * Author:      Marinos Ajans
 * Author URI:  https://marinosajans.com.tr
 * License:     GPL2
 * Text Domain: marinos-attribution-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAT_VERSION', '1.0.0' );
define( 'MAT_PLUGIN_FILE', __FILE__ );
define( 'MAT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MAT_PLUGIN_PATH . 'includes/class-mat-database.php';
require_once MAT_PLUGIN_PATH . 'includes/class-mat-tracker.php';
require_once MAT_PLUGIN_PATH . 'includes/class-mat-admin.php';

register_activation_hook( __FILE__, array( 'MAT_Database', 'activate' ) );

function mat_bootstrap() {
	new MAT_Tracker();
	new MAT_Admin();
}
add_action( 'plugins_loaded', 'mat_bootstrap' );
