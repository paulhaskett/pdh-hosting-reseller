<?php
if (!defined('ABSPATH'))
    exit;

// Show field on product page
add_action('woocommerce_single_product_summary', function () {
    global $product;

    if ($product->get_type() === 'domain') {
        echo '<div class="domain-registration-fields">';
        woocommerce_form_field('domain_name', [
            'type' => 'text',
            'required' => true,
            'label' => __('Domain Name ', 'pdh-hosting-reseller'),
            'placeholder' => 'example.com',
            'class' => 'input'
        ]);
        woocommerce_form_field('domain-years-selector', [
            'type' => 'select',
            'required' => true,
            'label' => __('Registration Period', 'pdh-hosting-reseller'),
            'class' => ['form-row-wide'],
            'options' => [
                '' => __('Please Select', 'pdh-hosting-reseller'),
                '1' => __('1 Year', 'pdh-hosting-reseller'),
                '2' => __('2 Years', 'pdh-hosting-reseller'),
                '3' => __('3 Years', 'pdh-hosting-reseller'),
                '4' => __('4 Years', 'pdh-hosting-reseller'),
                '5' => __('5 Years', 'pdh-hosting-reseller'),
                '10' => __('10 Years', 'pdh-hosting-reseller'),
            ],
            'default' => '',
        ]);
        echo '</div>';

        // Add CSS for better layout
        echo '<style>
            .domain-registration-fields { margin-bottom: 20px; }
            .domain-registration-fields .form-row { margin-bottom: 15px; }
        </style>';
    }
});



add_filter('woocommerce_quantity_input_min', function ($min, $product) {
    return $product->get_sku() === 'register-domain' ? 1 : $min;
}, 10, 2);

add_filter('woocommerce_quantity_input_max', function ($max, $product) {
    return $product->get_sku() === 'register-domain' ? 1 : $max;
}, 10, 2);

add_action('wp', function () {
    if (is_product() && get_post_field('post_name', get_the_ID()) === 'register-domain') {
        remove_action('woocommerce_before_add_to_cart_quantity', 'woocommerce_quantity_input', 10);
    }
});



// Validate input
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity) {
    $product = wc_get_product($product_id);

    if ($product && $product->get_type() === 'domain') {
        if (empty($_POST['domain_name'])) {
            wc_add_notice(__('Please enter a domain name.', 'pdh-hosting-reseller'), 'error');
            return false;
        }
    }
    if (empty($_POST['domain_years']) || !is_numeric($_POST['domain_years'])) {
        wc_add_notice(__('Please select a registration period.', 'pdh-hosting-reseller'), 'error');
        return false;
    }
    return $passed;
}, 10, 3);

// NOTE: Cart item data is now handled in hooks-domain-pricing.php
// The woocommerce_add_cart_item_data filter has been removed from here to avoid conflicts

// Show in cart and checkout - This stays here as it's just display
// add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
//     if (isset($cart_item['domain_name'])) {
//         $item_data[] = [
//             'key' => __('Domain Name', 'pdh-hosting-reseller'),
//             'value' => esc_html($cart_item['domain_name']),
//         ];
//     }
//     if (isset($cart_item['domain_years'])) {
//         $item_data[] = [
//             'key' => __('Registration Period 2', 'pdh-hosting-reseller'),
//             'value' => esc_html($cart_item['domain_years']),
//         ];
//     }
//     return $item_data;
// }, 10, 2);

// Save into order item
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($values['domain_name'])) {
        $item->add_meta_data('domain_name', $values['domain_name'], true);
    }
    if (isset($values['domain_tld'])) {
        $item->add_meta_data('domain_tld', $values['domain_tld'], true);
    }
    if (isset($values['domain_years'])) {
        $item->add_meta_data('domain_years', $values['domain_years'], true);
    }
}, 10, 4);

// Show domain in admin order items
add_action('woocommerce_before_order_itemmeta', function ($item_id, $item, $product) {
    if ($item->get_meta('domain_name')) {
        echo '<p><strong>' . __('Domain Name', 'pdh-hosting-reseller') . ':</strong> ' . esc_html($item->get_meta('domain_name')) . '</p>';
    }
    if ($item->get_meta('domain_years')) {
        echo '<p><strong>' . __('Registration Period', 'pdh-hosting-reseller') . ':</strong> ' . esc_html($item->get_meta('domain_years')) . ' Years</p>';
    }
    if ($item->get_meta('domain_tld')) {
        echo '<p><strong>' . __('TLD', 'pdh-hosting-reseller') . ':</strong> ' . esc_html($item->get_meta('domain_tld')) . ' </p>';
    }
}, 10, 3);

// Add editable field in admin not sure this is needed
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
    if ($product && $product->get_type() === 'domain') {
        woocommerce_wp_text_input([
            'id' => 'order_item_domain_name_' . $item_id,
            'name' => 'order_item_domain_name[' . $item_id . ']',
            'label' => __('Domain Name', 'pdh-hosting-resellerr'),
            'value' => $item->get_meta('domain_name'),
        ]);
    }
}, 10, 3);

// Save editable field if the above is not needed neither is this
add_action('woocommerce_before_save_order_item', function ($item) {
    $item_id = $item->get_id();
    error_log('Order item ID: ' . $item_id);
    if (isset($_POST['order_item_domain_name'][$item_id])) {
        $domain = sanitize_text_field($_POST['order_item_domain_name'][$item_id]);
        $item->update_meta_data('domain_name', $domain);
    }
}, 10, 2);

// Template override this also may not be needed
add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
    $plugin_path = plugin_dir_path(__FILE__) . 'templates/';

    // Look in plugin's template folder first
    if (file_exists($plugin_path . $template_name)) {
        $template = $plugin_path . $template_name;
    }

    return $template;
}, 10, 3);
