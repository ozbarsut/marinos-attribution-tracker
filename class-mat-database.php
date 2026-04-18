<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MAT_Database {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mat_events';
	}

	public static function activate() {
		global $wpdb;

		$table_name = self::table_name();
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			event_label VARCHAR(255) NOT NULL DEFAULT '',
			page_url TEXT NULL,
			target_url TEXT NULL,
			referrer_url TEXT NULL,
			source VARCHAR(100) NOT NULL DEFAULT '',
			medium VARCHAR(100) NOT NULL DEFAULT '',
			campaign VARCHAR(100) NOT NULL DEFAULT '',
			search_engine VARCHAR(100) NOT NULL DEFAULT '',
			search_keyword VARCHAR(255) NOT NULL DEFAULT '',
			visitor_ip VARCHAR(45) NOT NULL DEFAULT '',
			user_agent TEXT NULL,
			metadata LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY event_type (event_type),
			KEY created_at (created_at),
			KEY source (source),
			KEY search_keyword (search_keyword)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
