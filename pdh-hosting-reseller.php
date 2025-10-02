<?php

/**
 * Plugin Name:       Pdh Hosting Reseller
 * Description:       Example block scaffolded with Create Block tool.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pdh-hosting-reseller
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



	// Load main plugin class
	require_once plugin_dir_path(__FILE__) . 'includes/class-wp-enom-hestia-reseller.php';

	// Load frontend domain fields
	require_once plugin_dir_path(__FILE__) . 'includes/domain-fields.php';
	// Load API classes
	require_once plugin_dir_path(__FILE__) . 'includes/class-enom-api.php';
	require_once plugin_dir_path(__FILE__) . 'includes/class-hestiacp-api.php';


	// 3. Add localized data for your view script
	add_action('enqueue_block_assets', function () {
		$handle = 'pdh-hosting-reseller-enom-check-domain-available-view-script-js';
		if (wp_script_is($handle, 'registered')) {
			wp_localize_script(
				$handle,
				'DomainWidget',
				[
					'restUrl' => esc_url(rest_url('pdh-enom/v2/check-domain')),
					'token'   => wp_create_nonce('pdh_enom_check_domain'),
				]
			);
		}
	});



	// Load custom WC product class after WooCommerce is loaded
	add_action('plugins_loaded', function () {
		if (class_exists('WC_Product_Simple')) {
			require_once plugin_dir_path(__FILE__) . 'includes/class-wc-product-domain.php';
		}
	});






	// Initialize main plugin
	new WP_Enom_Hestia_Reseller();
}
add_action('init', 'pdh_hosting_reseller_pdh_hosting_reseller_block_init');



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
});

add_action('wp_footer', function () {
?>
	<script>
		const DomainWidget = {
			restUrl: '<?php echo esc_url(rest_url('pdh-enom/v2/check-domain')); ?>',
			token: '<?php echo wp_create_nonce('wp_rest'); ?>'
		};
	</script>
<?php
});
