<?php

if (!defined('ABSPATH')) {
    exit;
}

// include class enom
if (!class_exists('PDH_Enom_API')) {
    require_once plugin_dir_path(__FILE__) . './class-enom-api.php';
}

function test_callback()
{
    $enom = new PDH_Enom_API();
    $response = $enom->test();
    return rest_ensure_response($response);
}

function check_domain_callback(WP_REST_Request $request)
{
    $params = $request->get_json_params();
    $sld = sanitize_text_field($params['domain'] ?? '');
    $tld = sanitize_text_field($params['tld'] ?? '');

    $securityToken = $request->get_header('X-WP-Nonce');

    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }

    try {
        $enom = new PDH_Enom_API();
        $response = $enom->check_domain($sld, $tld);

        // Determine availability
        $domain_available = !empty($response['Domains']['Domain']['RRPText']) && $response['Domains']['Domain']['RRPText'] !== 'domain not available';
        $price = floatval($response['Domains']['Domain']['Prices']['Registration'] ?? 0);

        // Return response - let frontend handle cart addition
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}

function get_tld_list_callback(WP_REST_Request $request)
{
    $securityToken = $request->get_header('X-WP-Nonce');
    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }
    try {
        $enom = new PDH_Enom_API();
        $response = $enom->get_tld_list();
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}

function get_name_suggestions_callback(WP_REST_REQUEST $request)
{
    $securityToken = $request->get_header('X-WP-Nonce');
    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }
    $params = $request->get_json_params();
    $searchterm = sanitize_text_field($params['searchterm'] ?? '');
    try {
        $enom = new PDH_Enom_API();
        $response = $enom->get_name_suggestions($searchterm);
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}

function pdh_ensure_woocommerce_cart()
{
    // If WooCommerce core is not active, bail.
    if (! class_exists('WooCommerce')) {
        return;
    }

    // Session handler
    if (null === WC()->session) {
        $handler_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
        if (class_exists($handler_class)) {
            WC()->session = new $handler_class();
            // init sets session cookie etc.
            if (method_exists(WC()->session, 'init')) {
                WC()->session->init();
            }
        }
    }

    // Customer
    if (null === WC()->customer) {
        // Pass true to force data loading
        WC()->customer = new WC_Customer(get_current_user_id(), true);
    }

    // Cart
    if (null === WC()->cart) {
        WC()->cart = new WC_Cart();
    }
}

function pdh_rest_add_domain_to_cart_callback(WP_REST_Request $request)
{
    error_log('=== REST ADD TO CART CALLED ===');

    // Basic nonce check from localized JS
    $nonce = $request->get_header('X-WP-Nonce') ?? '';
    if (! wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['error' => 'Invalid token'], 403);
    }

    $params = $request->get_json_params();
    error_log('Request params: ' . print_r($params, true));

    $domain_name = isset($params['domain_name']) ? sanitize_text_field($params['domain_name']) : '';
    $domain_tld  = isset($params['domain_tld']) ? sanitize_text_field($params['domain_tld']) : '';
    $price       = isset($params['domain_registration_price']) ? floatval($params['domain_registration_price']) : 0;

    error_log("Domain: $domain_name, TLD: $domain_tld, Price: $price");

    if (empty($domain_name) || empty($domain_tld) || $price <= 0) {
        error_log('ERROR: Missing or invalid data');
        return new WP_REST_Response(['error' => 'Missing or invalid data'], 400);
    }

    // Ensure WC session/cart/customer exists for this request
    pdh_ensure_woocommerce_cart();

    // Locate product (example: find by SKU or slug)
    $product_id = wc_get_product_id_by_sku('register-domain');

    if (! $product_id) {
        error_log('ERROR: Product with SKU "register-domain" not found');
        return new WP_REST_Response(['error' => 'Domain product not found'], 404);
    }

    error_log('Found product ID: ' . $product_id);

    $quantity = 1;
    $variation_id = 0;
    $variation = [];

    // This cart_item_data will be picked up by the woocommerce_add_cart_item_data filter
    $cart_item_data = [
        'domain_name'  => $domain_name,
        'domain_tld'   => $domain_tld,
        'domain_registration_price' => $price,
        'unique_key' => md5(microtime() . rand()), // Prevent merging
    ];

    error_log('Cart item data: ' . print_r($cart_item_data, true));

    // Add to cart
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);

    if (! $cart_item_key) {
        error_log('ERROR: Failed to add to cart');
        return new WP_REST_Response(['error' => 'Failed to add to cart'], 500);
    }

    error_log('Successfully added to cart with key: ' . $cart_item_key);

    // CRITICAL FIX: Manually set the price on the cart item's product object
    // This is necessary because woocommerce_before_calculate_totals may not run in REST context
    $cart_contents = WC()->cart->get_cart();
    if (isset($cart_contents[$cart_item_key])) {
        $cart_item = &$cart_contents[$cart_item_key];
        if (isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')) {
            $cart_item['data']->set_price($price);
            error_log('Manually set product price to: ' . $price);
        }
    }

    // Force cart calculation to apply the price
    WC()->cart->calculate_totals();

    // Get the cart item to verify the fix worked
    $verify_item = WC()->cart->get_cart_item($cart_item_key);
    $final_price = 'UNKNOWN';

    if ($verify_item && isset($verify_item['data']) && is_a($verify_item['data'], 'WC_Product')) {
        $final_price = $verify_item['data']->get_price();
    }

    error_log('Final product price: ' . $final_price);

    return rest_ensure_response([
        'success' => true,
        'cart_item_key' => $cart_item_key,
        'debug' => [
            'price_in_cart_data' => $verify_item['domain_registration_price'] ?? 'NOT SET',
            'product_price' => $final_price,
        ]
    ]);
}
