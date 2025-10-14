<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add domain price and metadata to cart items
 */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['domain_registration_price'])) {
        $price = floatval(str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['domain_registration_price']))));
        if ($price > 0) {
            $cart_item_data['domain_registration_price'] = $price;
            if (isset($_POST['domain_name'])) {
                $cart_item_data['domain_name'] = sanitize_text_field(wp_unslash($_POST['domain_name']));
            }
            if (isset($_POST['domain_tld'])) {
                $cart_item_data['domain_tld'] = sanitize_text_field(wp_unslash($_POST['domain_tld']));
            }
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
    }
    return $cart_item_data;
}, 10, 3);


/**
 * Ensure WooCommerce sets the correct price before totals are calculated
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (!empty($cart_item['domain_registration_price']) && is_object($cart_item['data'])) {
            $price = floatval($cart_item['domain_registration_price']);
            if ($price > 0) {
                $cart_item['data']->set_price($price);
            }
        }
    }
}, 20, 1);


/**
 * Display domain info in cart/checkout
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['domain_name'])) {
        $domain_display = esc_html($cart_item['domain_name']);
        if (!empty($cart_item['domain_tld'])) {
            $domain_display .= '.' . esc_html($cart_item['domain_tld']);
        }
        $item_data[] = [
            'key'   => __('Domain', 'pdh-hosting-reseller'),
            'value' => $domain_display,
        ];
    }
    return $item_data;
}, 10, 2);


/**
 * Persist domain data into the order
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (!empty($values['domain_name'])) {
        $domain_display = $values['domain_name'];
        if (!empty($values['domain_tld'])) {
            $domain_display .= '.' . $values['domain_tld'];
        }
        $item->add_meta_data(__('Domain', 'pdh-hosting-reseller'), $domain_display);
    }
}, 10, 4);
