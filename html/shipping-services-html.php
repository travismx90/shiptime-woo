<?php require_once(dirname(__FILE__).'/../connector/SignupClient.php'); ?>
<?php require_once(dirname(__FILE__).'/../includes/shipping-service.php'); ?>
<style> .form-table td { padding-top: 0 !important; padding-bottom: 0 !important; }</style>
<tr valign="top" id="service_options">
	<td class="forminp" colspan="2" style="padding-left:0px">
		<table class="shipping_services widefat">
			<thead>
				<th>&nbsp; Service</th>
				<th>&nbsp; Display Name</th>
				<th>&nbsp; Carrier</th>
				<th class="check-column" style="padding:20px 10px 20px 0 !important">Enabled?<input style="margin-left:1px" type="checkbox" /></th>
				<th>&nbsp; &nbsp;<?php echo sprintf('Markup (%s)', get_woocommerce_currency_symbol()); ?></th>
				<th>&nbsp; &nbsp;Markup (%)</th>
			</thead>
			<tbody>
				<?php
			    function sortServices($sort) {
			      if (empty($sort)) { return false; }
			      uasort($sort, 'serviceSorting');
			      return $sort;
			    }
			    function serviceSorting($a, $b) {
			    	return strcmp($a['CarrierName'], $b['CarrierName']);
			    }
				?>
				<?php
					global $wpdb;
					$shipping_services = array();
					$shiptime_carriers = array();
					$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
					$encUser = $shiptime_auth->username;
					$encPass = $shiptime_auth->password;
					$signupClient = new emergeit\SignupClient();
					$req = new emergeit\GetServicesRequest();
					$req->IntegrationID = $shiptime_auth->integration_id;
					$req->Credentials->EncryptedPassword = $encPass;
					$req->Credentials->EncryptedUsername = $encUser;
					$resp = $signupClient->getServices($req);
					foreach ($resp->ServiceOptions as $serviceOption) {
						$skip = false;
						foreach ($shipping_services as $serviceId => $data) {
							if ($serviceOption->CarrierName == $data['CarrierName'] && $serviceOption->ServiceName == $data['ServiceName']) {
								$skip = true;
							}
						}
						if (!$skip) {
							$shipping_services[$serviceOption->ServiceId] = array(
								'CarrierId' => $serviceOption->CarrierId,
								'CarrierName' => $serviceOption->CarrierName,
								'ServiceName' => $serviceOption->ServiceName,
								'ServiceType' => $serviceOption->ServiceType
							);
						}
					}
					$shipping_services = sortServices($shipping_services);

					$this->available_services = array();
					if (is_array($shipping_services)) {
						foreach ($shipping_services as $service_id => $data) {
							$this->available_services[] = new emergeit\ShippingService($service_id,$data['ServiceName'],$data['CarrierId'],$data['CarrierName'],$shiptime_auth->country);
						}
					}

					$shiptime_settings = get_option('woocommerce_shiptime_settings');

					if (is_array($this->available_services)) {
						foreach ($this->available_services as $service) {
							if ($service->isValid()) {
								$intl = false;
								if (!$service->isDomestic()) { $intl = true; } ?>
								<tr<?php if ($intl) echo " class='intl'"; ?> style='height:40px'>
									<td>
										<input type="hidden" name="services[<?php echo $service->getId(); ?>][name]" value="<?php echo $service->getFullName(); ?>" />
										<input type="hidden" name="services[<?php echo $service->getId(); ?>][intl]" value="<?php echo $intl ? 1 : 0; ?>" />
										<?php echo '<strong>'.$service->getName().'</strong>'; ?>
									</td>
									<td>
										<input type="text" name="services[<?php echo $service->getId(); ?>][display_name]" value="<?php echo isset( $this->services[$service->getId()]['display_name'] ) ? $this->services[$service->getId()]['display_name'] : $service->getDisplayName(); ?>" />
									</td>
									<td>
										<input type="hidden" name="services[<?php echo $service->getId(); ?>][carrier]" value="<?php echo $shipping_services[$service->getId()]['CarrierName']; ?>" />
										<?php echo $shipping_services[$service->getId()]['CarrierName']; ?>
									</td>
									<td class="check-column">
										<label>
											<input type="checkbox" name="services[<?php echo $service->getId(); ?>][enabled]" <?php checked( (isset($this->services[$service->getId()]['enabled']) || $shiptime_settings === false ), true ); ?> />
										</label>
									</td>
									<td>
										<?php echo get_woocommerce_currency_symbol(); ?><input type="text" name="services[<?php echo $service->getId(); ?>][markup_fixed]" placeholder="N/A" value="<?php echo isset( $this->services[$service->getId()]['markup_fixed'] ) ? $this->services[$service->getId()]['markup_fixed'] : ''; ?>" size="4" />
									</td>
									<td>
										<input type="text" name="services[<?php echo $service->getId(); ?>][markup_percentage]" placeholder="N/A" value="<?php echo isset( $this->services[$service->getId()]['markup_percentage'] ) ? $this->services[$service->getId()]['markup_percentage'] : ''; ?>" size="4" />%
									</td>
								</tr>
								<?php
							}
						}
					}
				?>
			</tbody>
		</table>
		<br>
	</td>
</tr>
