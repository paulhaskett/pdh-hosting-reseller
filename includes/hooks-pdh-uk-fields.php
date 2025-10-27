<?php

/**
 * UK Legal Requirements for Domain Registration
 * 
 * Handles UK-specific legal fields required for .uk, .co.uk, .org.uk domains
 * 
 * @package PdhHostingReseller
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add UK-specific fields to domain registration form
 * Only shown when registering .uk TLDs
 */
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;

    if ($product->get_type() !== 'domain') {
        return;
    }

?>
    <div class="uk-legal-fields" style="display: none; margin-top: 20px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
        <h4><?php _e('UK Domain Registration Requirements', 'pdh-hosting-reseller'); ?></h4>
        <p style="font-size: 0.9em; color: #856404;">
            <?php _e('For .uk, .co.uk, .org.uk, and other UK domain registrations, you must declare your registrant type.', 'pdh-hosting-reseller'); ?>
        </p>

        <?php
        // Registrant Type
        woocommerce_form_field('domain_uk_registrant_type', [
            'type' => 'select',
            'required' => true,
            'label' => __('Registrant Type', 'pdh-hosting-reseller'),
            'class' => ['form-row-wide'],
            'options' => [
                '' => __('Select registrant type', 'pdh-hosting-reseller'),
                'IND' => __('UK individual', 'pdh-hosting-reseller'),
                'FIND' => __('Non-UK individual', 'pdh-hosting-reseller'),
                'LTD' => __('UK Limited Company', 'pdh-hosting-reseller'),
                'PLC' => __('UK Public Limited Company', 'pdh-hosting-reseller'),
                'PTNR' => __('UK Partnership', 'pdh-hosting-reseller'),
                'LLP' => __('UK Limited Liability Partnership', 'pdh-hosting-reseller'),
                'STRA' => __('UK Sole Trader', 'pdh-hosting-reseller'),
                'RCHAR' => __('UK Registered Charity', 'pdh-hosting-reseller'),
                'IP' => __('UK Industrial/Provident Registered Company', 'pdh-hosting-reseller'),
                'SCH' => __('UK School', 'pdh-hosting-reseller'),
                'GOV' => __('UK Government Body', 'pdh-hosting-reseller'),
                'CRC' => __('UK Corporation by Royal Charter', 'pdh-hosting-reseller'),
                'STAT' => __('UK Statutory Body', 'pdh-hosting-reseller'),
                'OTHER' => __('UK Entity (other)', 'pdh-hosting-reseller'),
                'FCORP' => __('Non-UK Corporation', 'pdh-hosting-reseller'),
                'FOTHER' => __('Non-UK Entity', 'pdh-hosting-reseller'),
            ],
        ]);

        // Company Registration Number (for companies)
        woocommerce_form_field('domain_uk_company_number', [
            'type' => 'text',
            'required' => false,
            'label' => __('Company Registration Number (if applicable)', 'pdh-hosting-reseller'),
            'class' => ['form-row-wide', 'uk-company-field'],
            'input_class' => ['input-text'],
            'description' => __('Required for UK Limited Companies, PLCs, LLPs', 'pdh-hosting-reseller'),
        ]);

        // Trading Name (optional)
        woocommerce_form_field('domain_uk_trading_name', [
            'type' => 'text',
            'required' => false,
            'label' => __('Trading Name (if different from registered name)', 'pdh-hosting-reseller'),
            'class' => ['form-row-wide'],
            'input_class' => ['input-text'],
        ]);
        ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Show/hide UK fields based on TLD
            function checkForUKDomain() {
                var domainName = $('#domain_name').val();
                var ukTlds = ['.uk', '.co.uk', '.org.uk', '.me.uk', '.ltd.uk', '.plc.uk'];
                var isUK = false;

                ukTlds.forEach(function(tld) {
                    if (domainName && domainName.toLowerCase().endsWith(tld)) {
                        isUK = true;
                    }
                });

                if (isUK) {
                    $('.uk-legal-fields').slideDown();
                    $('#domain_uk_registrant_type').attr('required', true);
                } else {
                    $('.uk-legal-fields').slideUp();
                    $('#domain_uk_registrant_type').removeAttr('required');
                }
            }

            // Check on page load and on domain name change
            $('#domain_name').on('change keyup blur', checkForUKDomain);
            checkForUKDomain();

            // Show/hide company number field based on registrant type
            $('#domain_uk_registrant_type').on('change', function() {
                var type = $(this).val();
                var companyTypes = ['LTD', 'PLC', 'LLP', 'IP', 'RCHAR', 'CRC'];

                if (companyTypes.includes(type)) {
                    $('.uk-company-field').slideDown();
                    $('#domain_uk_company_number').attr('required', true);
                } else {
                    $('.uk-company-field').slideUp();
                    $('#domain_uk_company_number').removeAttr('required');
                }
            });

        });
    </script>
<?php
}, 26); // Run after domain fields (priority 25)

/**
 * Validate UK-specific fields
 */
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity) {
    $product = wc_get_product($product_id);
    error_log('===UK ADD TO CART VALIDATION FIRED===');

    if (!$product || $product->get_type() !== 'domain') {
        return $passed;
    }

    // Check if this is a UK domain
    $domain_name = isset($_POST['domain_name']) ? sanitize_text_field(wp_unslash($_POST['domain_name'])) : '';
    $uk_tlds = ['.uk', '.co.uk', '.org.uk', '.me.uk', '.ltd.uk', '.plc.uk'];
    $is_uk_domain = false;

    foreach ($uk_tlds as $tld) {
        if (stripos($domain_name, $tld) !== false) {
            $is_uk_domain = true;
            break;
        }
    }

    // If UK domain, validate UK fields
    if ($is_uk_domain) {
        // Registrant type is required
        if (empty($_POST['domain_uk_registrant_type'])) {
            wc_add_notice(
                __('Registrant type is required for UK domain registration.', 'pdh-hosting-reseller'),
                'error'
            );
            $passed = false;
        }

        // Company number required for certain types
        $registrant_type = isset($_POST['domain_uk_registrant_type']) ? sanitize_text_field(wp_unslash($_POST['domain_uk_registrant_type'])) : '';
        $company_types = ['LTD', 'PLC', 'LLP', 'IP', 'RCHAR', 'CRC'];

        if (in_array($registrant_type, $company_types)) {
            if (empty($_POST['domain_uk_company_number'])) {
                wc_add_notice(
                    __('Company registration number is required for this registrant type.', 'pdh-hosting-reseller'),
                    'error'
                );
                $passed = false;
            }
        }
    }

    return $passed;
}, 15, 3); // Run after standard validation (priority 10)

/**
 * Save UK-specific data to cart item
 * This extends the existing hook in hooks-domain-pricing.php
 */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    $product = wc_get_product($product_id);
    error_log('===UK FIELDS ADD TO CART ITEM DATA HOOK FIRED===');
    error_log('product add to cart UK fields product type -' . $product->get_type());

    if (!$product || $product->get_type() !== 'domain') {
        return $cart_item_data;
    }

    // Save UK legal fields if provided
    $uk_fields = [
        'domain_uk_registrant_type',
        'domain_uk_company_number',
        'domain_uk_trading_name',
    ];

    foreach ($uk_fields as $field) {
        if (!empty($_POST[$field])) {
            $cart_item_data[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
            error_log('cart_item_data ' . $field);
        } else {
            error_log('no POST data for uk fields');
        }
    }
    error_log(print_r($cart_item_data, true));

    return $cart_item_data;
}, 20, 3); // Run after main cart data hook (priority 10)

/**
 * Display UK legal info in cart
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    error_log('===UK GET ITEM DATA HOOK FRIED===');
    // Check if this item has UK legal data
    if (!empty($cart_item['domain_uk_registrant_type'])) {
        $registrant_type_labels = [
            'IND' => __('UK individual', 'pdh-hosting-reseller'),
            'FIND' => __('Non-UK individual', 'pdh-hosting-reseller'),
            'LTD' => __('UK Limited Company', 'pdh-hosting-reseller'),
            'PLC' => __('UK Public Limited Company', 'pdh-hosting-reseller'),
            'PTNR' => __('UK Partnership', 'pdh-hosting-reseller'),
            'LLP' => __('UK Limited Liability Partnership', 'pdh-hosting-reseller'),
            'STRA' => __('UK Sole Trader', 'pdh-hosting-reseller'),
            'RCHAR' => __('UK Registered Charity', 'pdh-hosting-reseller'),
            'FCORP' => __('Non-UK Corporation', 'pdh-hosting-reseller'),
        ];

        $type = $cart_item['domain_uk_registrant_type'];
        $label = isset($registrant_type_labels[$type]) ? $registrant_type_labels[$type] : $type;

        $item_data[] = [
            'key'   => __('Registrant Type', 'pdh-hosting-reseller'),
            'value' => $label,
        ];

        if (!empty($cart_item['domain_uk_company_number'])) {
            $item_data[] = [
                'key'   => __('Company Number', 'pdh-hosting-reseller'),
                'value' => esc_html($cart_item['domain_uk_company_number']),
            ];
        }
        if (!empty($cart_item['domain_uk_trading_name'])) {
            $item_data[] = [
                'key'   => __('Company Trading Name', 'pdh-hosting-reseller'),
                'value' => esc_html($cart_item['domain_uk_trading_name']),
            ];
        }
        if (!empty($cart_item['domain_uk_'])) {
            $item_data[] = [
                'key'   => __('Company Number', 'pdh-hosting-reseller'),
                'value' => esc_html($cart_item['domain_uk_trading_name']),
            ];
        }
    }

    return $item_data;
}, 20, 2);

/**
 * Save UK legal data to order
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    // Save UK legal fields to order if present
    if (!empty($values['domain_uk_registrant_type'])) {
        $item->add_meta_data('domain_uk_registrant_type', $values['domain_uk_registrant_type']);

        // Also save a display-friendly version
        $registrant_type_labels = [
            'IND' => __('UK individual', 'pdh-hosting-reseller'),
            'FIND' => __('Non-UK individual', 'pdh-hosting-reseller'),
            'LTD' => __('UK Limited Company', 'pdh-hosting-reseller'),
            'PLC' => __('UK Public Limited Company', 'pdh-hosting-reseller'),
            'PTNR' => __('UK Partnership', 'pdh-hosting-reseller'),
            'LLP' => __('UK Limited Liability Partnership', 'pdh-hosting-reseller'),
            'STRA' => __('UK Sole Trader', 'pdh-hosting-reseller'),
            'RCHAR' => __('UK Registered Charity', 'pdh-hosting-reseller'),
            'FCORP' => __('Non-UK Corporation', 'pdh-hosting-reseller'),
        ];

        $type = $values['domain_uk_registrant_type'];
        $label = isset($registrant_type_labels[$type]) ? $registrant_type_labels[$type] : $type;

        $item->add_meta_data(__('UK Registrant Type', 'pdh-hosting-reseller'), $label, true);
    }

    if (!empty($values['domain_uk_company_number'])) {
        $item->add_meta_data('domain_uk_company_number', $values['domain_uk_company_number']);
        $item->add_meta_data(__('UK Company Number', 'pdh-hosting-reseller'), $values['domain_uk_company_number'], true);
    }

    if (!empty($values['domain_uk_trading_name'])) {
        $item->add_meta_data('domain_uk_trading_name', $values['domain_uk_trading_name']);
        $item->add_meta_data(__('UK Trading Name', 'pdh-hosting-reseller'), $values['domain_uk_trading_name']);
    }
}, 20, 4);

/**
 * Helper function to check if domain is UK TLD
 * 
 * @param string $domain Full domain name (e.g., "example.co.uk")
 * @return bool
 */
function pdh_is_uk_domain($domain)
{
    $uk_tlds = ['.uk', '.co.uk', '.org.uk', '.me.uk', '.ltd.uk', '.plc.uk', '.net.uk', '.sch.uk'];

    foreach ($uk_tlds as $tld) {
        if (stripos($domain, $tld) !== false) {
            return true;
        }
    }

    return false;
}
