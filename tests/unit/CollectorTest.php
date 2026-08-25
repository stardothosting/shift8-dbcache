<?php

use PHPUnit\Framework\TestCase;

class CollectorTest extends TestCase {
	public function test_normalize_sql_reduces_values_to_fingerprint() {
		$collector = Shift8_DBCache_Collector::get_instance();

		$this->assertSame(
			"select * from wp_posts where id = ? and post_status = '?'",
			$collector->normalize_sql( "SELECT * FROM wp_posts WHERE id = 42 AND post_status = 'publish'" )
		);
	}

	public function test_default_state_contains_expected_keys() {
		$state = Shift8_DBCache_Collector::get_default_state();

		$this->assertArrayHasKey( 'capture_active', $state );
		$this->assertArrayHasKey( 'last_capture', $state );
		$this->assertSame( '0', $state['capture_active'] );
	}

	public function test_describe_query_creates_readable_order_lookup_summary() {
		$collector = Shift8_DBCache_Collector::get_instance();
		$insight = $collector->describe_query(
			"SELECT * FROM wp_posts WHERE ID = 123 AND post_type = 'shop_order'",
			'wp_posts',
			'woocommerce'
		);

		$this->assertSame( 'WooCommerce order query lookup by ID', $insight['label'] );
		$this->assertContains( 'WooCommerce frontend', $insight['summary'] );
		$this->assertContains( 'wildcarding the unique ID', $insight['rule_hint'] );
	}
}