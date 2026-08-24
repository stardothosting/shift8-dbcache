<?php
/**
 * Main plugin class.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Plugin {
	private static $instance = null;
	private $collector;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-settings.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-collector.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-admin.php';

		register_activation_hook( SHIFT8_DBCACHE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SHIFT8_DBCACHE_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( is_admin() ) {
			Shift8_DBCache_Admin::get_instance();
		}

		$this->collector = Shift8_DBCache_Collector::get_instance();
		$this->collector->boot();
	}

	public function activate() {
		if ( false === get_option( 'shift8_dbcache_settings', false ) ) {
			add_option( 'shift8_dbcache_settings', Shift8_DBCache_Settings::get_default_settings(), '', false );
		}

		if ( false === get_option( 'shift8_dbcache_analysis', false ) ) {
			add_option( 'shift8_dbcache_analysis', Shift8_DBCache_Collector::get_default_state(), '', false );
		}
	}

	public function deactivate() {
		delete_transient( 'shift8_dbcache_capture_active' );
	}
}