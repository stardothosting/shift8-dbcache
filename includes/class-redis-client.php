<?php
/**
 * Redis client wrapper for active cache execution.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Redis_Client {
	private static $client = null;
	private static $status = null;
	private static $runtime_config = null;

	public static function set_runtime_config( array $config ) {
		self::$runtime_config = $config;
		self::reset();
	}

	public static function clear_runtime_config() {
		self::$runtime_config = null;
		self::reset();
	}

	public static function get_status() {
		if ( null !== self::$status ) {
			return self::$status;
		}

		$config = self::get_config();
		$status = array(
			'enabled' => ! empty( $config['enabled'] ),
			'loaded' => class_exists( 'Redis' ),
			'configured' => ! empty( $config['host'] ),
			'connected' => false,
			'message' => '',
		);

		if ( ! $status['enabled'] ) {
			$status['message'] = 'Active cache is disabled.';
			self::$status = $status;
			return $status;
		}

		if ( ! $status['loaded'] ) {
			$status['message'] = 'The phpredis extension is not loaded.';
			self::$status = $status;
			return $status;
		}

		if ( ! $status['configured'] ) {
			$status['message'] = 'Redis host is not configured.';
			self::$status = $status;
			return $status;
		}

		$client = self::get_client();
		$status['connected'] = is_object( $client );
		$status['message'] = $status['connected'] ? 'Connected to Redis.' : 'Redis connection failed.';

		self::$status = $status;

		return $status;
	}

	public static function get_client() {
		if ( null !== self::$client ) {
			return self::$client;
		}

		$config = self::get_config();

		if ( empty( $config['enabled'] ) || empty( $config['host'] ) || ! class_exists( 'Redis' ) ) {
			return null;
		}

		try {
			$client = new Redis();
			$connected = $client->connect( $config['host'], (int) $config['port'], (float) $config['timeout'] );

			if ( ! $connected ) {
				return null;
			}

			if ( '' !== $config['password'] ) {
				$client->auth( $config['password'] );
			}

			if ( ! empty( $config['database'] ) ) {
				$client->select( (int) $config['database'] );
			}

			self::$client = $client;
		} catch ( Throwable $throwable ) {
			self::$client = null;
		}

		return self::$client;
	}

	public static function get( $key ) {
		$client = self::get_client();

		if ( ! $client ) {
			return false;
		}

		try {
			return $client->get( $key );
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	public static function set( $key, $value, $ttl ) {
		$client = self::get_client();

		if ( ! $client ) {
			return false;
		}

		try {
			return $client->setex( $key, max( 1, (int) $ttl ), serialize( $value ) );
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	public static function delete( $key ) {
		$client = self::get_client();

		if ( ! $client ) {
			return false;
		}

		try {
			return (bool) $client->del( $key );
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	public static function get_rule_stats( $rule_id ) {
		$client = self::get_client();
		$stats = self::get_default_rule_stats();

		if ( ! $client || '' === (string) $rule_id ) {
			return $stats;
		}

		try {
			$stored = $client->get( self::get_rule_stats_key( $rule_id ) );
			if ( ! is_string( $stored ) || '' === $stored ) {
				return $stats;
			}

			$data = @unserialize( $stored );
			if ( ! is_array( $data ) ) {
				return $stats;
			}

			return wp_parse_args( $data, $stats );
		} catch ( Throwable $throwable ) {
			return $stats;
		}
	}

	public static function record_rule_hit( $rule_id, $ttl ) {
		self::update_rule_stats(
			$rule_id,
			function( $stats ) {
				$stats['hits']++;
				$stats['last_hit_at'] = time();

				return $stats;
			},
			$ttl
		);
	}

	public static function record_rule_miss( $rule_id, $ttl ) {
		self::update_rule_stats(
			$rule_id,
			function( $stats ) {
				$stats['misses']++;

				return $stats;
			},
			$ttl
		);
	}

	public static function record_rule_write( $rule_id, $ttl ) {
		self::update_rule_stats(
			$rule_id,
			function( $stats ) use ( $ttl ) {
				$stats['writes']++;
				$stats['hits'] = 0;
				$stats['expires_at'] = time() + max( 1, (int) $ttl );
				$stats['last_write_at'] = time();

				return $stats;
			},
			$ttl
		);
	}

	public static function reset() {
		self::$client = null;
		self::$status = null;
	}

	private static function get_config() {
		if ( is_array( self::$runtime_config ) ) {
			return self::$runtime_config;
		}

		return Shift8_DBCache_Settings::get_redis_config();
	}

	private static function update_rule_stats( $rule_id, callable $callback, $ttl ) {
		$client = self::get_client();

		if ( ! $client || '' === (string) $rule_id ) {
			return false;
		}

		$stats = self::get_rule_stats( $rule_id );
		$stats = $callback( $stats );
		$ttl = max( 1, (int) $ttl );

		try {
			return $client->setex( self::get_rule_stats_key( $rule_id ), $ttl, serialize( $stats ) );
		} catch ( Throwable $throwable ) {
			return false;
		}
	}

	private static function get_rule_stats_key( $rule_id ) {
		$config = self::get_config();
		$prefix = ! empty( $config['prefix'] ) ? $config['prefix'] : 'shift8_dbcache';

		return sprintf( '%s:rule-stats:%s', $prefix, sanitize_key( (string) $rule_id ) );
	}

	private static function get_default_rule_stats() {
		return array(
			'hits' => 0,
			'misses' => 0,
			'writes' => 0,
			'last_hit_at' => 0,
			'last_write_at' => 0,
			'expires_at' => 0,
		);
	}
}