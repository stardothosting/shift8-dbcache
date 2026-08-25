<?php
/**
 * Cache runtime helpers shared by the plugin and the db drop-in.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Runtime {
	const RULE_TYPE_FINGERPRINT = 'exact_fingerprint';
	const RULE_TYPE_GROUP = 'insight_group';

	public static function normalize_sql( $sql ) {
		$sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );
		$sql = preg_replace( "/'[^'\\]*(?:\\.[^'\\]*)*'/", "'?'", $sql );
		$sql = preg_replace( '/\b\d+\b/', '?', $sql );

		return strtolower( $sql );
	}

	public static function is_cacheable_sql( $sql ) {
		$sql = ltrim( (string) $sql );

		if ( '' === $sql || 0 !== stripos( $sql, 'select' ) ) {
			return false;
		}

		if ( preg_match( '/\bfor\s+update\b/i', $sql ) ) {
			return false;
		}

		return true;
	}

	public static function match_rule( $sql, array $rules ) {
		$profile = self::build_query_profile( $sql );

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) || '1' !== $rule['enabled'] ) {
				continue;
			}

			$match_type = isset( $rule['match_type'] ) ? $rule['match_type'] : self::RULE_TYPE_FINGERPRINT;
			$target_value = isset( $rule['target_value'] ) ? (string) $rule['target_value'] : '';

			if ( self::RULE_TYPE_GROUP === $match_type && $target_value === $profile['group_key'] ) {
				return $rule;
			}

			if ( self::RULE_TYPE_FINGERPRINT === $match_type && $target_value === $profile['fingerprint'] ) {
				return $rule;
			}
		}

		return null;
	}

	public static function build_query_profile( $sql, $component = '' ) {
		$fingerprint = self::normalize_sql( $sql );
		$table_hint = self::extract_table_hint( $sql );
		$entity = self::detect_entity_label( $fingerprint, $table_hint );
		$action = self::detect_query_action( $fingerprint );
		$group_key = sanitize_key( strtolower( preg_replace( '/[^a-z0-9]+/', '_', $entity . '_' . $action ) ) );

		return array(
			'fingerprint' => $fingerprint,
			'table_hint' => $table_hint,
			'entity_label' => $entity,
			'action_label' => $action,
			'group_key' => $group_key,
			'group_label' => trim( $entity . ' ' . $action ),
			'component' => sanitize_key( (string) $component ),
			'rule_hint' => self::build_rule_hint( $action, $entity ),
		);
	}

	public static function build_cache_key( array $rule, $sql, $operation, array $context = array() ) {
		$redis_config = method_exists( 'Shift8_DBCache_Settings', 'get_redis_config' )
			? Shift8_DBCache_Settings::get_redis_config()
			: array( 'prefix' => 'shift8_dbcache' );
		$prefix = ! empty( $redis_config['prefix'] ) ? $redis_config['prefix'] : 'shift8_dbcache';
		$payload = array(
			'rule' => isset( $rule['id'] ) ? $rule['id'] : '',
			'fingerprint' => self::normalize_sql( $sql ),
			'operation' => (string) $operation,
			'context' => self::normalize_context( $context ),
		);

		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );

		return sprintf( '%s:%s', $prefix, sha1( (string) $json ) );
	}

	public static function build_cache_payload( $value, array $rule, $stale_behavior ) {
		$ttl = (int) $rule['ttl'];
		$now = time();
		$stale_extension = 'serve_stale_then_refresh' === $stale_behavior ? min( $ttl, 300 ) : 0;
		$stale_until = $now + $ttl + $stale_extension;

		return array(
			'value' => $value,
			'fresh_until' => $now + $ttl,
			'stale_until' => $stale_until,
			'ttl' => $ttl + $stale_extension,
		);
	}

	public static function decode_cache_payload( $payload ) {
		if ( ! is_string( $payload ) || '' === $payload ) {
			return null;
		}

		$data = @unserialize( $payload );
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! isset( $data['fresh_until'], $data['stale_until'] ) ) {
			return null;
		}

		return $data;
	}

	public static function normalize_context( array $context ) {
		ksort( $context );

		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$context[ $key ] = self::normalize_context( $value );
			}
		}

		return $context;
	}

	public static function extract_table_hint( $sql ) {
		$sql = (string) $sql;

		if ( preg_match( '/\bfrom\s+`?([a-z0-9_]+)`?/i', $sql, $matches ) ) {
			return strtolower( $matches[1] );
		}

		if ( preg_match( '/\bjoin\s+`?([a-z0-9_]+)`?/i', $sql, $matches ) ) {
			return strtolower( $matches[1] );
		}

		return '';
	}

	private static function detect_entity_label( $normalized_sql, $table_hint ) {
		$table_map = array(
			'wp_posts' => 'WordPress content query',
			'wp_postmeta' => 'WordPress content metadata query',
			'wp_options' => 'WordPress option query',
			'wp_users' => 'WordPress user query',
			'wp_usermeta' => 'WordPress user metadata query',
			'woocommerce_sessions' => 'WooCommerce session query',
			'wc_orders' => 'WooCommerce order query',
			'wc_order_addresses' => 'WooCommerce order address query',
			'wc_order_operational_data' => 'WooCommerce order operational query',
			'wc_orders_meta' => 'WooCommerce order metadata query',
		);

		if ( false !== strpos( $normalized_sql, 'shop_order' ) || false !== strpos( $normalized_sql, 'wc_orders' ) ) {
			return 'WooCommerce order query';
		}

		if ( false !== strpos( $normalized_sql, 'user_email' ) || false !== strpos( $normalized_sql, 'user_login' ) ) {
			return 'WordPress user query';
		}

		if ( isset( $table_map[ $table_hint ] ) ) {
			return $table_map[ $table_hint ];
		}

		if ( '' !== $table_hint ) {
			return $table_hint . ' query';
		}

		return 'Database query';
	}

	private static function detect_query_action( $normalized_sql ) {
		if ( preg_match( '/\bwhere\b.+\b(id|post_id|order_id|user_id|comment_id|meta_id)\s*=\s*(\?|\'\?\')/i', $normalized_sql ) ) {
			return 'lookup by ID';
		}

		if ( false !== strpos( $normalized_sql, 'meta_key' ) ) {
			return 'metadata lookup';
		}

		if ( false !== strpos( $normalized_sql, 'option_name' ) ) {
			return 'lookup by option name';
		}

		if ( false !== strpos( $normalized_sql, 'post_name' ) || false !== strpos( $normalized_sql, 'post_title' ) ) {
			return 'content lookup';
		}

		if ( false !== strpos( $normalized_sql, 'join ' ) ) {
			return 'joined lookup';
		}

		return 'read query';
	}

	private static function build_rule_hint( $action, $entity ) {
		if ( 'lookup by ID' === $action ) {
			return sprintf( 'Cache similar %s requests while wildcarding the unique ID.', strtolower( $entity ) );
		}

		if ( 'metadata lookup' === $action ) {
			return 'Cache this repeated metadata pattern only if the underlying metadata is stable enough.';
		}

		if ( 'lookup by option name' === $action ) {
			return 'Cache this named option lookup only if it rarely changes and is safe to share from Redis.';
		}

		return 'Review the normalized pattern and only create a rule if this query shape repeats and can be served safely from Redis.';
	}
}
