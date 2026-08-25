<?php

use PHPUnit\Framework\TestCase;

class CollectorAggregationTest extends TestCase {
	public function test_collect_queries_groups_select_fingerprints() {
		$collector = Shift8_DBCache_Collector::get_instance();
		$rows = array(
			array( 'SELECT * FROM wp_posts WHERE id = 1', 0.20, '' ),
			array( 'SELECT * FROM wp_posts WHERE id = 2', 0.15, '' ),
			array( 'SELECT * FROM wp_postmeta WHERE post_id = 9', 0.05, '' ),
		);

		$aggregates = $collector->collect_queries( $rows, '/shop', 'frontend' );

		$this->assertCount( 2, $aggregates );
		$this->assertArrayHasKey( 'select * from wp_posts where id = ?', $aggregates );
		$this->assertSame( 2, $aggregates['select * from wp_posts where id = ?']['count'] );
		$this->assertSame( 0.35, round( $aggregates['select * from wp_posts where id = ?']['total_time'], 2 ) );
		$this->assertSame( 'frontend', $aggregates['select * from wp_posts where id = ?']['component'] );
	}

	public function test_get_report_rows_sorts_by_total_time_then_count() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_analysis' => array(
				'last_capture' => array(
					'aggregates' => array(
						'query-a' => array( 'fingerprint' => 'query-a', 'count' => 1, 'total_time' => 0.10, 'max_time' => 0.10, 'component' => 'frontend', 'table_hint' => 'wp_posts' ),
						'query-b' => array( 'fingerprint' => 'query-b', 'count' => 3, 'total_time' => 0.40, 'max_time' => 0.20, 'component' => 'frontend', 'table_hint' => 'wp_postmeta' ),
					),
				),
			),
		);

		$rows = Shift8_DBCache_Collector::get_instance()->get_report_rows();

		$this->assertSame( 'query-b', $rows[0]['fingerprint'] );
		$this->assertSame( 'query-a', $rows[1]['fingerprint'] );
	}

	public function test_purge_expired_captures_clears_stale_state() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => array(
				'retention_days' => 1,
			),
			'shift8_dbcache_analysis' => array(
				'updated_at' => time() - ( DAY_IN_SECONDS * 2 ),
				'last_capture' => array( 'aggregates' => array() ),
			),
		);

		$collector = Shift8_DBCache_Collector::get_instance();
		$this->assertTrue( $collector->purge_expired_captures() );
		$state = get_option( 'shift8_dbcache_analysis' );
		$this->assertSame( '0', $state['capture_active'] );
	}

	public function test_collect_queries_ignores_fast_noise_below_threshold() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => array_merge(
				Shift8_DBCache_Settings::get_default_settings(),
				array(
					'capture_min_query_time' => 0.05,
					'max_tracked_patterns' => 500,
				)
			),
		);

		$collector = Shift8_DBCache_Collector::get_instance();
		$aggregates = $collector->collect_queries(
			array(
				array( 'SELECT * FROM wp_posts WHERE id = 1', 0.01, '' ),
				array( 'SELECT * FROM wp_posts WHERE id = 2', 0.06, '' ),
			),
			'/shop',
			'frontend'
		);

		$this->assertCount( 1, $aggregates );
		$this->assertArrayHasKey( 'select * from wp_posts where id = ?', $aggregates );
		$this->assertSame( 1, $aggregates['select * from wp_posts where id = ?']['count'] );
	}

	public function test_collect_queries_caps_distinct_patterns() {
		global $shift8_dbcache_test_options;
		$shift8_dbcache_test_options = array(
			'shift8_dbcache_settings' => array_merge(
				Shift8_DBCache_Settings::get_default_settings(),
				array(
					'capture_min_query_time' => 0.01,
					'max_tracked_patterns' => 2,
				)
			),
		);

		$collector = Shift8_DBCache_Collector::get_instance();
		$aggregates = $collector->collect_queries(
			array(
				array( 'SELECT * FROM wp_posts WHERE id = 1', 0.10, '' ),
				array( 'SELECT * FROM wp_postmeta WHERE post_id = 2', 0.10, '' ),
				array( 'SELECT * FROM wp_users WHERE ID = 3', 0.10, '' ),
			),
			'/shop',
			'frontend'
		);

		$this->assertCount( 2, $aggregates );
	}
}