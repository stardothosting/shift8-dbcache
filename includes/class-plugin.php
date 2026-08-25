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
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-rules.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-runtime.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-redis-client.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-dropin.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-collector.php';
		require_once SHIFT8_DBCACHE_PATH . 'includes/class-admin.php';

		register_activation_hook( SHIFT8_DBCACHE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( SHIFT8_DBCACHE_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'sync_dropin' ) );
		add_action( 'update_option_shift8_dbcache_settings', array( $this, 'sync_dropin' ), 10, 0 );
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

		if ( false === get_option( Shift8_DBCache_Rules::OPTION_NAME, false ) ) {
			add_option( Shift8_DBCache_Rules::OPTION_NAME, array(), '', false );
		}

		$this->sync_dropin();
	}

	public function deactivate() {
		delete_transient( 'shift8_dbcache_capture_active' );
		Shift8_DBCache_Dropin::remove();
	}

	public function sync_dropin() {
		Shift8_DBCache_Dropin::sync();
	}
}