<?php
/*
 *  Plugin Name: ShipTime for WooCommerce
 *  Plugin URI: http://www.shiptime.com
 *  Description: Real-time shipping rates, label printing, and shipment tracking for your WooCommerce orders.
 *  Version: 0.0.29
 *  Author: ShipTime
 *  Author URI: http://www.shiptime.com
 *
 *  WC requires at least: 3.0.0
 *	WC tested up to: 3.8.0
 *
 *  Copyright: � 2019 ShipTime
 *  License: GNU General Public License v3.0
 *  License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) {
	exit;
}

// This plugin requires an active installation of WooCommerce
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	class ShipTime_WooCommerce {

		public function __construct() {
			// Check that the plugin requirements are being met by this web server
			add_action('admin_notices', array($this, 'check_requirements'));

			// Activation hooks
			register_activation_hook(__FILE__, array($this, 'shiptime_activation_check'));
			register_activation_hook(__FILE__, array($this, 'shiptime_database_check'));

			// Localization (To add French translation)
			// load_plugin_textdomain('wc_shiptime', false, dirname(plugin_basename(__FILE__)) . '/languages/');

			// Normal Actions and Filters
			add_action('init', array($this, 'init'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_links'));
			add_action('woocommerce_shipping_init', array($this, 'shipping_init'));
			add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
			add_action('woocommerce_cart_collaterals', array($this, 'wc_shiptime_cart_css'));
			add_action('woocommerce_before_cart_contents', array($this, 'wc_shiptime_debug_output1'));
			add_action('woocommerce_after_cart_contents', array($this, 'wc_shiptime_cart_js'));
			add_filter('woocommerce_add_to_cart_fragments', array($this, 'wc_shiptime_debug_output2'));
			add_action('woocommerce_checkout_order_processed', array($this, 'save_shipping_details'));
			add_action('woocommerce_product_options_shipping', array($this, 'add_product_fields'));
			add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
			add_action('admin_notices', array($this, 'show_notices'));
			add_action('admin_enqueue_scripts', array($this, 'load_js'));
		}

		// Check for ShipTime account
		public function shiptime_activation_check() {
			set_transient('shiptime_signup_redirect', 1, 30);
		}

		// Check for ShipTime database tables
		public function shiptime_database_check() {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			require_once(ABSPATH.'wp-admin/includes/upgrade.php');

			// Table: shiptime_login
			$table_name = $wpdb->prefix . 'shiptime_login';
			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				username varchar(255) DEFAULT '' NOT NULL,
				password varchar(255) DEFAULT '' NOT NULL,
				integration_id varchar(64) DEFAULT '' NOT NULL,
				first_name varchar(64) DEFAULT '' NOT NULL,
				last_name varchar(64) DEFAULT '' NOT NULL,
				email varchar(255) DEFAULT '' NOT NULL,
				company varchar(127) DEFAULT '' NOT NULL,
				address varchar(255) DEFAULT '' NOT NULL,
				country varchar(64) DEFAULT '' NOT NULL,
				city varchar(64) DEFAULT '' NOT NULL,
				state varchar(64) DEFAULT '' NOT NULL,
				zip varchar(20) DEFAULT '' NOT NULL,
				phone varchar(20) DEFAULT '' NOT NULL,
				lang varchar(20) DEFAULT '' NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			dbDelta( $sql );

			// Additional column: integration_id
			$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
					"WHERE table_name = '" . $wpdb->prefix . "shiptime_login' " .
					"AND column_name = 'integration_id'";
			$row = $wpdb->get_results($sql);
			if (empty($row)) {
				$sql = "ALTER TABLE " . $wpdb->prefix . 'shiptime_login' .
						" ADD COLUMN integration_id VARCHAR(64) NOT NULL AFTER password";
				$wpdb->query($sql);
				// Set value of integration_id based on country
				$sql = "SELECT country FROM " . $wpdb->prefix . 'shiptime_login' .
						" ORDER BY id DESC LIMIT 1";
				$var = $wpdb->get_var($sql);
				$sql = "UPDATE " . $wpdb->prefix . 'shiptime_login' .
						" SET integration_id = '";
				if ($var === 'CA') {
					// WooCommerce Canada = 9af5f7f4-1f07-61e8-1c2d-df7ae01bbeff
					$sql .= "9af5f7f4-1f07-61e8-1c2d-df7ae01bbeff'";
				}
				if ($var === 'US') {
					// WooCommerce U.S.A. = c42a72d0-100f-441e-9611-502aee4d8059
					$sql .= "c42a72d0-100f-441e-9611-502aee4d8059'";
				}
				$sql .= " LIMIT 100";
				$wpdb->query($sql);
			}

			// Table: shiptime_order
			$table_name = $wpdb->prefix . 'shiptime_order';
			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				post_id mediumint(9) NOT NULL,
				package_data text NOT NULL,
				shipping_service varchar(255) DEFAULT '' NOT NULL,
				insurance_type enum('', 'CARRIER', 'SHIPTIME') DEFAULT '' NOT NULL,
				tracking_nums text NOT NULL,
				label_url text NOT NULL,
				invoice_url text NOT NULL,
				emergeit_id varchar(255) DEFAULT '' NOT NULL,
				box_codes text NOT NULL,
				recalc text NOT NULL,
				quoted_rate decimal(8,2) NOT NULL,
				markup_rate decimal(8,2) NOT NULL,
				recalc_rate decimal(8,2) NOT NULL,
				taxes decimal(8,2) NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			dbDelta( $sql );

			// Additional column: insurance_type
			$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
					"WHERE table_name = '" . $wpdb->prefix . "shiptime_order' " .
					"AND column_name = 'insurance_type'";
			$row = $wpdb->get_results($sql);
			if (empty($row)) {
				$sql = "ALTER TABLE " . $wpdb->prefix . 'shiptime_order' .
						" ADD COLUMN insurance_type ENUM('', 'CARRIER', 'SHIPTIME') NOT NULL" .
						" DEFAULT '' AFTER shipping_service";
				$wpdb->query($sql);
			}

			// Table: shiptime_quote
			$table_name = $wpdb->prefix . 'shiptime_quote';
			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				order_id mediumint(9) NOT NULL,
				cart_sessid varchar(255) DEFAULT '' NOT NULL,
				shipping_method varchar(255) DEFAULT '' NOT NULL,
				quote text NOT NULL,
				packages text NOT NULL,
				debug text NOT NULL,
				UNIQUE KEY id (id)
			) $charset_collate;";
			dbDelta( $sql );

			if (!$wpdb->query("SELECT * FROM wp_woocommerce_tax_rates WHERE tax_rate_name = 'ShipTime'")) {
				// 0% tax for shipping (handled by ShipTime)
				$shiptime_tax_rate = array(
					'tax_rate_country'  => '',
					'tax_rate_state'    => '',
					'tax_rate'          => 0.00,
					'tax_rate_name'     => 'ShipTime',
					'tax_rate_priority' => 1,
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => 1,
					'tax_rate_order'    => 0,
					'tax_rate_class'    => 'zero-rate'
				);
				WC_Tax::_insert_tax_rate( $shiptime_tax_rate );
			}

			// Shipping Tax Class: Zero Rate
			// ShipTime API handles all shipping taxes natively as necessary
			update_option('woocommerce_shipping_tax_class', 'zero-rate');

			// Enable shipping calculator on cart page
			update_option('woocommerce_enable_shipping_calc', 'yes');

			// Hide shipping costs until an address is entered
			// ShipTime API requires FULL address info before rating
			update_option('woocommerce_shipping_cost_requires_address', 'yes');
		}

		// Files to load every time class is instantiated
		public function init() {
			// Signup API for offsite registration
			require_once ('includes/class-wc-shiptime-signup.php');
			// Enhancements to Woo Orders page
			require_once ('includes/class-wc-shiptime-order.php');
		}

		// Links for Plugins page
		public function plugin_links($links) {
			$plugin_links = array(
				'<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wc_shipping_shiptime') . '">Settings</a>',
				'<a href="' . admin_url('index.php?page=shiptime-signup&action=update') . '">Profile Details</a>',
				'<a href="http://shiptime.com">Support</a>'
			);

			return array_merge($plugin_links, $links);
		}

		// Require all core files
		public function shipping_init() {
			// Packaging classes
			require_once ('includes/box.php');
			require_once ('includes/package.php');
			require_once ('includes/shipment.php');
			// Helper & Utility classes
			require_once ('includes/shipping-service.php');
			// Main class responsible for generating shipping rates
			require_once ('includes/class-wc-shiptime-shipping.php');
		}

		// Add ShipTime as Woo shipping method
		public function add_shipping_method($methods) {
			$methods[] = 'WC_Shipping_ShipTime';
			return $methods;
		}

		// When Woo order submitted, save shipping info for default shipment values
		public function save_shipping_details($order_id) {
			global $woocommerce, $wpdb;

			$cart_sessid = array_shift(array_keys($woocommerce->session->cart));
			$quote = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_quote WHERE cart_sessid='".$cart_sessid."' ORDER BY id DESC LIMIT 1");
			try {
				$woo_order = new WC_Order($order_id);
				$shipping_method = trim(array_shift(explode('[', $woo_order->get_shipping_method())));

				// associate woo order id with cart session id
				$wpdb->update(
					"{$wpdb->prefix}shiptime_quote",
					array(
						'order_id' => $order_id,
						'cart_sessid' => $cart_sessid,
						'shipping_method' => $shipping_method
					),
					array( 'id' => $quote->id ),
					array( '%d', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} catch (Exception $e) {
				// Something went wrong...
				// this function runs during woocommerce_checkout_order_processed action
			}
		}

		// Add CSS to Cart page
		public function wc_shiptime_cart_css() {
			// Widen cart totals div so each shipping ServiceName fits on a single line
			echo '<style>
			.woocommerce .cart-collaterals .cart_totals { width: 70%; }
			.wc-proceed-to-checkout, .wc-proceed-to-checkout .button { margin-bottom: 0.5em; }
			.shiptime_debug pre { font-size: 0.9em;line-height: 1em; }
			</style>';
		}

		// Add JS to Cart page
		public function wc_shiptime_cart_js() {
			// Add HTML for Estimated Delivery below shipping rates
			wp_enqueue_script('shiptime-cart', plugins_url('js/wc-shiptime-cart-html.js', __FILE__), array('jquery'), null, true);
		}

		// Add Debug Mode Output to Cart page - Scenario 1: Initial page load
		public function wc_shiptime_debug_output1() {
			global $current_user, $woocommerce, $wpdb;

			$is_admin = (!empty($current_user->roles) && in_array('administrator', $current_user->roles)) ? true : false;
			if ($is_admin) {
				$cart_sessids = array_keys($woocommerce->session->cart);
				$cart_sessid = array_shift($cart_sessids);
				$quote = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_quote WHERE cart_sessid='".$cart_sessid."' ORDER BY id DESC LIMIT 1");
				// Add HTML for Debug Mode above shipping rates
				wp_enqueue_script('shiptime-debug', plugins_url('js/wc-shiptime-debug-html.js', __FILE__), array('jquery'), null, true);
				$data = array(
					'debug' => $quote->debug
				);
				wp_localize_script('shiptime-debug', 'php_vars', $data);
			}
		}

		// Add Debug Mode Output to Cart page - Scenario 2: AJAX; Add data to cart fragments
		public function wc_shiptime_debug_output2($fragments) {
			global $woocommerce, $wpdb;

			$cart_sessid = array_shift(array_keys($woocommerce->session->cart));
			$quote = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_quote WHERE cart_sessid='".$cart_sessid."' ORDER BY id DESC LIMIT 1");
			// Add HTML for Debug Mode above shipping rates
			$fragments['div.shiptime_debug'] = '<div class="shiptime_debug">'.$quote->debug.'</div>';
			// Add back HTML for Estimated Delivery below shipping rates
			$fragments['tr.shiptime_info'] = '<tr class="shiptime_info"><td colspan="2"><p>*[#] = Estimated Number of Business Days for Delivery</p></td></tr>';
			return $fragments;
		}

		// Add product-level shipping fields
		public function add_product_fields() {
			echo '<h2 style="font-weight:600;border-top:1px solid #eee">ShipTime Settings</h2>';

			// Shipping Method
			woocommerce_wp_select(
				array(
					'id' => 'shiptime_ship_method',
					'label' => __('Shipping Method', 'wc_shiptime'),
					'options' => array(
						'' => __('-- Select -- ', 'wc_shiptime'),
						'C' => __('Carrier Rates', 'wc_shiptime'),
						'F' => __('Flat Fee', 'wc_shiptime'),
						'Z' => __('Free', 'wc_shiptime'),
						'D' => __('Free Domestic', 'wc_shiptime')
					),
					'desc_tip' => 'true',
					'description' => '(Optional) Select the shipping method to apply to this product. Default: Carrier Rates'
				)
			);

			echo '<span class="shiptime_ff">';

			// Flat Fee for Domestic Shipments
			woocommerce_wp_text_input(
				array(
					'id' => 'shiptime_ff_dom',
					'label' => 'Flat Fee - Domestic Shipments',
					'placeholder' => '0.00',
					'type' => 'number',
					'custom_attributes' => array(
						'step' => '0.01',
						'min' => '0.01'
					),
					'desc_tip' => 'true',
					'description' => 'Enter shipping fee for this product which will be used for all domestic shipments. Fee is applied multiple times for cart quantities > 1.'
				)
			);

			// Flat Fee for Intl Shipments
			woocommerce_wp_text_input(
				array(
					'id' => 'shiptime_ff_intl',
					'label' => 'Flat Fee - International Shipments',
					'placeholder' => '0.00',
					'type' => 'number',
					'custom_attributes' => array(
						'step' => '0.01',
						'min' => '0.01'
					),
					'desc_tip' => 'true',
					'description' => 'Enter shipping fee for this product which will be used for all international shipments. Fee is applied multiple times for cart quantities > 1.'
				)
			);

			echo '</span>';

			// The following fields are required for intl shipments
			echo '<p><strong>International Shipping</strong></p>';

			// HS Code
			woocommerce_wp_text_input(
				array(
					'id' => 'shiptime_hs_code',
					'label' => 'HS Code',
					'placeholder' => '000000',
					'type' => 'text',
					'desc_tip' => 'true',
					'description' => 'Must be 6 or 10 digits'
				)
			);
			echo '<p><a href="https://www.canadapost.ca/cpotools/apps/wtz/business/findHsCode?execution=e1s1" target="_blank">HS Code Search</a></p>';

			// Country of Origin - Where product was manufactured
			woocommerce_wp_select(
				array(
					'id' => 'shiptime_origin_country',
					'label' => 'Country of Origin',
					'class' => 'select',
					'options' => array_merge(array('' => '-- Please Select --'),WC()->countries->get_allowed_countries())
				)
			);
		}

		// Save function
		public function save_product_fields($post_id) {
			// Shipping Method
			$shiptime_ship_method = $_POST['shiptime_ship_method'];
			if (!empty($shiptime_ship_method) && strlen($shiptime_ship_method) == 1) {
				update_post_meta($post_id, 'shiptime_ship_method', esc_attr($shiptime_ship_method));
			}
			else {
				delete_post_meta($post_id, 'shiptime_ship_method');
			}

			// Flat Fee Dom
			$shiptime_ff_dom = $_POST['shiptime_ff_dom'];
			if (!empty($shiptime_ff_dom) && is_numeric($shiptime_ff_dom)) {
				update_post_meta($post_id, 'shiptime_ff_dom', esc_attr($shiptime_ff_dom));
			}
			else {
				delete_post_meta($post_id, 'shiptime_ff_dom');
			}

			// Flat Fee Intl
			$shiptime_ff_intl = $_POST['shiptime_ff_intl'];
			if (!empty($shiptime_ff_intl) && is_numeric($shiptime_ff_intl)) {
				update_post_meta($post_id, 'shiptime_ff_intl', esc_attr($shiptime_ff_intl));
			}
			else {
				delete_post_meta($post_id, 'shiptime_ff_intl');
			}

			// HS Code
			$shiptime_hs_code = $_POST['shiptime_hs_code'];
			if (is_numeric($shiptime_hs_code) && (strlen($shiptime_hs_code) == 6 || strlen($shiptime_hs_code) == 10)) {
				update_post_meta($post_id, 'shiptime_hs_code', esc_attr(str_pad($shiptime_hs_code, 10, "0", STR_PAD_RIGHT)));
			}
			else {
				delete_post_meta($post_id, 'shiptime_hs_code');
			}

			// Origin Country
			$shiptime_origin_country = $_POST['shiptime_origin_country'];
			if (!empty($shiptime_origin_country) && strlen($shiptime_origin_country) <= 2) {
				update_post_meta($post_id, 'shiptime_origin_country', esc_attr($shiptime_origin_country));
			}
			else {
				delete_post_meta($post_id, 'shiptime_origin_country');
			}
		}

		// Do not install the plugin if requirements not met
		public function check_requirements() {
			// Check for SOAP extension
			if (!extension_loaded('soap')) {
				echo '<div class="error">
				<p>The PHP SOAP extension must be installed to run the ShipTime for WooCommerce plugin.</p>
				</div>';
			}
		}

		// If plugin not configured, prompt user in WP admin
		public function show_notices() {
			if (get_transient('shiptime_signup_required')) {
				echo '<div class="error">
				<p>A ShipTime account is required to receive discounted shipping rates. <a href="'. admin_url( 'index.php?page=shiptime-signup' ) . '" target="_self"> Use Built-In Signup</a> to instantly integrate discounted shipping.</p>
				</div>';
			}

			if (get_transient('shiptime_signup_success')) {
				echo '<div class="updated">
				<p>You have successfully integrated discounted shipping with ShipTime. Go to <a target="_blank" href="http://shiptime.com">shiptime.com</a> to access your account directly.</p>
				</div>';
				delete_transient('shiptime_signup_success');
			}
		}

		// Load JQuery
		public function load_js() {
			$screen = get_current_screen();

			wp_enqueue_script('jquery');

			if ($screen->base == 'woocommerce_page_wc-settings') {
				wp_enqueue_script('shiptime-settings', plugins_url('js/wc-shiptime-shipping-settings.js', __FILE__), array('jquery'), null, true);
			} elseif ($screen->base == 'post') {
				wp_enqueue_script('shiptime-product-settings', plugins_url('js/wc-shiptime-product-shipping-settings.js', __FILE__), array('jquery'), null, true);
			}
		}

	}

	new ShipTime_WooCommerce(); // Instantiate main class

} else {
	echo '<div class="error">
	<p>An installation of WooCommerce is required to use the ShipTime plugin.</p>
	</div>';
}
