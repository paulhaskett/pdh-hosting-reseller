<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom WooCommerce Product Type: Domain
 */
class WC_Product_Domain extends WC_Product_Simple
{
    public function __construct($product = 0)
    {
        $this->get_type();
        parent::__construct($product);
    }

    public function get_type()
    {
        return 'domain';
    }
    public function is_purchasable()
    {
        return true;
    }
    public function get_price($context = 'view')
    {
        $regular_price = $this->get_regular_price($context);
        $sale_price    = $this->get_sale_price($context);

        if ($this->is_on_sale() && $sale_price !== '') {
            return $sale_price;
        }

        return $regular_price;
    }
    public function is_sold_individually()
    {
        return true; // optional, domains are usually one per order
    }
    public function is_in_stock()
    {
        return true;
    }
}
