<?php
/**
 * Minimal wpdb wrapper for targeted Redis-backed read caching.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'wpdb' ) && ! class_exists( 'Shift8_DBCache_WPDB' ) ) {
	class Shift8_DBCache_WPDB extends wpdb {
		private static $bypass_depth = 0;
		private static $runtime_state = null;

		public function get_results( $query = null, $output = OBJECT ) {
			if ( null === $query ) {
				return parent::get_results( $query, $output );
			}

			return $this->with_cache(
				$query,
				'get_results',
				array( 'output' => $output ),
				function() use ( $query, $output ) {
					return parent::get_results( $query, $output );
				}
			);
		}

		public function get_row( $query = null, $output = OBJECT, $y = 0 ) {
			if ( null === $query ) {
				return parent::get_row( $query, $output, $y );
			}

			return $this->with_cache(
				$query,
				'get_row',
				array(
					'output' => $output,
					'offset' => (int) $y,
				),
				function() use ( $query, $output, $y ) {
					return parent::get_row( $query, $output, $y );
				}
			);
		}

		public function get_var( $query = null, $x = 0, $y = 0 ) {
			if ( null === $query ) {
				return parent::get_var( $query, $x, $y );
			}

			return $this->with_cache(
				$query,
				'get_var',
				array(
					'column' => (int) $x,
					'offset' => (int) $y,
				),
				function() use ( $query, $x, $y ) {
					return parent::get_var( $query, $x, $y );
				}
			);
		}

		public function get_col( $query = null, $x = 0 ) {
			if ( null === $query ) {
				return parent::get_col( $query, $x );
			}

			return $this->with_cache(
				$query,
				'get_col',
				array( 'column' => (int) $x ),
				function() use ( $query, $x ) {
					return parent::get_col( $query, $x );
				}
			);
		}

		private function with_cache( $query, $operation, array $context, callable $callback ) {
			if ( $this->should_bypass_cache( $query ) ) {
				return $callback();
			}

			$runtime_state = $this->get_runtime_state();
			if ( empty( $runtime_state['settings']['redis_enabled'] ) || empty( $runtime_state['rules'] ) ) {
				return $callback();
			}

			$rule = Shift8_DBCache_Runtime::match_rule( $query, $runtime_state['rules'] );
			if ( ! $rule ) {
				return $callback();
			}

			$cache_key = Shift8_DBCache_Runtime::build_cache_key( $rule, $query, $operation, $context );
			$payload = Shift8_DBCache_Runtime::decode_cache_payload( Shift8_DBCache_Redis_Client::get( $cache_key ) );

			if ( is_array( $payload ) ) {
				$now = time();
				if ( $now <= (int) $payload['stale_until'] ) {
					Shift8_DBCache_Redis_Client::record_rule_hit( $rule['id'], max( 1, (int) $payload['stale_until'] - $now ) );

					if ( $now > (int) $payload['fresh_until'] && 'serve_stale_then_refresh' === $runtime_state['settings']['stale_behavior'] ) {
						Shift8_DBCache_Redis_Client::delete( $cache_key );
					}

					return $payload['value'];
				}

				Shift8_DBCache_Redis_Client::delete( $cache_key );
			}

			Shift8_DBCache_Redis_Client::record_rule_miss( $rule['id'], max( 1, (int) $rule['ttl'] ) );

			$value = $callback();

			if ( ! empty( $this->last_error ) ) {
				return $value;
			}

			$payload = Shift8_DBCache_Runtime::build_cache_payload( $value, $rule, $runtime_state['settings']['stale_behavior'] );
			Shift8_DBCache_Redis_Client::set( $cache_key, $payload, $payload['ttl'] );
			Shift8_DBCache_Redis_Client::record_rule_write( $rule['id'], $payload['ttl'] );

			return $value;
		}

		private function should_bypass_cache( $query ) {
			if ( self::$bypass_depth > 0 ) {
				return true;
			}

			if ( ! Shift8_DBCache_Runtime::is_cacheable_sql( $query ) ) {
				return true;
			}

			return ! function_exists( 'get_option' );
		}

		private function get_runtime_state() {
			if ( is_array( self::$runtime_state ) ) {
				return self::$runtime_state;
			}

			return $this->with_bypass(
				function() {
					$settings = Shift8_DBCache_Settings::get_settings();
					$config = Shift8_DBCache_Settings::get_redis_config();
					$rules = Shift8_DBCache_Rules::get_enabled_rules();

					Shift8_DBCache_Redis_Client::set_runtime_config( $config );

					self::$runtime_state = array(
						'settings' => $settings,
						'config' => $config,
						'rules' => $rules,
					);

					return self::$runtime_state;
				}
			);
		}

		private function with_bypass( callable $callback ) {
			self::$bypass_depth++;

			try {
				return $callback();
			} finally {
				self::$bypass_depth--;
			}
		}
	}
}