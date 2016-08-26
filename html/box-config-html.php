<style type="text/css">
.box_config td, .box_config th {
	vertical-align: middle;
	padding: 4px 8px;
}
.box_config td input {
	margin-right: 4px;
}
.box_config .check-column {
	vertical-align: middle;
	text-align: left;
	padding: 0 8px;
}
</style>
<tr valign="top" id="packing_options">
	<td class="forminp" colspan="2" style="padding-left:0px">
		<table class="box_config widefat">
			<thead>
				<tr>
					<?php
						$dim_uom = get_option( 'woocommerce_dimension_unit' );
						$weight_uom = get_option( 'woocommerce_weight_unit' );
					?>
					<th class="check-column"><input style="margin:1px 0 0 -1px !important" type="checkbox" /></th>
					<th>Label</th>
					<th>Total Length (<?php echo $dim_uom; ?>)</th>
					<th>Total Width (<?php echo $dim_uom; ?>)</th>
					<th>Total Height (<?php echo $dim_uom; ?>)</th>
					<th>Inner Length (<?php echo $dim_uom; ?>)</th>
					<th>Inner Width (<?php echo $dim_uom; ?>)</th>
					<th>Inner Height (<?php echo $dim_uom; ?>)</th>
					<th>Packing Weight (<?php echo $weight_uom; ?>) &nbsp; <img class="help_tip" style="float:none;" data-tip="<?php _e( 'Packing Weight = (Weight of Empty Box) + (Weight of Packing Materials)', 'wc_shiptime' ); ?>" src="<?php echo WC()->plugin_url();?>/assets/images/help.png" height="16" width="16" />
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="8">
						<a href="#" class="button plus insert">Add Box Configuration</a>
					</th>
					<th colspan="1">
						<a href="#" class="button minus remove">Remove Selected</a>
					</th>
				</tr>
			</tfoot>
			<tbody id="rates">
				<?php
				if ( $this->boxes ) {
					foreach ( $this->boxes as $id => $box ) {
						?>
						<tr>
							<td class="check-column"><input type="checkbox" /></td>
							<td><input type="text" size="10" name="box_label[<?php echo $id; ?>]" value="<?php echo isset( $box['label'] ) ? esc_attr( $box['label'] ) : ''; ?>" /></td>
							<td><input type="text" size="5" name="box_outer_length[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['outer_length'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_outer_width[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['outer_width'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_outer_height[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['outer_height'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_inner_length[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['inner_length'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_inner_width[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['inner_width'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_inner_height[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['inner_height'] ); ?>" /></td>
							<td><input type="text" size="5" name="box_weight[<?php echo $id; ?>]" value="<?php echo esc_attr( $box['weight'] ); ?>" /></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
		
		<script type="text/javascript">

			jQuery(window).load(function(){

				jQuery('.box_config .insert').click( function() {
					var $tbody = jQuery('.box_config').find('tbody');
					var size = $tbody.find('tr').size();
					var code = '<tr class="new">\
					<td class="check-column"><input type="checkbox" /></td>\
					<td><input type="text" size="10" name="box_label[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_outer_length[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_outer_width[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_outer_height[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_inner_length[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_inner_width[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_inner_height[' + size + ']" /></td>\
					<td><input type="text" size="5" name="box_weight[' + size + ']" />lbs</td>\
					</tr>';

					$tbody.append( code );

					return false;
				} );

				jQuery('.box_config .remove').click(function() {
					var $tbody = jQuery('.box_config').find('tbody');

					$tbody.find('.check-column input:checked').each(function() {
						jQuery(this).closest('tr').hide().find('input').val('');
					});

					return false;
				});

			});

		</script>

	</td>
</tr>