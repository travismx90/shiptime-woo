<?php
/**
 * WC_Shipping_ShipTime class.
 *
 * @extends WC_Shipping_Method
 *
 * @author      travism
 * @version     1.0
 */
class WC_Shipping_ShipTime extends WC_Shipping_Method {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id = 'shiptime';
		$this->title = $this->method_title = 'ShipTime';
		$this->method_description = 'The <strong>ShipTime</strong> plugin obtains rates in real time from the ShipTime web service during cart/checkout.';
		$this->init();
	}

	/**
	 * init function.
	 *
	 * @access public
	 * @return void
	 */
	private function init() {
		global $wpdb;

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->debug = isset($this->settings['debug_mode']) && $this->settings['debug_mode'] == 'yes' ? true : false;
		$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : false;
		$this->turnaround_days = isset($this->settings['turnaround_days']) && (int)$this->settings['turnaround_days'] >= 0 ? $this->settings['turnaround_days'] : 1;
		$this->boxes = isset($this->settings['boxes']) ? $this->settings['boxes'] : array();
		$this->services = isset($this->settings['services']) ? $this->settings['services'] : array();
		$this->fallback_type = isset($this->settings['fallback_type']) ? $this->settings['fallback_type'] : '';
		$this->fallback_fee = isset($this->settings['fallback_fee']) ? $this->settings['fallback_fee'] : '';
		$this->fallback_max = isset($this->settings['fallback_max']) ? $this->settings['fallback_max'] : '';
		$this->shipping_threshold = isset($this->settings['cart_threshold']) && (float)$this->settings['cart_threshold'] > 0 ? $this->settings['cart_threshold'] : 0;

		// ShipTime API credentials
		$this->shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");

		// Instantiate the API connector
		if (is_object($this->shiptime_auth)) {
			require_once(dirname(__FILE__).'/../connector/RatingClient.php');
			$this->ratingClient = new emergeit\RatingClient($this->shiptime_auth->username, $this->shiptime_auth->password);
		}

		// Save configuration settings to DB
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		// Clear all cached data after updating configuration settings
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'clear_transients'));
	}

	/**
	 * clear_transients function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_transients() {
		global $wpdb;

		$wpdb->query("DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_st_quote_%') OR `option_name` LIKE ('_transient_timeout_st_quote_%')");
	}

	/**
	 * init_form_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public function init_form_fields() {
		global $woocommerce;

		$this->form_fields = array(
			'enabled' => array(
				'title' => 'Enable/Disable',
				'type' => 'checkbox',
				'label' => 'Enable this shipping method',
				'default' => 'yes'
			),
			'debug_mode' => array(
				'title' => 'Debug Mode',
				'label' => 'Enable debug mode',
				'type' => 'checkbox',
				'default' => 'no',
				'description' => 'Enable debug mode to show debugging data for ship rates in your cart. Only you, not your customers, can view this debug data.'
			),
			'turnaround_days' => array(
				'title' => 'Turnaround Days',
				'type' => 'number',
				'description' => 'Enter the number of business days it takes to process a new order. This is added to shipment transit days to calculate estimated delivery.',
				'custom_attributes' => array(
					'step' => '1',
					'min' => '0'
				),
				'default' => '1'
			),
			'cart_threshold' => array(
				'title' => 'Free Shipping Promotion',
				'type' => 'number',
				'description' => 'Enter a minimum cart total (> 0) to qualify orders for free domestic ground shipping.',
				'custom_attributes' => array(
					'step' => '0.01',
					'min' => '0'
				),
				'default' => '0'
			),
			'box' => array(
				'title' => 'Configured Boxes',
				'type' => 'title',
				'description' => 'Configure the box sizes that you use to ship items for most accurate results. Total dimensions are used for shipping calculations and inner dimensions are used for packaging multiple items into a single box.'
			),
			'boxes'  => array(
				'type' => 'box_config'
			),
			'service' => array(
				'title' => 'Shipping Services',
				'type' => 'title',
				'description' => 'Enable/Disable shipping services and assign markups.'
			),
			'enable_intl' => array(
				'title' => 'International Shipping',
				'label' => 'Enable International Shipping Services',
				'type' => 'checkbox',
				'default' => 'no',
				'description' => 'Enable full list of shipping services to support international shipments.'
			),
			'services'  => array(
				'type'  => 'shipping_service'
			),
			'fallback' => array(
				'title' => 'Fallback Rate',
				'type' => 'title',
				'description' => 'Default rate if ShipTime cannot be reached or if no rates are found.'
			),
			'fallback_type' => array(
				'title' => 'Type',
				'type' => 'select',
				'default' => '',
				'options' => array(
					'' => 'None',
					'per_item' => 'Per Item',
					'per_order' => 'Per Order'
				),
				'description' => ''
			),
			'fallback_fee' => array(
				'title' => 'Amount',
				'type' => 'number',
				'description' => 'Enter an amount for the fallback rate.',
				'custom_attributes' => array(
					'step' => '0.01',
					'min' => '0'
				)
			),
			'fallback_max' => array(
				'title' => 'Maximum Amount',
				'type' => 'number',
				'description' => 'Set a maximum amount when fallback rate is used.',
				'custom_attributes' => array(
					'step' => '0.01',
					'min' => '0'
				)
			)
		);
	}

	/**
	 * generate html function for 'shipping_service' form field type.
	 */
	public function generate_shipping_service_html() {
		ob_start();
		include(dirname(__FILE__).'/../html/shipping-services-html.php');
		return ob_get_clean();
	}

	/**
	 * validate function for 'shipping_service' form field type.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_shipping_service_field( $key ) {
		$services = array();
		$data = $_POST['services'];

		foreach ($data as $serviceId => $options) {
			$services[wc_clean($serviceId)] = array(
				'id' => wc_clean($serviceId),
				'name' => wc_clean($options['name']),
				'intl' => wc_clean($options['intl']),
				'enabled' => wc_clean($options['enabled']),
				'markup_fixed' => wc_clean($options['markup_fixed']),
				'markup_percentage' => wc_clean($options['markup_percentage'])
			);
		}

		return $services;
	}

	/**
	 * generate html function for 'box_config' form field type.
	 */
	public function generate_box_config_html() {
		ob_start();
		include(dirname(__FILE__).'/../html/box-config-html.php');
		return ob_get_clean();
	}

	/**
	 * validate function for 'box_config' form field type.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_box_config_field( $key ) {
		$boxes = array();

		if (isset($_POST['box_weight'])) {
			$box_label        = $_POST['box_label'];
			$box_outer_length = $_POST['box_outer_length'];
			$box_outer_width  = $_POST['box_outer_width'];
			$box_outer_height = $_POST['box_outer_height'];
			$box_inner_length = $_POST['box_inner_length'];
			$box_inner_width  = $_POST['box_inner_width'];
			$box_inner_height = $_POST['box_inner_height'];
			$box_weight       = $_POST['box_weight'];

			for ($i=0; $i<sizeof($box_weight); $i++) {

				if ($box_outer_length[$i] && $box_outer_width[$i] && $box_outer_height[$i] && $box_inner_length[$i] && $box_inner_width[$i] && $box_inner_height[$i]) {

					$boxes[] = array(
						'label'        => wc_clean($box_label[$i]),
						'outer_length' => floatval($box_outer_length[$i]),
						'outer_width'  => floatval($box_outer_width[$i]),
						'outer_height' => floatval($box_outer_height[$i]),
						'inner_length' => floatval($box_inner_length[$i]),
						'inner_width'  => floatval($box_inner_width[$i]),
						'inner_height' => floatval($box_inner_height[$i]),
						'weight'       => floatval($box_weight[$i])
					);

				}
			}
		}

		return $boxes;
	}

	/**
	 * calculate_shipping function.
	 *
	 * @access public
	 * @param mixed $package
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		global $wpdb;
		global $woocommerce;
		global $current_user;

		// WooCommerce currency setting
		$base_currency = get_woocommerce_currency();

		// Must be WP admin to see Debug output
		$is_admin = (!empty($current_user->roles) && in_array('administrator', $current_user->roles)) ? true : false;

		if (is_object($this->shiptime_auth)) {
			// Calculate if shipping fields are set
			if (!empty($package['destination']['country'])) {
				$dest_country = $package['destination']['country'];
				$dest_postcode = !empty($package['destination']['postcode']) ? $package['destination']['postcode'] : '';
				$dest_state = !empty($package['destination']['state']) ? $package['destination']['state'] : '';

				// Check if customer has set required destination info
				if ( ( ($dest_country == 'US' || $dest_country == 'CA') && empty($dest_postcode) ) ) {
					return;
				}

				// Create the XML request
				$req = new emergeit\GetRatesRequest();
				$req->From->Attention = ucwords($this->shiptime_auth->first_name) . ' ' . ucwords($this->shiptime_auth->last_name);
				$req->From->Phone = $this->shiptime_auth->phone;
				$req->From->CompanyName = $this->shiptime_auth->company;
				$req->From->StreetAddress = ucwords($this->shiptime_auth->address);
				$req->From->CountryCode = $this->shiptime_auth->country;
				$req->From->PostalCode = $this->shiptime_auth->zip;
				$req->From->Province = $this->shiptime_auth->state;
				$req->From->City = ucwords($this->shiptime_auth->city);
				$req->From->Notify = false;
				$req->From->Residential = false;

				$req->To->Attention = 'John Smith';
				$req->To->Phone = '5555555555';
				$req->To->CompanyName = 'Test Company';
				$req->To->StreetAddress = '1 Main St';
				$req->To->CountryCode = $dest_country;
				$req->To->PostalCode = $dest_postcode;
				$req->To->Province = $dest_state;
				$req->To->City = ''; // lookup handled by API
				$req->To->Notify = false;
				$req->To->Residential = false;

				$req->PackageType = 'PACKAGE';

				$is_domestic = ($req->From->CountryCode == $req->To->CountryCode);

				// Create an array of items to rate
				$items = array();

				// Loop through package items
				foreach ($package['contents'] as $item_id => $values) {
					// Skip digital items
					if (!$values['data']->needs_shipping()) {
						continue;
					}

					// Populate Item Data
					$item = array();
					$item["id"] = $values['data']->post->ID;
					$item["sku"] = $values['data']->get_sku();
					$item["name"] = $values['data']->post->post_name;
					$item["quantity"] = $values['quantity'];
					$item["value"] = $values['data']->get_price();
					$item["weight"] = woocommerce_get_weight($values['data']->get_weight(), 'lbs');
					// If no weight set for product assume 1 lb
					$item["weight"] = !empty($item["weight"]) ? $item["weight"] : 1;
					if (!empty($values['data']->length) && !empty($values['data']->height) && !empty($values['data']->width)) {
						$item["length"] = woocommerce_get_dimension($values['data']->length, 'in');
						$item["width"] = woocommerce_get_dimension($values['data']->width, 'in');
						$item["height"] = woocommerce_get_dimension($values['data']->height, 'in');
					} else {
						// If no L,W,H set for product assume 1x1x1 in
						$item["length"] = 1;
						$item["width"] = 1;
						$item["height"] = 1;
					}

					// Add this item to array
					$items[] = $item;
				}

				// Int'l Shipment?
				if (!$is_domestic) {
					// Customs Invoice Required
					$dt = new emergeit\DutiesAndTaxes();
					$dt->Dutiable = true;
					$sel = sanitize_text_field($_GET['shiptime_selection']);
					if ($sel !== 'CONSIGNEE') { $sel = 'SHIPPER'; }
					$dt->Selection = 'SHIPPER'; // 'CONSIGNEE'
					$req->CustomsInvoice->DutiesAndTaxes = $dt;

					$ic = new emergeit\InvoiceContact();
					$ic->City = '-';
					$ic->CompanyName = '-';
					$ic->CountryCode = $dest_country;
					$ic->Email = '-';
					$ic->Phone = '-';
					$ic->PostalCode = $dest_postcode;
					$ic->Province = $dest_state;
					$ic->StreetAddress = '1 Main St';
					$ic->CustomsBroker = '-';
					$ic->ShipperTaxId = '-';
					$req->CustomsInvoice->InvoiceContact = $ic;

					$req->CustomsInvoice->InvoiceItems = array();
					foreach ($items as $item) {
						$i = new emergeit\InvoiceItem();
						$hs_code = get_post_meta($item['id'], 'shiptime_hs_code', true);
						$i->Code = str_replace(".", "", $hs_code);
						$i->Description = $item['name'];
						$i->Origin = get_post_meta($item['id'], 'shiptime_origin_country', true);
						$i->Quantity->Value = (int)$item['quantity'];
						$i->UnitPrice->Amount = $item['value'];
						$i->UnitPrice->CurrencyCode = get_woocommerce_currency();

						$req->CustomsInvoice->InvoiceItems[] = $i;
					}
				} else {
					unset($req->CustomsInvoice);
				}

				// Debug output
				if ($this->debug && $is_admin === true) {
						echo 'DEBUG ITEM DATA<br>';
						echo '<pre>' . print_r($items, true) . '</pre>';
						echo 'END DEBUG ITEM DATA<br>';
				}

				// Convert Items array to Packages array
				$sh = new emergeit\ShipmentBuilder();
				$sh->setItems($items);
				$boxes = array();
				// Normalize units of measure for API
				foreach ($this->boxes as $box) {
					$boxes[] = array(
						'label' => $box['label'],
						'weight' => woocommerce_get_weight($box['weight'], 'lbs'),
						'inner_length' => woocommerce_get_dimension($box['inner_length'], 'in'),
						'inner_width' => woocommerce_get_dimension($box['inner_width'], 'in'),
						'inner_height' => woocommerce_get_dimension($box['inner_height'], 'in'),
						'outer_length' => woocommerce_get_dimension($box['outer_length'], 'in'),
						'outer_width' => woocommerce_get_dimension($box['outer_width'], 'in'),
						'outer_height' => woocommerce_get_dimension($box['outer_height'], 'in')
					);
				}
				$packages = $sh->package($boxes);

				foreach ($packages as $package) {
					$item = new emergeit\LineItem();

					$item->Length->UnitsType = $package->getDimUnit();
					$item->Length->Value = $package->getLength();
					$item->Width->UnitsType = $package->getDimUnit();
					$item->Width->Value = $package->getWidth();
					$item->Height->UnitsType = $package->getDimUnit();
					$item->Height->Value = $package->getHeight();
					$item->Weight->UnitsType = $package->getWeightUnit();
					// TODO: Support packages < 1 LB
					$pkg_weight = $package->getWeight();
					$item->Weight->Value = $pkg_weight >= 1 ? $pkg_weight : 1;
					$item->Description = 'Item Line Description';

					$req->ShipmentItems[] = $item;
				}

				// Unique identifier for cart items & destiniation
				$request_identifier = serialize($items) . $dest_country . $dest_postcode;

				// Check for cached response
				$transient = 'st_quote_' . md5($request_identifier);
				$cached_response = get_transient($transient);

				$shipRates = array();
				$sortedRates = array();
				$cached = false;

				if ($cached_response !== false) {
					// Cached response
					$cached = true;
					$shipRates = unserialize($cached_response);
				} else {
					// New API call
					if ($this->ratingClient->isConnected()) {
						$shipRates = $this->ratingClient->getRates($req);
					}

					if ($shipRates) {
						// Cache quote data for 30 mins
						set_transient($transient, serialize($shipRates), 30 * MINUTE_IN_SECONDS);
					}
				}

				// Debug output
				if ($this->debug && $is_admin === true) {
					echo 'DEBUG API RESPONSE: SHIP RATES<br>';
					echo '<pre>' . print_r($shipRates, true) . '</pre>';
					echo 'END DEBUG API RESPONSE: SHIP RATES<br>';
				}

				if (!empty($shipRates->AvailableRates)) {
					// Store response into DB
					// Used to retrieve package level details later
					if (!$cached) {
						// Account for Metric/Imperial
						foreach ($packages as $package) {
							// Convert back from LB/IN (returned from API) to Metric if necessary (based on Woo setting)
							$package->setWeight(round(wc_get_weight($package->getWeight(), get_option( 'woocommerce_weight_unit' ), 'lbs'), 1));
							$package->setLength(round(wc_get_dimension($package->getLength(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
							$package->setWidth(round(wc_get_dimension($package->getWidth(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
							$package->setHeight(round(wc_get_dimension($package->getHeight(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
						}
						$wpdb->insert(
							$wpdb->prefix.'shiptime_quote',
							array(
								'order_id' => 0,
								'cart_sessid' => array_shift(array_keys($woocommerce->session->cart)),
								'quote' => serialize($shipRates),
								'packages' => serialize($packages)
							),
							array(
								'%d', '%s', '%s', '%s'
							)
						);
					}

					foreach ($shipRates->AvailableRates as $shipRate) {
						// Add Rate
						$l = (strpos($shipRate->ServiceName, $shipRate->CarrierName) !== false ? $shipRate->ServiceName : $shipRate->CarrierName . " ". $shipRate->ServiceName) . " [" . ((int)$this->turnaround_days + (int)$shipRate->TransitDays) . "]*";
						$c = ($is_domestic && strpos($shipRate->ServiceName, 'Ground') !== false && !empty($this->shipping_threshold) && (float)$woocommerce->cart->cart_contents_total >= $this->shipping_threshold) ? 0.00 : $shipRate->TotalCharge->Amount/100.00;
						if ($c == 0) $l .= " (FREE)";
						if ($this->services[$shipRate->ServiceId]['enabled'] == 'on') {
							$markup_fixed = $this->services[$shipRate->ServiceId]['markup_fixed'];
							$markup_fixed = is_numeric($markup_fixed) && !empty($markup_fixed) ? $markup_fixed : 0;
							$markup_percentage = $this->services[$shipRate->ServiceId]['markup_percentage'];
							$markup_percentage = is_numeric($markup_percentage) && !empty($markup_percentage) ? (float)$markup_percentage/100.00 + 1 : 0;
							if (!empty($c)) {
								if (!empty($markup_fixed)) {
									$c += $markup_fixed;
								} elseif (!empty($markup_percentage)) {
									$base_charge = $shipRate->BaseCharge->Amount/100.00;
									$fuel_charge = 0;
									$accessorial_charge = 0;
									foreach ($shipRate->Surcharges as $surcharge) {
										if ($surcharge->Code == 'FUEL') {
											$fuel_charge += $surcharge->Price->Amount/100.00;
										} else {
											$accessorial_charge += $surcharge->Price->Amount/100.00;
										}
									}
									$tax_charge += ($shipRate->TotalCharge->Amount-$shipRate->TotalBeforeTaxes->Amount)/100.00;
									$c = (($base_charge + $fuel_charge + $tax_charge) * $markup_percentage) + $accessorial_charge;
								}
								// API returns prices in CAD
								// Convert, if necessary, to store currency
								if (get_woocommerce_currency() != 'CAD') {
									$c = emergeit\CurrencyUtil::convert('CAD',get_woocommerce_currency(),$c);
								}
							}
							$rate = array(
								'id'    => $this->id . ':' . $shipRate->ServiceId,
								'label' => $l,
								'cost'  => $c
							);
							$sortedRates[] = $rate;
						}
					}

					uasort($sortedRates, array($this, 'sortRates'));

					foreach ($sortedRates as $rate) {
						$this->add_rate($rate);
					}

				} else {
					// Add fallback shipping rate if merchant has configured this setting
					if (!empty($this->fallback_type) && !empty($this->fallback_fee)) {
						$cost = $this->fallback_type === 'per_order' ? $this->fallback_fee : $woocommerce->cart->cart_contents_count * $this->fallback_fee;
						if (!empty($this->fallback_max) && $this->fallback_type !== 'per_order' && $cost > $this->fallback_max) { $cost = $this->fallback_max; }
						$rate = array(
							'id' => $this->id . '_fallback_rate',
							'label' => 'Shipping',
							'cost' => $cost
						);
						$this->add_rate($rate);
					} else {
						// Unable to determine any services to be rated.
						return;
					}
				}
			} else {
				// Order shipping destination not set.
				return;
			}
		} else {
			// No API Credentials.
			return;
		}

	}

	public function sortRates($a,$b) {
		if ($a['cost'] == $b['cost']) {
			return 0;
		}
		return ($a['cost'] < $b['cost']) ? -1 : 1;
	}

}
