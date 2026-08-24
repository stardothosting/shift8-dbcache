<?php

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {
	public function test_defaults_include_redis_and_stale_settings() {
		$defaults = Shift8_DBCache_Settings::get_default_settings();

		$this->assertSame( '0', $defaults['capture_enabled'] );
		$this->assertSame( 'serve_stale_then_refresh', $defaults['stale_behavior'] );
		$this->assertContains( 'request_pattern', $defaults['rule_sources'] );
	}

	public function test_sanitize_settings_clamps_and_filters_values() {
		$sanitized = Shift8_DBCache_Settings::sanitize_settings( array(
			'capture_enabled' => '1',
			'capture_window_minutes' => '2',
			'retention_days' => '0',
			'stale_behavior' => 'not-valid',
			'redis_enabled' => 'yes',
			'redis_host' => ' redis.example.com ',
			'redis_port' => '70000',
			'redis_database' => '-3',
			'redis_prefix' => 'Shift8 DB Cache!',
			'rule_sources' => array( 'sql_fingerprint', 'invalid', 'component' ),
		) );

		$this->assertSame( '1', $sanitized['capture_enabled'] );
		$this->assertSame( 5, $sanitized['capture_window_minutes'] );
		$this->assertSame( 1, $sanitized['retention_days'] );
		$this->assertSame( 'serve_stale_then_refresh', $sanitized['stale_behavior'] );
		$this->assertSame( '1', $sanitized['redis_enabled'] );
		$this->assertSame( 'redis.example.com', $sanitized['redis_host'] );
		$this->assertSame( 70000, $sanitized['redis_port'] );
		$this->assertSame( 0, $sanitized['redis_database'] );
		$this->assertSame( 'shiftdbcache', $sanitized['redis_prefix'] );
		$this->assertSame( array( 'sql_fingerprint', 'component' ), $sanitized['rule_sources'] );
	}
}