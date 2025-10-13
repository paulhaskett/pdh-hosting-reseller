<?php

/**
 * Plugin Name:       Pdh Hosting Reseller
 * Description:       Enom Hestiacp Reseller
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pdh-hosting-reseller
 * Requires Plugins:  woocommerce
 *
 * @package PdhHostingReseller
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
/**
 * Registers the block using a `blocks-manifest.php` file, which improves the performance of block type registration.
 * Behind the scenes, it also registers all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function pdh_hosting_reseller_pdh_hosting_reseller_block_init()
{
	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block  metadata.
	 * Added in WordPress 6.8 to simplify the block metadata registration process added in WordPress 6.7.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 */
	if (function_exists('wp_register_block_types_from_metadata_collection')) {
		wp_register_block_types_from_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
		return;
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` file.
	 * Added to WordPress 6.7 to improve the performance of block type registration.
	 *
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	if (function_exists('wp_register_block_metadata_collection')) {
		wp_register_block_metadata_collection(__DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php');
	}
	/**
	 * Registers the block type(s) in the `blocks-manifest.php` file.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	$manifest_data = require __DIR__ . '/build/blocks-manifest.php';

	foreach (array_keys($manifest_data) as $block_type) {
		register_block_type(__DIR__ . "/build/{$block_type}");
	}



	// // Load main plugin class
	// require_once plugin_dir_path(__FILE__) . 'includes/class-wp-enom-hestia-reseller.php';

	// // Load frontend domain fields
	// require_once plugin_dir_path(__FILE__) . 'includes/domain-fields.php';
	// // Load API classes
	// //require_once plugin_dir_path(__FILE__) . 'includes/rest-routs/class-enom-api.php';
	// require_once plugin_dir_path(__FILE__) . 'includes/class-hestiacp-api.php';



}
add_action('init', 'pdh_hosting_reseller_pdh_hosting_reseller_block_init');

add_action('plugins_loaded', function () {
	if (!class_exists('WooCommerce')) {
		return;
	}

	require_once plugin_dir_path(__FILE__) . 'includes/class-wp-enom-hestia-reseller.php';

	require_once plugin_dir_path(__FILE__) . 'includes/domain-fields.php';
	require_once plugin_dir_path(__FILE__) . 'includes/class-hestiacp-api.php';



	new WP_Enom_Hestia_Reseller();
});

// Load the REST routes
require_once plugin_dir_path(__FILE__) . 'includes/rest-routs/rest-enom.php';

add_action('rest_api_init', function () {
	register_rest_route(
		'pdh-enom/v2',
		'/check-domain',
		[
			'methods' => 'POST',
			'callback' => 'check_domain_callback',
			'permission_callback' => '__return_true',


		]
	);
	register_rest_route(
		'pdh-enom-test/v2',
		'/test',
		[
			'methods' => 'GET',
			'callback' => 'test_callback',
			'permission_callback' => '__return_true',


		]
	);
	register_rest_route(
		'pdh-enom/v2',
		'/get-name-suggestions',
		[
			'methods' => 'POST',
			'callback' => 'get_name_suggestions_callback',
			'permission_callback' => '__return_true',


		]
	);
	register_rest_route(
		'pdh-enom/v2',
		'/get-tld-list',
		[
			'methods' => 'GET',
			'callback' => 'get_tld_list_callback',
			'permission_callback' => '__return_true',


		]
	);
});

add_action('wp_enqueue_scripts', function () {
	$handle = 'pdh-hosting-reseller-enom-check-domain-available-view-script';
	wp_localize_script(
		$handle,
		'DomainWidget',
		[
			'restUrl' => esc_url(rest_url('pdh-enom/v2/check-domain')),
			'token'   => wp_create_nonce('wp_rest'),
		]
	);
});

add_action('wp_footer', function () {



	// add the user selected domain name to domain name input field after redirect to the product page
	if (is_product()) : ?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const params = new URLSearchParams(window.location.search);
				const domain = params.get('domain_name');
				const price = params.get('price');
				if (domain) {
					const input = document.getElementById('domain_name');
					if (input) {
						input.value = domain;
					}
				}

			});
		</script>
<?php endif;
});


// schedule creation on plugin activation
register_activation_hook(__FILE__, 'pdh_schedule_domain_product_creation');
function pdh_schedule_domain_product_creation()
{
	// set a flag option so we can create the product once
	update_option('pdh_create_domain_product', true);
}

// Attempt creation after WooCommerce and product types are registered.

add_action('init', 'pdh_create_domain_product_if_scheduled', 100);

function pdh_create_domain_product_if_scheduled()
{
	// only run once
	if (! get_option('pdh_create_domain_product')) {
		return;
	}

	// Must have WooCommerce active
	if (! class_exists('WooCommerce')) {
		// Keep the flag so it will retry next request
		error_log('PDH: WooCommerce not active — delaying product creation.');
		return;
	}

	// Ensure custom product class file is loaded 
	$domain_product_class_file = plugin_dir_path(__FILE__) . 'includes/class-wc-product-domain.php';
	if (file_exists($domain_product_class_file)) {
		require_once $domain_product_class_file;
	}

	// Wait until the product_type taxonomy exists
	if (! taxonomy_exists('product_type')) {
		error_log('PDH: product_type taxonomy not registered yet; delaying product creation.');
		return;
	}

	$slug = 'register-domain';

	// If product exists by slug, ensure it is assigned the domain type and has price meta
	$existing = get_page_by_path($slug, OBJECT, 'product');
	if ($existing) {
		// assign product_type term 'domain' 
		wp_set_object_terms($existing->ID, 'domain', 'product_type', false);

		// ensure basic meta exists (price etc.)
		if ('' === get_post_meta($existing->ID, '_regular_price', true)) {
			update_post_meta($existing->ID, '_regular_price', '0.00');
			update_post_meta($existing->ID, '_price', '0.00');
		}

		if ('' === get_post_meta($existing->ID, '_stock_status', true)) {
			update_post_meta($existing->ID, '_stock_status', 'instock');
		}

		// remove scheduled flag
		delete_option('pdh_create_domain_product');

		// try to clear cached lookups
		if (function_exists('wc_delete_product_transients')) {
			wc_delete_product_transients($existing->ID);
		}

		return;
	}

	// Create a new product (post)
	$post_id = wp_insert_post([
		'post_title'   => 'Register Domain',
		'post_name'    => $slug,
		'post_type'    => 'product',
		'post_status'  => 'publish',
		'post_content' => 'Register a domain via Enom', // optional; 
	]);

	// Set SKU and other WooCommerce data
	if ($post_id && !is_wp_error($post_id)) {
		$product = wc_get_product($post_id);
		if (!$product) {
			$product = new WC_Product_Simple($post_id);
		}
		$product->set_sku('register-domain');
		$product->set_price(0);
		$product->set_regular_price(0);
		$product->save();
	}

	if (is_wp_error($post_id) || ! $post_id) {
		error_log('PDH: Failed to create product: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'unknown'));
		return;
	}

	// Set product type taxonomy to 'domain'
	wp_set_object_terms($post_id, 'domain', 'product_type', false);

	// Minimal meta so WooCommerce treats it like a product with price
	update_post_meta($post_id, '_regular_price', '0.00');
	update_post_meta($post_id, '_price', '0.00');
	update_post_meta($post_id, '_stock_status', 'instock');
	update_post_meta($post_id, '_visibility', 'visible'); // older WC versions use this

	// If you want to prefill domain product custom meta:
	update_post_meta($post_id, '_domain_default_tld', get_option('enom_default_tld', 'com'));
	update_post_meta($post_id, '_domain_default_package', get_option('hestia_package', ''));

	// Clear transients/lookup caches
	if (function_exists('wc_delete_product_transients')) {
		wc_delete_product_transients($post_id);
	}

	// clean up flag so we don't try again
	delete_option('pdh_create_domain_product');

	// final sanity: log created
	error_log('PDH: Created domain product with ID ' . $post_id);
}
