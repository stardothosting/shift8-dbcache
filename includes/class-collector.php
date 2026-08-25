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
		$settings = Shift8_DBCache_Settings::get_settings();
		$minimum_query_time = isset( $settings['capture_min_query_time'] ) ? (float) $settings['capture_min_query_time'] : 0.02;
		$max_tracked_patterns = isset( $settings['max_tracked_patterns'] ) ? (int) $settings['max_tracked_patterns'] : 500;

		foreach ( $queries as $query ) {
			if ( ! isset( $query[0], $query[1] ) ) {
				continue;
			}

			$sql = trim( (string) $query[0] );
			if ( '' === $sql || 0 !== stripos( $sql, 'select' ) ) {
				continue;
			}

			$query_time = (float) $query[1];
			if ( $query_time < $minimum_query_time ) {
				continue;
			}

			$fingerprint = $this->normalize_sql( $sql );
			if ( ! isset( $aggregates[ $fingerprint ] ) && count( $aggregates ) >= $max_tracked_patterns ) {
				continue;
			}

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
			$bucket['total_time'] += $query_time;
			$bucket['max_time'] = max( $bucket['max_time'], $query_time );
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

		foreach ( $rows as &$row ) {
			$insight = $this->describe_query(
				isset( $row['sql_example'] ) ? $row['sql_example'] : '',
				isset( $row['table_hint'] ) ? $row['table_hint'] : '',
				isset( $row['component'] ) ? $row['component'] : ''
			);

			$row['avg_time'] = ! empty( $row['count'] ) ? ( (float) $row['total_time'] / (int) $row['count'] ) : 0.0;
			$row['insight_label'] = $insight['label'];
			$row['insight_summary'] = $insight['summary'];
			$row['rule_hint'] = $insight['rule_hint'];
			$row['technical_summary'] = $insight['technical_summary'];
			$row['group_key'] = $insight['group_key'];
			$row['group_label'] = $insight['group_label'];
		}
		unset( $row );

		return $rows;
	}

	public function describe_query( $sql, $table_hint = '', $component = '' ) {
		$normalized = $this->normalize_sql( $sql );
		$table_hint = strtolower( (string) $table_hint );
		$component = sanitize_key( (string) $component );
		$group_key = '';
		$group_label = '';

		if ( class_exists( 'Shift8_DBCache_Runtime' ) && method_exists( 'Shift8_DBCache_Runtime', 'build_query_profile' ) ) {
			$profile = Shift8_DBCache_Runtime::build_query_profile( $sql, $component );
			$entity = $profile['entity_label'];
			$action = $profile['action_label'];
			$group_key = $profile['group_key'];
			$group_label = $profile['group_label'];
			$rule_hint = $profile['rule_hint'];
		} else {
			$entity = $this->detect_entity_label( $normalized, $table_hint );
			$action = $this->detect_query_action( $normalized );
			$group_key = sanitize_key( strtolower( preg_replace( '/[^a-z0-9]+/', '_', $entity . '_' . $action ) ) );
			$group_label = trim( $entity . ' ' . $action );
			$rule_hint = $this->build_rule_hint( $action, $entity );
		}
		$label = trim( $entity . ' ' . $action );

		return array(
			'label' => '' !== $label ? $label : __( 'Database lookup', 'shift8-dbcache' ),
			'summary' => sprintf(
				/* translators: 1: query label, 2: runtime component */
				__( '%1$s triggered by the %2$s.', 'shift8-dbcache' ),
				'' !== $label ? $label : __( 'Database lookup', 'shift8-dbcache' ),
				$this->get_component_label( $component )
			),
			'rule_hint' => $rule_hint,
			'technical_summary' => sprintf(
				/* translators: 1: table hint, 2: normalized SQL pattern */
				__( 'Table: %1$s. Pattern: %2$s', 'shift8-dbcache' ),
				'' !== $table_hint ? $table_hint : __( 'unknown', 'shift8-dbcache' ),
				$normalized
			),
			'group_key' => $group_key,
			'group_label' => $group_label,
		);
	}

	private function detect_entity_label( $normalized_sql, $table_hint ) {
		$table_map = array(
			'wp_posts' => __( 'WordPress content query', 'shift8-dbcache' ),
			'wp_postmeta' => __( 'WordPress content metadata query', 'shift8-dbcache' ),
			'wp_options' => __( 'WordPress option query', 'shift8-dbcache' ),
			'wp_users' => __( 'WordPress user query', 'shift8-dbcache' ),
			'wp_usermeta' => __( 'WordPress user metadata query', 'shift8-dbcache' ),
			'woocommerce_sessions' => __( 'WooCommerce session query', 'shift8-dbcache' ),
			'wc_orders' => __( 'WooCommerce order query', 'shift8-dbcache' ),
			'wc_order_addresses' => __( 'WooCommerce order address query', 'shift8-dbcache' ),
			'wc_order_operational_data' => __( 'WooCommerce order operational query', 'shift8-dbcache' ),
			'wc_orders_meta' => __( 'WooCommerce order metadata query', 'shift8-dbcache' ),
		);

		if ( false !== strpos( $normalized_sql, 'shop_order' ) || false !== strpos( $normalized_sql, 'wc_orders' ) ) {
			return __( 'WooCommerce order query', 'shift8-dbcache' );
		}

		if ( false !== strpos( $normalized_sql, 'user_email' ) || false !== strpos( $normalized_sql, 'user_login' ) ) {
			return __( 'WordPress user query', 'shift8-dbcache' );
		}

		if ( isset( $table_map[ $table_hint ] ) ) {
			return $table_map[ $table_hint ];
		}

		if ( '' !== $table_hint ) {
			return sprintf( __( '%s query', 'shift8-dbcache' ), $table_hint );
		}

		return __( 'Database query', 'shift8-dbcache' );
	}

	private function detect_query_action( $normalized_sql ) {
		if ( preg_match( '/\bwhere\b.+\b(id|post_id|order_id|user_id|comment_id|meta_id)\s*=\s*(\?|\'\?\')/i', $normalized_sql ) ) {
			return __( 'lookup by ID', 'shift8-dbcache' );
		}

		if ( false !== strpos( $normalized_sql, 'meta_key' ) ) {
			return __( 'metadata lookup', 'shift8-dbcache' );
		}

		if ( false !== strpos( $normalized_sql, 'option_name' ) ) {
			return __( 'lookup by option name', 'shift8-dbcache' );
		}

		if ( false !== strpos( $normalized_sql, 'post_name' ) || false !== strpos( $normalized_sql, 'post_title' ) ) {
			return __( 'content lookup', 'shift8-dbcache' );
		}

		if ( false !== strpos( $normalized_sql, 'join ' ) ) {
			return __( 'joined lookup', 'shift8-dbcache' );
		}

		return __( 'read query', 'shift8-dbcache' );
	}

	private function build_rule_hint( $action, $entity ) {
		if ( __( 'lookup by ID', 'shift8-dbcache' ) === $action ) {
			return sprintf( __( 'Cache similar %s requests while wildcarding the unique ID.', 'shift8-dbcache' ), strtolower( $entity ) );
		}

		if ( __( 'metadata lookup', 'shift8-dbcache' ) === $action ) {
			return __( 'Cache this repeated metadata pattern only if the underlying metadata is stable enough.', 'shift8-dbcache' );
		}

		if ( __( 'lookup by option name', 'shift8-dbcache' ) === $action ) {
			return __( 'Cache this named option lookup only if it rarely changes and is safe to share from Redis.', 'shift8-dbcache' );
		}

		return __( 'Review the normalized pattern and only create a rule if this query shape repeats and can be served safely from Redis.', 'shift8-dbcache' );
	}

	private function get_component_label( $component ) {
		$labels = array(
			'admin' => __( 'admin area', 'shift8-dbcache' ),
			'woocommerce' => __( 'WooCommerce frontend', 'shift8-dbcache' ),
			'frontend' => __( 'frontend', 'shift8-dbcache' ),
		);

		return isset( $labels[ $component ] ) ? $labels[ $component ] : __( 'site runtime', 'shift8-dbcache' );
	}

	public function normalize_sql( $sql ) {
		$sql = preg_replace( '/\s+/', ' ', trim( $sql ) );
		$sql = preg_replace( "/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "'?'", $sql );
		$sql = preg_replace( '/\b\d+\b/', '?', $sql );
		if ( class_exists( 'Shift8_DBCache_Runtime' ) ) {
			return Shift8_DBCache_Runtime::normalize_sql( $sql );
		}

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