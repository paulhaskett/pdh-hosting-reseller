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
        return parent::get_price($context);
    }

    public function is_sold_individually()
    {
        return false; // optional, domains are usually one per order
    }
    public function is_in_stock()
    {
        return true;
    }
}
