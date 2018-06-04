<?php
/**
 * WC_ShipTime_Signup
 * Automatically register for ShipTime from within WordPress
 *
 * Takes new merchants through configuration of ShipTime account
 *
 * @author      travism
 * @version     1.0
*/
class WC_ShipTime_Signup {

	/**
	 * Hook in tabs
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menus'  ) );
		add_action( 'admin_init', array( $this, 'shiptime_signup' ) );
		add_action( 'admin_init', array( $this, 'shiptime_redirect' ) );
	}

	/**
	 * Add admin menus/screens
	 */
	public function admin_menus() {
		add_dashboard_page( '', '', 'manage_options', 'shiptime-signup', '' );
	}

	/**
	 * Handle redirects to signup page after install
	 */
	public function shiptime_redirect() {
		global $wpdb;

		if ( ! get_transient( 'shiptime_signup_redirect' ) ) {
			return;
		}

		delete_transient( 'shiptime_signup_redirect' );
		set_transient( 'shiptime_signup_required', 1, 0 ); // 0 = never expires

		$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");

		$shiptime_activated = !empty($row);

		// Check if merchant already has a ShipTime account
		if ($shiptime_activated) return;

		if ( (!empty($_GET['page']) && in_array($_GET['page'], array('shiptime-signup'))) || is_network_admin() ) {
			return;
		}

		// If the user needs to signup, send to signup form
		wp_safe_redirect( admin_url( 'index.php?page=shiptime-signup' ) );
		exit;
	}

	/**
	 * Show the start screen
	 */
	public function shiptime_signup() {
		global $wpdb;

		$shiptime_activated = false;
		$row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login GROUP BY id HAVING MIN(id)");
		if ( !empty($row) ) {
			$shiptime_activated = true;
		}

		if ( empty( $_GET['page'] ) || 'shiptime-signup' !== $_GET['page'] ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
		wp_enqueue_style( 'wc-setup', WC()->plugin_url() . '/assets/css/wc-setup.css', array( 'dashicons', 'install' ), WC_VERSION );

		if (!empty($_POST['shiptime_signup'])) {
			call_user_func( array($this, 'shiptime_signup_screen_save') );
		}

		ob_start();
		if (!$shiptime_activated) {
			$this->shiptime_signup_screen();
		} else {
			if (get_transient('shiptime_signup_required')) {
				delete_transient('shiptime_signup_required');
			}
			$this->shiptime_signedup_screen();
		}
		exit;
	}

	public static function get_default_values($user_info) {
		$defaults = array(
			'shiptime_address' => '',
			'shiptime_city' => '',
			'shiptime_company' => '',
			'shiptime_country' => '',
			'shiptime_email' => '',
			'shiptime_passwd' => '',
			'shiptime_first_name' => '',
			'shiptime_last_name' => '',
			'shiptime_lang' => '',
			'shiptime_phone' => '',
			'shiptime_postal_code' => '',
			'shiptime_state' => ''
		);

		foreach (array_keys($defaults) as $field) {
			if (isset($_POST[$field])) {
				$defaults[$field] = sanitize_text_field($_POST[$field]);
			} else {
				switch ($field) {
					case 'shiptime_address':
						$defaults[$field] = !empty($user_info['billing_address_1'][0]) ? ucwords($user_info['billing_address_1'][0]) : ucwords($user_info['shipping_address_1'][0]);
						break;
					case 'shiptime_city':
						$defaults[$field] = !empty($user_info['billing_city'][0]) ? ucwords($user_info['billing_city'][0]) : ucwords($user_info['shipping_city'][0]);
						break;
					case 'shiptime_company':
						$defaults[$field] = !empty($user_info['billing_company'][0]) ? $user_info['billing_company'][0] : $user_info['shipping_company'][0];
						break;
					case 'shiptime_country':
						$defaults[$field] = !empty($user_info['billing_country'][0]) ? $user_info['billing_country'][0] : $user_info['shipping_country'][0];
						break;
					case 'shiptime_email':
						$defaults[$field] = !empty($user_info['billing_email'][0]) ? $user_info['billing_email'][0] : $user_info['shipping_email'][0];
						break;
					case 'shiptime_passwd':
						break;
					case 'shiptime_first_name':
						$defaults[$field] = !empty($user_info['billing_first_name'][0]) ? ucwords($user_info['billing_first_name'][0]) : ucwords($user_info['shipping_first_name'][0]);
						break;
					case 'shiptime_last_name':
						$defaults[$field] = !empty($user_info['billing_last_name'][0]) ? ucwords($user_info['billing_last_name'][0]) : ucwords($user_info['shipping_last_name'][0]);
						break;
					case 'shiptime_lang':
						break;
					case 'shiptime_phone':
						$defaults[$field] = !empty($user_info['billing_phone'][0]) ? $user_info['billing_phone'][0] : $user_info['shipping_phone'][0];
						break;
					case 'shiptime_postal_code':
						$defaults[$field] = !empty($user_info['billing_postcode'][0]) ? $user_info['billing_postcode'][0] : $user_info['shipping_postcode'][0];
						break;
					case 'shiptime_state':
						$defaults[$field] = !empty($user_info['billing_state'][0]) ? $user_info['billing_state'][0] : $user_info['shipping_state'][0];
						break;
					default:
						break;
				}
			}
		}

		return $defaults;
	}

	/**
	 * ShipTime Signup Form
	 */
	public function shiptime_signup_screen() {
		$user_info = get_user_meta(get_current_user_id());
		$defaults = $this->get_default_values($user_info);

		$shiptime_address = $defaults['shiptime_address'];
		$shiptime_city = $defaults['shiptime_city'];
		$shiptime_company = $defaults['shiptime_company'];
		$shiptime_country = $defaults['shiptime_country'];
		$shiptime_email = $defaults['shiptime_email'];
		$shiptime_passwd = $defaults['shiptime_passwd'];
		$shiptime_first_name = $defaults['shiptime_first_name'];
		$shiptime_last_name = $defaults['shiptime_last_name'];
		$shiptime_lang = $defaults['shiptime_lang'];
		$shiptime_phone = $defaults['shiptime_phone'];
		$shiptime_postal_code = $defaults['shiptime_postal_code'];
		$shiptime_state = $defaults['shiptime_state'];
		?>
		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php _e( 'WooCommerce &rsaquo; Setup Wizard', 'woocommerce' ); ?></title>
			<?php wp_print_scripts( 'shiptime-signup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
			<style>
			div.error {
			    margin: 5px 0 15px 0;
			    background: #fff;
			    border-left: 4px solid #dc3232;
			    -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
			    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
			    padding: 12px;
			}
			div.wc-setup-content {
				/*display: none;*/
			}
			</style>
		</head>
		<body style="margin-top:20px !important" class="wc-setup wp-core-ui">
			<img src="http://www.shiptime.com/img/logo-shiptime.png" alt="ShipTime" />
			<h1>ShipTime Profile</h1>
			<?php
				if (isset($_SESSION['error'])) {
					$defaults = $_SESSION['defaults']; unset($_SESSION['defaults']);
					$shiptime_address = $defaults['shiptime_address'];
					$shiptime_city = $defaults['shiptime_city'];
					$shiptime_company = $defaults['shiptime_company'];
					$shiptime_country = $defaults['shiptime_country'];
					$shiptime_email = $defaults['shiptime_email'];
					$shiptime_passwd = $defaults['shiptime_passwd'];
					$shiptime_first_name = $defaults['shiptime_first_name'];
					$shiptime_last_name = $defaults['shiptime_last_name'];
					$shiptime_lang = $defaults['shiptime_lang'];
					$shiptime_phone = $defaults['shiptime_phone'];
					$shiptime_postal_code = $defaults['shiptime_postal_code'];
					$shiptime_state = $defaults['shiptime_state'];
			?>
				<div class="error">
					<?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
				</div>
			<?php
				}
			?>
			<div class="wc-setup-content">
				<form method="post">
					<table class="wc-setup-pages form-table" cellspacing="0">
						<thead>
							<tr>
								<th class="page-name"><?php _e( 'Option', 'woocommerce' ); ?></th>
								<th class="page-description"><?php _e( 'Value', 'woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="page-name"><?php echo _x( 'First Name', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_first_name" name="shiptime_first_name" value="<?php echo esc_attr( $shiptime_first_name ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Last Name', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_last_name" name="shiptime_last_name" value="<?php echo esc_attr( $shiptime_last_name ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Email', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_email" name="shiptime_email" value="<?php echo esc_attr( $shiptime_email ) ; ?>" /></td>
							</tr>
							<?php if (!$_GET['new_signup']) { ?>
							<tr>
								<td class="page-name">
									<?php echo _x( 'Encrypted Username', 'Page title', 'woocommerce' ); ?>
								</td>
								<td><input type="text" id="shiptime_user" name="shiptime_user" value="<?php echo esc_attr( $shiptime_user ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name">
									<?php echo _x( 'Encrypted Password', 'Page title', 'woocommerce' ); ?>
								</td>
								<td><input type="text" id="shiptime_passwd" name="shiptime_passwd" value="<?php echo esc_attr( $shiptime_passwd ) ; ?>" /></td>
							</tr>
							<?php } else { ?>
							<tr>
								<td class="page-name"><?php echo _x( 'Password', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="password" id="shiptime_passwd" name="shiptime_passwd" value="<?php echo esc_attr( $shiptime_passwd ) ; ?>" /></td>
							</tr>
							<?php } ?>
							<tr>
								<td class="page-name"><?php echo _x( 'Company', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_company" name="shiptime_company" value="<?php echo esc_attr( $shiptime_company ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Address', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_address" name="shiptime_address" value="<?php echo esc_attr( $shiptime_address ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Country', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select onchange="loadStates()" id="shiptime_country" name="shiptime_country" style="height:28px !important">
										<option value=""><?php _e( 'Select a country&hellip;', 'woocommerce' ); ?></option>
									<?php
										$shiptime_country = isset($_POST['shiptime_country']) ? $_POST['shiptime_country'] : 'CA';
										foreach( WC()->countries->get_shipping_countries() as $key => $value )
											echo '<option value="' . esc_attr( $key ) . '"' . ($key==$shiptime_country ? ' selected' : '') . '>' . esc_html( $value ) . '</option>';
									?>
									</select>
								</td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'City', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_city" name="shiptime_city" value="<?php echo esc_attr( $shiptime_city ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'State', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select id="shiptime_state" name="shiptime_state" style="height:28px !important">
										<option value=""><?php _e( 'Select a state&hellip;', 'woocommerce' ); ?></option>
									<?php
										foreach( WC()->countries->get_states( $shiptime_country ) as $ckey => $cvalue )
											echo '<option value="' . esc_attr( $ckey ) . '" ' . selected( $shiptime_state, $ckey, false ) . '>' . __( esc_html( $cvalue ), 'woocommerce' ) .'</option>';
									?>
									</select>
								</td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Postal Code', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_postal_code" name="shiptime_postal_code" value="<?php echo esc_attr( $shiptime_postal_code ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Phone', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_phone" name="shiptime_phone" value="<?php echo esc_attr( $shiptime_phone ) ; ?>" /></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Language', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select name="shiptime_lang" style="height:28px !important">
										<option value=""><?php _e( 'English (EN)', 'woocommerce' ); ?></option>
										<option value=""><?php _e( 'French (FR)', 'woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<p><input type="submit" class="button-primary button button-large" value="Sign Up" name="shiptime_signup" /></p>
					<?php wp_nonce_field( 'shiptime-signup' ); ?>
				</form>
			</div>
			<a class="wc-return-to-dashboard" href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Return to the WordPress Dashboard', 'woocommerce' ); ?></a>
		</body>
		</html>
		<script type="text/javascript">
			function loadStates() {
				var country = document.getElementById("shiptime_country");
				var statesHtml = document.getElementById("shiptime_state");

				if (country.value == "US") {
					statesHtml.innerHTML= '<option value="">Select a state…</option><option value="AL">Alabama</option><option value="AK">Alaska</option><option value="AZ">Arizona</option><option value="AR">Arkansas</option><option value="CA">California</option><option value="CO">Colorado</option><option value="CT">Connecticut</option><option value="DE">Delaware</option><option value="DC">District Of Columbia</option><option value="FL">Florida</option><option value="GA">Georgia</option><option value="HI">Hawaii</option><option value="ID">Idaho</option><option value="IL">Illinois</option><option value="IN">Indiana</option><option value="IA">Iowa</option><option value="KS">Kansas</option><option value="KY">Kentucky</option><option value="LA">Louisiana</option><option value="ME">Maine</option><option value="MD">Maryland</option><option value="MA">Massachusetts</option><option value="MI">Michigan</option><option value="MN">Minnesota</option><option value="MS">Mississippi</option><option value="MO">Missouri</option><option value="MT">Montana</option><option value="NE">Nebraska</option><option value="NV">Nevada</option><option value="NH">New Hampshire</option><option value="NJ">New Jersey</option><option value="NM">New Mexico</option><option value="NY">New York</option><option value="NC">North Carolina</option><option value="ND">North Dakota</option><option value="OH">Ohio</option><option value="OK">Oklahoma</option><option value="OR">Oregon</option><option value="PA">Pennsylvania</option><option value="RI">Rhode Island</option><option value="SC">South Carolina</option><option value="SD">South Dakota</option><option value="TN">Tennessee</option><option value="TX">Texas</option><option value="UT">Utah</option><option value="VT">Vermont</option><option value="VA">Virginia</option><option value="WA">Washington</option><option value="WV">West Virginia</option><option value="WI">Wisconsin</option><option value="WY">Wyoming</option><option value="AA">Armed Forces (AA)</option><option value="AE">Armed Forces (AE)</option><option value="AP">Armed Forces (AP)</option>';
				} else if (country.value == "CA") {
					statesHtml.innerHTML = '<option value="">Select a state…</option><option value="AB">Alberta</option><option value="BC">British Columbia</option><option value="MB">Manitoba</option><option value="NB">New Brunswick</option><option value="NL">Newfoundland and Labrador</option><option value="NT">Northwest Territories</option><option value="NS">Nova Scotia</option><option value="NU">Nunavut</option><option value="ON" selected="selected">Ontario</option><option value="PE">Prince Edward Island</option><option value="QC">Quebec</option><option value="SK">Saskatchewan</option><option value="YT">Yukon Territory</option>';
				} else {
					statesHtml.innerHTML = '<option value="">Selected Country not supported</option>';
				}
			}
		</script>		
		<?php
	}

	/**
	 * Save ShipTime Signup Form
	 * Make call to Signup API, Store API credentials for later use
	 * Upon success, redirect user to Dashboard
	 * Upon failure, validate form data
	 */
	public function shiptime_signup_screen_save() {
		global $wpdb;

		check_admin_referer( 'shiptime-signup' );

		// Parse out all user input
		$shiptime_address = sanitize_text_field($_POST['shiptime_address']);
		$shiptime_city = sanitize_text_field($_POST['shiptime_city']);
		$shiptime_company = sanitize_text_field($_POST['shiptime_company']);
		$shiptime_country = sanitize_text_field($_POST['shiptime_country']);
		$shiptime_email = sanitize_text_field($_POST['shiptime_email']);
		$shiptime_user = sanitize_text_field($_POST['shiptime_user']);
		$shiptime_passwd = sanitize_text_field($_POST['shiptime_passwd']);
		$shiptime_first_name = sanitize_text_field($_POST['shiptime_first_name']);
		$shiptime_last_name = sanitize_text_field($_POST['shiptime_last_name']);
		$shiptime_lang = sanitize_text_field($_POST['shiptime_lang']);
		if (empty($shiptime_lang)) { $shiptime_lang = 'EN'; }
		$shiptime_phone = sanitize_text_field($_POST['shiptime_phone']);
		$shiptime_postal_code = sanitize_text_field($_POST['shiptime_postal_code']);
		$shiptime_state = sanitize_text_field($_POST['shiptime_state']);

		if (!empty($shiptime_user)) {
			// Current ShipTime Merchant: Store Info
			$shiptime_login = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
			$shiptime_login_id = empty($shiptime_login) ? 0 : $shiptime_login->id;
			if (empty($shiptime_login_id)) {
				$wpdb->insert(
					"{$wpdb->prefix}shiptime_login",
					array(
						'username' => $shiptime_user,
						'password' => $shiptime_passwd,
						'first_name' => $shiptime_first_name,
						'last_name' => $shiptime_last_name,
						'email' => $shiptime_email,
						'company' => $shiptime_company,
						'address' => $shiptime_address,
						'country' => $shiptime_country,
						'city' => $shiptime_city,
						'state' => $shiptime_state,
						'zip' => $shiptime_postal_code,
						'phone' => $shiptime_phone,
						'lang' => $shiptime_lang
					)
				);
				delete_transient( 'shiptime_signup_required' );
				set_transient( 'shiptime_signup_success', 1, 30 );
			} else {
				$wpdb->update(
					"{$wpdb->prefix}shiptime_login",
					array(
						'username' => $shiptime_user,
						'password' => $shiptime_passwd,
						'first_name' => $shiptime_first_name,
						'last_name' => $shiptime_last_name,
						'email' => $shiptime_email,
						'company' => $shiptime_company,
						'address' => $shiptime_address,
						'country' => $shiptime_country,
						'city' => $shiptime_city,
						'state' => $shiptime_state,
						'zip' => $shiptime_postal_code,
						'phone' => $shiptime_phone,
						'lang' => $shiptime_lang
					),
					array( 'id' => $shiptime_login_id )
				);
			}
			wp_redirect( admin_url() );
			exit;
		} else {
			// New ShipTime Merchant: Submit Signup Request
			require_once(dirname(__FILE__).'/../connector/SignupClient.php');
			$signupClient = new emergeit\SignupClient();

			$req = new emergeit\SignupRequest();

			$req->IntegrationID = "85566cfb-9d0e-421b-bc78-649a1711a3ea";
			$req->Address = $shiptime_address;
			$req->City = $shiptime_city;
			$req->CompanyName = $shiptime_company;
			$req->Country = $shiptime_country;
			$req->Email = $shiptime_email;
			$req->Password = $shiptime_passwd;
			$req->FirstName = $shiptime_first_name;
			$req->LastName = $shiptime_last_name;
			$req->Language = $shiptime_lang;
			$req->Phone = $shiptime_phone;
			$req->PostalCode = $shiptime_postal_code;
			$req->State = $shiptime_state;

			$resp = $signupClient->signup($req);

			if (!empty($resp->key->EncryptedUsername)) {
				// Success
				$wpdb->insert(
					"{$wpdb->prefix}shiptime_login",
					array(
						'username' => $resp->key->EncryptedUsername,
						'password' => $resp->key->EncryptedPassword,
						'first_name' => $shiptime_first_name,
						'last_name' => $shiptime_last_name,
						'email' => $shiptime_email,
						'company' => $shiptime_company,
						'address' => $shiptime_address,
						'country' => $shiptime_country,
						'city' => $shiptime_city,
						'state' => $shiptime_state,
						'zip' => $shiptime_postal_code,
						'phone' => $shiptime_phone,
						'lang' => $shiptime_lang
					)
				);
				delete_transient( 'shiptime_signup_required' );
				set_transient( 'shiptime_signup_success', 1, 30 );
				wp_redirect( admin_url() );
				exit;
			} else {
				// Failure
				$_SESSION['error'] = array_shift($resp->Messages)->Text;
				$_SESSION['defaults'] = array(
					'shiptime_address' => $shiptime_address,
					'shiptime_city' => $shiptime_city,
					'shiptime_company' => $shiptime_company,
					'shiptime_country' => $shiptime_country,
					'shiptime_email' => $shiptime_email,
					'shiptime_passwd' => $shiptime_passwd,
					'shiptime_first_name' => $shiptime_first_name,
					'shiptime_last_name' => $shiptime_last_name,
					'shiptime_lang' => $shiptime_lang,
					'shiptime_phone' => $shiptime_phone,
					'shiptime_postal_code' => $shiptime_postal_code,
					'shiptime_state' => $shiptime_state
				);
			}
		}
	}

	/**
	 * ShipTime Already Signed Up
	 */
	public function shiptime_signedup_screen() {
		?>
		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title>ShipTime Profile Details</title>
			<?php wp_print_scripts( 'shiptime-signup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body style="margin-top:20px !important" class="wc-setup wp-core-ui">
			<img src="http://www.shiptime.com/img/logo-shiptime.png" alt="ShipTime" />
			<h1>Update ShipTime Profile</h1>
			<p>The following information is used when processing shipments from your WooCommerce orders.</p>
			<?php
				global $wpdb;
				$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
			?>
			<div class="wc-setup-content">
				<form method="post">
					<table class="wc-setup-pages form-table" cellspacing="0">
						<thead>
							<tr>
								<th class="page-name"><?php _e( 'Option', 'woocommerce' ); ?></th>
								<th class="page-description"><?php _e( 'Value', 'woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td class="page-name"><?php echo _x( 'First Name', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_first_name" name="shiptime_first_name" value="<?php echo esc_attr( $shiptime_auth->first_name ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Last Name', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_last_name" name="shiptime_last_name" value="<?php echo esc_attr( $shiptime_auth->last_name ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Email', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_email" name="shiptime_email" value="<?php echo esc_attr( $shiptime_auth->email ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name">
									<?php echo _x( 'Encrypted Username', 'Page title', 'woocommerce' ); ?>
								</td>
								<td><input type="text" id="shiptime_user" name="shiptime_user" value="<?php echo esc_attr( $shiptime_auth->username ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name">
									<?php echo _x( 'Encrypted Password', 'Page title', 'woocommerce' ); ?>
								</td>
								<td><input type="text" id="shiptime_passwd" name="shiptime_passwd" value="<?php echo esc_attr( $shiptime_auth->password ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Company', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_company" name="shiptime_company" value="<?php echo esc_attr( $shiptime_auth->company ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Address', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_address" name="shiptime_address" value="<?php echo esc_attr( $shiptime_auth->address ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Country', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select onchange="loadStates()" id="shiptime_country" name="shiptime_country" style="height:28px !important" required>
										<option value=""><?php _e( 'Select a country&hellip;', 'woocommerce' ); ?></option>
									<?php
										$shiptime_country = $shiptime_auth->country;
										foreach( WC()->countries->get_shipping_countries() as $key => $value )
											echo '<option value="' . esc_attr( $key ) . '"' . ($key==$shiptime_country ? ' selected' : '') . '>' . esc_html( $value ) . '</option>';
									?>
									</select>
								</td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'City', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_city" name="shiptime_city" value="<?php echo esc_attr( $shiptime_auth->city ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'State', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select id="shiptime_state" name="shiptime_state" style="height:28px !important" required>
										<option value=""><?php _e( 'Select a state&hellip;', 'woocommerce' ); ?></option>
									<?php
										foreach( WC()->countries->get_states( $shiptime_country ) as $ckey => $cvalue )
											echo '<option value="' . esc_attr( $ckey ) . '" ' . selected( $shiptime_auth->state, $ckey, false ) . '>' . __( esc_html( $cvalue ), 'woocommerce' ) .'</option>';
									?>
									</select>
								</td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Postal Code', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_postal_code" name="shiptime_postal_code" value="<?php echo esc_attr( $shiptime_auth->zip ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Phone', 'Page title', 'woocommerce' ); ?></td>
								<td><input type="text" id="shiptime_phone" name="shiptime_phone" value="<?php echo esc_attr( $shiptime_auth->phone ) ; ?>" required/></td>
							</tr>
							<tr>
								<td class="page-name"><?php echo _x( 'Language', 'Page title', 'woocommerce' ); ?></td>
								<td>
									<select name="shiptime_lang" style="height:28px !important" required>
										<option value="EN"<?php echo selected( $shiptime_auth->lang, 'EN', false ); ?>><?php _e( 'English (EN)', 'woocommerce' ); ?></option>
										<option value="FR"<?php echo selected( $shiptime_auth->lang, 'FR', false ); ?>><?php _e( 'French (FR)', 'woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
					<p><input type="submit" class="button-primary button button-large" value="Update Profile" name="shiptime_signup" /></p>
					<?php wp_nonce_field( 'shiptime-signup' ); ?>
				</form>
			</div>
			<a class="wc-return-to-dashboard" href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Return to the WordPress Dashboard', 'woocommerce' ); ?></a>
		</body>
		</html>
		<script type="text/javascript">
			function loadStates() {
				var country = document.getElementById("shiptime_country");
				var statesHtml = document.getElementById("shiptime_state");

				if (country.value == "US") {
					statesHtml.innerHTML= '<option value="">Select a state…</option><option value="AL">Alabama</option><option value="AK">Alaska</option><option value="AZ">Arizona</option><option value="AR">Arkansas</option><option value="CA">California</option><option value="CO">Colorado</option><option value="CT">Connecticut</option><option value="DE">Delaware</option><option value="DC">District Of Columbia</option><option value="FL">Florida</option><option value="GA">Georgia</option><option value="HI">Hawaii</option><option value="ID">Idaho</option><option value="IL">Illinois</option><option value="IN">Indiana</option><option value="IA">Iowa</option><option value="KS">Kansas</option><option value="KY">Kentucky</option><option value="LA">Louisiana</option><option value="ME">Maine</option><option value="MD">Maryland</option><option value="MA">Massachusetts</option><option value="MI">Michigan</option><option value="MN">Minnesota</option><option value="MS">Mississippi</option><option value="MO">Missouri</option><option value="MT">Montana</option><option value="NE">Nebraska</option><option value="NV">Nevada</option><option value="NH">New Hampshire</option><option value="NJ">New Jersey</option><option value="NM">New Mexico</option><option value="NY">New York</option><option value="NC">North Carolina</option><option value="ND">North Dakota</option><option value="OH">Ohio</option><option value="OK">Oklahoma</option><option value="OR">Oregon</option><option value="PA">Pennsylvania</option><option value="RI">Rhode Island</option><option value="SC">South Carolina</option><option value="SD">South Dakota</option><option value="TN">Tennessee</option><option value="TX">Texas</option><option value="UT">Utah</option><option value="VT">Vermont</option><option value="VA">Virginia</option><option value="WA">Washington</option><option value="WV">West Virginia</option><option value="WI">Wisconsin</option><option value="WY">Wyoming</option><option value="AA">Armed Forces (AA)</option><option value="AE">Armed Forces (AE)</option><option value="AP">Armed Forces (AP)</option>';
				} else if (country.value == "CA") {
					statesHtml.innerHTML = '<option value="">Select a state…</option><option value="AB">Alberta</option><option value="BC">British Columbia</option><option value="MB">Manitoba</option><option value="NB">New Brunswick</option><option value="NL">Newfoundland and Labrador</option><option value="NT">Northwest Territories</option><option value="NS">Nova Scotia</option><option value="NU">Nunavut</option><option value="ON" selected="selected">Ontario</option><option value="PE">Prince Edward Island</option><option value="QC">Quebec</option><option value="SK">Saskatchewan</option><option value="YT">Yukon Territory</option>';
				} else {
					statesHtml.innerHTML = '<option value="">Selected Country not supported</option>';
				}
			}
		</script>		
		<?php
	}

}

new WC_ShipTime_Signup();
