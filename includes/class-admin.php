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
		$state = get_option( 'shift8_dbcache_analysis', Shift8_DBCache_Collector::get_default_state() );
		$rows = Shift8_DBCache_Collector::get_instance()->get_report_rows();
		$capture_active = '1' === (string) get_transient( 'shift8_dbcache_capture_active' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shift8 DB Cache', 'shift8-dbcache' ); ?></h1>
			<p><?php esc_html_e( 'Collect query evidence first, then decide what is worth caching.', 'shift8-dbcache' ); ?></p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Capture state updated.', 'shift8-dbcache' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom: 24px;">
				<?php wp_nonce_field( 'shift8_dbcache_capture_toggle' ); ?>
				<input type="hidden" name="action" value="shift8_dbcache_toggle_capture" />
				<button type="submit" name="dbcache_action" value="<?php echo esc_attr( $capture_active ? 'stop' : 'start' ); ?>" class="button button-primary">
					<?php echo esc_html( $capture_active ? __( 'Stop capture', 'shift8-dbcache' ) : __( 'Start capture', 'shift8-dbcache' ) ); ?>
				</button>
				<span style="margin-left:12px;">
					<?php echo esc_html( $capture_active ? __( 'Capture is active.', 'shift8-dbcache' ) : __( 'Capture is inactive.', 'shift8-dbcache' ) ); ?>
				</span>
			</form>

			<form method="post" action="options.php">
				<?php settings_fields( 'shift8_dbcache_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="capture_window_minutes"><?php esc_html_e( 'Capture window (minutes)', 'shift8-dbcache' ); ?></label></th>
						<td><input name="shift8_dbcache_settings[capture_window_minutes]" id="capture_window_minutes" type="number" min="5" value="<?php echo esc_attr( $settings['capture_window_minutes'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="retention_days"><?php esc_html_e( 'Retention (days)', 'shift8-dbcache' ); ?></label></th>
						<td><input name="shift8_dbcache_settings[retention_days]" id="retention_days" type="number" min="1" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="stale_behavior"><?php esc_html_e( 'Stale behavior', 'shift8-dbcache' ); ?></label></th>
						<td>
							<select name="shift8_dbcache_settings[stale_behavior]" id="stale_behavior">
								<option value="serve_stale_then_refresh" <?php selected( $settings['stale_behavior'], 'serve_stale_then_refresh' ); ?>><?php esc_html_e( 'Serve stale once, refresh in background', 'shift8-dbcache' ); ?></option>
								<option value="expire_hard" <?php selected( $settings['stale_behavior'], 'expire_hard' ); ?>><?php esc_html_e( 'Expire hard and fetch fresh', 'shift8-dbcache' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Latest analysis', 'shift8-dbcache' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: updated timestamp, 2: request uri */
						__( 'Updated %1$s. Last request: %2$s', 'shift8-dbcache' ),
						$state['updated_at'] ? gmdate( 'Y-m-d H:i:s', (int) $state['updated_at'] ) . ' UTC' : __( 'never', 'shift8-dbcache' ),
						! empty( $state['last_capture']['request_uri'] ) ? $state['last_capture']['request_uri'] : __( 'n/a', 'shift8-dbcache' )
					)
				);
				?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'SQL fingerprint', 'shift8-dbcache' ); ?></th>
						<th><?php esc_html_e( 'Count', 'shift8-dbcache' ); ?></th>
						<th><?php esc_html_e( 'Total time', 'shift8-dbcache' ); ?></th>
						<th><?php esc_html_e( 'Max time', 'shift8-dbcache' ); ?></th>
						<th><?php esc_html_e( 'Component', 'shift8-dbcache' ); ?></th>
						<th><?php esc_html_e( 'Table hint', 'shift8-dbcache' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No captured queries yet.', 'shift8-dbcache' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( array_slice( $rows, 0, 20 ) as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row['fingerprint'] ); ?></code></td>
								<td><?php echo esc_html( (string) $row['count'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $row['total_time'], 4 ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $row['max_time'], 4 ) ); ?></td>
								<td><?php echo esc_html( $row['component'] ); ?></td>
								<td><?php echo esc_html( $row['table_hint'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function get_icon_svg() {
		return 'data:image/svg+xml;base64,' . base64_encode( '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><text x="10" y="14" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold">S8</text></svg>' );
	}
}