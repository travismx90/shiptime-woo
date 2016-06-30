<?php require_once(dirname(__FILE__).'/../connector/SignupClient.php'); ?>
<style> .form-table td { padding-top: 0 !important; padding-bottom: 0 !important; }</style>
<tr valign="top" id="service_options">
	<td class="forminp" colspan="2" style="padding-left:0px">
		<table class="shipping_services widefat">
			<thead>
				<th>&nbsp; Service</th>
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
					$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
					$encUser = $shiptime_auth->username;
					$encPass = $shiptime_auth->password;
					$signupClient = new emergeit\SignupClient();
					$req = new emergeit\GetServicesRequest();
					$req->IntegrationID = "85566cfb-9d0e-421b-bc78-649a1711a3ea";
					$req->Credentials->EncryptedPassword = $encPass;
					$req->Credentials->EncryptedUsername = $encUser;
					$resp = $signupClient->getServices($req);
					foreach ($resp->ServiceOptions as $serviceOption) {
						$shipping_services[$serviceOption->ServiceId] = array(
							'CarrierId' => $serviceOption->CarrierId,
							'CarrierName' => $serviceOption->CarrierName,
							'ServiceName' => $serviceOption->ServiceName,
							'ServiceType' => $serviceOption->ServiceType
						);
					}
					$shipping_services = sortServices($shipping_services);

					$this->available_services = array();
					foreach ($shipping_services as $service_id => $data) {
						$this->available_services[$data['ServiceName']] = $service_id;
					}

					$this->intl_services = array(
						'DHL INTL EXPRESS 10:30',
				        'DHL INTL EXPRESS 12:00',
				        'DHL INTL EXPRESS WORLDWIDE',
						'FedEx International First',
				        'FedEx International Priority',
				        'FedEx International Economy',
				        'Purolator Express U.S.',
				        'Purolator Express Pack U.S.',
				        'Purolator Express Pack International',
				        'Purolator Ground U.S.',
				        'USPS Priority Mail International',
				        'USPS Express Mail International'
					);

					$shiptime_settings = get_option('woocommerce_shiptime_settings');

					foreach ( $this->available_services as $serviceName => $serviceId ) {
						$intl = false;
						$name = strpos($serviceName, $shipping_services[$serviceId]['CarrierName']) !== false ? $serviceName : $shipping_services[$serviceId]['CarrierName'] . " " . $serviceName;
						if (in_array($name, $this->intl_services)) { $intl = true; } ?>
						<tr<?php if ($intl) echo " class='intl'"; ?> style='height:40px'>
							<td>
								<input type="hidden" name="services[<?php echo $serviceId; ?>][name]" value="<?php echo $name; ?>" />
								<input type="hidden" name="services[<?php echo $serviceId; ?>][intl]" value="<?php echo $intl ? 1 : 0; ?>" />
								<?php echo '<strong>'.$serviceName.'</strong>'; ?>
							</td>
							<td>
								<?php echo $shipping_services[$serviceId]['CarrierName']; ?>
							</td>
							<td class="check-column">
								<label>
									<input type="checkbox" name="services[<?php echo $serviceId; ?>][enabled]" <?php checked( (isset($this->services[$serviceId]['enabled']) || $shiptime_settings === false ), true ); ?> />
								</label>
							</td>
							<td>
								<?php echo get_woocommerce_currency_symbol(); ?><input type="text" name="services[<?php echo $serviceId; ?>][markup_fixed]" placeholder="N/A" value="<?php echo isset( $this->services[$serviceId]['markup_fixed'] ) ? $this->services[$serviceId]['markup_fixed'] : ''; ?>" size="4" />
							</td>
							<td>
								<input type="text" name="services[<?php echo $serviceId; ?>][markup_percentage]" placeholder="N/A" value="<?php echo isset( $this->services[$serviceId]['markup_percentage'] ) ? $this->services[$serviceId]['markup_percentage'] : ''; ?>" size="4" />%
							</td>
						</tr>
						<?php
					}
				?>
			</tbody>
		</table>
	</td>
</tr>