<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add domain price and metadata to cart items
 */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    error_log('=== ADD CART ITEM DATA HOOK FIRED ===');
    error_log('Product ID: ' . $product_id);

    $product = wc_get_product($product_id);

    // Only process domain products
    if (!$product || $product->get_type() !== 'domain') {
        error_log('Not a domain product, skipping');
        return $cart_item_data;
    }

    error_log('Domain product detected!');
    $domain_name = '';
    $domain_tld = '';

    // ALWAYS add unique key first to prevent merging - CRITICAL!
    $cart_item_data['unique_key'] = md5(microtime() . rand());

    // Get domain name (from either source)
    if (!empty($_POST['domain_name'])) {
        $cart_item_data['domain_name'] = sanitize_text_field(wp_unslash($_POST['domain_name']));
        error_log('Domain name set: ' . $cart_item_data['domain_name']);
    }

    // Get domain TLD
    if (!empty($_POST['domain_tld'])) {
        $cart_item_data['domain_tld'] = sanitize_text_field(wp_unslash($_POST['domain_tld']));
        error_log('Domain TLD set: ' . $cart_item_data['domain_tld']);
    }
    // Get domain registration period
    if (!empty($_POST['domain_years'])) {
        $cart_item_data['domain_years'] = intval(wp_unslash($_POST['domain_years']));
        error_log('Domain registration period set: ' . $cart_item_data['domain_years']);
    }

    // Get and validate the price
    if (isset($_POST['domain_registration_price'])) {
        $price = floatval(str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['domain_registration_price']))));
        error_log('Raw price from POST: ' . $_POST['domain_registration_price']);
        error_log('Processed price: ' . $price);

        if ($price > 0) {
            $cart_item_data['domain_registration_price'] = $price;
            error_log('Price saved to cart item data: ' . $price);
        } else {
            error_log('WARNING: Price is 0 or negative, not saving');
        }
    } else {
        error_log('WARNING: domain_registration_price not found in POST');
    }

    // Get and validate the price (this is per-year price)
    $price_per_year = 0;
    if (isset($_POST['domain_registration_price'])) {
        $price_per_year = floatval(str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['domain_registration_price']))));
        error_log('Price per year from POST: ' . $price_per_year);
    }

    // Get number of years (default to 1)
    $years = 1;
    if ($price_per_year > 0) {
        // Calculate total price based on years
        $total_price = $price_per_year * $years;
        $cart_item_data['domain_registration_price'] = $total_price;
        $cart_item_data['domain_price_per_year'] = $price_per_year;
        error_log('Total price saved to cart: ' . $total_price . ' (' . $years . ' years x £' . $price_per_year . ')');
    } else {
        error_log('WARNING: Price is 0 or negative, not saving');
    }

    // Create a unique identifier based on domain + tld (not random)
    // This allows WooCommerce to detect if the same domain is being added again
    $full_domain = $domain_name . ($domain_tld ? '.' . $domain_tld : '');
    if ($full_domain) {
        $cart_item_data['domain_identifier'] = md5($full_domain);
        error_log('Domain identifier: ' . $cart_item_data['domain_identifier']);
    }

    error_log('Final cart_item_data with unique_key: ' . $cart_item_data['unique_key']);

    return $cart_item_data;
}, 10, 3);


/**
 * Set price when cart item is loaded from session
 * This ensures the price persists across page loads
 */
add_filter('woocommerce_get_cart_item_from_session', function ($cart_item, $values, $key) {
    if (!empty($values['domain_registration_price'])) {
        $price = floatval($values['domain_registration_price']);
        if ($price > 0 && isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')) {
            $cart_item['data']->set_price($price);
            error_log('Set price from session: ' . $price);
        }
    }
    return $cart_item;
}, 10, 3);


/**
 * Prevent domain products from merging in cart
 * Each domain registration should be a separate line item
 */
add_filter('woocommerce_add_to_cart_sold_individually_found_in_cart', function ($found_in_cart, $product_id) {
    $product = wc_get_product($product_id);

    // For domain products, we handle "sold individually" differently
    // We want each domain to be a separate cart item, not merged
    if ($product && $product->get_type() === 'domain') {
        // Return false to allow multiple separate items
        // The unique_key in cart_item_data will prevent actual merging
        return false;
    }

    return $found_in_cart;
}, 10, 2);


/**
 * Ensure WooCommerce sets the correct price before totals are calculated
 * CRITICAL: This must run to actually change the price displayed
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    error_log('=== BEFORE CALCULATE TOTALS HOOK FIRED ===');

    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return; // Prevent infinite loops
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        error_log('Processing cart item: ' . $cart_item_key);

        if (!empty($cart_item['domain_registration_price']) && isset($cart_item['data'])) {
            $price = floatval($cart_item['domain_registration_price']);
            error_log('Found domain_registration_price: ' . $price);

            if ($price > 0 && is_a($cart_item['data'], 'WC_Product')) {
                $old_price = $cart_item['data']->get_price();
                $cart_item['data']->set_price($price);
                $new_price = $cart_item['data']->get_price();

                error_log('Price changed from ' . $old_price . ' to ' . $new_price);
            } else {
                error_log('WARNING: Could not set price. Price: ' . $price . ', Is WC_Product: ' . is_a($cart_item['data'], 'WC_Product'));
            }
        } else {
            error_log('No domain_registration_price found in this cart item');
        }
    }
}, 10, 1);


/**
 * Display domain info in cart/checkout
 * Show the full domain name so users can distinguish between multiple domains
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
            'display' => '<strong>' . $domain_display . '</strong>', // Make it prominent
        ];
    }
    if (!empty($cart_item['domain_years'])) {
        $years = intval($cart_item['domain_years']);
        $item_data[] = [
            'key'   => __('Registration Period', 'pdh-hosting-reseller'),
            'value' => sprintf(_n('%d year', '%d years', $years, 'pdh-hosting-reseller'), $years),
        ];
    } else {
        error_log('no domain years');
    }
    if (!empty($cart_item['domain_name'])) {
        $sld = $cart_item['domain_name'];
        $item_data[] = [
            'key'   => __('SLD', 'pdh-hosting-reseller'),
            'value' => $sld,
        ];
    } else {
        error_log('no domain years');
    }
    if (!empty($cart_item['domain_tld'])) {
        $tld = $cart_item['domain_tld'];
        $item_data[] = [
            'key'   => __('TLD', 'pdh-hosting-reseller'),
            'value' =>  $tld,
        ];
    } else {
        error_log('no domain tld');
    }
    return $item_data;
}, 10, 2);


/**
 * Change the product title in cart to include domain name
 * This makes it crystal clear which domain each line item is for
 */
add_filter('woocommerce_cart_item_name', function ($product_name, $cart_item, $cart_item_key) {
    if (!empty($cart_item['domain_name'])) {
        $domain_display = esc_html($cart_item['domain_name']);
        if (!empty($cart_item['domain_tld'])) {
            $domain_display .= '.' . esc_html($cart_item['domain_tld']);
        }
        $product_name .= '<br><small style="font-size: 0.9em; color: #666;">(' . $domain_display . ')</small>';
    }
    return $product_name;
}, 10, 3);


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
    if (!empty($values['domain_years'])) {
        $years = intval($values['domain_years']);
        $item->add_meta_data(__('Registration Period', 'pdh-hosting-reseller'), $years);
    }
    // Also save the price so you know what was charged
    if (!empty($values['domain_registration_price'])) {
        $item->add_meta_data('_domain_registration_price', $values['domain_registration_price']);
    }
}, 10, 4);

/**
 * Check if the same domain already exists in cart before adding
 */
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = 0, $variations = [], $cart_item_data = []) {
    $product = wc_get_product($product_id);

    // Only check domain products
    if (!$product || $product->get_type() !== 'domain') {
        return $passed;
    }

    // Get the domain being added
    $new_domain_name = sanitize_text_field($_POST['domain_name'] ?? '');
    $new_domain_tld = sanitize_text_field($_POST['domain_tld'] ?? '');
    $new_full_domain = $new_domain_name . ($new_domain_tld ? '.' . $new_domain_tld : '');

    if (empty($new_full_domain)) {
        return $passed; // Let other validation handle empty domain
    }

    // Check if this domain already exists in cart
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['domain_name'])) {
            $existing_domain = $cart_item['domain_name'];
            if (!empty($cart_item['domain_tld'])) {
                $existing_domain .= '.' . $cart_item['domain_tld'];
            }

            if (strtolower($existing_domain) === strtolower($new_full_domain)) {
                wc_add_notice(
                    sprintf(
                        __('The domain "%s" is already in your cart. You can only register each domain once. If you want to change the registration period, please remove it from the cart first.', 'pdh-hosting-reseller'),
                        esc_html($new_full_domain)
                    ),
                    'error'
                );
                return false;
            }
        }
    }

    return $passed;
}, 10, 6);



add_action('woocommerce_order_status_completed', function ($order_id) {
    $order = wc_get_order($order_id);
    error_log('=== ORDER STATUS COMPLETED HOOK FIRED ===');

    foreach ($order->get_items() as $item) {
        // This tells Intelephense exactly what type $item is
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $product = $item->get_product();
        error_log('domain ' . $item->get_meta('domain_name'));
        error_log('tld ' . $item->get_meta('domain_tld'));
        error_log('reg type ' . $item->get_meta('domain_uk_registrant_type'));

        if ($product && $product->get_type() === 'domain') {
            // Get domain info from order
            $domain_name = $item->get_meta('domain_name'); // "example"
            $domain_tld = $item->get_meta('domain_tld');   // "com"
            $years = $item->get_meta('domain_years') ?: 1;

            // Get customer info from order
            $contact = [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'organization' => $order->get_billing_company(),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postal_code' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ];
            // Add .uk extended attributes (if present)
            $uk_fields = [
                'uk_legal_type'   => $item->get_meta('domain_uk_registrant_type'),
                'uk_reg_co_no'    => $item->get_meta('domain_uk_company_number'),
                'registered_for'  => $item->get_meta('domain_uk_trading_name'),
                'uk_reg_opt_out'  => $item->get_meta('domain_uk_reg_opt_out'),
            ];

            // Only include if relevant (.uk TLD)
            if (strpos($domain_tld, 'uk') !== false) {
                $contact = array_merge($contact, $uk_fields);
                error_log('contact array after merge ' . print_r($contact, true));
            }

            try {

                $enom = new PDH_Enom_API();

                // Register the domain
                $result = $enom->register_domain($domain_name, $domain_tld, $years, $contact);

                if ($result['success']) {
                    // Success! Update order
                    $order->add_order_note(
                        sprintf(
                            __('Domain %s successfully registered with Enom. Order ID: %s', 'pdh-hosting-reseller'),
                            $result['domain'],
                            $result['order_id']
                        )
                    );

                    // Save Enom order ID to item meta
                    $item->add_meta_data('_enom_order_id', $result['order_id'], true);
                    $item->save();

                    error_log("SUCCESS: Registered {$result['domain']} for {$years} years");
                } else {
                    throw new Exception('Registration returned unsuccessful');
                }
            } catch (Exception $e) {
                // Log error
                error_log('Domain registration error: ' . $e->getMessage());

                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('Domain registration failed: %s', 'pdh-hosting-reseller'),
                        $e->getMessage()
                    )
                );

                // Optional: Send email to admin
                wp_mail(
                    get_option('admin_email'),
                    'Domain Registration Failed',
                    "Order #{$order_id}: Failed to register {$domain_name}.{$domain_tld}\n\nError: {$e->getMessage()}"
                );
            }
        }
    }
}, 30, 7);
