<style> .form-table td { padding-top: 0 !important; padding-bottom: 0 !important; }</style>
<tr valign="top" id="service_options">
	<td class="forminp" colspan="2" style="padding-left:0px">
		<table class="usps_services widefat">
			<thead>
				<th><?php _e( '&nbsp; Service', 'wf-usps-woocommerce-shipping' ); ?></th>
				<th><?php _e( 'Enabled?', 'wf-usps-woocommerce-shipping' ); ?></th>
				<th><?php echo sprintf( __( 'Markup (%s)', 'wf-usps-woocommerce-shipping' ), get_woocommerce_currency_symbol() ); ?></th>
				<th><?php _e( 'Markup (%)', 'wf-usps-woocommerce-shipping' ); ?></th>
			</thead>
			<tbody>
				<?php
					$sort = 0;
					$this->services = array(
						'FedEx First Overnight' => 'FIRST_OVERNIGHT',
				        'FedEx Priority Overnight' => 'PRIORITY_OVERNIGHT',
				        'FedEx Standard Overnight' => 'STANDARD_OVERNIGHT',
				        'FedEx Ground' => 'FEDEX_GROUND',
				        'Canpar Ground' => '1',
				        'Canpar Select' => '5',
				        'Canpar Express' => 'E',
				        'Loomis Ground' => 'DD',
				        'Loomis Express 18:00' => 'DE',
				        'Dicom Ground' => 'GRD',
				        'FedEx International Priority' => 'INTERNATIONAL_PRIORITY',
				        'FedEx International Economy' => 'INTERNATIONAL_ECONOMY',
				        'FedEx International Ground' => 'FEDEX_GROUND',
				        'DHL INTL EXPRESS WORLDWIDE' => 'P_P'
					);
					$this->ordered_services = array();

					foreach ( $this->services as $serviceName => $serviceId ) {
						?>
						<tr>
							<td>
								<input type="text" name="<?php echo $serviceName; ?>" placeholder="<?php echo $serviceName; ?>" size="35" />
							</td>
							<td>
								<ul class="sub_services" style="font-size: 0.92em; color: #555">
									<li>
										<label>
											<input type="checkbox" name="service[<?php echo $serviceId; ?>][enabled]" <?php checked( ( ! isset( $this->custom_services[$serviceId]['enabled'] ) || ! empty( $this->custom_services[$serviceId]['enabled'] ) ), true ); ?> />
											<?php echo $name; ?>
										</label>
									</li>
								</ul>
							</td>
							<td>
								<ul class="sub_services" style="font-size: 0.92em; color: #555">
									<li>
										<?php echo get_woocommerce_currency_symbol(); ?><input type="text" name="service[<?php echo $serviceId; ?>][markup_fixed]" placeholder="N/A" value="<?php echo isset( $this->custom_services[$serviceId]['markup_fixed'] ) ? $this->custom_services[$serviceId]['markup_fixed'] : ''; ?>" size="4" />
									</li>
								</ul>
							</td>
							<td>
								<ul class="sub_services" style="font-size: 0.92em; color: #555">
									<li>
										<input type="text" name="service[<?php echo $serviceId; ?>][markup_percentage]" placeholder="N/A" value="<?php echo isset( $this->custom_services[$serviceId]['markup_percentage'] ) ? $this->custom_services[$serviceId]['markup_percentage'] : ''; ?>" size="4" />%
									</li>
								</ul>
							</td>
						</tr>
						<?php
					}
				?>
			</tbody>
		</table>
	</td>
</tr>