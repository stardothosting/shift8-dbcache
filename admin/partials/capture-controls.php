<?php $capture_active = isset( $capture_active ) ? (bool) $capture_active : false; ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="shift8-dbcache-capture-controls">
	<?php wp_nonce_field( 'shift8_dbcache_capture_toggle' ); ?>
	<input type="hidden" name="action" value="shift8_dbcache_toggle_capture" />
	<button type="submit" name="dbcache_action" value="<?php echo esc_attr( $capture_active ? 'stop' : 'start' ); ?>" class="button button-primary">
		<?php echo esc_html( $capture_active ? __( 'Stop capture', 'shift8-dbcache' ) : __( 'Start capture', 'shift8-dbcache' ) ); ?>
	</button>
	<span class="shift8-dbcache-capture-status">
		<?php echo esc_html( $capture_active ? __( 'Capture is active.', 'shift8-dbcache' ) : __( 'Capture is inactive.', 'shift8-dbcache' ) ); ?>
	</span>
</form>