<?php $rule_stats = isset( $rule_stats ) && is_array( $rule_stats ) ? $rule_stats : array(); ?>
<div class="card shift8-dbcache-card">
	<h2 class="title"><?php esc_html_e( 'Active cache rules', 'shift8-dbcache' ); ?></h2>
	<?php if ( empty( $rules ) ) : ?>
		<p><?php esc_html_e( 'No cache rules defined yet. Create rules from the captured fingerprints below.', 'shift8-dbcache' ); ?></p>
	<?php else : ?>
		<table class="widefat striped shift8-dbcache-rules-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rule', 'shift8-dbcache' ); ?></th>
					<th><?php esc_html_e( 'Scope', 'shift8-dbcache' ); ?></th>
					<th><?php esc_html_e( 'Stats', 'shift8-dbcache' ); ?></th>
					<th><?php esc_html_e( 'TTL', 'shift8-dbcache' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'shift8-dbcache' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'shift8-dbcache' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rules as $rule ) : ?>
					<?php $form_id = 'shift8-dbcache-rule-' . esc_attr( $rule['id'] ); ?>
					<?php $stats = isset( $rule_stats[ $rule['id'] ] ) ? $rule_stats[ $rule['id'] ] : array( 'hits' => 0, 'misses' => 0, 'writes' => 0, 'last_hit_at' => 0, 'last_write_at' => 0, 'expires_at' => 0 ); ?>
					<tr>
						<td>
							<strong><?php echo esc_html( ! empty( $rule['label'] ) ? $rule['label'] : $rule['target_value'] ); ?></strong>
							<p><code><?php echo esc_html( $rule['target_value'] ); ?></code></p>
						</td>
						<td><?php echo esc_html( Shift8_DBCache_Runtime::RULE_TYPE_GROUP === $rule['match_type'] ? __( 'Query family', 'shift8-dbcache' ) : __( 'Exact pattern', 'shift8-dbcache' ) ); ?></td>
						<td>
							<p><strong><?php esc_html_e( 'Hits', 'shift8-dbcache' ); ?>:</strong> <?php echo esc_html( (string) $stats['hits'] ); ?></p>
							<p><strong><?php esc_html_e( 'Misses', 'shift8-dbcache' ); ?>:</strong> <?php echo esc_html( (string) $stats['misses'] ); ?></p>
							<p><strong><?php esc_html_e( 'Writes', 'shift8-dbcache' ); ?>:</strong> <?php echo esc_html( (string) $stats['writes'] ); ?></p>
							<p><strong><?php esc_html_e( 'Expires', 'shift8-dbcache' ); ?>:</strong> <?php echo esc_html( ! empty( $stats['expires_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $stats['expires_at'] ) . ' UTC' : __( 'n/a', 'shift8-dbcache' ) ); ?></p>
						</td>
						<td><input form="<?php echo esc_attr( $form_id ); ?>" type="number" min="30" name="ttl" value="<?php echo esc_attr( $rule['ttl'] ); ?>" class="small-text" /></td>
						<td><label><input form="<?php echo esc_attr( $form_id ); ?>" type="checkbox" name="enabled" value="1"<?php echo '1' === $rule['enabled'] ? ' checked="checked"' : ''; ?> /> <?php esc_html_e( 'Enabled', 'shift8-dbcache' ); ?></label></td>
						<td>
							<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="shift8-dbcache-inline-form">
								<?php wp_nonce_field( 'shift8_dbcache_save_rule' ); ?>
								<input type="hidden" name="action" value="shift8_dbcache_save_rule" />
								<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>" />
								<input type="hidden" name="match_type" value="<?php echo esc_attr( $rule['match_type'] ); ?>" />
								<input type="hidden" name="target_value" value="<?php echo esc_attr( $rule['target_value'] ); ?>" />
								<input type="hidden" name="label" value="<?php echo esc_attr( $rule['label'] ); ?>" />
							</form>
							<div class="shift8-dbcache-action-buttons">
								<button type="submit" form="<?php echo esc_attr( $form_id ); ?>" class="button button-secondary"><?php esc_html_e( 'Save', 'shift8-dbcache' ); ?></button>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="shift8-dbcache-inline-form">
									<?php wp_nonce_field( 'shift8_dbcache_delete_rule' ); ?>
									<input type="hidden" name="action" value="shift8_dbcache_delete_rule" />
									<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>" />
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'shift8-dbcache' ); ?></button>
								</form>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>