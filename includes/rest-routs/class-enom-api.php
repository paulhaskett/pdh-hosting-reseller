<?php

if (!defined('ABSPATH')) {
    exit;
}

class PDH_Enom_API
{
    private $username;
    private $password;
    private $endpoint;

    public function __construct()
    {
        $this->username = ENOM_USERNAME;
        $this->password = ENOM_API_KEY;
        $this->endpoint = ENOM_URL;
    }

    private function request($command, $args = [], $expect_json = false)
    {
        $params = array_merge([
            'uid'     => $this->username,
            'pw'      => $this->password,
            'command' => $command,
            // Only request JSON if explicitly told
            'ResponseType' => $expect_json ? 'JSON' : 'XML',
        ], $args);

        $url = add_query_arg($params, $this->endpoint);

        $response = wp_remote_get($url, [
            'timeout' => 30,
        ]);



        if (is_wp_error($response)) {
            throw new Exception('Enom API error: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);



        if ($expect_json) {
            return json_decode($body, true);
        }

        // Parse XML into array
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new Exception('Invalid XML response from Enom');
        }
        // prob better to update price on add to cart
        //$api_response = json_decode(json_encode($xml), true);
        // if ($command === 'check') {
        //     $product = wc_get_product_id_by_sku('register-domain');
        //     if ($product) {
        //         $wc_product = wc_get_product($product);
        //         $price = floatval($api_response['Domains']['Domain']['Prices']['Registration']); // from Enom
        //         $wc_product->set_regular_price($price);
        //         $wc_product->save();
        //     }
        // }

        return json_decode(json_encode($xml), true);
    }

    public function register_domain($domain, $tld, $years = 1)
    {
        return $this->request('Purchase', [
            'SLD'      => $domain,
            'TLD'      => $tld,
            'NumYears' => $years,
        ]);
    }

    public function check_domain($domain, $tld, $includePrice = 1)
    {

        return $this->request('check', [
            'SLD' => $domain,
            'TLD' => $tld,
            'version' => '2',
            'includeprice' => $includePrice
        ]);
    }

    public function get_tld_list()
    {
        return $this->request('gettldlist', []);
    }

    public function get_name_suggestions($searchterm = 'example.com')
    {
        return $this->request('getnamesuggestions', [
            'searchterm' => $searchterm,

        ]);
    }
    public function test()
    {
        return [1, 2, 3, 4];
    }
}
