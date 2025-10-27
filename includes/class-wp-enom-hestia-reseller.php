<?php
if (!defined('ABSPATH')) {
    exit;
}


class WP_Enom_Hestia_Reseller
{



    public function __construct()
    {
        // Admin menu page
        add_action('admin_menu', [$this, 'admin_menu']);
        // Register product type & fields
        add_action('init', [$this, 'register_product_type']);
        // load template files

        // Register plugin settings
        add_action('admin_init', [$this, 'register_settings']);
        // Hook into order completion
        add_action('woocommerce_order_status_completed', [$this, 'process_order']);
        add_action('woocommerce_before_single_product', function () {
            if (!is_singular('product')) return;
            global $product;
            if (!$product || $product->get_type() !== 'domain') return;
            // Override the default display with domain widget
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
            // Stop rendering the default summary
            remove_all_actions('woocommerce_single_product_summary');
        }, 5);

        // Load custom WC_Product class after WooCommerce loads
        add_action('plugins_loaded', function () {
            if (!class_exists('WC_Product_Domain')) {
                require_once plugin_dir_path(__FILE__) . './class-wc-product-domain.php';
            }
        });








        // add to cart button
        add_action('woocommerce_single_product_summary', function () {
            global $product;
            if ($product && $product->get_type() === 'domain') {
                wc_get_template('single-product/add-to-cart/simple.php', ['product' => $product]);
            }
            require_once plugin_dir_path(__FILE__) . './domain-fields.php';
        }, 30);

        //add_filter('woocommerce_locate_template', [$this, 'load_plugin_templates'], 10, 3);

    }





    /** -------------------------
     * SETTINGS REGISTRATION
     * ------------------------- */
    public function register_settings()
    {
        register_setting('reseller_settings', 'hestia_user');
        register_setting('reseller_settings', 'hestia_package');
        register_setting('reseller_settings', 'enom_default_tld');
        register_setting('reseller_settings', 'enom_nameservers');
        register_setting('reseller_settings', 'hestia_server');

        add_settings_section(
            'reseller_config_section',
            __('Plugin Configuration', 'wp-enom-hestia-reseller'),
            function () {
                echo '<p>Configure default Enom & HestiaCP settings.</p>';
            },
            'reseller_settings'
        );

        // Helper: generate a text field
        $fields = [
            'hestia_user' => 'HestiaCP Username',
            'hestia_package' => 'HestiaCP Default Package',
            'enom_default_tld' => 'Enom Default TLD',
            'enom_nameservers' => 'Enom Default Nameservers',
            'hestia_server' => 'HestiaCP Server URL'
        ];

        foreach ($fields as $id => $label) {
            add_settings_field($id, __($label, 'wp-enom-hestia-reseller'), function () use ($id) {
                $value = get_option($id, '');
                echo '<input type="text" name="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="regular-text">';
            }, 'reseller_settings', 'reseller_config_section');
        }
    }

    /** -------------------------
     * ADMIN MENU PAGE
     * ------------------------- */
    public function admin_menu()
    {
        add_menu_page(
            'Reseller Settings',
            'Reseller Settings',
            'manage_options',
            'reseller-settings',
            [$this, 'settings_page']
        );
    }

    public function settings_page()
    {
        echo '<form method="post" action="options.php">';
        settings_fields('reseller_settings');
        do_settings_sections('reseller_settings');
        submit_button();
        echo '</form>';
    }

    /** -------------------------
     * REGISTER CUSTOM PRODUCT TYPE
     * ------------------------- */
    public function register_product_type()
    {
        require_once plugin_dir_path(__FILE__) . 'class-wc-product-domain.php';
        // Add 'Domain' type to product selector
        add_filter('product_type_selector', function ($types) {
            $types['domain'] = __('Domain', 'wp-enom-hestia-reseller');
            return $types;
        });
        // Ensure WooCommerce uses custom class for 'domain' products
        add_filter('woocommerce_product_class', function ($classname, $product_type) {
            if ($product_type === 'domain') {
                $classname = 'WC_Product_Domain';
            }
            return $classname;
        }, 10, 2);


        // Add custom product fields (general data tab)
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_domain_product_fields'], 20);
        add_action('woocommerce_process_product_meta', [$this, 'save_domain_product_fields'], 30);

        // Add custom pricing fields via custom tab
        add_filter('woocommerce_product_data_tabs', function ($tabs) {
            $tabs['domain'] = [
                'label' => __('Domain Pricing', 'wp-enom-hestia-reseller'),
                'target' => 'domain_product_options',
                'class' => ['show_if_domain']
            ];
            return $tabs;
        });

        add_action('woocommerce_product_data_panels', [$this, 'add_domain_price_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_domain_price_fields'], 40);

        // add_action('woocommerce_single_product_summary', function () {
        //     global $product;
        //     if ($product->get_type() === 'domain') {
        //         echo '<p>Default TLD: ' . esc_html($product->get_meta('_domain_default_tld')) . '</p>';
        //         echo '<p>Hosting Package: ' . esc_html($product->get_meta('_domain_default_package')) . '</p>';
        //     }
        // }, 25);
        // Load frontend domain fields
        require_once plugin_dir_path(__FILE__) . './domain-fields.php';
        require_once plugin_dir_path(__FILE__) . './hooks-pdh-uk-fields.php';
    }

    /** -------------------------
     * DOMAIN PRODUCT FIELDS
     * ------------------------- */
    public function add_domain_product_fields()
    {

        echo '<div class="options_group show_if_domain">';

        woocommerce_wp_text_input([
            'id' => '_domain_default_tld',
            'label' => __('Default TLD', 'wp-enom-hestia-reseller'),
            'placeholder' => 'com',
            'desc_tip' => true,
            'description' => __('Default TLD if customer does not specify one.', 'wp-enom-hestia-reseller'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_domain_default_package',
            'label' => __('Hosting Package', 'wp-enom-hestia-reseller'),
            'placeholder' => 'default',
            'desc_tip' => true,
            'description' => __('Default hosting package for provisioned accounts.', 'wp-enom-hestia-reseller'),
        ]);

        echo '</div>';
    }

    public function save_domain_product_fields($post_id)
    {
        $tld = isset($_POST['_domain_default_tld']) ? sanitize_text_field($_POST['_domain_default_tld']) : '';
        $package = isset($_POST['_domain_default_package']) ? sanitize_text_field($_POST['_domain_default_package']) : '';

        update_post_meta($post_id, '_domain_default_tld', $tld);
        update_post_meta($post_id, '_domain_default_package', $package);
    }

    /** -------------------------
     * DOMAIN PRICE FIELDS
     * ------------------------- */
    public function add_domain_price_fields()
    {
        global $post;
        $product = wc_get_product($post->ID);



        echo '<div id="domain_product_options" class="panel woocommerce_options_panel show_if_domain">';
        if ($product) {


            woocommerce_wp_text_input([
                'id' => '_regular_price',
                'class' => 'short wc_input_price form-field',
                'label' => __('Regular Price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')',
                'data_type' => 'price',
                'name' => '_regular_price',
                'value' => get_post_meta($post->ID, '_regular_price', true), // starting value
            ]);

            woocommerce_wp_text_input([
                'id' => '_sale_price',
                'class' => 'short wc_input_price form-field',
                'label' => __('Sale Price', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')',
                'data_type' => 'price',
                'name' => '_sale_price',
                'value' => get_post_meta($post->ID, '_sale_price', true), // starting value
            ]);
        }
        echo '</div>';
    }

    public function save_domain_price_fields($post_id)
    {
        // Save the price fields
        add_action('woocommerce_process_product_meta', function ($post_id) {
            $product = wc_get_product($post_id);
            if (!$product || $product->get_type() !== 'domain')
                return;

            if (isset($_POST['_regular_price'])) {
                $product->set_regular_price(sanitize_text_field($_POST['_regular_price']));
            }
            if (isset($_POST['_sale_price'])) {
                $product->set_sale_price(sanitize_text_field($_POST['_sale_price']));
            }

            $product->save();
        });
    }

    /** -------------------------
     * ORDER PROCESSING
     * ------------------------- */
    public function process_order($order_id)
    {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item_id => $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product = $item->get_product();
                if ($product && $product->get_type() === 'domain') {
                    $this->register_domain($item);
                    $this->provision_hosting($item);
                }
            }
        }
    }

    private function register_domain($item)
    {
        $domain_name = $item->get_meta('domain_name');
        // TODO: Add Enom API request to register domain
    }

    private function provision_hosting($item)
    {
        $username = $item->get_meta('hosting_user');
        $password = $item->get_meta('hosting_pass');
        $package = $item->get_meta('hosting_package');
        // TODO: Add HestiaCP API request to provision hosting
    }
} // End class