<?php
if (!defined('ABSPATH'))
    exit;

// Show field on product page
add_action('woocommerce_single_product_summary', function () {
    global $product;

    if ($product->get_type() === 'domain') {
        woocommerce_form_field('domain_name', [
            'type' => 'text',
            'required' => true,
            'label' => __('Domain Name ', 'wp-enom-hestia-reseller'),
            'placeholder' => 'example.com',
            'class' => 'input'
        ]);
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
            wc_add_notice(__('Please enter a domain name.', 'wp-enom-hestia-reseller'), 'error');
            return false;
        }
    }
    return $passed;
}, 10, 3);

// NOTE: Cart item data is now handled in hooks-domain-pricing.php
// The woocommerce_add_cart_item_data filter has been removed from here to avoid conflicts

// Show in cart and checkout - This stays here as it's just display
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (isset($cart_item['domain_name'])) {
        $item_data[] = [
            'key' => __('Domain Name', 'wp-enom-hestia-reseller'),
            'value' => esc_html($cart_item['domain_name']),
        ];
    }
    return $item_data;
}, 10, 2);

// Save into order item
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($values['domain_name'])) {
        $item->add_meta_data('domain_name', $values['domain_name'], true);
    }
}, 10, 4);

// Show domain in admin order items
add_action('woocommerce_before_order_itemmeta', function ($item_id, $item, $product) {
    if ($item->get_meta('domain_name')) {
        echo '<p><strong>' . __('Domain Name', 'wp-enom-hestia-reseller') . ':</strong> ' . esc_html($item->get_meta('domain_name')) . '</p>';
    }
}, 10, 3);

// Add editable field in admin
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
    if ($product && $product->get_type() === 'domain') {
        woocommerce_wp_text_input([
            'id' => 'order_item_domain_name_' . $item_id,
            'name' => 'order_item_domain_name[' . $item_id . ']',
            'label' => __('Domain Name', 'wp-enom-hestia-reseller'),
            'value' => $item->get_meta('domain_name'),
        ]);
    }
}, 10, 3);

// Save editable field
add_action('woocommerce_before_save_order_item', function ($item_id, $item) {
    if (isset($_POST['order_item_domain_name'][$item_id])) {
        $domain = sanitize_text_field($_POST['order_item_domain_name'][$item_id]);
        $item->update_meta_data('domain_name', $domain);
    }
}, 10, 2);

// Template override
add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
    $plugin_path = plugin_dir_path(__FILE__) . 'templates/';

    // Look in plugin's template folder first
    if (file_exists($plugin_path . $template_name)) {
        $template = $plugin_path . $template_name;
    }

    return $template;
}, 10, 3);
