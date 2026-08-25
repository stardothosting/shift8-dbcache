<?php
/**
 * Admin interface.
 *
 * @package Shift8\DBCache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shift8_DBCache_Admin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_shift8_dbcache_save_rule', array( $this, 'handle_save_rule' ) );
		add_action( 'admin_post_shift8_dbcache_delete_rule', array( $this, 'handle_delete_rule' ) );
		add_action( 'admin_post_shift8_dbcache_test_redis_connection', array( $this, 'handle_test_redis_connection' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'shift8-dbcache' ) ) {
			return;
		}

		wp_enqueue_style(
			'shift8-dbcache-admin',
			SHIFT8_DBCACHE_URL . 'admin/css/admin.css',
			array(),
			SHIFT8_DBCACHE_VERSION
		);
	}

	public function register_menu() {
		if ( empty( $GLOBALS['admin_page_hooks']['shift8-settings'] ) ) {
			add_menu_page(
				esc_html__( 'Shift8 Settings', 'shift8-dbcache' ),
				esc_html__( 'Shift8', 'shift8-dbcache' ),
				'manage_options',
				'shift8-settings',
				array( $this, 'render_shift8_home' ),
				$this->get_icon_svg()
			);
		}

		add_submenu_page(
			'shift8-settings',
			esc_html__( 'Shift8 DB Cache', 'shift8-dbcache' ),
			esc_html__( 'DB Cache', 'shift8-dbcache' ),
			'manage_options',
			'shift8-dbcache',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'shift8_dbcache_settings',
			'shift8_dbcache_settings',
			array(
				'sanitize_callback' => array( 'Shift8_DBCache_Settings', 'sanitize_settings' ),
				'default' => Shift8_DBCache_Settings::get_default_settings(),
			)
		);
	}

	public function render_shift8_home() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shift8 Settings', 'shift8-dbcache' ); ?></h1>
			<p><?php esc_html_e( 'Use the Shift8 menu to configure individual plugins.', 'shift8-dbcache' ); ?></p>
			<div class="card">
				<h2><?php esc_html_e( 'Available Plugins', 'shift8-dbcache' ); ?></h2>
				<ul>
					<li><strong><?php esc_html_e( 'DB Cache', 'shift8-dbcache' ); ?></strong> - <?php esc_html_e( 'Analysis-first precision database cache rules.', 'shift8-dbcache' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'shift8-dbcache' ) );
		}

		$settings = Shift8_DBCache_Settings::get_settings();
		$rules = Shift8_DBCache_Rules::get_rules();
		$rule_stats = $this->get_rule_stats_map( $rules );
		$rule_indexes = $this->get_rule_indexes( $rules );
		$state = get_option( 'shift8_dbcache_analysis', Shift8_DBCache_Collector::get_default_state() );
		$rows = Shift8_DBCache_Collector::get_instance()->get_report_rows();
		$analysis_filters = $this->get_analysis_filters();
		$filtered_rows = $this->filter_analysis_rows( $rows, $analysis_filters );
		$paginated_analysis = $this->paginate_analysis_rows( $filtered_rows, $analysis_filters['page'], $analysis_filters['per_page'] );
		$capture_active = '1' === (string) get_transient( 'shift8_dbcache_capture_active' );
		$dropin_status = Shift8_DBCache_Dropin::get_status();
		$redis_status = Shift8_DBCache_Redis_Client::get_status();
		$redis_status = is_array( $redis_status ) ? $redis_status : array(
			'enabled' => false,
			'loaded' => false,
			'configured' => false,
			'connected' => false,
			'message' => '',
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shift8 DB Cache', 'shift8-dbcache' ); ?></h1>
			<p><?php esc_html_e( 'Collect query evidence first, then decide what is worth caching.', 'shift8-dbcache' ); ?></p>

			<?php $this->render_runtime_notices( $settings, $dropin_status, $redis_status ); ?>

			<?php $this->render_action_notices(); ?>

			<div class="shift8-dbcache-admin-container">
				<div class="shift8-dbcache-main-content">
					<?php $this->render_capture_controls( $capture_active ); ?>
					<?php $this->render_settings_form( $settings, $redis_status ); ?>
					<?php $this->render_rules_section( $rules, $rule_stats ); ?>
					<?php $this->render_analysis_section( $paginated_analysis, $rule_indexes, $settings, $state, $analysis_filters ); ?>
				</div>
				<div class="shift8-dbcache-sidebar">
					<?php $this->render_status_sidebar( $redis_status, $dropin_status, $rules, $filtered_rows ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_action_notices() {
		if ( isset( $_GET['updated'] ) ) {
			?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Capture state updated.', 'shift8-dbcache' ); ?></p></div><?php
		}

		if ( isset( $_GET['rule-updated'] ) ) {
			?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache rule saved.', 'shift8-dbcache' ); ?></p></div><?php
		}

		if ( isset( $_GET['rule-deleted'] ) ) {
			?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache rule deleted.', 'shift8-dbcache' ); ?></p></div><?php
		}

		if ( isset( $_GET['redis-tested'] ) ) {
			$result = get_transient( $this->get_redis_test_transient_key() );
			delete_transient( $this->get_redis_test_transient_key() );
			if ( is_array( $result ) && ! empty( $result['message'] ) ) {
				$class = ! empty( $result['success'] ) ? 'notice notice-success is-dismissible' : 'notice notice-warning is-dismissible';
				?><div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $result['message'] ); ?></p></div><?php
			}
		}
	}

	private function render_capture_controls( $capture_active ) {
		$this->render_partial( 'capture-controls', array( 'capture_active' => $capture_active ) );
	}

	private function render_settings_form( array $settings, array $redis_status ) {
		$this->render_partial( 'settings-card', compact( 'settings', 'redis_status' ) );
	}

	private function render_rules_section( array $rules, array $rule_stats ) {
		$this->render_partial( 'rules-card', compact( 'rules', 'rule_stats' ) );
	}

	private function render_analysis_section( array $analysis_data, array $rule_indexes, array $settings, array $state, array $filters ) {
		$this->render_partial( 'analysis-card', compact( 'analysis_data', 'rule_indexes', 'settings', 'state', 'filters' ) );
	}

	private function render_partial( $partial, array $vars = array() ) {
		$file = SHIFT8_DBCACHE_PATH . 'admin/partials/' . $partial . '.php';
		if ( ! file_exists( $file ) ) {
			return;
		}

		extract( $vars, EXTR_SKIP );
		include $file;
	}

	private function render_analysis_filters( array $filters ) {
		?>
		<form method="get" class="shift8-dbcache-analysis-filters">
			<input type="hidden" name="page" value="shift8-dbcache" />
			<label><?php esc_html_e( 'Minimum total time', 'shift8-dbcache' ); ?> <input type="number" step="0.001" min="0" name="min_total_time" value="<?php echo esc_attr( $filters['min_total_time'] ); ?>" class="small-text" /></label>
			<label><?php esc_html_e( 'Minimum occurrences', 'shift8-dbcache' ); ?> <input type="number" step="1" min="1" name="min_count" value="<?php echo esc_attr( $filters['min_count'] ); ?>" class="small-text" /></label>
			<label><?php esc_html_e( 'Component', 'shift8-dbcache' ); ?> <select name="component"><option value=""><?php esc_html_e( 'All', 'shift8-dbcache' ); ?></option><option value="frontend"<?php selected( $filters['component'], 'frontend' ); ?>><?php esc_html_e( 'Frontend', 'shift8-dbcache' ); ?></option><option value="woocommerce"<?php selected( $filters['component'], 'woocommerce' ); ?>><?php esc_html_e( 'WooCommerce', 'shift8-dbcache' ); ?></option><option value="admin"<?php selected( $filters['component'], 'admin' ); ?>><?php esc_html_e( 'Admin', 'shift8-dbcache' ); ?></option></select></label>
			<label><?php esc_html_e( 'Sort by', 'shift8-dbcache' ); ?> <select name="sort"><option value="total_time"<?php selected( $filters['sort'], 'total_time' ); ?>><?php esc_html_e( 'Total time', 'shift8-dbcache' ); ?></option><option value="max_time"<?php selected( $filters['sort'], 'max_time' ); ?>><?php esc_html_e( 'Slowest query', 'shift8-dbcache' ); ?></option><option value="avg_time"<?php selected( $filters['sort'], 'avg_time' ); ?>><?php esc_html_e( 'Average time', 'shift8-dbcache' ); ?></option><option value="count"<?php selected( $filters['sort'], 'count' ); ?>><?php esc_html_e( 'Occurrences', 'shift8-dbcache' ); ?></option></select></label>
			<label><?php esc_html_e( 'Per page', 'shift8-dbcache' ); ?> <select name="per_page"><option value="10"<?php selected( $filters['per_page'], 10 ); ?>>10</option><option value="20"<?php selected( $filters['per_page'], 20 ); ?>>20</option><option value="50"<?php selected( $filters['per_page'], 50 ); ?>>50</option></select></label>
			<?php submit_button( __( 'Apply filters', 'shift8-dbcache' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}

	private function render_analysis_pagination( array $pagination, array $filters ) {
		if ( $pagination['total_pages'] <= 1 ) {
			return;
		}

		echo '<div class="shift8-dbcache-pagination">';
		for ( $page = 1; $page <= $pagination['total_pages']; $page++ ) {
			$url = add_query_arg(
				array(
					'page' => 'shift8-dbcache',
					'paged' => $page,
					'min_total_time' => $filters['min_total_time'],
					'min_count' => $filters['min_count'],
					'component' => $filters['component'],
					'sort' => $filters['sort'],
					'per_page' => $filters['per_page'],
				),
				admin_url( 'admin.php' )
			);

			printf(
				'<a class="button %1$s" href="%2$s">%3$s</a>',
				$page === $pagination['current_page'] ? 'button-primary' : 'button-secondary',
				esc_url( $url ),
				esc_html( (string) $page )
			);
		}
		echo '</div>';
	}

	private function get_analysis_filters() {
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'total_time';
		$allowed_sort = array( 'total_time', 'max_time', 'avg_time', 'count' );

		return array(
			'min_total_time' => isset( $_GET['min_total_time'] ) ? max( 0, (float) wp_unslash( $_GET['min_total_time'] ) ) : 0,
			'min_count' => isset( $_GET['min_count'] ) ? max( 1, absint( wp_unslash( $_GET['min_count'] ) ) ) : 1,
			'component' => isset( $_GET['component'] ) ? sanitize_key( wp_unslash( $_GET['component'] ) ) : '',
			'sort' => in_array( $sort, $allowed_sort, true ) ? $sort : 'total_time',
			'page' => isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1,
			'per_page' => isset( $_GET['per_page'] ) ? min( 50, max( 10, absint( wp_unslash( $_GET['per_page'] ) ) ) ) : 20,
		);
	}

	private function filter_analysis_rows( array $rows, array $filters ) {
		$filtered = array_values(
			array_filter(
				$rows,
				function( $row ) use ( $filters ) {
					if ( (float) $row['total_time'] < $filters['min_total_time'] ) {
						return false;
					}

					if ( (int) $row['count'] < $filters['min_count'] ) {
						return false;
					}

					if ( '' !== $filters['component'] && $filters['component'] !== $row['component'] ) {
						return false;
					}

					return true;
				}
			)
		);

		usort(
			$filtered,
			function( $left, $right ) use ( $filters ) {
				$field = $filters['sort'];
				$left_value = isset( $left[ $field ] ) ? $left[ $field ] : 0;
				$right_value = isset( $right[ $field ] ) ? $right[ $field ] : 0;

				if ( $left_value === $right_value ) {
					return $right['count'] <=> $left['count'];
				}

				return $right_value <=> $left_value;
			}
		);

		return $filtered;
	}

	private function paginate_analysis_rows( array $rows, $page, $per_page ) {
		$total = count( $rows );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page = min( $page, $total_pages );

		return array(
			'rows' => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
			'pagination' => array(
				'current_page' => $page,
				'per_page' => $per_page,
				'total_rows' => $total,
				'total_pages' => $total_pages,
			),
		);
	}

	private function render_status_sidebar( array $redis_status, array $dropin_status, array $rules, array $rows ) {
		$this->render_partial( 'status-sidebar', compact( 'redis_status', 'dropin_status', 'rules', 'rows' ) );
	}

	public function handle_save_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage cache rules.', 'shift8-dbcache' ) );
		}

		check_admin_referer( 'shift8_dbcache_save_rule' );

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : '';
		$match_type = isset( $_POST['match_type'] ) ? sanitize_key( wp_unslash( $_POST['match_type'] ) ) : Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT;
		$target_value = isset( $_POST['target_value'] ) ? sanitize_text_field( wp_unslash( $_POST['target_value'] ) ) : '';
		$fingerprint = isset( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$exact_target_value = isset( $_POST['exact_target_value'] ) ? sanitize_text_field( wp_unslash( $_POST['exact_target_value'] ) ) : '';
		$group_target_value = isset( $_POST['group_target_value'] ) ? sanitize_text_field( wp_unslash( $_POST['group_target_value'] ) ) : '';
		$exact_label = isset( $_POST['exact_label'] ) ? sanitize_text_field( wp_unslash( $_POST['exact_label'] ) ) : '';
		$group_label = isset( $_POST['group_label'] ) ? sanitize_text_field( wp_unslash( $_POST['group_label'] ) ) : '';
		$ttl = isset( $_POST['ttl'] ) ? absint( wp_unslash( $_POST['ttl'] ) ) : Shift8_DBCache_Settings::get_settings()['default_rule_ttl'];
		$enabled = isset( $_POST['enabled'] ) ? '1' : '0';

		if ( Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $match_type && '' === $target_value && '' !== $group_target_value ) {
			$target_value = $group_target_value;
		}

		if ( Shift8_DBCache_Runtime::RULE_TYPE_FINGERPRINT === $match_type && '' === $target_value && '' !== $exact_target_value ) {
			$target_value = $exact_target_value;
		}

		if ( '' === $target_value && '' !== $fingerprint ) {
			$target_value = $fingerprint;
		}

		if ( '' === $label ) {
			$label = Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $match_type ? $group_label : $exact_label;
		}

		if ( '' === $target_value ) {
			wp_die( esc_html__( 'A rule target is required to save a cache rule.', 'shift8-dbcache' ) );
		}

		$payload = array(
			'match_type' => $match_type,
			'target_value' => $target_value,
			'fingerprint' => $fingerprint,
			'label' => $label,
			'ttl' => $ttl,
			'enabled' => $enabled,
		);

		if ( '' !== $rule_id ) {
			Shift8_DBCache_Rules::update_rule( $rule_id, $payload );
		} else {
			Shift8_DBCache_Rules::add_rule( $payload );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'shift8-dbcache', 'rule-updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_rule() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage cache rules.', 'shift8-dbcache' ) );
		}

		check_admin_referer( 'shift8_dbcache_delete_rule' );

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( $_POST['rule_id'] ) ) : '';
		if ( '' !== $rule_id ) {
			Shift8_DBCache_Rules::delete_rule( $rule_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'shift8-dbcache', 'rule-deleted' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_test_redis_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to test Redis connectivity.', 'shift8-dbcache' ) );
		}

		check_admin_referer( 'shift8_dbcache_test_redis_connection', 'shift8_dbcache_test_nonce' );

		$posted_settings = isset( $_POST['shift8_dbcache_settings'] ) ? Shift8_DBCache_Settings::sanitize_settings( wp_unslash( $_POST['shift8_dbcache_settings'] ) ) : Shift8_DBCache_Settings::get_settings();
		$config = array(
			'enabled' => '1' === $posted_settings['redis_enabled'],
			'host' => trim( (string) $posted_settings['redis_host'] ),
			'port' => (int) $posted_settings['redis_port'],
			'database' => (int) $posted_settings['redis_database'],
			'prefix' => (string) $posted_settings['redis_prefix'],
			'password' => Shift8_DBCache_Settings::get_redis_config()['password'],
			'timeout' => defined( 'SHIFT8_DBCACHE_REDIS_TIMEOUT' ) ? (float) SHIFT8_DBCACHE_REDIS_TIMEOUT : 1.0,
		);

		Shift8_DBCache_Redis_Client::set_runtime_config( $config );
		$status = Shift8_DBCache_Redis_Client::get_status();
		Shift8_DBCache_Redis_Client::clear_runtime_config();
		$status = is_array( $status ) ? $status : array(
			'connected' => false,
			'message' => __( 'Unknown Redis status response.', 'shift8-dbcache' ),
		);

		set_transient(
			$this->get_redis_test_transient_key(),
			array(
				'success' => ! empty( $status['connected'] ),
				'message' => ! empty( $status['connected'] )
					? __( 'Redis connection test succeeded with the current form values.', 'shift8-dbcache' )
					: sprintf( __( 'Redis connection test failed: %s', 'shift8-dbcache' ), $status['message'] ),
			),
			60
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'shift8-dbcache', 'redis-tested' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function render_runtime_notices( array $settings, array $dropin_status, array $redis_status ) {
		if ( $dropin_status['conflict'] ) {
			?>
			<div class="notice notice-error"><p><?php esc_html_e( 'An existing db.php drop-in is already present. Shift8 DB Cache will not override another database drop-in.', 'shift8-dbcache' ); ?></p></div>
			<?php
			return;
		}

		if ( '1' !== $settings['redis_enabled'] ) {
			?>
			<div class="notice notice-info"><p><?php esc_html_e( 'Active caching is disabled. Capture and rule authoring remain available.', 'shift8-dbcache' ); ?></p></div>
			<?php
			return;
		}

		if ( ! $redis_status['loaded'] ) {
			?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The phpredis extension is not loaded, so Redis-backed caching is unavailable.', 'shift8-dbcache' ); ?></p></div>
			<?php
			return;
		}

		if ( empty( $settings['redis_host'] ) ) {
			?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Set a Redis host before enabling active caching. Remote Redis servers are supported through the Redis host field.', 'shift8-dbcache' ); ?></p></div>
			<?php
			return;
		}

		if ( ! $dropin_status['exists'] ) {
			?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'The Shift8 db.php drop-in is not installed yet, so cache rules will not execute until it is available.', 'shift8-dbcache' ); ?></p></div>
			<?php
		}

		if ( ! $redis_status['connected'] ) {
			?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Redis is configured but not reachable right now. The plugin will safely fall back to uncached reads.', 'shift8-dbcache' ); ?></p></div>
			<?php
			return;
		}

		?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Redis-backed active caching is enabled and the connection check succeeded.', 'shift8-dbcache' ); ?></p></div>
		<?php
	}

	private function get_rule_indexes( array $rules ) {
		$exact = array();
		$groups = array();

		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['target_value'] ) ) {
				if ( Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $rule['match_type'] ) {
					$groups[ $rule['target_value'] ] = $rule;
				} else {
					$exact[ $rule['target_value'] ] = $rule;
				}
			}
		}

		return array(
			'exact' => $exact,
			'groups' => $groups,
		);
	}

	private function get_rule_stats_map( array $rules ) {
		$stats_map = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['id'] ) ) {
				continue;
			}

			$stats_map[ $rule['id'] ] = Shift8_DBCache_Redis_Client::get_rule_stats( $rule['id'] );
		}

		return $stats_map;
	}

	private function get_redis_test_transient_key() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		return 'shift8_dbcache_redis_test_' . $user_id;
	}

	private function get_icon_svg() {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><text x="10" y="14" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold">S8</text></svg>' );
	}
}