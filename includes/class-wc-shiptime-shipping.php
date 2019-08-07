<?php
/**
 * WC_Shipping_ShipTime class.
 *
 * @extends WC_Shipping_Method
 *
 * @author      travism
 * @version     1.0
 */
require_once('class-wc-functions.php');
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
		$this->method_description = 'The <strong>ShipTime</strong> plugin obtains rates in real time from the '
			. 'ShipTime web service during cart/checkout.';
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
		$this->excluded_countries = isset($this->settings['excluded_countries']) ? $this->settings['excluded_countries'] : array();
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
				'description' => 'Enable debug mode to show debugging data on the Cart page above the Cart totals. Only WordPress administrators, not your customers, can view this debug data.'
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
			'exclusions' => array(
				'title' => 'Shipping Zones',
				'type' => 'title',
				'description' => 'Restrict shipping by location.'
			),
			'excluded_countries' => array(
				'type' => 'country_multiselector',
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
	 * generate html function for 'country_multiselector' form field type.
	 */
	public function generate_country_multiselector_html() {
		ob_start();
		$opts = WC()->countries->get_shipping_countries();
		shiptime_wp_multi_select(
			array(
				'id' => 'shiptime_exclude_countries',
				'label' => 'Do not ship to the following: &nbsp; ',
				'class' => 'select',
				'options' => $opts,
				'desc_tip' => 'true',
				'description' => 'Note: You will not be able to accept orders from the countries selected here.'
			),
			'excluded_countries'
        );
		return ob_get_clean();
	}

	/**
	 * validate function for 'country_multiselector' form field type.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_country_multiselector_field( $key ) {
		return $_POST['shiptime_exclude_countries'];
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
			if (array_key_exists('enabled', $options)) { // prevent undefined index notice
				$services[wc_clean($serviceId)] = array(
					'id' => wc_clean($serviceId),
					'name' => wc_clean($options['name']),
					'display_name' => wc_clean($options['display_name']),
					'carrier' => wc_clean($options['carrier']),
					'intl' => wc_clean($options['intl']),
					'enabled' => wc_clean($options['enabled']),
					'markup_fixed' => wc_clean($options['markup_fixed']),
					'markup_percentage' => wc_clean($options['markup_percentage'])
				);
			} else {
				$services[wc_clean($serviceId)] = array(
					'id' => wc_clean($serviceId),
					'name' => wc_clean($options['name']),
					'display_name' => wc_clean($options['display_name']),
					'carrier' => wc_clean($options['carrier']),
					'intl' => wc_clean($options['intl']),
					'markup_fixed' => wc_clean($options['markup_fixed']),
					'markup_percentage' => wc_clean($options['markup_percentage'])
				);
			}
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

				if ($box_outer_length[$i] && $box_outer_width[$i] && $box_outer_height[$i] && 
					$box_inner_length[$i] && $box_inner_width[$i] && $box_inner_height[$i]) {

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
		global $current_user, $woocommerce, $wpdb;

		// WooCommerce currency setting
		$base_currency = get_woocommerce_currency();

		// Must be WP admin to see Debug output
		$debug_output = '';
		$is_admin = (!empty($current_user->roles) && in_array('administrator', $current_user->roles)) ? true : false;

		if (is_object($this->shiptime_auth)) {
			// Retrieve destination information
			$dest_country = $package['destination']['country'];
			$dest_state = $package['destination']['state'];
			$dest_city = !empty($package['destination']['city']) ? $package['destination']['city'] : '-';
			$dest_postcode = $package['destination']['postcode'];
			$dest_addr = $package['destination']['address'] . 
				(!empty($package['destination']['address_2']) ? $package['destination']['address_2'] : '');
			if (empty($dest_addr)) {
				// default street address when calculating shipping on Cart page before entering full address
				$dest_addr = '123 Test Street';
			}
						
			// Calculate if required shipping fields are set			
			if (!empty($dest_country) && !empty($dest_postcode) && !empty($dest_state) && !empty($dest_addr)) {
				// Do not calculate shipping if excluded country
				if (in_array($dest_country, $this->excluded_countries)) return;

				// Create the XML request
				$req = new emergeit\GetRatesRequest();
				$req->IntegrationID = $this->shiptime_auth->integration_id;
				$req->From->Attention = ucwords($this->shiptime_auth->first_name.' '.$this->shiptime_auth->last_name);
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
				$req->To->CompanyName = 'Example Company';
				$req->To->StreetAddress = $dest_addr;
				$req->To->CountryCode = $dest_country;
				$req->To->PostalCode = $dest_postcode;
				$req->To->Province = $dest_state;
				$req->To->City = ''; // lookup handled by ShipTime API
				$req->To->Notify = false;
				$req->To->Residential = false;

				$req->PackageType = 'PACKAGE';

				$is_domestic = ($req->From->CountryCode == $req->To->CountryCode);

				// Create an array of items to rate
				$items = array();

				// Total cost of all "flat fee" shipping items
				$ff_shipping = 0;

				// Does this order contain a "free" or "flat fee" shipping item
				$free_or_ff = false;

				// Loop through package items
				foreach ($package['contents'] as $item_id => $values) {
					// Skip digital items
					if (!$values['data']->needs_shipping()) {
						continue;
					}

					// Get the Product WP_Post object
					$postid = $values['product_id'];
					$prod = get_post($postid);

					// Populate Item Data
					$item = array();
					$item["id"] = $postid;
					$item["sku"] = $values['data']->get_sku();
					$item["name"] = $prod->post_name;
					$item["quantity"] = $values['quantity'];
					$item["value"] = $values['data']->get_price();
					$item["weight"] = wc_get_weight($values['data']->get_weight(), 'lbs');
					// If no weight set for product assume 1 lb
					$item["weight"] = !empty($item["weight"]) ? $item["weight"] : 1;
					$item["length"] = $values['data']->get_length();
					$item["width"] = $values['data']->get_width();
					$item["height"] = $values['data']->get_height();
					if ($item["length"] && $item["width"] && $item["height"]) {
						$item["length"] = wc_get_dimension($item["length"], 'in');
						$item["width"] = wc_get_dimension($item["width"], 'in');
						$item["height"] = wc_get_dimension($item["height"], 'in');
					} else {
						// If no L,W,H set for product assume 1x1x1 in
						$item["length"] = 1;
						$item["width"] = 1;
						$item["height"] = 1;
					}

					// Track all "free" shipping items
					$ship_method = get_post_meta($postid, 'shiptime_ship_method', true);
					if ($ship_method === 'Z' || ($ship_method === 'D' && $is_domestic)) {
						$free_or_ff = true;
						$item["send_to_api"] = false;
					}

					// Track all "flat fee" shipping items
					$ff_dom = get_post_meta($postid, 'shiptime_ff_dom', true);
					$ff_intl = get_post_meta($postid, 'shiptime_ff_intl', true);
					if ($ship_method === 'F') {
						$free_or_ff = true;
						$item["send_to_api"] = false;
						if (!$is_domestic && !empty($ff_intl) && is_numeric($ff_intl)) {
							$ff_shipping += $ff_intl*$values['quantity'];
						} elseif (!empty($ff_dom) && is_numeric($ff_dom)) {
							$ff_shipping += $ff_dom*$values['quantity'];
						}
					}

					if (!array_key_exists('send_to_api', $item)) $item["send_to_api"] = true;

					// Add this item to array
					$items[] = $item;
				}

				// Int'l Shipment?
				if (!$is_domestic) {
					// Customs Invoice Required
					$dt = new emergeit\DutiesAndTaxes();
					$dt->Dutiable = true;
					$dt->Selection = 'SHIPPER'; // 'SHIPPER' or 'CONSIGNEE'
					$req->CustomsInvoice->DutiesAndTaxes = $dt;

					$ic = new emergeit\InvoiceContact();
					$ic->City = $dest_city;
					$ic->CompanyName = 'Example Company';
					$ic->CountryCode = $dest_country;
					$ic->Email = '-';
					$ic->Phone = '5555555555';
					$ic->PostalCode = $dest_postcode;
					$ic->Province = $dest_state;
					$ic->StreetAddress = $dest_addr;
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
						$i->UnitPrice->CurrencyCode = $base_currency;

						$req->CustomsInvoice->InvoiceItems[] = $i;
					}
				} else {
					unset($req->CustomsInvoice);
				}

				// Convert Items array to Packages array
				$packages = $this->pkg($items, true);

				foreach ($packages as $pkg) {
					$item = new emergeit\LineItem();

					$item->Length->UnitsType = $pkg->getDimUnit();
					$item->Length->Value = $pkg->getLength();
					$item->Width->UnitsType = $pkg->getDimUnit();
					$item->Width->Value = $pkg->getWidth();
					$item->Height->UnitsType = $pkg->getDimUnit();
					$item->Height->Value = $pkg->getHeight();
					$item->Weight->UnitsType = $pkg->getWeightUnit();
					// TODO: Support packages < 1 LB
					$pkg_weight = $pkg->getWeight();
					$item->Weight->Value = $pkg_weight >= 1 ? $pkg_weight : 1;
					$item->Description = 'Item Line Description';

					$req->ShipmentItems[] = $item;
				}

				// Debug output - ShipTime API Request
				if ($this->debug && $is_admin === true) {
					$debug_output .= 'BEGIN DEBUG: SHIP RATES API REQUEST<br>';
					$debug_output .= '<pre>' . print_r($req, true) . '</pre>';
					$debug_output .= 'END DEBUG: SHIP RATES API REQUEST<br>';					
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
					if (!empty($packages)) {
						if ($this->ratingClient->isConnected()) {
							$shipRates = $this->ratingClient->getRates($req);
						}
						if ($shipRates) {
							// Cache quote data for 30 mins
							set_transient($transient, serialize($shipRates), 30 * MINUTE_IN_SECONDS);
						}
					}
				}

				// Debug output - ShipTime API Response
				if ($this->debug && $is_admin === true) {
					$debug_output .= 'BEGIN DEBUG: SHIP RATES API RESPONSE<br>';
					$debug_output .= '<pre>' . print_r($shipRates, true) . '</pre>';
					$debug_output .= 'END DEBUG: SHIP RATES API RESPONSE<br>';
				}

				// Store response into DB
				// Used to retrieve package level details later
				if (!$cached) {
					// Make sure package data is for ALL items, not just carrier calculated
					$packages = $this->pkg($items, false);

					// Account for Metric/Imperial
					foreach ($packages as $pkg) {
						// Convert back from LB/IN (returned from API) to Metric if necessary (based on Woo setting)
						$pkg->setWeight(round(wc_get_weight($pkg->getWeight(), get_option( 'woocommerce_weight_unit' ), 'lbs'), 1));
						$pkg->setLength(round(wc_get_dimension($pkg->getLength(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
						$pkg->setWidth(round(wc_get_dimension($pkg->getWidth(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
						$pkg->setHeight(round(wc_get_dimension($pkg->getHeight(), get_option( 'woocommerce_dimension_unit' ), 'in'), 1));
					}
					if (!empty($woocommerce->session->cart)) {
						$sessids = array_keys($woocommerce->session->cart);
						$sessid = array_shift($sessids);
					} else {
						$sessids = array_keys($woocommerce->cart->get_cart());
						$sessid = array_shift($sessids);
					}
					if (isset($sessid)) {
						$wpdb->insert(
							$wpdb->prefix.'shiptime_quote',
							array(
								'order_id' => 0,
								'cart_sessid' => $sessid,
								'quote' => serialize($shipRates),
								'packages' => serialize($packages),
								'debug' => $debug_output
							),
							array(
								'%d', '%s', '%s', '%s', '%s'
							)
						);
					}
				}

				if (isset($shipRates->AvailableRates)) {
					foreach ($shipRates->AvailableRates as $shipRate) {
						// Add Rate
						if (array_key_exists($shipRate->ServiceId, $this->services)) { // skip services not enabled
							$lbl = $shipRate->CarrierName . " ". $this->services[$shipRate->ServiceId]['display_name'] . " [" . ((int)$this->turnaround_days + (int)$shipRate->TransitDays) . "]*";
							$exch_rate = (float) $shipRate->ExchangeRate;
							$cost = ($is_domestic && strpos($shipRate->ServiceName, 'Ground') !== false && !empty($this->shipping_threshold) && (float)$woocommerce->cart->cart_contents_total >= $this->shipping_threshold) ? 0.00 : $shipRate->TotalCharge->Amount*$exch_rate/100.00;
							if ($cost == 0) $lbl .= " (FREE)";
							if ($this->services[$shipRate->ServiceId]['enabled'] == 'on') {
								$markup_fixed = $this->services[$shipRate->ServiceId]['markup_fixed'];
								$markup_fixed = is_numeric($markup_fixed) && !empty($markup_fixed) ? $markup_fixed : 0;
								$markup_percentage = $this->services[$shipRate->ServiceId]['markup_percentage'];
								$markup_percentage = is_numeric($markup_percentage) && !empty($markup_percentage) ? (float)$markup_percentage/100.00 + 1 : 0;
								if (!empty($cost)) {
									if (!empty($markup_fixed)) {
										$cost += $markup_fixed;
									} elseif (!empty($markup_percentage)) {
										$base_charge = $shipRate->BaseCharge->Amount*$exch_rate/100.00;
										$fuel_charge = 0;
										$accessorial_charge = 0;
										foreach ($shipRate->Surcharges as $surcharge) {
											if ($surcharge->Code == 'FUEL') {
												$fuel_charge += $surcharge->Price->Amount*$exch_rate/100.00;
											} else {
												$accessorial_charge += $surcharge->Price->Amount*$exch_rate/100.00;
											}
										}
										$tax_charge += ($shipRate->TotalCharge->Amount-$shipRate->TotalBeforeTaxes->Amount)*$exch_rate/100.00;
										$cost = (($base_charge + $fuel_charge + $tax_charge) * $markup_percentage) + $accessorial_charge;
									}
									// Add cost of "flat fee" items if applicable
									if (!empty($ff_shipping) && is_numeric($ff_shipping)) {
										$cost = number_format($cost+$ff_shipping, 2, '.', '');
									}
								}
								$rate = array(
									'id'    => $this->id . ':' . $shipRate->ServiceId,
									'label' => $lbl,
									'cost'  => $cost
								);
								$sortedRates[] = $rate;
							}
						}
					}

					uasort($sortedRates, array($this, 'sortRates'));

					foreach ($sortedRates as $rate) {
						$this->add_rate($rate);
					}

				} elseif ($free_or_ff) {
					// Check if we have only "free" and/or only "flat fee" items
					$svc = "FREE";
					$lbl = "Free Shipping";
					$cost = 0;
					if (!empty($ff_shipping) && is_numeric($ff_shipping)) {
						$svc = "FLATFEE";
						$lbl = "Shipping Rate";
						$cost = number_format($ff_shipping, 2, '.', '');
					}
					$rate = array(
						'id'    => $this->id . ':' . $svc,
						'label' => $lbl,
						'cost'  => $cost
					);
					$this->add_rate($rate);
				} else {
					// Add fallback shipping rate if merchant has configured this setting
					if (!empty($this->fallback_type) && !empty($this->fallback_fee)) {
						$cost = $this->fallback_type === 'per_order' ? $this->fallback_fee : $woocommerce->cart->cart_contents_count * $this->fallback_fee;
						if (!empty($this->fallback_max) && $this->fallback_type !== 'per_order' && $cost > $this->fallback_max) { $cost = $this->fallback_max; }
						$rate = array(
							'id' => $this->id . ':FALLBACK',
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

	public function testCalc($item) {
		return $item['send_to_api'];
	}

	public function pkg($items, $calcOnly=true) {
		$sh = new emergeit\ShipmentBuilder();
		if (!$calcOnly) {
			// include all items in pkg data
			$sh->setItems($items);
		} else {
			// include only carrier calc items in pkg data
			$sh->setItems(array_filter($items, array($this, 'testCalc')));
		}
		$boxes = array();
		// Normalize units of measure for API
		foreach ($this->boxes as $box) {
			$boxes[] = array(
				'label' => $box['label'],
				'weight' => wc_get_weight($box['weight'], 'lbs'),
				'inner_length' => wc_get_dimension($box['inner_length'], 'in'),
				'inner_width' => wc_get_dimension($box['inner_width'], 'in'),
				'inner_height' => wc_get_dimension($box['inner_height'], 'in'),
				'outer_length' => wc_get_dimension($box['outer_length'], 'in'),
				'outer_width' => wc_get_dimension($box['outer_width'], 'in'),
				'outer_height' => wc_get_dimension($box['outer_height'], 'in')
			);
		}
		return $sh->package($boxes);
	}

}
