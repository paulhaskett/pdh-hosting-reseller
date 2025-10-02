<?php

if (!defined('ABSPATH')) {
    exit;
}


// add_action('enqueue_block_assets', function () {
//     $handle = 'pdh-hosting-reseller-enom-check-domain-available-view-script-js'; // match HTML

//     if (wp_script_is($handle, 'registered')) {
//         wp_add_inline_script(
//             $handle,
//             'const DomainWidget = ' . wp_json_encode([
//                 'restUrl' => esc_url(rest_url('pdh-enom/v2/check-domain')),
//                 'token'   => wp_create_nonce('wp_rest'),
//             ]) . ';',
//             'before'
//         );
//     }
// });



function test_callback()
{
    return [1, 2, 3, 4, 5];
}
function check_domain_callback(WP_REST_REQUEST $request)
{
    $params = $request->get_json_params();
    $sld = sanitize_text_field($params['domain'] ?? '');
    $tld = sanitize_text_field($params['tld'] ?? '');

    $securityToken = $request->get_header('X-WP-Nonce');

    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }


    // $response = [1, 1, 1,];
    // return $response;



    try {
        // Replace with real reseller credentials
        $enom = new Enom_API(
            ENOM_USERNAME,
            ENOM_API_KEY,
            'https://resellertest.enom.com/interface.asp'
        );
        $response = $enom->check_domain($sld, $tld);
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}
