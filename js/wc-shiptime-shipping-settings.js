jQuery(document).ready(function($) {

    toggle_services();
    toggle_fallback();

    $(document).on('change', '#woocommerce_shiptime_enable_intl', function(e) {
        toggle_services();
    });

    $(document).on('change', '#woocommerce_shiptime_fallback_type', function(e) {
        toggle_fallback();
    });

    function toggle_services() {
        var enable_intl = $('#woocommerce_shiptime_enable_intl').prop("checked");
        if (enable_intl) {
            $('tr.intl').fadeIn();
        }
        else {
            $('tr.intl').fadeOut();
        }
    }

    function toggle_fallback() {
        var fallback_type = $('#woocommerce_shiptime_fallback_type').val();
        if (fallback_type == 'per_item') {
            $('#woocommerce_shiptime_fallback_fee').closest('tr').fadeIn();
            $('#woocommerce_shiptime_fallback_max').closest('tr').fadeIn();
        }
        else if (fallback_type == 'per_order') {
            $('#woocommerce_shiptime_fallback_fee').closest('tr').fadeIn();
            $('#woocommerce_shiptime_fallback_max').closest('tr').fadeOut();
        } else {
            $('#woocommerce_shiptime_fallback_fee').closest('tr').fadeOut();
            $('#woocommerce_shiptime_fallback_max').closest('tr').fadeOut();
        }
    }

});