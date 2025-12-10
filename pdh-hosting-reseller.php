<?php

/**
 * Plugin Name:       Pdh Hosting Reseller
 * Description:       Enom Hestiacp Reseller
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            PDH Web Development
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pdh-hosting-reseller
 * Requires Plugins:  woocommerce + stripe
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
	require_once plugin_dir_path(__FILE__) . 'includes/hooks-domain-pricing.php';
	//require_once plugin_dir_path(__FILE__) . 'includes/domain-fields.php';
	require_once plugin_dir_path(__FILE__) . 'includes/class-hestiacp-api.php';
	require_once plugin_dir_path(__FILE__) . 'includes/hooks-checkout-requirements.php';
	//require_once plugin_dir_path(__FILE__) . 'includes/hooks-pdh-uk-fields.php';





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
	register_rest_route('pdh-enom/v2', '/add-domain-to-cart', [
		'methods'             => 'POST',
		'callback'            => 'pdh_rest_add_domain_to_cart_callback',
		'permission_callback' => '__return_true', // handled by token/auth inside callback
	]);
});

// load DomainWidget for passing security token
add_action('wp_enqueue_scripts', function () {
	$handle = 'pdh-hosting-reseller-enom-check-domain-available-view-script';
	wp_localize_script(
		$handle,
		'DomainWidget',
		[
			'restUrl' => esc_url(rest_url('pdh-enom/v2/check-domain')),
			'token'   => wp_create_nonce('wp_rest'),
			'currency' => get_woocommerce_currency(),                 // "GBP", 
			'currencySymbol' => get_woocommerce_currency_symbol(),    // "£", 
		]
	);
});

add_action('wp_footer', function () {
	// Only run on single product pages
	if (!is_product()) {
		return;
	}

	global $product;

	// Only run for domain products
	if (!$product || $product->get_type() !== 'domain') {
		return;
	}
?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Get URL parameters from the address bar
			// Example: /product/register-domain/?domain_name=example&domain_tld=com&price=6.99
			const params = new URLSearchParams(window.location.search);
			const domainName = params.get('domain_name');
			const domainTld = params.get('domain_tld');
			const domainInput = document.getElementById('domain_name');
			if (domainInput) {

				domainInput.style.cursor = 'not-allowed';
				domainInput.setAttribute('required', 'required');
			}


			const pricePerYear = params.get('price') || params.get('domain_registration_price');

			console.log('URL Params:', {
				domainName,
				domainTld,
				pricePerYear
			});

			// If domain name was passed in URL, fill in the form
			if (domainName && domainTld) {
				// Fill in the visible domain name input field

				if (domainInput) {
					domainInput.value = domainName + '.' + domainTld;

					// Make it readonly since user already searched for it

					domainInput.style.backgroundColor = '#f5f5f5';
					domainInput.style.cursor = 'not-allowed';

					// Add a note explaining this
					const note = document.createElement('small');
					note.style.display = 'block';
					note.style.marginTop = '5px';
					note.style.color = '#666';
					note.textContent = 'Domain selected from search. Use the search widget to find a different domain.';

					if (domainInput.parentNode && !domainInput.parentNode.querySelector('small')) {
						domainInput.parentNode.appendChild(note);
					}

					console.log('Set domain_name input to:', domainName);
				}

				// Get the add-to-cart form
				const productForm = document.querySelector('form.cart');
				if (productForm) {
					// Create or update hidden field for domain_name
					ensureHiddenField(productForm, 'domain_name', domainName);


					// Create or update hidden field for domain_tld
					if (domainTld) {
						ensureHiddenField(productForm, 'domain_tld', domainTld);
					}

					// Store the per-year price for calculations
					if (pricePerYear && parseFloat(pricePerYear) > 0) {
						window.domainPricePerYear = parseFloat(pricePerYear);
						console.log('Stored domainPricePerYear:', window.domainPricePerYear);

						// Update price initially (for 1 year)
						updatePriceForYears(1);

						// Listen for changes to the years dropdown
						const yearsSelect = document.getElementById('domain-years-selector');
						console.log('Years select element:', yearsSelect);

						if (yearsSelect) {
							console.log('Setting up change listener for years dropdown');
							yearsSelect.setAttribute('required', 'required');
							yearsSelect.addEventListener('change', function() {
								const years = parseInt(this.value) || 1;
								console.log('Years changed to:', years);
								updatePriceForYears(years);
								ensureHiddenField(productForm, 'domain_years', years);
							});

							// Also add an input event listener as backup
							yearsSelect.addEventListener('input', function() {
								const years = parseInt(this.value) || 1;
								console.log('Years input event:', years);
								updatePriceForYears(years);
							});
						} else {
							console.warn('domain_years dropdown not found - it may not be rendered yet');
							// Try again after a delay if not found
							setTimeout(function() {
								const yearsSelect = document.getElementById('domain_years');
								if (yearsSelect) {
									console.log('Found domain_years after delay, setting up listener');
									yearsSelect.addEventListener('change', function() {
										//const years = parseInt(this.value) || 1;
										console.log('Years changed to:', years);
										updatePriceForYears(years);
									});
								}
							}, 500);
						}
					}


				} else {
					console.warn('Product form not found');
				}
			}

			/**
			 * Update price based on number of years selected
			 */
			function updatePriceForYears(years) {
				if (!window.domainPricePerYear || years < 1) return;

				// Calculate total price
				const totalPrice = window.domainPricePerYear * years;
				console.log('Years:', years, 'Total price:', totalPrice);

				// Update hidden field in form with the total price
				const productForm = document.querySelector('form.cart');
				if (productForm) {
					ensureHiddenField(productForm, 'domain_registration_price', totalPrice.toString());
				}

				// Update the displayed price on the page
				updateDisplayedPrice(totalPrice, years);
			}

			/**
			 * Create or update a hidden input field
			 */
			function ensureHiddenField(form, name, value) {
				let field = form.querySelector('input[name="' + name + '"]');

				// Check if field is visible (like domain_name text input)
				const isVisible = field && field.type !== 'hidden';

				if (!field || isVisible) {
					// Need to create a hidden field
					let hiddenField = form.querySelector('input[type="hidden"][name="' + name + '"]');
					if (!hiddenField) {
						hiddenField = document.createElement('input');
						hiddenField.type = 'hidden';
						hiddenField.name = name;
						form.appendChild(hiddenField);
						console.log('Created hidden field:', name);
					}
					hiddenField.value = value;
				} else {
					// Update existing hidden field
					field.value = value;
				}

				console.log('Set ' + name + ' = ' + value);
			}

			/**
			 * Update the displayed price on the product page
			 */
			function updateDisplayedPrice(newPrice, years) {
				if (isNaN(newPrice) || newPrice <= 0) return;

				const formatted = newPrice.toFixed(2);
				console.log('updateDisplayedPrice called:', {
					newPrice,
					formatted,
					years
				});

				// Get currency symbol from DomainWidget or default to £
				const symbol = (window.DomainWidget && window.DomainWidget.currencySymbol) ?
					window.DomainWidget.currencySymbol :
					'£';

				console.log('Currency symbol:', symbol);

				// Find all price elements on the page and update them
				const priceElements = document.querySelectorAll('.woocommerce-Price-amount.amount');
				console.log('Found price elements:', priceElements.length);

				priceElements.forEach(function(el, index) {
					let priceHtml = '<bdi><span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' + formatted + '</bdi>';

					// Add years note if more than 1 year
					if (years > 1) {
						priceHtml += ' <small style="font-size: 0.8em; color: #666;">(' + years + ' years)</small>';
					}

					console.log('Updating price element', index, 'with:', priceHtml);
					el.innerHTML = priceHtml;
				});

				// If no price elements found, try alternate selectors
				if (priceElements.length === 0) {
					console.warn('No .woocommerce-Price-amount elements found, trying alternate selectors');

					// Try .price class
					const altPrices = document.querySelectorAll('.price');
					console.log('Found .price elements:', altPrices.length);
					altPrices.forEach(function(el) {
						const priceSpan = el.querySelector('.woocommerce-Price-amount');
						if (priceSpan) {
							let priceHtml = '<span class="woocommerce-Price-amount"><bdi><span class="woocommerce-Price-currencySymbol">' + symbol + '</span>' + formatted + '</bdi></span>';
							if (years > 1) {
								priceHtml += ' <small style="font-size: 0.8em; color: #666;">(' + years + ' years)</small>';
							}
							el.innerHTML = priceHtml;
						}
					});
				}
			}
		});
	</script>

<?php
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

// reset domain product price after order 
add_action('woocommerce_thankyou', 'pdh_reset_domain_product_price', 10, 1);
function pdh_reset_domain_product_price($order_id)
{
	if (!$order_id) {
		return;
	}

	$order = wc_get_order($order_id);

	if (!$order) {
		return;
	}

	// Loop through ordered items
	foreach ($order->get_items() as $item) {
		$product = $item->get_product();

		if ($product && $product->get_sku() === 'register-domain') {
			$product->set_regular_price(0);
			$product->set_sale_price('');
			$product->save();
		}
	}
}

// Make phone required for ALL orders
add_filter('woocommerce_billing_fields', function ($fields) {
	$fields['billing_phone']['required'] = true;
	return $fields;
});
