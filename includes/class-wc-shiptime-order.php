<?php
/**
 * WC_Order_ShipTime
 *
 * Adds UI elements to Woo Order screen
 *
 * @author      travism
 * @version     1.0
*/
require_once(dirname(__FILE__).'/../connector/RatingClient.php');
require_once(dirname(__FILE__).'/../connector/ShippingClient.php');
require_once(dirname(__FILE__).'/../connector/SignupClient.php');
require_once(dirname(__FILE__).'/../connector/CurrencyUtil.php');

class WC_Order_ShipTime {

	// TODO: Add carriers here as they are added to the plugin
	const FEDEX_TRACKING = "https://www.fedex.com/Tracking?action=track&tracknumbers=";
	const CANPAR_TRACKING = "https://www.canpar.com/en/track/TrackingAction.do?locale=en&type=0&shipper_num=42091720&reference=";
	const CANPOST_TRACKING = "https://www.canadapost.ca/cpotools/apps/track/personal/findByTrackNumber?execution=e1s1&trackNumbers=";
	const PURO_TRACKING = "https://eshiponline.purolator.com/SHIPONLINE/Public/Track/TrackingDetails.aspx?pin=";
	const DHL_TRACKING = "http://www.dhl.com/content/g0/en/express/tracking.shtml?AWB=";
	const DICOM_TRACKING = "https://www.dicom.com/en/dicomexpress/tracking/load-tracking/";
	const LOOMIS_TRACKING = "http://www.loomisexpress.com/ca/wfTrackingStatus.aspx?PieceNumber=";
	
	private $shiptime_carriers = array();
	private $carrier_list = array(
		'FedEx' => FEDEX_TRACKING,
		'DHL INTL' => DHL_TRACKING,
		'Canpar' => CANPAR_TRACKING,
		'Canada Post' => CANPOST_TRACKING,
		'Purolator' => PURO_TRACKING,
		'Dicom' => DICOM_TRACKING,
		'Loomis' => LOOMIS_TRACKING
	);

	private $shiptime_domestic = array();
	private $shiptime_intl = array();
	private $all_services = null;

	private $_ratingClient = null;
	private $_shippingClient = null;
	private $_signupClient = null;

	public function __construct() {
		if ($this->wc_order_shiptime_init()) {
			if ( is_admin() ) {
				add_action( 'add_meta_boxes', array( $this, 'add_shiptime_metabox' ), 15 );
			}

			// Add shipment tracking information to customer email
			//add_action( 'woocommerce_email_order_meta', array( $this, 'shiptime_order_email'), 20 );

			if ( isset( $_GET['recalc'] ) && (int)$_GET['recalc'] == 1 ) {
				add_action( 'init', array( $this, 'recalc' ), 15 );
			}

			if ( isset( $_GET['shiptime_place_shipment'] ) ) {
				add_action( 'init', array( $this, 'shiptime_place_shipment' ), 15 );
			}
			else if ( isset( $_GET['shiptime_cancel_shipment'] ) ) {
				add_action( 'init', array( $this, 'shiptime_cancel_shipment' ), 15 );
			}
			else if ( isset( $_GET['shiptime_track_shipment'] ) ) {
				add_action( 'admin_notices', array( $this, 'shiptime_track_shipment' ), 15 );
				//add_action( 'init', array( $this, 'shiptime_track_shipment' ), 15 );
			}
			else if ( isset( $_GET['shiptime_box_selection'] ) ) {
				add_action( 'init', array( $this, 'shiptime_box_selection' ), 15 );
			}
			else if ( isset( $_GET['shiptime_box_addition'] ) ) {
				add_action( 'init', array( $this, 'shiptime_box_addition' ), 15 );
			}
			else if ( isset( $_GET['shiptime_pkg_addition'] ) ) {
				add_action( 'init', array( $this, 'shiptime_pkg_addition' ), 15 );
			}
			else if ( isset( $_GET['shiptime_pkg_deletion'] ) ) {
				add_action( 'init', array( $this, 'shiptime_pkg_deletion' ), 15 );
			}
		}
	}

	static function sort_boxes($sort) {
		if (empty($sort)) { return false; }
		uasort($sort, array(__CLASS__, 'box_sorting'));
		return $sort;
	}

	static function box_sorting($a, $b) {
		$a_vol = $a['inner_length']*$a['inner_width']*$a['inner_height'];
		$b_vol = $a['inner_length']*$a['inner_width']*$a['inner_height'];
		$a_wgt = $a['weight'];
		$b_wgt = $b['weight'];
		if ( $a_vol == $b_vol ) {
			if ( $a_wgt == $b_wgt ) {
				return 0;
			}
			return ( $a_wgt < $b_wgt ) ? 1 : -1;
		}
		return ( $a_vol < $b_vol ) ? 1 : -1;
	}

	static function find_box_by_dims($length, $width, $height) {
		$def = 'PLACEHOLDER-CONFIG';
		// Set Boxes
		$shiptime_settings = get_option('woocommerce_shiptime_settings');
		$boxes = $shiptime_settings['boxes'];
		$tmp = array(
			'label' => $def,
			'outer_length' => $length,
			'outer_width' => $width,
			'outer_height' => $height,
			'inner_length' => 0,
			'inner_width' => 0,
			'inner_height' => 0,
			'weight' => 0
		);
		$boxes[] = $tmp;
		$boxes = array_reverse(self::sort_boxes($boxes));

		// If package matches a configured box
		foreach ($boxes as $box) {
			$dims = array($box['outer_length'], $box['outer_width'], $box['outer_height']);
			sort($dims);

			if ($dims[2] == $length && $dims[1] == $width && $dims[0] == $height && $box['label'] !== $def) {
				return $box['label'];
			}
		}

		// If package lesser dims than any configured box
		$first = array_shift($boxes);
		if ($first['label'] == $def) {
			$smallest = array_shift($boxes);
			return $smallest['label'];
		}

		// If package greater dims than any configured box
		return false;
	}

	static function find_box_by_label($label) {
		$shiptime_settings = get_option('woocommerce_shiptime_settings');
		foreach ($shiptime_settings['boxes'] as $box) {
			if ($box['label'] == $label) {
				return $box;
			}
		}
	}

	function wc_order_shiptime_init() {
		if (!isset($_GET['post'])) return;
		global $wpdb;

		if (isset($_GET['post']) && is_numeric($_GET['post'])) {
			$oid = trim($_GET['post']);

			// Only display for Orders, not Products
			$order = $this->get_wc_order($oid);
			if ( !$order ) return;

			$this->weight_uom = strtoupper(get_option( 'woocommerce_weight_unit' ));
			$this->dim_uom = strtoupper(get_option( 'woocommerce_dimension_unit' ));
			$this->shipping_meta = array();
			$this->shiptime_data = array();

			// Set ServiceName => ServiceId pairs
			$shiptime_settings = get_option('woocommerce_shiptime_settings');
			if ($shiptime_settings && array_key_exists('services', $shiptime_settings)) {
				foreach ($shiptime_settings['services'] as $serviceId => $data) {
					if ($data['enabled'] == 'on') {
						if ($data['intl'] == '1') {
							$this->shiptime_intl[$data['name']] = $serviceId;
						} else {
							$this->shiptime_domestic[$data['name']] = $serviceId;
						}
					}
				}
			}
			$this->all_services = array_merge($this->shiptime_domestic,$this->shiptime_intl);

			$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
			if (is_object($shiptime_auth)) {
				$encUser = $shiptime_auth->username;
				$encPass = $shiptime_auth->password;
				$this->_ratingClient = new emergeit\RatingClient($encUser, $encPass);
				$this->_shippingClient = new emergeit\ShippingClient($encUser, $encPass);
				$this->_signupCLient = new emergeit\SignupClient();

				// Set CarrierName => CarrierId pairs
				$req = new emergeit\GetServicesRequest();
				$req->IntegrationID = "85566cfb-9d0e-421b-bc78-649a1711a3ea";
				$req->Credentials->EncryptedPassword = $encPass;
				$req->Credentials->EncryptedUsername = $encUser;
				$resp = $this->_signupCLient->getServices($req);
				foreach ($resp->ServiceOptions as $serviceOption) {
					if (array_key_exists($serviceOption->CarrierName, $this->carrier_list)) {
						$this->shiptime_carriers[$serviceOption->CarrierName] = $serviceOption->CarrierId;
					}
				}
			}
			return true;
		} else {
			return false;
		}
	}

	function get_shipping_meta($shiptime_quote) {
		$quotes = self::casttoclass('stdClass', unserialize($shiptime_quote->quote));
		$service = $shiptime_quote->shipping_method;

		foreach ($quotes->AvailableRates as $quote) {
			$l = strpos($quote->ServiceName, $quote->CarrierName) !== false ? $quote->ServiceName : $quote->CarrierName . " ". $quote->ServiceName;
			if ($l == $service) {
				return array(
					'ServiceName' => $service,
					'Packages' => unserialize($shiptime_quote->packages),
					'Quote' => $quote
				);
			}
		}

		return null;
	}

	static function casttoclass($class, $object) {
		return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
	}

	function add_shiptime_metabox() {
		global $post;
		global $wpdb;

		if ( !$post ) return;

		$order = $this->get_wc_order($post->ID);
		if ( !$order ) return;
		$shiptime_quote = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_quote WHERE order_id=".$order->id." ORDER BY id DESC LIMIT 1");
		if (is_object($shiptime_quote)) {
			$this->shipping_meta = $this->get_shipping_meta($shiptime_quote);
		}
		if (!isset($this->shipping_meta)) return;

		$this->shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$post->ID}");

		if (!isset($this->shiptime_data) && $this->shipping_meta) {
			$packages = $box_codes = array();
			foreach ($this->shipping_meta['Packages'] as $pkg) {
				$pkg = self::casttoclass('stdClass', $pkg);
				$packages[] = array(
					'weight' => $pkg->Weight,
					'length' => $pkg->Length,
					'width'  => $pkg->Width,
					'height' => $pkg->Height
				);
				$box_codes[] = self::find_box_by_dims($pkg->Length, $pkg->Width, $pkg->Height);
			}

			$current_rate = wc_price($order->get_total_shipping(), array('currency' => $order->get_order_currency()));
			$current_rate = (float)preg_replace('/&.*?;/', '', strip_tags($current_rate));

			$quoted_rate = $this->shipping_meta['Quote']->TotalBeforeTaxes->Amount/100.00;
			$total_charge = $this->shipping_meta['Quote']->TotalCharge->Amount/100.00;
			$taxes = (float) $total_charge - $quoted_rate;

			$wpdb->insert(
				$wpdb->prefix.'shiptime_order',
				array(
					'post_id' => $post->ID,
					'package_data' => serialize($packages),
					'box_codes' => serialize($box_codes),
					'shipping_service' => $this->shipping_meta['ServiceName'],
					'tracking_nums' => serialize(array()),
					'label_url' => serialize(array()),
					'invoice_url' => serialize(array()),
					'emergeit_id' => 1234,
					'quoted_rate' => number_format($quoted_rate, 2),
					'markup_rate' => number_format($current_rate, 2),
					'taxes' => number_format($taxes, 2)
				),
				array(
					'%d','%s','%s','%s','%s','%s','%s','%d','%f','%f','%f'
				)
			);
		}

		$this->shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$post->ID}");

		add_meta_box( 'shipment_metabox1', 'Shipment Details', array( $this, 'shiptime_metabox_content1' ), 'shop_order', 'side', 'default' );

		add_meta_box( 'shipment_metabox2', 'Labels & Tracking', array( $this, 'shiptime_metabox_content2' ), 'shop_order', 'side', 'default' );

		add_meta_box( 'shipment_metabox3', 'Rate Details', array( $this, 'shiptime_metabox_content3' ), 'shop_order', 'side', 'default' );
	}

	function shiptime_metabox_content1() {
		global $wpdb;
		global $post;
		$shipmentId = '';

		$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");

		if($this->shiptime_data->emergeit_id == '1234') {
			$href_url 				= admin_url( '/post.php?post='.$post->ID.'&action=edit&shiptime_place_shipment='.base64_encode( $post->ID ) );
			$order 					= $this->get_wc_order( $post->ID );
			$shipping_method 		= !empty($this->shiptime_data->shipping_service) ? $this->shiptime_data->shipping_service : $order->get_shipping_method();
		?>
			<strong>Select Shipping Service:</strong>
		<?php
			$this->shipping_services = $this->shiptime_domestic;
			if($order->shipping_country != $shiptime_auth->country) {
				$this->shipping_services = $this->shiptime_intl;
			}
			echo '<ul><li class="wide"><select class="select" name="shiptime_shipping_method" id="shiptime_shipping_method">';
			foreach($this->shipping_services as $service_name => $service_code){
				echo '<option value="'.$service_name.'" ' . selected(strtolower($shipping_method), strtolower($service_name)) . ' >'.$service_name.'</option>';
			}
			echo '</select></li>';
			if($order->shipping_country != $shiptime_auth->country) {
				echo '<strong>Who will pay duties/taxes?</strong>';
				echo '<li class="wide"><select class="select" name="shiptime_selection" id="shiptime_selection">';
				echo '<option value="SHIPPER" selected>SHIPPER</option>';
				echo '<option value="CONSIGNEE">CONSIGNEE</option>';
				echo '</select></li>';
			}
		?>
			<br><strong>Verify Package Details:<br></strong>
		<?php
			$pkgs = unserialize($this->shiptime_data->package_data);
			$boxs = unserialize($this->shiptime_data->box_codes);
			if (!empty($pkgs)) {
				$c=count($pkgs);
				echo "<input type='hidden' name='parcel_count' id='parcel_count' value='{$c}'>";
				for ($i=0; $i<$c; $i++) {
					echo "<br><strong>Package ".($i+1)." of {$c}</strong>&nbsp;-&nbsp;<a href='" . admin_url( "/post.php?post=".$post->ID."&action=edit&shiptime_pkg_deletion=" . base64_encode( $post->ID ) . "&shiptime_pkg=" . $i ) . "'>Remove</a><hr>";
		?>
				<li>
					<span style='width:50px;display:inline-block'><strong>Weight:</strong></span><input type="text" name="parcel_weight_<?php echo $i+1; ?>" id="parcel_weight_<?php echo $i+1; ?>" value="<?php echo $this->arr_check($pkgs[$i], 'weight'); ?>" size="3" />&nbsp;<?php echo $this->weight_uom;?><br>
					<span style='width:50px;display:inline-block'><strong>Length:</strong></span><input type="text" name="parcel_length_<?php echo $i+1; ?>" id="parcel_length_<?php echo $i+1; ?>" value="<?php echo $this->arr_check($pkgs[$i], 'length'); ?>" size="3" />&nbsp;<?php echo $this->dim_uom;?><br>
					<span style='width:50px;display:inline-block'><strong>Width:</strong></span><input type="text" name="parcel_width_<?php echo $i+1; ?>" id="parcel_width_<?php echo $i+1; ?>" value="<?php echo $this->arr_check($pkgs[$i], 'width'); ?>" size="3" />&nbsp;<?php echo $this->dim_uom;?><br>
					<span style='width:50px;display:inline-block'><strong>Height:</strong></span><input type="text" name="parcel_height_<?php echo $i+1; ?>" id="parcel_height_<?php echo $i+1; ?>" value="<?php echo $this->arr_check($pkgs[$i], 'height'); ?>" size="3" />&nbsp;<?php echo $this->dim_uom;?><br>
					Selected Box: <?php echo '<strong>' . (!empty($boxs[$i]) ? $boxs[$i] : 'N/A') . '</strong>'; ?><br>
					<a href="#TB_inline?width=600&height=480&inlineId=choose-box" class="thickbox" onClick="changeBox(<?php echo $i; ?>)">Change box</a>&nbsp; or &nbsp;<a href="#TB_inline?width=600&height=480&inlineId=add-box" class="thickbox">Add new box</a>
					<?php add_thickbox(); ?>
					<div id="choose-box" style="display:none;">
						<h2>Select Box Configuration for Shipment</h2>
						<p>
							<?php
							$shiptime_settings = get_option('woocommerce_shiptime_settings');
							$weight_uom = strtoupper(get_option( 'woocommerce_weight_unit' ));
							$dim_uom = strtoupper(get_option( 'woocommerce_dimension_unit' ));
							foreach ($shiptime_settings['boxes'] as $box) {
								echo "<input type='checkbox' class='choose_box' name='box_choice[]' value='" . $box['label'] . "'><strong>" . $box['label'] . "</strong><br>";
								echo "Outside Dimensions: {$box['outer_length']} X {$box['outer_width']} X {$box['outer_height']} {$dim_uom}<br>";
								echo "Inside Dimensions: {$box['inner_length']} X {$box['inner_width']} X {$box['inner_height']} {$dim_uom}<br>Packing Weight: {$box['weight']} {$weight_uom}<br><br>";
							}
							?>
							<a href="<?php echo admin_url( '/?shiptime_box_selection='.base64_encode( $post->ID  ) ); ?>" class="button-primary choose_box">Submit</a>
						</p>
						<script type="text/javascript">
							jQuery("a.choose_box").on("click", function() {
							   location.href = this.href + '&shiptime_box=' + jQuery(".choose_box:checked").val() + '&pkg=' + lastId;
							   return false;
							});
						</script>
					</div>
					<div id="add-box" style="display:none;">
						<h2>Quick Add Box Configuration for Shipment</h2>
						<p>
							<style>
							table.box td label { font-weight: bold; text-align: right; display: block; }
							</style>
							<table class='box'>
								<?php
									$weight_uom = strtolower(get_option( 'woocommerce_weight_unit' ));
									$dim_uom = strtolower(get_option( 'woocommerce_dimension_unit' ));
								?>
								<tr>
									<td><label for="shiptime_box_label">Label</label></td>
									<td><input type="text" id="shiptime_box_label" name="shiptime_box_label">
								</tr>
								<tr>
									<td><label for="shiptime_box_outer_length">Total Length (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_outer_length" name="shiptime_box_outer_length">
								</tr>
								<tr>
									<td><label for="shiptime_box_outer_width">Total Width (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_outer_width" name="shiptime_box_outer_width">
								</tr>
								<tr>
									<td><label for="shiptime_box_outer_height">Total Height (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_outer_height" name="shiptime_box_outer_height">
								</tr>
								<tr>
									<td><label for="shiptime_box_inner_length">Inner Length (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_inner_length" name="shiptime_box_inner_length">
								</tr>
								<tr>
									<td><label for="shiptime_box_inner_width">Inner Width (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_inner_width" name="shiptime_box_inner_width">
								</tr>
								<tr>
									<td><label for="shiptime_box_inner_height">Inner Height (<?php echo $dim_uom; ?>)</label>
									<td><input type="text" id="shiptime_box_inner_height" name="shiptime_box_inner_height">
								</tr>
								<tr>
									<td><label for="shiptime_box_weight">Packing Weight (<?php echo $weight_uom; ?>)&nbsp;<img class="help_tip" style="float:none;" data-tip="Packing Weight = (Weight of Empty Box) + (Weight of Packing Materials)" src="<?php echo WC()->plugin_url();?>/assets/images/help.png" height="16" width="16" /></label>
									<td><input type="text" id="shiptime_box_weight" name="shiptime_box_weight">
								</tr>
							</table>
							<br><br>
							<a href="<?php echo admin_url( '/?shiptime_box_addition='.base64_encode( $post->ID ) ); ?>" class="button-primary add_box">Submit</a>
						</p>
						<script type="text/javascript">
							jQuery("a.add_box").on("click", function() {
							   location.href = this.href
							    + '&shiptime_box_label=' + jQuery('#shiptime_box_label').val()
							    + '&shiptime_box_outer_length=' + jQuery('#shiptime_box_outer_length').val()
								+ '&shiptime_box_outer_width=' + jQuery('#shiptime_box_outer_width').val()
								+ '&shiptime_box_outer_height=' + jQuery('#shiptime_box_outer_height').val()
								+ '&shiptime_box_inner_length=' + jQuery('#shiptime_box_inner_length').val()
								+ '&shiptime_box_inner_width=' + jQuery('#shiptime_box_inner_width').val()
								+ '&shiptime_box_inner_height=' + jQuery('#shiptime_box_inner_height').val()
								+ '&shiptime_box_weight=' + jQuery('#shiptime_box_weight').val();
							   return false;
							});
						</script>
					</div>
				</li>
		<?php
				}
			}
		?>
			</ul>
			<span style="height:10px;display:block"></span><hr>
			<a href="#TB_inline?width=600&height=480&inlineId=add-pkg" class="thickbox">+ Add Package to Shipment</a>
			<span style="height:10px;display:block"></span>
		<?php
			if (!empty($this->shiptime_data->recalc)) {
				echo $this->shiptime_data->recalc;
			}
		?>
			<div id="add-pkg" style="display:none;">
				<h2>Select Box Configuration for Shipment</h2>
				<p>
					<?php
					$shiptime_settings = get_option('woocommerce_shiptime_settings');
					$weight_uom = strtoupper(get_option( 'woocommerce_weight_unit' ));
					$dim_uom = strtoupper(get_option( 'woocommerce_dimension_unit' ));
					foreach ($shiptime_settings['boxes'] as $box) {
						echo "<input type='checkbox' class='choose_box' name='box_choice[]' value='" . $box['label'] . "'><strong>" . $box['label'] . "</strong><br>";
						echo "Outside Dimensions: {$box['outer_length']} X {$box['outer_width']} X {$box['outer_height']} {$dim_uom}<br>";
						echo "Inside Dimensions: {$box['inner_length']} X {$box['inner_width']} X {$box['inner_height']} {$dim_uom}<br>Packing Weight: {$box['weight']} {$weight_uom}<br><br>";
						}
					?>
					<a href="<?php echo admin_url( '/?shiptime_pkg_addition='.base64_encode( $post->ID ) ); ?>" class="button-primary choose_box">Submit</a>
				</p>
				<script type="text/javascript">
					jQuery("a.choose_box").on("click", function() {
					   location.href = this.href + '&shiptime_box=' + jQuery(".choose_box:checked").val() + '&pkg=' + lastId;
					   return false;
					});
				</script>
			</div>
			<span style='height:10px;display:block'></span>
			<a href="<?php echo $href_url; ?>" class="button button-primary tips place_shipment" data-tip="Create New Shipment">Create Shipment</a>
			<script type="text/javascript">
				jQuery("a.place_shipment").on("click", function() {
					loc = this.href;
					for (i=1; i<=parseInt(jQuery('#parcel_count').val()); i++) {
						loc +=
						'&parcel_weight_'+i+'=' + jQuery('#parcel_weight_'+i).val() +
				   		'&parcel_length_'+i+'=' + jQuery('#parcel_length_'+i).val() +
						'&parcel_width_'+i+'=' + jQuery('#parcel_width_'+i).val() +
						'&parcel_height_'+i+'=' + jQuery('#parcel_height_'+i).val();
					}
					location.href = loc + '&shiptime_shipping_method=' + jQuery('#shiptime_shipping_method').val() + '&shiptime_selection=' + jQuery('#shiptime_selection').val();
				    return false;
				});
			</script>
			<a href="<?php echo admin_url( '/post.php?post='.$post->ID.'&action=edit&recalc=1' ); ?>" class="button button-primary tips recalc" data-tip="Recalculate Shipping Costs">Recalculate</a>
			<script type="text/javascript">
				jQuery("a.recalc").on("click", function() {
					loc = this.href;
					for (i=1; i<=parseInt(jQuery('#parcel_count').val()); i++) {
						loc +=
						'&parcel_weight_'+i+'=' + jQuery('#parcel_weight_'+i).val() +
				   		'&parcel_length_'+i+'=' + jQuery('#parcel_length_'+i).val() +
						'&parcel_width_'+i+'=' + jQuery('#parcel_width_'+i).val() +
						'&parcel_height_'+i+'=' + jQuery('#parcel_height_'+i).val();
					}
					location.href = loc + '&shiptime_shipping_method=' + jQuery('#shiptime_shipping_method').val() + '&shiptime_selection=' + jQuery('#shiptime_selection').val();
				    return false;
				});
			</script>
			<script type="text/javascript">
				var lastId;

				function changeBox(id) {
					lastId = id;
				}
			</script>
		<?php
		}
		else {
			$pkgs = unserialize($this->shiptime_data->package_data);
			if (!empty($pkgs)) {
				for ($i=0,$c=count($pkgs); $i<$c; $i++) {
					echo "<strong>Package ".($i+1)." of {$c}</strong><hr>";

					echo 'Weight: '.$pkgs[$i]['weight'].' '.$this->weight_uom.'<br>';
					echo 'Dimensions: '.$pkgs[$i]['length'].' X '.$pkgs[$i]['width'].' X '.$pkgs[$i]['height'].' '.$this->dim_uom.'<br>';
					echo 'Service: <strong>'.$this->shiptime_data->shipping_service.'</strong><img class="help_tip" style="float:none;" data-tip="'.'Note: This may not be the same service the customer chose during checkout.'.'" src="'.WC()->plugin_url().'/assets/images/help.png" height="16" width="16" /><br>';
				}
			}

			$href_url = admin_url( '/post.php?post='.$post->ID.'&action=edit&shiptime_cancel_shipment='.base64_encode( $post->ID ) );
			?>
				<br><a class="button tips" href="<?php echo $href_url; ?>" data-tip="Cancel Current Shipment">Cancel Shipment</a>
			<?php
		}
	}

	function shiptime_metabox_content2() {
		global $wpdb;
		global $post;
		$shipmentId = '';
		$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");

		if(empty($this->shipping_meta)) {
			$order 					= $this->get_wc_order( $post->ID );
			$shipping_method 		= $order->get_shipping_method();
		} else {

		?>
			<?php
			$href_url = admin_url( '/post.php?post='.$post->ID.'&action=edit&shiptime_track_shipment='.base64_encode( $post->ID ) );
			if ($this->shiptime_data->emergeit_id == '1234') {
				echo "No shipment has been created.";
			} else {
				$pkgs = $this->shipping_meta['Packages'];
				$tnms = unserialize($this->shiptime_data->tracking_nums);
				if (!empty($pkgs)) {
					for ($i=0,$c=count($pkgs); $i<$c; $i++) {
						echo "<br><strong>Package ".($i+1)." of {$c}</strong><hr>";

						//echo 'Weight: '.$pkgs[$i]['Weight'].' '.$this->weight_uom.'<br>';
						//echo 'Dimensions: '.$pkgs[$i]['Length'].' X '.$pkgs[$i]['Width'].' X '.$pkgs[$i]['Height'].' '.$this->dim_uom.'<br>';

						$trackURL = '';

						foreach ($this->carrier_list as $carrierName => $carrierUrl) {
							if (stripos($this->shiptime_data->shipping_service, $carrierName) !== false) {
								$trackURL = "<a target='_new' href='" . constant("self::$carrierUrl") . $tnms[$i] . "'>Track No. {$tnms[$i]}</a><br>";
								break;
							}
						}

						if (empty($trackURL)) {
							$trackURL = "<a target='_new' href='#" . $tnms[$i] . "'>Track No. {$tnms[$i]}</a><br>";
						}
						echo $trackURL;
					}
				}
				?>
				<br>
				<a class="button button-primary tips" href="<?php echo $href_url; ?>" data-tip="Track Current Shipment">Track Shipment</a>
				<?php if ($order->shipping_country != $shiptime_auth->country) { ?>
				<a target="_new" class="button button-primary tips" href="<?php echo $this->shiptime_data->label_url; ?>" data-tip="Print Shipping Label(s)">Print Label</a>
				<?php } else { ?>
				<br><br>
				<a target="_new" class="button button-primary tips" href="<?php echo $this->shiptime_data->label_url; ?>" data-tip="Print Shipping Label(s)">Print Label</a>
				<a target="_new" class="button button-primary tips" href="<?php echo $this->shiptime_data->invoice_url; ?>" data-tip="Print Customs Invoice">Print Customs Doc</a>
				<?php } ?>
				<br>
			<?php
			}
		}
	}

	function shiptime_metabox_content3() {
		$quote = $this->shipping_meta['Quote'];

		$rate_info = $this->shiptime_rate_details($quote);

		$k = 'details';
		if (array_key_exists($k, $rate_info)) {
			echo $rate_info[$k];
		}

		if (get_woocommerce_currency() != 'CAD') {
			echo "<br><em>Totals displayed in CAD</em><br>";
			echo "Total (".get_woocommerce_currency()."): ".get_woocommerce_currency_symbol().emergeit\CurrencyUtil::convert('CAD',get_woocommerce_currency(),$rate_info['total']);
		}		
	}

	function shiptime_rate_details($quote) {
		$r = array();

		if (isset($quote)) {
			$shiptime_settings = get_option('woocommerce_shiptime_settings');
			$services = $shiptime_settings['services'];

			$markup_fixed = $services[$quote->ServiceId]['markup_fixed'];
			$markup_fixed = is_numeric($markup_fixed) && !empty($markup_fixed) ? $markup_fixed : 0;
			$markup_percentage = $services[$quote->ServiceId]['markup_percentage'];
			$markup_percentage = is_numeric($markup_percentage) && !empty($markup_percentage) ? (float)$markup_percentage : 0;

			$pre = get_woocommerce_currency_symbol();
			$base = number_format($quote->BaseCharge->Amount/100.00,2);
			$fuel = $accessorial = 0.00;
			foreach ($quote->Surcharges as $surcharge) {
				if ($surcharge->Code == 'FUEL') {
					$fuel += $surcharge->Price->Amount/100.00;
				} else {
					$accessorial += $surcharge->Price->Amount/100.00;
				}
			}
			$markup_fixed = number_format($markup_fixed,2);
			$markup_percentage = number_format($markup_percentage,2);
			$fuel = number_format($fuel,2);
			$accessorial = number_format($accessorial,2);
			$total_before_tax = number_format($quote->TotalBeforeTaxes->Amount/100.00,2);
			$total_after_tax = number_format($quote->TotalCharge->Amount/100.00,2);
			$taxes = number_format($total_after_tax-$total_before_tax,2);
			foreach ($quote->Taxes as $tax) {
				$tax_type = $tax->Name;
				break;
			}

			$r['details'] = "Base Charge: ".$pre.$base."<br>";
			$r['details'] .= "Fuel Surcharge: ".$pre.$fuel."<br>";
			$r['details'] .= "Other Surcharges: ".$pre.$accessorial."<br>";
			if ($markup_fixed>0) {
				$r['details'] .= "Fixed Markup: ".$pre.$markup_fixed."<br>";
				$r['details'] .= "Total (w/o taxes): ".$pre.number_format(($total_before_tax+$markup_fixed),2)."<br>";
				$r['details'] .= $tax_type.": ".$pre.$taxes."<br>";
				$total = number_format(($total_after_tax+$markup_fixed),2);
				$r['details'] .= "Total (with taxes): ".$pre.$total."<br>";
			} elseif ($markup_percentage>0) {
				$r['details'] .= "Percentage Markup: ".$markup_percentage."%<br>";
				$r['details'] .= "Total (w/o taxes): ".$pre.number_format(floor(100*($total_before_tax-$accessorial)*(1+$markup_percentage/100.00))/100.00,2)."<br>";
				$r['details'] .= $tax_type.": ".$pre.number_format(ceil(100*$taxes*(1+$markup_percentage/100.00))/100.00,2)."<br>";
				$total = number_format(ceil(100*(($total_before_tax-$accessorial)*(1+$markup_percentage/100.00)+($taxes*(1+$markup_percentage/100.00))))/100.00,2);
				$r['details'] .= "Total (with taxes): ".$pre.$total."<br>";
			} else {
				$r['details'] .= "Total (w/o taxes): ".$pre.$total_before_tax."<br>";
				$r['details'] .= $tax_type.": ".$pre.$taxes."<br>";
				$total = $total_after_tax;
				$r['details'] .= "Total (with taxes): ".$pre.$total."<br>";
			}

			$r['total'] = $total;
		}

		return $r;
	}

	function shiptime_track_shipment() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_track_shipment']);

		if (isset($id)) {
			// Make trackShipment Request
			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$req = new emergeit\TrackShipmentRequest();
			$req->ShipId = $shiptime_data->emergeit_id;

			if ($this->_shippingClient->isConnected()) {
				$resp = $this->_shippingClient->trackShipment($req);

				// Show notice
				if (empty($resp->Messages)) {
		        	$msg = '<div class="updated"><p>Shipment Status: '.$resp->TrackingRecord->CurrentStatus.'</p></div>';
				} else {
					$msg = '<div class="error"><p>';
					$msg .= 'Unable to track shipment.';
					foreach ($resp->Messages as $m) {
						$msg .= "<pre><strong>".print_r($m->Severity, true)."</strong>: ".print_r(htmlentities($m->Text), true)."</pre>";
					}
					$msg .= '</p></div>';
				}
				ob_start();
				echo $msg;
			} else {
				$msg = '<div class="error"><p>Connection to ShipTime has failed. Please try again in a moment.</p></div>';
				ob_start();
				echo $msg;
			}
		}
	}

	function shiptime_cancel_shipment() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_cancel_shipment']);

		if (isset($id)) {
			// Make trackShipment Request
			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$req = new emergeit\CancelShipmentRequest();
			$req->ShipId = $shiptime_data->emergeit_id;

			if ($this->_shippingClient->isConnected()) {
				$resp = $this->_shippingClient->cancelShipment($req);

				$wpdb->update(
					"{$wpdb->prefix}shiptime_order",
					array(
						'tracking_nums' => serialize(array()),
						'label_url' => '',
						'invoice_url' => '',
						'emergeit_id' => 1234
					),
					array( 'post_id' => $id ),
					array(
						'%s',
						'%s',
						'%s',
						'%d'
					),
					array( '%d' )
				);

				// Show notice
				if (empty($resp->Messages)) {
					$msg = '<div class="updated"><p>The shipment has been cancelled.</p></div>';
				} else {
					$msg = '<div class="error"><p>';
					$msg .= 'Unable to cancel shipment.';
					foreach ($resp->Messages as $m) {
						$msg .= "<pre><strong>".print_r($m->Severity, true)."</strong>: ".print_r(htmlentities($m->Text), true)."</pre>";
					}
					$msg .= '</p></div>';
				}
				ob_start();
				echo $msg;
			} else {
				$msg = '<div class="error"><p>Connection to ShipTime has failed. Please try again in a moment.</p></div>';
				ob_start();
				echo $msg;
			}
		}
	}

	function shiptime_place_shipment() {
		global $woocommerce;
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_place_shipment']);
		$order = $this->get_wc_order($id);

		$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
		if (isset($shiptime_auth) && !get_transient('shiptime_signup_required')) {
			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$shiptime_pkgs = unserialize($shiptime_data->package_data);
			$ship_addr = $order->get_address('shipping');
			$bill_addr = $order->get_address('billing');

			// Store data from form submit
			$c=count($shiptime_pkgs);
			$pkgs = array();
			for ($i=1; $i<=$c; $i++) {
				$pkgs[] = array(
					'weight' => sanitize_text_field($_GET["parcel_weight_{$i}"]),
					'length' => sanitize_text_field($_GET["parcel_length_{$i}"]),
					'width' => sanitize_text_field($_GET["parcel_width_{$i}"]),
					'height' => sanitize_text_field($_GET["parcel_height_{$i}"])
				);
			}
			$wpdb->update(
				"{$wpdb->prefix}shiptime_order",
				array(
					'package_data' => serialize($pkgs),
					'shipping_service' => sanitize_text_field($_GET['shiptime_shipping_method'])
				),
				array( 'post_id' => $id ),
				array(
					'%s',
					'%s'
				),
				array( '%d' )
			);

			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$shiptime_pkgs = unserialize($shiptime_data->package_data);

			// Make placeShipment Request
			$req = new emergeit\PlaceShipmentRequest();

			foreach ($this->shiptime_carriers as $carrier => $cid) {
				if (strpos($shiptime_data->shipping_service, $carrier) !== false) {
					$req->CarrierId = $cid;
				}
			}
			if ($order->shipping_country != $shiptime_auth->country) {
				$req->ServiceId = $this->shiptime_intl[$shiptime_data->shipping_service];
			} else {
				$req->ServiceId = $this->shiptime_domestic[$shiptime_data->shipping_service];
			}

			// Pull merchant info from ShipTime signup
			$req->From->Attention = ucwords($shiptime_auth->first_name) . ' ' . ucwords($shiptime_auth->last_name);
			$req->From->City = ucwords($shiptime_auth->city);
			$req->From->Phone = $shiptime_auth->phone;
			$req->From->CompanyName = $shiptime_auth->company;
			$req->From->CountryCode = $shiptime_auth->country;
			$req->From->Email = $shiptime_auth->email;
			$req->From->PostalCode = $shiptime_auth->zip;
			$req->From->Province = $shiptime_auth->state;
			$req->From->StreetAddress = ucwords($shiptime_auth->address);
			$req->From->Notify = false;
			$req->From->Residential = false;
			// Pull customer info from Woo order
			$user_info = get_user_meta(absint($order->customer_user));
			$req->To->Attention = ucwords($ship_addr['first_name'] . ' ' . $ship_addr['last_name']);
			// Verify/Correct City based on CountryCode and PostalCode
			$ship_city = ucwords($ship_addr['city']);
			$loc = new emergeit\GetLocationRequest();
			$loc->CountryCode = $ship_addr['country'];
			$loc->PostalCode = $ship_addr['postcode'];
			try {
				if ($this->_ratingClient->isConnected()) {
					$api_resp = $this->_ratingClient->getLocation($loc);
					if (is_object($api_resp)) {
						$api_city = $api_resp->Location->city;
						if ($api_city != $ship_city) {
							$ship_city = $api_city;
						}
					}
				}
			} catch (Exception $e) {
				// Returns SoapFault Exception on Failure
			}
			$req->To->City = $ship_city;
			$req->To->Phone = $bill_addr['phone'];
			$req->To->CompanyName = !empty($ship_addr['company']) ? $ship_addr['company'] : 'NA';
			$req->To->CountryCode = $ship_addr['country'];
			$req->To->Email = $bill_addr['email'];
			$req->To->PostalCode = $ship_addr['postcode'];
			$req->To->Province = $ship_addr['state'];
			$req->To->StreetAddress = ucwords($ship_addr['address_1']);
			$req->To->StreetAddress2 = ucwords($ship_addr['address_2']);
			$req->To->Notify = false;
			$req->To->Residential = false;
			$req->PackageType = 'PACKAGE';
			foreach ($shiptime_pkgs as $pkg) {
				$item = new emergeit\LineItem();
				$item->Length->UnitsType = 'IN';
				$item->Length->Value = ceil(woocommerce_get_dimension($pkg['length'], 'in'));
				$item->Width->UnitsType = 'IN';
				$item->Width->Value = ceil(woocommerce_get_dimension($pkg['width'], 'in'));
				$item->Height->UnitsType = 'IN';
				$item->Height->Value = ceil(woocommerce_get_dimension($pkg['height'], 'in'));
				$item->Weight->UnitsType = 'LB';
				// TODO: Support packages < 1 LB
				$pkg_weight = woocommerce_get_weight($pkg['weight'], 'lbs');
				$item->Weight->Value = $pkg_weight >= 1 ? $pkg_weight : 1;

				if ($order->shipping_country != $shiptime_auth->country) {
					$desc = array();
					foreach ( $order->get_items( array( 'line_item' ) ) as $iid => $data ) {
						$product = $order->get_product_from_item( $data );
						$desc[] = $product->get_title();
					}
					$item->Description = implode(',', $desc);
				}

				$req->ShipmentItems[] = $item;
			}
			$req->DeferredProcessing = false;

			if ($order->shipping_country != $shiptime_auth->country) {
				// Int'l shipments - Customs Invoice
				$dt = new emergeit\DutiesAndTaxes();
				$dt->Dutiable = true;
				$sel = sanitize_text_field($_GET['shiptime_selection']);
				if ($sel !== 'CONSIGNEE') { $sel = 'SHIPPER'; }
				$dt->Selection = $sel;
				$req->CustomsInvoice->DutiesAndTaxes = $dt;

				$ic = new emergeit\InvoiceContact();
				$ic->City = ucwords($bill_addr['city']);
				$ic->CompanyName = !empty($bill_addr['company']) ? $bill_addr['company'] : 'NA';
				$ic->CountryCode = $order->shipping_country;
				$ic->Email = $bill_addr['email'];
				$ic->Phone = $bill_addr['phone'];
				$ic->PostalCode = $bill_addr['postcode'];
				$ic->Province = $bill_addr['state'];
				$ic->StreetAddress = ucwords($bill_addr['address_1']);
				$ic->CustomsBroker = '-';
				$ic->ShipperTaxId = '-';
				$req->CustomsInvoice->InvoiceContact = $ic;

				$req->CustomsInvoice->InvoiceItems = array();
				foreach ( $order->get_items( array( 'line_item' ) ) as $item_id => $item ) {
					$product = $order->get_product_from_item( $item );

					$i = new emergeit\InvoiceItem();
					$i->Code = get_post_meta($product->id, 'shiptime_hs_code', true);
					$i->Description = $product->get_title();
					$i->Origin = get_post_meta($product->id, 'shiptime_origin_country', true);
					$i->Quantity->Value = (int)$item['qty'];
					$i->UnitPrice->Amount = $item['line_subtotal'];
					$i->UnitPrice->CurrencyCode = get_woocommerce_currency();

					$req->CustomsInvoice->InvoiceItems[] = $i;
				}
			} else {
				unset($req->CustomsInvoice);
			}


			if ($this->_shippingClient->isConnected()) {

				$resp = $this->_shippingClient->placeShipment($req);

				// Store data from Response
				$tracking_nums = $resp->TrackingNumbers;
				if (!empty($tracking_nums)) {
					$c=count($shiptime_pkgs);
					$pkgs = array();
					for ($i=1; $i<=$c; $i++) {
					  $pkgs[] = array(
							'weight' => sanitize_text_field($_GET["parcel_weight_{$i}"]),
							'length' => sanitize_text_field($_GET["parcel_length_{$i}"]),
							'width' => sanitize_text_field($_GET["parcel_width_{$i}"]),
							'height' => sanitize_text_field($_GET["parcel_height_{$i}"])
					  );
					}
					$wpdb->update(
					  "{$wpdb->prefix}shiptime_order",
					  array(
							'package_data' => serialize($pkgs),
							'shipping_service' => sanitize_text_field($_GET['shiptime_shipping_method']),
							'tracking_nums' => serialize($tracking_nums),
							'label_url' => $resp->LabelUrl,
							'invoice_url' => $resp->InvoiceUrl,
							'emergeit_id' => $resp->ShipId
					  ),
					  array(
							'post_id' => $id
					  ),
					  array(
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%d'
					  ),
					  array(
							'%d'
					  )
					);
					$wpdb->update(
					  "{$wpdb->prefix}woocommerce_order_items",
					  array(
							'order_item_name' => sanitize_text_field($_GET['shiptime_shipping_method'])
					  ),
					  array( 'order_id' => $id, 'order_item_type' => 'shipping' ),
					  array( '%s' ),
					  array( '%d', '%s' )
					);

					// Return to order page
					wp_safe_redirect( admin_url("/post.php?post={$id}&action=edit") );
				} else {
					$msg = '<div class="error"><p>';
					$msg .= "Unable to create shipment.";
					foreach ($resp->Messages as $m) {
						$msg .= "<pre><strong>".print_r($m->Severity, true)."</strong>: ".print_r(htmlentities($m->Text), true)."</pre>";
					}
					$msg .= '</p></div>';
					ob_start();
					echo $msg;
				}

			} else {
				$msg = '<div class="error"><p>Connection to ShipTime has failed. Please try again in a moment.</p></div>';
				ob_start();
				echo $msg;
			}
		} else {
			// No login row found or shiptime_signup_required transient is set
		}
	}

	function shiptime_box_selection() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_box_selection']);
		$pid = (int)sanitize_text_field($_GET['pkg']);

		// Box selection
		$box = sanitize_text_field($_GET['shiptime_box']);

		// DB update
		$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
		$shiptime_pkgs = unserialize($shiptime_data->package_data);
		$shiptime_boxs = unserialize($shiptime_data->box_codes);
		$shiptime_settings = get_option('woocommerce_shiptime_settings');

		if (count($shiptime_boxs) > $pid) {
			$shiptime_boxs[$pid] = $box;
		}
		if (count($shiptime_pkgs) > $pid) {
			foreach ($shiptime_settings['boxes'] as $b) {
				if ($b['label'] == $box) {
					$shiptime_pkgs[$pid]['length'] = $b['outer_length'];
					$shiptime_pkgs[$pid]['width'] = $b['outer_width'];
					$shiptime_pkgs[$pid]['height'] = $b['outer_height'];
					break;
				}
			}
		}
		$wpdb->update(
			"{$wpdb->prefix}shiptime_order",
			array(
				'package_data' => serialize($shiptime_pkgs),
				'box_codes' => serialize($shiptime_boxs)
			),
			array( 'post_id' => $id ),
			array(
				'%s',
				'%s'
			),
			array( '%d' )
		);

		// Return to order page
		wp_safe_redirect( admin_url("/post.php?post={$id}&action=edit") );
	}

	function shiptime_box_addition() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_box_addition']);
		$pid = (int)sanitize_text_field($_GET['pkg']);

		// Add box
		$new_box = array(
			'label' => sanitize_text_field($_GET['shiptime_box_label']),
			'outer_length' => sanitize_text_field($_GET['shiptime_box_outer_length']),
			'outer_width' => sanitize_text_field($_GET['shiptime_box_outer_width']),
			'outer_height' => sanitize_text_field($_GET['shiptime_box_outer_height']),
			'inner_length' => sanitize_text_field($_GET['shiptime_box_inner_length']),
			'inner_width' => sanitize_text_field($_GET['shiptime_box_inner_width']),
			'inner_height' => sanitize_text_field($_GET['shiptime_box_inner_height']),
			'weight' => sanitize_text_field($_GET['shiptime_box_weight'])
		);
		$shiptime_settings = get_option('woocommerce_shiptime_settings');
		$shiptime_settings['boxes'][] = $new_box;
		update_option('woocommerce_shiptime_settings', $shiptime_settings);

		// DB update
		$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
		$shiptime_pkgs = unserialize($shiptime_data->package_data);
		$shiptime_boxs = unserialize($shiptime_data->box_codes);
		if (count($shiptime_boxs) > $pid) {
			$shiptime_boxs[$pid] = $new_box['label'];
		}
		if (count($shiptime_pkgs) > $pid) {
			$shiptime_pkgs[$pid]['length'] = $new_box['outer_length'];
			$shiptime_pkgs[$pid]['width'] = $new_box['outer_width'];
			$shiptime_pkgs[$pid]['height'] = $new_box['outer_height'];
		}
		$wpdb->update(
			"{$wpdb->prefix}shiptime_order",
			array(
				'package_data' => serialize($shiptime_pkgs),
				'box_codes' => serialize($shiptime_boxs)
			),
			array( 'post_id' => $id ),
			array(
				'%s',
				'%s'
			),
			array( '%d' )
		);

		// Return to order page
		wp_safe_redirect( admin_url("/post.php?post={$id}&action=edit") );
	}

	function shiptime_pkg_addition() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_pkg_addition']);

		// Box selection
		$box = sanitize_text_field($_GET['shiptime_box']);

		// DB update
		$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
		$shiptime_pkgs = unserialize($shiptime_data->package_data);
		$shiptime_boxs = unserialize($shiptime_data->box_codes);
		$shiptime_settings = get_option('woocommerce_shiptime_settings');

		foreach ($shiptime_settings['boxes'] as $b) {
			if ($b['label'] == $box) {
				$shiptime_pkgs[] = array(
					'weight' => $b['weight'],
					'length' => $b['outer_length'],
					'width'  => $b['outer_width'],
					'height' => $b['outer_height']
				);
				$shiptime_boxs[] = $box;
				break;
			}
		}

		$wpdb->update(
			"{$wpdb->prefix}shiptime_order",
			array(
				'package_data' => serialize($shiptime_pkgs),
				'box_codes' => serialize($shiptime_boxs)
			),
			array( 'post_id' => $id ),
			array(
				'%s',
				'%s'
			),
			array( '%d' )
		);

		// Return to order page
		wp_safe_redirect( admin_url("/post.php?post={$id}&action=edit") );
	}

	function shiptime_pkg_deletion() {
		global $wpdb;

		// Woo order id
		$id = base64_decode($_GET['shiptime_pkg_deletion']);

		// Package selection
		$pid = (int)sanitize_text_field($_GET['shiptime_pkg']);

		// DB update
		$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
		$shiptime_pkgs = unserialize($shiptime_data->package_data);
		$shiptime_boxs = unserialize($shiptime_data->box_codes);

		if (count($shiptime_boxs) > $pid) {
			unset($shiptime_boxs[$pid]);
		}

		if (count($shiptime_pkgs) > $pid) {
			unset($shiptime_pkgs[$pid]);
		}

		$wpdb->update(
			"{$wpdb->prefix}shiptime_order",
			array(
				'package_data' => serialize(array_values($shiptime_pkgs)),
				'box_codes' => serialize(array_values($shiptime_boxs))
			),
			array( 'post_id' => $id ),
			array(
				'%s',
				'%s'
			),
			array( '%d' )
		);

		// Return to order page
		wp_safe_redirect( admin_url("/post.php?post={$id}&action=edit") );
	}

	function recalc() {
		global $woocommerce;
		global $wpdb;

		// Woo order id
		if (isset($_GET['post']) && is_numeric($_GET['post'])) {
			$id = $_GET['post'];
			$order = $this->get_wc_order($id);

			$shiptime_auth = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_login");
			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$shiptime_pkgs = unserialize($shiptime_data->package_data);
			$ship_addr = $order->get_address('shipping');
			$bill_addr = $order->get_address('billing');

			// Store data from form submit
			$c=count($shiptime_pkgs);
			$pkgs = array();
			for ($i=1; $i<=$c; $i++) {
				$pkgs[] = array(
					'weight' => sanitize_text_field($_GET["parcel_weight_{$i}"]),
					'length' => sanitize_text_field($_GET["parcel_length_{$i}"]),
					'width' => sanitize_text_field($_GET["parcel_width_{$i}"]),
					'height' => sanitize_text_field($_GET["parcel_height_{$i}"])
				);
			}
			$wpdb->update(
				"{$wpdb->prefix}shiptime_order",
				array(
					'package_data' => serialize($pkgs),
					'shipping_service' => sanitize_text_field($_GET['shiptime_shipping_method'])
				),
				array( 'post_id' => $id ),
				array(
					'%s',
					'%s'
				),
				array( '%d' )
			);

			$shiptime_data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shiptime_order WHERE post_id={$id}");
			$shiptime_pkgs = unserialize($shiptime_data->package_data);
			$shiptime_settings = get_option('woocommerce_shiptime_settings');

			// Setup XML Request
			$req = new emergeit\GetRatesRequest();

			// Pull merchant info from ShipTime signup
			$req->From->Attention = ucwords($shiptime_auth->first_name) . ' ' . ucwords($shiptime_auth->last_name);
			$req->From->City = ucwords($shiptime_auth->city);
			$req->From->Phone = $shiptime_auth->phone;
			$req->From->CompanyName = $shiptime_auth->company;
			$req->From->CountryCode = $shiptime_auth->country;
			$req->From->Email = $shiptime_auth->email;
			$req->From->PostalCode = $shiptime_auth->zip;
			$req->From->Province = $shiptime_auth->state;
			$req->From->StreetAddress = ucwords($shiptime_auth->address);
			$req->From->Notify = false;
			$req->From->Residential = false;
			// Pull customer info from Woo order
			$user_info = get_user_meta(absint($order->customer_user));
			$req->To->Attention = ucwords($ship_addr['first_name'] . ' ' . $ship_addr['last_name']);
			$req->To->City = ucwords($ship_addr['city']);
			$req->To->Phone = $bill_addr['phone'];
			$req->To->CompanyName = !empty($ship_addr['company']) ? $ship_addr['company'] : '-';
			$req->To->CountryCode = $ship_addr['country'];
			$req->To->Email = $bill_addr['email'];
			$req->To->PostalCode = $ship_addr['postcode'];
			$req->To->Province = $ship_addr['state'];
			$req->To->StreetAddress = ucwords($ship_addr['address_1']);
			$req->To->StreetAddress2 = ucwords($ship_addr['address_2']);
			$req->To->Notify = false;
			$req->To->Residential = false;

			$req->PackageType = 'PACKAGE';
			$is_domestic = ($order->shipping_country == $shiptime_auth->country);

			foreach ($shiptime_pkgs as $pkg) {
				$item = new emergeit\LineItem();
				$item->Length->UnitsType = 'IN';
				$item->Length->Value = woocommerce_get_dimension($pkg['length'], 'in');
				$item->Width->UnitsType = 'IN';
				$item->Width->Value = woocommerce_get_dimension($pkg['width'], 'in');
				$item->Height->UnitsType = 'IN';
				$item->Height->Value = woocommerce_get_dimension($pkg['height'], 'in');
				$item->Weight->UnitsType = 'LB';
				// TODO: Support packages < 1 LB
				$pkg_weight = woocommerce_get_weight($pkg['weight'], 'lbs');
				$item->Weight->Value = $pkg_weight >= 1 ? $pkg_weight : 1;

				if (!$is_domestic) {
					$desc = array();
					foreach ( $order->get_items( array( 'line_item' ) ) as $iid => $data ) {
						$product = $order->get_product_from_item( $data );
						$desc[] = $product->get_title();
					}
					$item->Description = implode(',', $desc);
				}

				$req->ShipmentItems[] = $item;
			}
			$req->DeferredProcessing = false;

			// Int'l Shipment?
			if (!$is_domestic) {
				// Customs Invoice Required
				$dt = new emergeit\DutiesAndTaxes();
				$dt->Dutiable = true;
				$dt->Selection = 'SHIPPER'; // 'CONSIGNEE'
				$req->CustomsInvoice->DutiesAndTaxes = $dt;

				$ic = new emergeit\InvoiceContact();
				$ic->City = ucwords($bill_addr['city']);
				$ic->CompanyName = !empty($bill_addr['company']) ? $bill_addr['company'] : 'NA';
				$ic->CountryCode = $order->shipping_country;
				$ic->Email = $bill_addr['email'];
				$ic->Phone = $bill_addr['phone'];
				$ic->PostalCode = $bill_addr['postcode'];
				$ic->Province = $bill_addr['state'];
				$ic->StreetAddress = ucwords($bill_addr['address_1']);
				$ic->CustomsBroker = '-';
				$ic->ShipperTaxId = '-';
				$req->CustomsInvoice->InvoiceContact = $ic;

				$req->CustomsInvoice->InvoiceItems = array();
				foreach ( $order->get_items( array( 'line_item' ) ) as $item_id => $item ) {
					$product = $order->get_product_from_item( $item );

					$i = new emergeit\InvoiceItem();
					$i->Code = get_post_meta($product->id, 'shiptime_hs_code', true);
					$i->Description = $product->get_title();
					$i->Origin = get_post_meta($product->id, 'shiptime_origin_country', true);
					$i->Quantity->Value = (int)$item['qty'];
					$i->UnitPrice->Amount = $item['line_subtotal'];
					$i->UnitPrice->CurrencyCode = get_woocommerce_currency();

					$req->CustomsInvoice->InvoiceItems[] = $i;
				}
			} else {
				unset($req->CustomsInvoice);
			}

			if ($this->_ratingClient->isConnected()) {
				// New API call
				$shipRates = $this->_ratingClient->getRates($req);

				if (!empty($shipRates->AvailableRates)) {
					foreach ($shipRates->AvailableRates as $shipRate) {
						$l = strpos($shipRate->ServiceName, $shipRate->CarrierName) !== false ? $shipRate->ServiceName : $shipRate->CarrierName . " " . (!$is_domestic && stripos($shipRate->ServiceName, 'Ground') !== false ? "International " : "") . $shipRate->ServiceName;
						if (strtolower($l) == strtolower(sanitize_text_field($_GET['shiptime_shipping_method']))) {
							$current_rate = wc_price($order->get_total_shipping(), array('currency' => $order->get_order_currency()));
							$rate_info = $this->shiptime_rate_details($shipRate);
							if (array_key_exists('total', $rate_info) && array_key_exists('details', $rate_info)) {
								$msg = '<strong>'.$l.'</strong><br>'.$rate_info['details'];
								if (get_woocommerce_currency() != 'CAD') {
									$msg .= "<br><em>Totals displayed in CAD</em><br>";
									$msg .= "Total (".get_woocommerce_currency()."): ".get_woocommerce_currency_symbol().emergeit\CurrencyUtil::convert('CAD',get_woocommerce_currency(),$rate_info['total']);
								}	
								$new_rate = $rate_info['total'];
							}
							break;
						}
					}
				} else {
					$msg = "Unable to determine shipping rates.";
					foreach ($shipRates->Messages as $m) {
						$msg .= "<pre><strong>".print_r($m->Severity, true)."</strong>: ".print_r(htmlentities($m->Text), true)."</pre>";
					}
					$err = true;
				}
			} else {
				$msg = "Connection to ShipTime has failed. Please try again in a moment.";
				$err = true;
			}

			if (!empty($msg)) {
				if ($err) {
					ob_start();
					echo '<div class="error"><p>'.$msg.'</p></div>';
				} else {
					$current_rate = (float)preg_replace('/&.*?;/', '', strip_tags($current_rate));
					$new_rate = (float)preg_replace('/&.*?;/', '', strip_tags($new_rate));
					// API returns prices in CAD
					// Convert, if necessary, to store currency
					if (get_woocommerce_currency() != 'CAD') {
						$new_rate = emergeit\CurrencyUtil::convert('CAD',get_woocommerce_currency(),$new_rate);
					}
					if ($new_rate == $current_rate) {
						$recalc = "<span style='padding:5px;display:block;background-color:#efefef;color:#444'>Shipping rate for this order recalculated to be the same as the rate your customer paid.</span>";
					} elseif ($new_rate > $current_rate) {
						$recalc = "<span style='padding:5px;display:block;background-color:#f6e5e5;color:#A00'>Note: New shipping rate for this order is " . wc_price($new_rate) . ", which is " . wc_price($new_rate-$current_rate) . " more than the rate your customer paid.</span>";
					} else {
						$recalc = "<span style='padding:5px;display:block;background-color:#e5f6e5;color:#0A0'>Note: New shipping rate for this order is " . wc_price($new_rate) . ", which is " . wc_price($current_rate-$new_rate) . " less than the rate your customer paid.</span>";
					}

					$wpdb->update(
						"{$wpdb->prefix}shiptime_order",
						array(
							'recalc' => $recalc,
							'recalc_rate' => number_format($new_rate, 2)
						),
						array( 'post_id' => $id ),
						array(
							'%s','%f'
						),
						array( '%d' )
					);

					ob_start();
					echo '<div class="updated"><p>'.$msg.'</p></div>';
				}
			}
		}
	}

	function get_wc_shipping_method($order) {
		$shipping_methods = $order->get_shipping_methods();

		if (!$shipping_methods) {
			return false;
		}

		return array_shift($shipping_methods);
	}

	function get_wc_order($orderId) {
		if (!class_exists('WC_Order')) {
			return false;
		}
		try {
			return new WC_Order($orderId);
		} catch (Exception $e) {
			return false;
		}
	}

	function obj_check($obj, $prop) {
		if (is_object($obj) && isset($obj->{$prop})) {
			return $obj->{$prop};
		} else {
			return "";
		}
	}

	function arr_check($arr, $key) {
		if (is_array($arr) && array_key_exists($key, $arr)) {
			return $arr[$key];
		} else {
			return "";
		}
	}

}

new WC_Order_ShipTime();
