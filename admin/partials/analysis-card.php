<?php
$analysis_data = isset( $analysis_data ) && is_array( $analysis_data ) ? $analysis_data : array( 'rows' => array(), 'pagination' => array( 'total_pages' => 1 ) );
$rule_indexes = isset( $rule_indexes ) && is_array( $rule_indexes ) ? $rule_indexes : array( 'exact' => array(), 'groups' => array() );
$settings = isset( $settings ) && is_array( $settings ) ? $settings : array( 'default_rule_ttl' => 300 );
$state = isset( $state ) && is_array( $state ) ? $state : array( 'updated_at' => 0, 'last_capture' => array() );
$filters = isset( $filters ) && is_array( $filters ) ? $filters : array();
$rows = $analysis_data['rows'];
$pagination = $analysis_data['pagination'];
?>
<div class="card shift8-dbcache-card">
	<h2 class="title"><?php esc_html_e( 'Latest analysis', 'shift8-dbcache' ); ?></h2>
	<p><?php esc_html_e( 'This view translates captured SQL into readable patterns so you can decide whether a repeated query shape deserves its own Redis rule.', 'shift8-dbcache' ); ?></p>
	<p><?php echo esc_html( sprintf( __( 'Updated %1$s. Last request: %2$s', 'shift8-dbcache' ), $state['updated_at'] ? gmdate( 'Y-m-d H:i:s', (int) $state['updated_at'] ) . ' UTC' : __( 'never', 'shift8-dbcache' ), ! empty( $state['last_capture']['request_uri'] ) ? $state['last_capture']['request_uri'] : __( 'n/a', 'shift8-dbcache' ) ) ); ?></p>
	<?php $this->render_analysis_filters( $filters ); ?>
	<table class="widefat striped shift8-dbcache-analysis-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Readable insight', 'shift8-dbcache' ); ?></th>
				<th><?php esc_html_e( 'Impact', 'shift8-dbcache' ); ?></th>
				<th><?php esc_html_e( 'Where it happened', 'shift8-dbcache' ); ?></th>
				<th><?php esc_html_e( 'Caching guidance', 'shift8-dbcache' ); ?></th>
				<th><?php esc_html_e( 'Create rule', 'shift8-dbcache' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No captured queries yet.', 'shift8-dbcache' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$exact_rule = isset( $rule_indexes['exact'][ $row['fingerprint'] ] ) ? $rule_indexes['exact'][ $row['fingerprint'] ] : null;
					$group_rule = isset( $rule_indexes['groups'][ $row['group_key'] ] ) ? $rule_indexes['groups'][ $row['group_key'] ] : null;
					$form_id = 'shift8-dbcache-create-' . md5( $row['fingerprint'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $row['insight_label'] ); ?></strong>
							<p><?php echo esc_html( $row['insight_summary'] ); ?></p>
							<details>
								<summary><?php esc_html_e( 'Technical details', 'shift8-dbcache' ); ?></summary>
								<p><code><?php echo esc_html( $row['fingerprint'] ); ?></code></p>
								<p><code><?php echo esc_html( $row['sql_example'] ); ?></code></p>
								<p><?php echo esc_html( $row['technical_summary'] ); ?></p>
							</details>
						</td>
						<td>
							<strong><?php echo esc_html( sprintf( __( '%1$s similar queries took %2$s seconds total.', 'shift8-dbcache' ), (int) $row['count'], number_format_i18n( (float) $row['total_time'], 4 ) ) ); ?></strong>
							<p><?php echo esc_html( sprintf( __( 'Average %1$s seconds each. Slowest %2$s seconds.', 'shift8-dbcache' ), number_format_i18n( (float) $row['avg_time'], 4 ), number_format_i18n( (float) $row['max_time'], 4 ) ) ); ?></p>
						</td>
						<td>
							<p><strong><?php esc_html_e( 'Component:', 'shift8-dbcache' ); ?></strong> <?php echo esc_html( $row['component'] ); ?></p>
							<p><strong><?php esc_html_e( 'Last request:', 'shift8-dbcache' ); ?></strong> <?php echo esc_html( $row['last_request_uri'] ); ?></p>
						</td>
						<td>
							<p><?php echo esc_html( $row['rule_hint'] ); ?></p>
							<?php if ( $group_rule ) : ?><p class="shift8-dbcache-existing-rule"><?php esc_html_e( 'A query family rule already exists for this pattern.', 'shift8-dbcache' ); ?></p><?php endif; ?>
							<?php if ( $exact_rule ) : ?><p class="shift8-dbcache-existing-rule"><?php esc_html_e( 'An exact rule already exists for this normalized SQL pattern.', 'shift8-dbcache' ); ?></p><?php endif; ?>
						</td>
						<td>
							<form id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="shift8-dbcache-create-rule-form">
								<?php wp_nonce_field( 'shift8_dbcache_save_rule' ); ?>
								<input type="hidden" name="action" value="shift8_dbcache_save_rule" />
								<input type="hidden" name="enabled" value="1" />
								<input type="hidden" name="fingerprint" value="<?php echo esc_attr( $row['fingerprint'] ); ?>" />
								<input type="hidden" name="exact_target_value" value="<?php echo esc_attr( $row['fingerprint'] ); ?>" />
								<input type="hidden" name="group_target_value" value="<?php echo esc_attr( $row['group_key'] ); ?>" />
								<input type="hidden" name="exact_label" value="<?php echo esc_attr( $row['insight_label'] . ' ' . __( '(exact pattern)', 'shift8-dbcache' ) ); ?>" />
								<input type="hidden" name="group_label" value="<?php echo esc_attr( $row['group_label'] . ' ' . __( '(query family)', 'shift8-dbcache' ) ); ?>" />
								<label><?php esc_html_e( 'Scope', 'shift8-dbcache' ); ?> <select name="match_type"><option value="exact_fingerprint"><?php esc_html_e( 'Exact pattern', 'shift8-dbcache' ); ?></option><option value="insight_group"><?php esc_html_e( 'Query family', 'shift8-dbcache' ); ?></option></select></label>
								<label><?php esc_html_e( 'TTL', 'shift8-dbcache' ); ?> <input type="number" min="30" name="ttl" value="<?php echo esc_attr( $settings['default_rule_ttl'] ); ?>" class="small-text" /></label>
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Create rule', 'shift8-dbcache' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<?php $this->render_analysis_pagination( $pagination, $filters ); ?>
</div>