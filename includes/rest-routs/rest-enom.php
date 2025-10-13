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


//require_once plugin_dir_path(__FILE__) . './class-enom-api.php';
if (!class_exists('PDH_Enom_API')) {
    require_once plugin_dir_path(__FILE__) . './class-enom-api.php';
}

function test_callback()
{

    $enom = new PDH_Enom_API();
    $response = $enom->test();
    return rest_ensure_response($response);
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
        $enom = new PDH_Enom_API();
        $response = $enom->check_domain($sld, $tld);
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}
function get_tld_list_callback(WP_REST_Request $request)
{
    $securityToken = $request->get_header('X-WP-Nonce');
    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }
    try {
        $enom = new PDH_Enom_API();
        $response = $enom->get_tld_list();
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}
function get_name_suggestions_callback(WP_REST_REQUEST $request)
{
    $securityToken = $request->get_header('X-WP-Nonce');
    if (! wp_verify_nonce($securityToken, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid security token', ['status' => 403]);
    }
    $params = $request->get_json_params();
    $searchterm = sanitize_text_field($params['searchterm'] ?? '');
    try {
        $enom = new PDH_Enom_API();
        $response = $enom->get_name_suggestions($searchterm);
        return rest_ensure_response($response);
    } catch (Exception $e) {
        return new WP_Error('enom_error', $e->getMessage(), ['status' => 500]);
    }
}
