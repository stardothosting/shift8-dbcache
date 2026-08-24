<?php
/**
 * Query collector and aggregator.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Collector {
	private static $instance = null;
	private $booted = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function get_default_state() {
		return array(
			'capture_active' => '0',
			'capture_started_at' => 0,
			'capture_ended_at' => 0,
			'last_capture' => array(),
			'updated_at' => 0,
		);
	}

	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		if ( $this->is_capture_active() ) {
			if ( ! defined( 'SAVEQUERIES' ) ) {
				define( 'SAVEQUERIES', true );
			}

			add_action( 'shutdown', array( $this, 'collect_request' ), 1 );
		}

		add_action( 'admin_post_shift8_dbcache_toggle_capture', array( $this, 'handle_capture_toggle' ) );
	}

	public function is_capture_active() {
		return '1' === (string) get_transient( 'shift8_dbcache_capture_active' );
	}

	public function handle_capture_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage DB Cache.', 'shift8-dbcache' ) );
		}

		check_admin_referer( 'shift8_dbcache_capture_toggle' );

		$state = get_option( 'shift8_dbcache_analysis', self::get_default_state() );
		$action = isset( $_POST['dbcache_action'] ) ? sanitize_key( wp_unslash( $_POST['dbcache_action'] ) ) : '';

		if ( 'start' === $action ) {
			set_transient( 'shift8_dbcache_capture_active', '1', HOUR_IN_SECONDS );
			$state['capture_active'] = '1';
			$state['capture_started_at'] = time();
			$state['capture_ended_at'] = 0;
		} elseif ( 'stop' === $action ) {
			delete_transient( 'shift8_dbcache_capture_active' );
			$state['capture_active'] = '0';
			$state['capture_ended_at'] = time();
		}

		$state['updated_at'] = time();
		update_option( 'shift8_dbcache_analysis', $state, false );

		wp_safe_redirect( add_query_arg( array( 'page' => 'shift8-dbcache', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function collect_request() {
		/** @var wpdb $wpdb */
		global $wpdb;

		$wpdb_vars = is_object( $wpdb ) ? get_object_vars( $wpdb ) : array();
		$queries = isset( $wpdb_vars['queries'] ) && is_array( $wpdb_vars['queries'] ) ? $wpdb_vars['queries'] : array();

		if ( ! $wpdb || empty( $queries ) ) {
			return;
		}

		$state = get_option( 'shift8_dbcache_analysis', self::get_default_state() );
		$aggregates = isset( $state['last_capture']['aggregates'] ) ? $state['last_capture']['aggregates'] : array();
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$component = $this->detect_component();

		$aggregates = $this->collect_queries( $queries, $request_uri, $component, $aggregates );

		$state['last_capture'] = array(
			'aggregates' => $aggregates,
			'request_uri' => $request_uri,
			'component' => $component,
			'updated_at' => time(),
		);
		$state['updated_at'] = time();
		update_option( 'shift8_dbcache_analysis', $state, false );
	}

	public function collect_queries( array $queries, $request_uri = '', $component = '', array $aggregates = array() ) {
		$request_uri = (string) $request_uri;
		$component = '' !== $component ? sanitize_key( $component ) : $this->detect_component();

		foreach ( $queries as $query ) {
			if ( ! isset( $query[0], $query[1] ) ) {
				continue;
			}

			$sql = trim( (string) $query[0] );
			if ( '' === $sql || 0 !== stripos( $sql, 'select' ) ) {
				continue;
			}

			$fingerprint = $this->normalize_sql( $sql );
			$bucket = isset( $aggregates[ $fingerprint ] ) ? $aggregates[ $fingerprint ] : array(
				'fingerprint' => $fingerprint,
				'sql_example' => $sql,
				'count' => 0,
				'total_time' => 0,
				'max_time' => 0,
				'last_request_uri' => '',
				'component' => $component,
				'table_hint' => $this->extract_table_hint( $sql ),
			);

			$bucket['count']++;
			$bucket['total_time'] += (float) $query[1];
			$bucket['max_time'] = max( $bucket['max_time'], (float) $query[1] );
			$bucket['last_request_uri'] = $request_uri;
			if ( empty( $bucket['component'] ) ) {
				$bucket['component'] = $component;
			}

			$aggregates[ $fingerprint ] = $bucket;
		}

		return $aggregates;
	}

	public function get_report_rows() {
		$state = get_option( 'shift8_dbcache_analysis', self::get_default_state() );
		$aggregates = isset( $state['last_capture']['aggregates'] ) ? $state['last_capture']['aggregates'] : array();
		$rows = array_values( $aggregates );

		usort( $rows, function( $left, $right ) {
			if ( $left['total_time'] === $right['total_time'] ) {
				return $right['count'] <=> $left['count'];
			}

			return $right['total_time'] <=> $left['total_time'];
		} );

		return $rows;
	}

	public function normalize_sql( $sql ) {
		$sql = preg_replace( '/\s+/', ' ', trim( $sql ) );
		$sql = preg_replace( "/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "'?'", $sql );
		$sql = preg_replace( '/\b\d+\b/', '?', $sql );

		return strtolower( $sql );
	}

	private function extract_table_hint( $sql ) {
		if ( preg_match( '/\bfrom\s+`?([a-z0-9_]+)`?/i', $sql, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/\bjoin\s+`?([a-z0-9_]+)`?/i', $sql, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	private function detect_component() {
		if ( is_admin() ) {
			return 'admin';
		}

		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return 'woocommerce';
		}

		return 'frontend';
	}

	public function purge_expired_captures() {
		$settings = Shift8_DBCache_Settings::get_settings();
		$state = get_option( 'shift8_dbcache_analysis', self::get_default_state() );
		$updated_at = isset( $state['updated_at'] ) ? (int) $state['updated_at'] : 0;

		if ( ! $updated_at ) {
			return false;
		}

		if ( $updated_at < time() - ( DAY_IN_SECONDS * (int) $settings['retention_days'] ) ) {
			update_option( 'shift8_dbcache_analysis', self::get_default_state(), false );
			return true;
		}

		return false;
	}
}