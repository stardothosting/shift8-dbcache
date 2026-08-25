<?php
$redis_status = isset( $redis_status ) && is_array( $redis_status ) ? $redis_status : array();
$dropin_status = isset( $dropin_status ) && is_array( $dropin_status ) ? $dropin_status : array();
$rules = isset( $rules ) && is_array( $rules ) ? $rules : array();
$rows = isset( $rows ) && is_array( $rows ) ? $rows : array();
?>
<div class="card shift8-dbcache-card shift8-dbcache-status-card">
	<h3><?php esc_html_e( 'Runtime status', 'shift8-dbcache' ); ?></h3>
	<table class="widefat striped">
		<tbody>
			<tr><td><?php esc_html_e( 'Redis extension', 'shift8-dbcache' ); ?></td><td><?php echo esc_html( ! empty( $redis_status['loaded'] ) ? __( 'Loaded', 'shift8-dbcache' ) : __( 'Missing', 'shift8-dbcache' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Redis connection', 'shift8-dbcache' ); ?></td><td><?php echo esc_html( ! empty( $redis_status['connected'] ) ? __( 'Reachable', 'shift8-dbcache' ) : __( 'Unavailable', 'shift8-dbcache' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'db.php drop-in', 'shift8-dbcache' ); ?></td><td><?php echo esc_html( ! empty( $dropin_status['exists'] ) ? __( 'Installed', 'shift8-dbcache' ) : __( 'Not installed', 'shift8-dbcache' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Rule count', 'shift8-dbcache' ); ?></td><td><?php echo esc_html( (string) count( $rules ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Filtered patterns', 'shift8-dbcache' ); ?></td><td><?php echo esc_html( (string) count( $rows ) ); ?></td></tr>
		</tbody>
	</table>
</div>