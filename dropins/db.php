<?php
/**
 * SHIFT8_DBCACHE_DROPIN
 *
 * Minimal db.php loader for Shift8 DB Cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wpdb', false ) ) {
	if ( file_exists( ABSPATH . WPINC . '/class-wpdb.php' ) ) {
		require_once ABSPATH . WPINC . '/class-wpdb.php';
	} else {
		require_once ABSPATH . WPINC . '/wp-db.php';
	}
}

$shift8_dbcache_plugin_dir = __DIR__ . '/plugins/shift8-dbcache';

if ( file_exists( $shift8_dbcache_plugin_dir . '/includes/class-settings.php' ) ) {
	require_once $shift8_dbcache_plugin_dir . '/includes/class-settings.php';
	require_once $shift8_dbcache_plugin_dir . '/includes/class-rules.php';
	require_once $shift8_dbcache_plugin_dir . '/includes/class-runtime.php';
	require_once $shift8_dbcache_plugin_dir . '/includes/class-redis-client.php';
	require_once $shift8_dbcache_plugin_dir . '/includes/class-db-cache-wpdb.php';
}

global $wpdb, $table_prefix;

if ( class_exists( 'Shift8_DBCache_WPDB' ) ) {
	$wpdb = new Shift8_DBCache_WPDB( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
} else {
	$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
}

if ( isset( $table_prefix ) ) {
	$wpdb->set_prefix( $table_prefix );
}