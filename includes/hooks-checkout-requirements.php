<?php
if (!defined('ABSPATH')) {
    exit;
}
add_filter('woocommerce_billing_fields', function ($fields) {
    if (isset($fields['billing_phone'])) {
        $fields['billing_phone']['required'] = true;
    }
    return $fields;
});

add_action('woocommerce_checkout_process', function () {
    if (empty($_POST['billing_phone'])) {
        wc_add_notice(__('Phone number is required.', 'woocommerce'), 'error');
    }
});
