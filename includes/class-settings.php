<?php
/**
 * Settings helpers.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Settings {
	public static function get_default_settings() {
		return array(
			'capture_enabled'          => '0',
			'capture_window_minutes'    => 60,
			'capture_min_query_time'    => 0.02,
			'max_tracked_patterns'      => 500,
			'retention_days'            => 7,
			'stale_behavior'            => 'serve_stale_then_refresh',
			'default_rule_ttl'          => 300,
			'redis_enabled'             => '0',
			'redis_host'                => '',
			'redis_port'                => 6379,
			'redis_database'            => 0,
			'redis_prefix'              => '',
			'rule_sources'              => array( 'sql_fingerprint', 'component', 'request_pattern' ),
		);
	}

	public static function get_settings() {
		return wp_parse_args( get_option( 'shift8_dbcache_settings', array() ), self::get_default_settings() );
	}

	public static function sanitize_settings( $settings ) {
		$defaults = self::get_default_settings();
		$settings = wp_parse_args( $settings, $defaults );

		$settings['capture_enabled'] = ! empty( $settings['capture_enabled'] ) ? '1' : '0';
		$settings['capture_window_minutes'] = max( 5, absint( $settings['capture_window_minutes'] ) );
		$settings['capture_min_query_time'] = max( 0.001, (float) $settings['capture_min_query_time'] );
		$settings['max_tracked_patterns'] = min( 5000, max( 50, absint( $settings['max_tracked_patterns'] ) ) );
		$settings['retention_days'] = max( 1, absint( $settings['retention_days'] ) );
		$allowed_stale = array( 'serve_stale_then_refresh', 'expire_hard' );
		$settings['stale_behavior'] = in_array( $settings['stale_behavior'], $allowed_stale, true ) ? $settings['stale_behavior'] : $defaults['stale_behavior'];
		$settings['default_rule_ttl'] = min( 86400, max( 30, absint( $settings['default_rule_ttl'] ) ) );
		$settings['redis_enabled'] = ! empty( $settings['redis_enabled'] ) ? '1' : '0';
		$settings['redis_host'] = sanitize_text_field( wp_unslash( $settings['redis_host'] ) );
		$settings['redis_port'] = max( 1, absint( $settings['redis_port'] ) );
		$settings['redis_database'] = max( 0, (int) $settings['redis_database'] );
		$settings['redis_prefix'] = sanitize_key( $settings['redis_prefix'] );

		$allowed_sources = array( 'sql_fingerprint', 'component', 'request_pattern' );
		$rule_sources = array();
		foreach ( (array) $settings['rule_sources'] as $source ) {
			$source = sanitize_key( $source );
			if ( in_array( $source, $allowed_sources, true ) ) {
				$rule_sources[] = $source;
			}
		}
		$settings['rule_sources'] = array_values( array_unique( $rule_sources ) );

		return $settings;
	}

	public static function is_active_cache_enabled() {
		$settings = self::get_settings();

		return '1' === $settings['redis_enabled'];
	}

	public static function get_redis_config() {
		$settings = self::get_settings();
		$password = '';

		if ( defined( 'SHIFT8_DBCACHE_REDIS_PASSWORD' ) ) {
			$password = (string) SHIFT8_DBCACHE_REDIS_PASSWORD;
		} elseif ( false !== getenv( 'SHIFT8_DBCACHE_REDIS_PASSWORD' ) ) {
			$password = (string) getenv( 'SHIFT8_DBCACHE_REDIS_PASSWORD' );
		}

		$host = defined( 'SHIFT8_DBCACHE_REDIS_HOST' ) ? (string) SHIFT8_DBCACHE_REDIS_HOST : (string) $settings['redis_host'];
		$port = defined( 'SHIFT8_DBCACHE_REDIS_PORT' ) ? (int) SHIFT8_DBCACHE_REDIS_PORT : (int) $settings['redis_port'];
		$database = defined( 'SHIFT8_DBCACHE_REDIS_DATABASE' ) ? (int) SHIFT8_DBCACHE_REDIS_DATABASE : (int) $settings['redis_database'];
		$prefix = defined( 'SHIFT8_DBCACHE_REDIS_PREFIX' ) ? (string) SHIFT8_DBCACHE_REDIS_PREFIX : (string) $settings['redis_prefix'];
		$timeout = defined( 'SHIFT8_DBCACHE_REDIS_TIMEOUT' ) ? (float) SHIFT8_DBCACHE_REDIS_TIMEOUT : 1.0;

		return array(
			'enabled' => '1' === $settings['redis_enabled'],
			'host' => trim( $host ),
			'port' => max( 1, $port ),
			'database' => max( 0, $database ),
			'prefix' => sanitize_key( $prefix ),
			'password' => $password,
			'timeout' => max( 0.1, $timeout ),
		);
	}
}