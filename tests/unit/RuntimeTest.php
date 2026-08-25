<?php

use PHPUnit\Framework\TestCase;

class RuntimeTest extends TestCase {
	public function test_cacheable_sql_rejects_non_select_and_for_update() {
		$this->assertTrue( Shift8_DBCache_Runtime::is_cacheable_sql( 'SELECT * FROM wp_posts' ) );
		$this->assertFalse( Shift8_DBCache_Runtime::is_cacheable_sql( 'UPDATE wp_posts SET post_title = "x"' ) );
		$this->assertFalse( Shift8_DBCache_Runtime::is_cacheable_sql( 'SELECT * FROM wp_posts FOR UPDATE' ) );
	}

	public function test_match_rule_uses_normalized_fingerprint() {
		$rule = array(
			'id' => 'rule_1',
			'match_type' => Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT,
			'target_value' => 'select * from wp_posts where id = ?',
			'fingerprint' => 'select * from wp_posts where id = ?',
			'enabled' => '1',
			'ttl' => 300,
		);

		$matched = Shift8_DBCache_Runtime::match_rule( 'SELECT * FROM wp_posts WHERE id = 123', array( $rule ) );

		$this->assertSame( 'rule_1', $matched['id'] );
	}

	public function test_match_rule_supports_grouped_query_family() {
		$rule = array(
			'id' => 'rule_group',
			'match_type' => Shift8_DBCache_Runtime::RULE_TYPE_GROUP,
			'target_value' => 'woocommerce_order_query_lookup_by_id',
			'label' => 'WooCommerce order family',
			'enabled' => '1',
			'ttl' => 300,
		);

		$matched = Shift8_DBCache_Runtime::match_rule(
			"SELECT * FROM wp_posts WHERE ID = 123 AND post_type = 'shop_order'",
			array( $rule )
		);

		$this->assertSame( 'rule_group', $matched['id'] );
	}

	public function test_build_and_decode_cache_payload_preserves_value() {
		$payload = Shift8_DBCache_Runtime::build_cache_payload(
			array( 'value' => 'cached' ),
			array( 'ttl' => 60 ),
			'serve_stale_then_refresh'
		);

		$decoded = Shift8_DBCache_Runtime::decode_cache_payload( serialize( $payload ) );

		$this->assertSame( 'cached', $decoded['value']['value'] );
		$this->assertSame( 120, $decoded['ttl'] );
	}
}