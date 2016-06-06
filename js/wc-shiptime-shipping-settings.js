jQuery(document).ready(function($) {

    toggle_services();

    $(document).on('change', '#woocommerce_shiptime_enable_intl', function(e) {
        toggle_services();
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

});