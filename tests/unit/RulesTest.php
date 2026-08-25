<?php

use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase {
	public function test_add_rule_persists_and_finds_enabled_rule() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => Shift8_DBCache_Settings::get_default_settings(),
			Shift8_DBCache_Rules::OPTION_NAME => array(),
		);

		$rule = Shift8_DBCache_Rules::add_rule(
			array(
				'match_type' => Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT,
				'target_value' => 'SELECT * FROM wp_posts WHERE id = 42',
				'fingerprint' => 'SELECT * FROM wp_posts WHERE id = 42',
				'ttl' => 120,
				'enabled' => '1',
			)
		);

		$this->assertSame( 'select * from wp_posts where id = ?', $rule['fingerprint'] );
		$this->assertSame( 120, $rule['ttl'] );
		$this->assertSame( '1', $rule['enabled'] );

		$found = Shift8_DBCache_Rules::find_rule_for_fingerprint( 'SELECT * FROM wp_posts WHERE id = 99' );
		$this->assertSame( $rule['id'], $found['id'] );
	}

	public function test_update_rule_changes_ttl_and_enabled_state() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => Shift8_DBCache_Settings::get_default_settings(),
			Shift8_DBCache_Rules::OPTION_NAME => array(),
		);

		$rule = Shift8_DBCache_Rules::add_rule(
			array(
				'match_type' => Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT,
				'target_value' => 'select * from wp_options where option_name = ?',
				'fingerprint' => 'select * from wp_options where option_name = ?',
				'ttl' => 90,
				'enabled' => '1',
			)
		);

		$updated = Shift8_DBCache_Rules::update_rule(
			$rule['id'],
			array(
				'ttl' => 600,
				'enabled' => '0',
			)
		);

		$this->assertSame( 600, $updated['ttl'] );
		$this->assertSame( '0', $updated['enabled'] );
		$this->assertNull( Shift8_DBCache_Rules::find_rule_for_fingerprint( $rule['fingerprint'] ) );
	}

	public function test_add_group_rule_normalizes_group_target() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => Shift8_DBCache_Settings::get_default_settings(),
			Shift8_DBCache_Rules::OPTION_NAME => array(),
		);

		$rule = Shift8_DBCache_Rules::add_rule(
			array(
				'match_type' => Shift8_DBCache_Runtime::RULE_TYPE_GROUP,
				'target_value' => 'WooCommerce Order Query Lookup By ID',
				'label' => 'WooCommerce order query family',
				'ttl' => 300,
				'enabled' => '1',
			)
		);

		$this->assertSame( Shift8_DBCache_Runtime::RULE_TYPE_GROUP, $rule['match_type'] );
		$this->assertSame( 'woocommerce_order_query_lookup_by_id', $rule['target_value'] );
		$this->assertSame( '', $rule['fingerprint'] );
	}
}