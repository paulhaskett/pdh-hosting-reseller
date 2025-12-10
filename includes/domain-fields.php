<?php
if (!defined('ABSPATH'))
    exit;

// Show field on product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;

    if ($product->get_type() === 'domain') {
        echo '<div class="domain-registration-fields">';
        woocommerce_form_field('domain_name', [
            'type' => 'text',
            'required' => true,
            'label' => __('Domain Name ', 'pdh-hosting-reseller'),
            'placeholder' => 'example.com',
            'class' => 'input',
            'custom_attributes' => [
                'required' => 'required',
                'readonly' => 'readonly',
            ],
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
            'custom_attributes' => [
                'required' => 'required',
            ],
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
            if (empty($_POST['domain_years']) || !is_numeric($_POST['domain_years'])) {
                wc_add_notice(__('Please select a registration period.', 'pdh-hosting-reseller'), 'error');
                return false;
            }
        }
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
// add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
//     $plugin_path = plugin_dir_path(__FILE__) . 'templates/';

//     // Look in plugin's template folder first
//     if (file_exists($plugin_path . $template_name)) {
//         $template = $plugin_path . $template_name;
//     }

//     return $template;
// }, 10, 3);

// register templates 

register_block_template('pdh-hosting-reseller//single-product-register-domain', [
    'title'       => __('Register Domain Single Product', 'pdh-hosting-reseller'),
    'description' => __('Register a domain block template', 'pdh-hosting-reseller'),
    'content'     => '<!-- wp:template-part {"slug":"header","theme":"twentytwentyfive"} /-->

<!-- wp:group {"tagName":"main","layout":{"inherit":true,"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:woocommerce/breadcrumbs /-->

<!-- wp:woocommerce/store-notices /-->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"512px"} -->
<div class="wp-block-column" style="flex-basis:512px"><!-- wp:pdh-hosting-reseller/enom-check-domain-available /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-title {"level":1,"__woocommerceNamespace":"woocommerce/product-query/product-title"} /-->

<!-- wp:woocommerce/product-rating {"isDescendentOfSingleProductTemplate":true} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfSingleProductTemplate":true,"fontSize":"large"} /-->

<!-- wp:post-excerpt {"excerptLength":100,"__woocommerceNamespace":"woocommerce/product-query/product-summary"} /-->

<!-- wp:woocommerce/add-to-cart-form /-->

<!-- wp:woocommerce/product-meta {"metadata":{"ignoredHookedBlocks":["core/post-terms"]}} -->
<div class="wp-block-woocommerce-product-meta"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:woocommerce/product-sku /-->

<!-- wp:post-terms {"term":"product_cat","prefix":"Category: "} /-->

<!-- wp:post-terms {"term":"product_tag","prefix":"Tags: "} /--></div>
<!-- /wp:group -->

<!-- wp:post-terms {"term":"product_brand","prefix":"Brands: "} /--></div>
<!-- /wp:woocommerce/product-meta --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:woocommerce/product-collection {"queryId":5,"query":{"perPage":5,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":[],"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false,"relatedBy":{"categories":true,"tags":true}},"tagName":"div","displayLayout":{"type":"flex","columns":5,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/related","hideControls":["inherit"],"queryContextIncludes":["collection"],"__privatePreviewState":{"isPreview":false,"previewMessage":"Actual products will vary depending on the page being viewed."},"align":"wide"} -->
<div class="wp-block-woocommerce-product-collection alignwide"><!-- wp:heading {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<h2 class="wp-block-heading" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)">
				Related products			</h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"showSaleBadge":false,"imageSizing":"thumbnail","isDescendentOfQueryLoop":true} -->
<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /-->
<!-- /wp:woocommerce/product-image -->

<!-- wp:post-title {"textAlign":"center","level":3,"isLink":true,"style":{"spacing":{"margin":{"bottom":"0.75rem","top":"0"}}},"fontSize":"medium","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"textAlign":"center","fontSize":"small"} /-->

<!-- wp:woocommerce/product-button {"textAlign":"center","isDescendentOfQueryLoop":true,"fontSize":"small"} /-->
<!-- /wp:woocommerce/product-template --></div>
<!-- /wp:woocommerce/product-collection --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","theme":"twentytwentyfive"} /-->'
]);
