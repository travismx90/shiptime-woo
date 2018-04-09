<?php

/**
 * Output a select (multiple) input box.
 *
 * @access public
 * @param array $field
 * @param String $key
 * @return void
 */
function shiptime_wp_multi_select($field, $key) {
    $field['class'] = isset($field['class']) ? $field['class'] : 'select short';
    $field['wrapper_class'] = isset($field['wrapper_class']) ? $field['wrapper_class'] : '';
	$shiptime_settings = get_option('woocommerce_shiptime_settings');
    $field['value'] = isset($field['value']) ? $field['value'] : (array_key_exists($key, $shiptime_settings) ? $shiptime_settings[$key] : '');
    
    echo '<p style="font-weight:bold" class="form-field ' . esc_attr($field['id']) . '_field ' . esc_attr($field['wrapper_class']) . '"><label for="' . esc_attr($field['id']) . '">' . wp_kses_post($field['label']) . '</label><select id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '[]" class="' . esc_attr($field['class']) . '" multiple>';

    foreach ($field['options'] as $key => $value) {
        $selected = (is_array($field['value']) && in_array($key, $field['value'])) ? ' selected' : '';
        echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($value) . '</option>';
    }

    echo '</select> ';

    if (!empty($field['description'])) {

        if (isset($field['desc_tip']) && false !== $field['desc_tip']) {
            echo '<img class="help_tip" data-tip="' . esc_attr($field['description']) . '" src="' . esc_url(WC()->plugin_url()) . '/assets/images/help.png" height="16" width="16" />';
        } else {
            echo '<span class="description">' . wp_kses_post($field['description']) . '</span>';
        }
    }
    echo '</p>';
}
