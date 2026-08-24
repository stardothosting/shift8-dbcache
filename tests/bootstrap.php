<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
}

if ( ! class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
	require_once __DIR__ . '/phpunit-stub.php';
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/shift8-dbcache/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return 'shift8-dbcache/shift8-dbcache.php';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page() {
		return 'shift8-settings';
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page() {
		return 'shift8-dbcache';
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook() {
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook() {
		return true;
}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting() {
		return true;
}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can() {
		return true;
}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text ) {
		echo $text;
}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return $text;
}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text ) {
		echo $text;
}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $shift8_dbcache_test_options;
		return isset( $shift8_dbcache_test_options[ $option ] ) ? $shift8_dbcache_test_options[ $option ] : $default;
}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options[ $option ] = $value;
		return true;
}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value ) {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options[ $option ] = $value;
		return true;
}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		global $shift8_dbcache_test_transients;
		return isset( $shift8_dbcache_test_transients[ $key ] ) ? $shift8_dbcache_test_transients[ $key ] : false;
}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value ) {
		global $shift8_dbcache_test_transients;
		$shift8_dbcache_test_transients[ $key ] = $value;
		return true;
}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $shift8_dbcache_test_transients;
		unset( $shift8_dbcache_test_transients[ $key ] );
		return true;
}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer() {
		return true;
}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce() {
		return true;
}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field() {
		return true;
}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect() {
		return true;
}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		return $url;
}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields() {
		return true;
}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button() {
		return true;
}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $value, $current ) {
		return $value === $current ? ' selected="selected"' : '';
}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
}
}

if ( ! function_exists( 'is_woocommerce' ) ) {
	function is_woocommerce() {
		return false;
}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message ) {
		throw new Exception( (string) $message );
}
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-collector.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
require_once dirname( __DIR__ ) . '/includes/class-admin.php';