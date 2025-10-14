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

        // Only attempt cart add if available
        if ($domain_available && $price > 0) {

            // Initialise WC session/cart/customer for REST request
            if (null === WC()->session) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (null === WC()->customer) {
                WC()->customer = new WC_Customer(get_current_user_id(), true);
            }
            if (null === WC()->cart) {
                WC()->cart = new WC_Cart();
            }

            // Find the domain product
            $product_id = wc_get_product_id_by_sku('register-domain');
            if ($product_id) {
                WC()->cart->add_to_cart($product_id, 1, 0, [], [
                    'domain_name'  => $sld,
                    'domain_tld'   => $tld,
                    'domain_registration_price' => $price,
                    'is_domain_registration' => true,
                ]);
            } else {
                return "couldn't find register-domain product";
            }
        } else {
            return "domain not available or price is 0";
        }

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
    // Basic nonce check from localized JS
    $nonce = $request->get_header('X-WP-Nonce') ?? '';
    if (! wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_REST_Response(['error' => 'Invalid token'], 403);
    }

    $params = $request->get_json_params();
    $domain_name = isset($params['domain_name']) ? sanitize_text_field($params['domain_name']) : '';
    $domain_tld  = isset($params['domain_tld']) ? sanitize_text_field($params['domain_tld']) : '';
    $price       = isset($params['domain_registration_price']) ? floatval($params['domain_registration_price']) : 0;

    if (empty($domain_name) || empty($domain_tld) || $price <= 0) {
        return new WP_REST_Response(['error' => 'Missing or invalid data'], 400);
    }

    // Ensure WC session/cart/customer exists for this request
    pdh_ensure_woocommerce_cart();

    // Locate product (example: find by SKU or slug)
    $product_id = wc_get_product_id_by_sku('register-domain'); // or use get_page_by_path( ... )
    if (! $product_id) {
        return new WP_REST_Response(['error' => 'Domain product not found'], 404);
    }

    $quantity = 1;
    $variation_id = 0;
    $variation = [];
    $cart_item_data = [
        'domain_name'  => $domain_name,
        'domain_tld'   => $domain_tld,
        'domain_registration_price' => $price,
    ];

    // Add to cart
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);

    if (! $cart_item_key) {
        return new WP_REST_Response(['error' => 'Failed to add to cart'], 500);
    }

    // Optionally set cart item price override by filtering woocommerce_get_price or using 'woocommerce_add_cart_item' filter
    // For immediate display we can set the cart item data now (price override will need a filter to persist)
    $cart_item = WC()->cart->get_cart_item($cart_item_key);
    if ($cart_item) {
        //  need to use a filter to affect price when cart is rendered / totals calculated (see below)
        add_action('woocommerce_before_calculate_totals', 'pdh_set_domain_cart_item_price', 20, 1);
        function pdh_set_domain_cart_item_price($cart)
        {
            if (is_admin() && ! defined('DOING_AJAX')) {
                return;
            }
            // Make sure cart is loaded
            foreach ($cart->get_cart() as $cart_item) {
                if (! empty($cart_item['domain_registration_price'])) {
                    $price = floatval($cart_item['domain_registration_price']);
                    if ($price > 0) {
                        $cart_item['data']->set_price($price);
                    }
                }
            }
        }
    }

    return rest_ensure_response([
        'success' => true,
        'cart_item_key' => $cart_item_key,
    ]);
}
