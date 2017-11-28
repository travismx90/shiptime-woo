jQuery(document).ready(function($) {

	toggle_ship_fields();

	$(document).on('change', '#shiptime_ship_method', function(e) {
		toggle_ship_fields();
	});

	function toggle_ship_fields() {
		var ship_method = $('#shiptime_ship_method').val();

		if (ship_method === 'F') {
			// F = flat fee shipping
			$('.shiptime_ff').fadeIn();
		} else {
			// C = carrier rates from ShipTime API
			// Z = Free (zero shipping rate)
			// D = Free Domestic (intl shipments use carrier rates)
			$('.shiptime_ff').fadeOut();
		}
	}

});
