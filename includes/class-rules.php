<?php
/**
 * Cache rule storage helpers.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Rules {
	const OPTION_NAME = 'shift8_dbcache_rules';

	public static function get_rules() {
		$rules = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $rules ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $rules as $rule ) {
			$normalized[] = self::normalize_rule( $rule );
		}

		return array_values( array_filter( $normalized, array( __CLASS__, 'is_rule_valid' ) ) );
	}

	public static function get_enabled_rules() {
		return array_values(
			array_filter(
				self::get_rules(),
				function( $rule ) {
					return '1' === $rule['enabled'];
				}
			)
		);
	}

	public static function find_rule_for_fingerprint( $fingerprint ) {
		$fingerprint = self::normalize_fingerprint( $fingerprint );

		foreach ( self::get_enabled_rules() as $rule ) {
			if ( Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT === $rule['match_type'] && $rule['target_value'] === $fingerprint ) {
				return $rule;
			}
		}

		return null;
	}

	public static function add_rule( array $rule_data ) {
		$rules = self::get_rules();
		$rule = self::normalize_rule( $rule_data );

		foreach ( $rules as $index => $existing_rule ) {
			if ( $existing_rule['match_type'] === $rule['match_type'] && $existing_rule['target_value'] === $rule['target_value'] ) {
				$rule['id'] = $existing_rule['id'];
				$rule['created_at'] = $existing_rule['created_at'];
				$rules[ $index ] = $rule;
				self::save_rules( $rules );
				return $rule;
			}
		}

		$rules[] = $rule;
		self::save_rules( $rules );

		return $rule;
	}

	public static function update_rule( $rule_id, array $rule_data ) {
		$rules = self::get_rules();

		foreach ( $rules as $index => $rule ) {
			if ( $rule['id'] !== $rule_id ) {
				continue;
			}

			$rules[ $index ] = self::normalize_rule( array_merge( $rule, $rule_data, array( 'id' => $rule_id ) ) );
			self::save_rules( $rules );

			return $rules[ $index ];
		}

		return null;
	}

	public static function delete_rule( $rule_id ) {
		$rules = array_values(
			array_filter(
				self::get_rules(),
				function( $rule ) use ( $rule_id ) {
					return $rule['id'] !== $rule_id;
				}
			)
		);

		self::save_rules( $rules );
	}

	public static function save_rules( array $rules ) {
		update_option( self::OPTION_NAME, array_values( $rules ), false );
	}

	public static function normalize_rule( $rule ) {
		$settings = Shift8_DBCache_Settings::get_settings();
		$rule = is_array( $rule ) ? $rule : array();
		$created_at = isset( $rule['created_at'] ) ? (int) $rule['created_at'] : time();
		$match_type = isset( $rule['match_type'] ) ? sanitize_key( $rule['match_type'] ) : Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT;
		if ( ! in_array( $match_type, array( Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT, Shift8_DBCache_Runtime::RULE_TYPE_GROUP ), true ) ) {
			$match_type = Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT;
		}

		$target_value = isset( $rule['target_value'] ) ? (string) $rule['target_value'] : ( isset( $rule['fingerprint'] ) ? (string) $rule['fingerprint'] : '' );
		$target_value = self::normalize_target_value( $match_type, $target_value );
		$label = isset( $rule['label'] ) ? sanitize_text_field( $rule['label'] ) : '';
		if ( '' === $label ) {
			$label = Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $match_type ? ucwords( str_replace( '_', ' ', $target_value ) ) : $target_value;
		}

		return array(
			'id' => isset( $rule['id'] ) && '' !== (string) $rule['id'] ? sanitize_key( $rule['id'] ) : self::generate_rule_id(),
			'match_type' => $match_type,
			'target_value' => $target_value,
			'label' => $label,
			'fingerprint' => Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT === $match_type ? $target_value : '',
			'enabled' => ! empty( $rule['enabled'] ) ? '1' : '0',
			'ttl' => min( 86400, max( 30, isset( $rule['ttl'] ) ? absint( $rule['ttl'] ) : (int) $settings['default_rule_ttl'] ) ),
			'created_at' => $created_at,
			'updated_at' => time(),
		);
	}

	public static function normalize_fingerprint( $fingerprint ) {
		$fingerprint = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $fingerprint ) ) );

		return sanitize_text_field( $fingerprint );
	}

	public static function normalize_target_value( $match_type, $target_value ) {
		if ( Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $match_type ) {
			return sanitize_key( $target_value );
		}

		return self::normalize_fingerprint( $target_value );
	}

	private static function is_rule_valid( $rule ) {
		return ! empty( $rule['id'] ) && ! empty( $rule['target_value'] ) && ! empty( $rule['ttl'] ) && ! empty( $rule['match_type'] );
	}

	private static function generate_rule_id() {
		try {
			return 'rule_' . bin2hex( random_bytes( 8 ) );
		} catch ( Exception $exception ) {
			return 'rule_' . sanitize_key( uniqid( '', true ) );
		}
	}
}