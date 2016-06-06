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
        $this->method_description = 'The <strong>ShipTime</strong> plugin obtains rates in real time from the ShipTime API during cart/checkout.';
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
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : $this->enabled;
        $this->boxes = isset($this->settings['boxes']) ? $this->settings['boxes'] : array();
        $this->fallback_type = isset($this->settings['fallback_type']) ? $this->settings['fallback_type'] : $this->fallback_type;
        $this->fallback_fee = isset($this->settings['fallback_fee']) ? $this->settings['fallback_fee'] : $this->fallback_fee;
        $this->shipping_threshold = isset($this->settings['cart_threshold']) ? $this->settings['cart_threshold'] : 0;

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
                'default' => 'no'
            ),
            'debug_mode' => array(
                'title' => 'Debug Mode',
                'label' => 'Enable debug mode',
                'type' => 'checkbox',
                'default' => 'no',
                'description' => 'Enable debug mode to show debugging data for ship rates in your cart. Only you, not your customers, can view this debug data.'
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
                'type'  => 'shipping_services'
            ),            
            'fallback' => array(
                'title' => 'Fallback Rate',
                'type' => 'title',
                'description' => 'Default rate if the API cannot be reached or if no rates are found.'
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
            )
        );
    }

    /**
     * generate_services_html function.
     */
    public function generate_shipping_services_html() {
        ob_start();
        include(dirname(__FILE__).'/../includes/shipping-services-html.php');
        return ob_get_clean();
    }

    /**
     * generate html function for 'box_config' form field type.
     */
    public function generate_box_config_html() {
        ob_start();
        include(dirname(__FILE__).'/../includes/box-config-html.php');
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

    public function calculate_shipping($package) {
        global $wpdb;
        global $woocommerce;
        global $current_user;

        // WooCommerce currency setting
        $base_currency = get_woocommerce_currency();

        // WordPress admin? Useful if implementing debug mode for merchants.
        $is_admin = (!empty($current_user->roles) && in_array('administrator', $current_user->roles)) ? true : false;

        if (is_object($this->shiptime_auth)) {
            // Calculate if shipping fields are set
            if (!empty($package['destination']['country'])) {
                $dest_country = $package['destination']['country'];
                $dest_postcode = !empty($package['destination']['postcode']) ? $package['destination']['postcode'] : '';
                $dest_state = ($package['destination']['country'] == 'US' && !empty($package['destination']['state'])) ? $package['destination']['state'] : '';

                // Check if customer has set required destination info
                if ( ( ($dest_country == 'US' || $dest_country == 'CA') && empty($dest_postcode) ) ) {
                    return;
                }
                
                // Create the XML request
                $req = new emergeit\GetRatesRequest();
                $req->From->Attention = '-';
                $req->From->Phone = '-';
                $req->From->CompanyName = '-';
                $req->From->StreetAddress = '-';
                $req->From->CountryCode = $this->shiptime_auth->country;
                $req->From->PostalCode = $this->shiptime_auth->zip;
                $req->From->Province = $this->shiptime_auth->state;
                $req->From->City = $this->shiptime_auth->city;
                $req->From->Notify = false;
                $req->From->Residential = false;

                $req->To->Attention = '-';
                $req->To->Phone = '-';
                $req->To->CompanyName = '-';
                $req->To->StreetAddress = '-';
                $req->To->CountryCode = $dest_country;
                $req->To->PostalCode = $dest_postcode;
                $req->To->Province = $dest_state;
                $req->To->City = '-';
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
                    if ($values['data']->length && $values['data']->height && $values['data']->width) {
                        $item["length"] = woocommerce_get_dimension($values['data']->length, 'in');
                        $item["width"] = woocommerce_get_dimension($values['data']->width, 'in');
                        $item["height"] = woocommerce_get_dimension($values['data']->height, 'in');                        
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
                    $ic->CountryCode = '-';
                    $ic->Email = '-';
                    $ic->Phone = '-';
                    $ic->PostalCode = '-';
                    $ic->Province = '-';
                    $ic->StreetAddress = '-';
                    $ic->CustomsBroker = '-';
                    $ic->ShipperTaxId = '-';
                    $req->CustomsInvoice->InvoiceContact = $ic;

                    $req->CustomsInvoice->InvoiceItems = array();
                    foreach ($items as $item) {
                        $i = new emergeit\InvoiceItem();
                        $i->Code = get_post_meta($item['id'], 'shiptime_hs_code', true);
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
                $packages = $sh->package($this->boxes);
                
                foreach ($packages as $package) {
                    $item = new emergeit\LineItem();

                    $item->Length->UnitsType = $package->getDimUnit();
                    $item->Length->Value = $package->getLength();
                    $item->Width->UnitsType = $package->getDimUnit();
                    $item->Width->Value = $package->getWidth();
                    $item->Height->UnitsType = $package->getDimUnit();
                    $item->Height->Value = $package->getHeight();
                    $item->Weight->UnitsType = $package->getWeightUnit();
                    $item->Weight->Value = $package->getWeight();
                    $item->Description = 'Item Line Description';
                    
                    $req->ShipmentItems[] = $item;
                }

                // Unique identifier for cart items & destiniation
                $request_identifier = serialize($items) . $dest_country . $dest_postcode;

                // Check for cached response
                $transient = 'st_quote_' . md5($request_identifier);
                $cached_response = get_transient($transient);
                
                $shipRates = array();
                $cached = false;

                if ($cached_response !== false) {
                    // Cached response
                    $cached = true;
                    $shipRates = unserialize($cached_response);
                } else {
                    // New API call
                    $shipRates = $this->ratingClient->getRates($req);

                    if ($shipRates) {
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
                    // Store response in the current user's session
                    // Used to retrieve package level details later
                    //$woocommerce->session->shiptime_response = $shipRates;
                    if (!$cached) {
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
                        $l = strpos($shipRate->ServiceName, $shipRate->CarrierName) !== false ? $shipRate->ServiceName : $shipRate->CarrierName . " ". $shipRate->ServiceName;
                        $c = ($is_domestic && strpos($shipRate->ServiceName, 'Ground') !== false && !empty($this->shipping_threshold) && (float)$woocommerce->cart->cart_contents_total >= $this->shipping_threshold) ? 0 : $shipRate->TotalCharge->Amount/100.00;
                        $rate = array(
                            'id'    => $this->id . ':' . $shipRate->ServiceId,
                            'label' => $l,
                            'cost'  => $c
                        );
                        $this->add_rate($rate);
                    }

                } else {
                    // Add fallback shipping rate if merchant has configured this setting
                    if (!empty($this->fallback_type) && !empty($this->fallback_fee)) {
                        $cost = $this->fallback_type === 'per_order' ? $this->fallback_fee : $woocommerce->cart->cart_contents_count * $this->fallback_fee;
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

}
