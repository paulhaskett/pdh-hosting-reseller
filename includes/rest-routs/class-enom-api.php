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


        return json_decode(json_encode($xml), true);
    }

    public function register_domain($domain, $tld, $years, $contact)
    {
        // Validate inputs
        if (empty($domain) || empty($tld)) {
            throw new Exception('Domain and TLD are required');
        }

        if ($years < 1 || $years > 10) {
            throw new Exception('Years must be between 1 and 10');
        }

        // Sanitize domain and TLD
        $domain = strtolower(sanitize_text_field($domain));
        $tld = strtolower(sanitize_text_field($tld));
        // check for ext attributes
        if ($tld) {
            $extAttributes = $this->get_ext_attributes($tld);
            error_log('Attributes ' . print_r($extAttributes, true));
        }
        error_log('domain ' . $domain);
        error_log('tld ' . $tld);
        // Build params
        $params = [
            'SLD' => $domain,
            'TLD' => $tld,
            'NumYears' => $years,
            'UseDNS' => 'default', // Use Enom's default nameservers
        ];
        // Add contact information if provided
        if (!empty($contact)) {
            $params = array_merge($params, $this->sanitize_contact($contact));
        }
        error_log('params ' . print_r($params, true));
        error_log('contact ' . print_r($contact, true));


        // Make the purchase request
        try {
            $result = $this->request('Purchase', $params);

            // Check for errors
            if (isset($result['ErrCount']) && $result['ErrCount'] > 0) {
                $error_msg = isset($result['errors']['Err1']) ? $result['errors']['Err1'] : 'Unknown error';
                throw new Exception('Domain registration failed: ' . $error_msg);
            }

            // Check for success indicators
            if (isset($result['RRPCode']) && $result['RRPCode'] == 200) {
                // Success!
                return [
                    'success' => true,
                    'order_id' => $result['OrderID'] ?? null,
                    'domain' => $domain . '.' . $tld,
                    'years' => $years,
                    'rrp_text' => $result['RRPText'] ?? 'Registration successful',
                ];
            }

            // Unexpected response
            throw new Exception('Unexpected response from Enom: ' . print_r($result, true));
        } catch (Exception $e) {
            error_log('Enom registration error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function check_domain($domain, $tld, $includePrice = 1)
    {

        $result = $this->request('check', [
            'SLD' => $domain,
            'TLD' => $tld,
            'version' => '2',
            'includeprice' => $includePrice
        ]);

        return $result;
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

    /**
     * Sanitize and validate contact information
     */
    private function sanitize_contact($contact)
    {
        $sanitized = [];

        // Registrant contact
        if (!empty($contact['first_name'])) {
            $sanitized['RegistrantFirstName'] = sanitize_text_field($contact['first_name']);
        }
        if (!empty($contact['last_name'])) {
            $sanitized['RegistrantLastName'] = sanitize_text_field($contact['last_name']);
        }
        if (!empty($contact['organization'])) {
            $sanitized['RegistrantOrganizationName'] = sanitize_text_field($contact['organization']);
        }
        if (!empty($contact['address1'])) {
            $sanitized['RegistrantAddress1'] = sanitize_text_field($contact['address1']);
        }
        if (!empty($contact['address2'])) {
            $sanitized['RegistrantAddress2'] = sanitize_text_field($contact['address2']);
        }
        if (!empty($contact['city'])) {
            $sanitized['RegistrantCity'] = sanitize_text_field($contact['city']);
        }
        if (!empty($contact['state'])) {
            $sanitized['RegistrantStateProvince'] = sanitize_text_field($contact['state']);
            $sanitized['RegistrantStateProvinceChoice'] = 'P'; // P for Province/State
        }
        if (!empty($contact['postal_code'])) {
            $sanitized['RegistrantPostalCode'] = sanitize_text_field($contact['postal_code']);
        }
        if (!empty($contact['country'])) {
            $sanitized['RegistrantCountry'] = strtoupper(sanitize_text_field($contact['country']));
        }
        if (!empty($contact['email'])) {
            $sanitized['RegistrantEmailAddress'] = sanitize_email($contact['email']);
        }
        if (!empty($contact['phone'])) {
            // Format: +CountryCode.PhoneNumber (e.g., +44.2012345678)
            $sanitized['RegistrantPhone'] = $this->format_phone($contact['phone'], $contact['country'] ?? 'GB');
        }
        if (!empty($contact['fax'])) {
            $sanitized['RegistrantFax'] = $this->format_phone($contact['fax'], $contact['country'] ?? 'GB');
        }
        if (!empty($contact['uk_legal_type'])) {
            $sanitized['uk_legal_type'] = sanitize_text_field($contact['uk_legal_type']);
        }
        if (!empty($contact['uk_reg_co_no'])) {
            $sanitized['uk_reg_co_no'] = sanitize_text_field($contact['uk_reg_co_no']);
        }
        if (!empty($contact['registered_for'])) {
            $sanitized['registered_for'] = sanitize_text_field($contact['registered_for']);
        }
        if (!empty($contact['uk_reg_opt_out'])) {
            $sanitized['uk_reg_opt_out'] = sanitize_text_field($contact['uk_reg_opt_out']);
            if (!$this->validate_uk_company_number($sanitized)) {
                wc_add_notice(__('Invalid UK company registration number.', 'pdh-hosting-reseller'), 'error');
                return;
            }
        }

        return $sanitized;
    }
    private function format_phone($phone, $country_code = 'GB')
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If empty after cleaning, return default
        if (empty($phone)) {
            return '+44.0000000000'; // Placeholder
        }

        // Country code mapping (ISO 3166-1 alpha-2 to phone code)
        $country_codes = [
            // Europe
            'GB' => '44',  // United Kingdom
            'IE' => '353', // Ireland
            'FR' => '33',  // France
            'DE' => '49',  // Germany
            'ES' => '34',  // Spain
            'IT' => '39',  // Italy
            'NL' => '31',  // Netherlands
            'BE' => '32',  // Belgium
            'SE' => '46',  // Sweden
            'NO' => '47',  // Norway
            'DK' => '45',  // Denmark
            'FI' => '358', // Finland
            'PL' => '48',  // Poland
            'CH' => '41',  // Switzerland
            'AT' => '43',  // Austria
            'PT' => '351', // Portugal
            'GR' => '30',  // Greece

            // Americas
            'US' => '1',   // United States
            'CA' => '1',   // Canada
            'MX' => '52',  // Mexico
            'BR' => '55',  // Brazil
            'AR' => '54',  // Argentina
            'CL' => '56',  // Chile
            'CO' => '57',  // Colombia

            // Asia Pacific
            'AU' => '61',  // Australia
            'NZ' => '64',  // New Zealand
            'JP' => '81',  // Japan
            'CN' => '86',  // China
            'IN' => '91',  // India
            'SG' => '65',  // Singapore
            'HK' => '852', // Hong Kong
            'TH' => '66',  // Thailand
            'MY' => '60',  // Malaysia
            'ID' => '62',  // Indonesia
            'PH' => '63',  // Philippines
            'KR' => '82',  // South Korea
            'TW' => '886', // Taiwan

            // Middle East
            'AE' => '971', // UAE
            'SA' => '966', // Saudi Arabia
            'IL' => '972', // Israel
            'TR' => '90',  // Turkey

            // Africa
            'ZA' => '27',  // South Africa
            'EG' => '20',  // Egypt
            'NG' => '234', // Nigeria
            'KE' => '254', // Kenya
        ];

        // Get the country phone code
        $code = isset($country_codes[$country_code]) ? $country_codes[$country_code] : '44';

        // Remove leading zeros (common in UK numbers like 07700 900461)
        $phone = ltrim($phone, '0');

        // If phone already starts with the country code, remove it
        if (strpos($phone, $code) === 0) {
            $phone = substr($phone, strlen($code));
        }

        // Format: +CountryCode.PhoneNumber
        return '+' . $code . '.' . $phone;
    }

    private function get_ext_attributes($tld)
    {
        return $this->request('GetExtAttributes', [
            'TLD' => $tld
        ]);
    }
    private function validate_uk_company_number($number)
    {
        $number = strtoupper(trim($number));
        return preg_match('/^(?:\d{8}|(SC|NI|SL|OC|SE|IP|RC)\d{6})$/', $number);
    }
}
