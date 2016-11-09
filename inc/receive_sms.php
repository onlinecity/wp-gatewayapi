<?php

/**
 * Handler that receive smses from gatewayapi
 * We expect to receive a json payload with the smses
 * https://gatewayapi.com/docs/rest.html#mo-sms-receiving-sms-es
 *
 * Known properties:
 *
 * 'id', // (integer) – The ID of the MO SMS
 * 'msisdn', // (integer) – The MSISDN of the mobile device who sent the SMS.
 * 'receiver', // (integer) – The short code on which the SMS was received.
 * 'message', // (string) – The body of the SMS, incl. keyword.
 * 'senttime', // (integer) – The UNIX Timestamp when the SMS was sent.
 * 'webhook_label', // (string) – Label of the webhook who matched the SMS.
 * 'sender', // (string) – If SMS was sent with a text based sender, then this field is set. Optional.
 * 'mcc', // (integer) – MCC, mobile country code. Optional.
 * 'mnc', // (integer) – MNC, mobile network code. Optional.
 * 'validity_period', // (integer) – How long the SMS is valid. Optional.
 * 'encoding', // (string) – Encoding of the received SMS. Optional.
 * 'udh', // (string) – User data header of the received SMS. Optional.
 * 'payload', // (string) – Binary payload of the received SMS. Optional.
 *
 */
function _gwapi_receive_sms_json_handler()
{
    if (!(isset($_GET['token']) && $_GET['token'] === _gwapi_receive_sms_token())) {
        header('Content-type: application/json', true, 400);
        die(json_encode(['success' => false, 'error' => 'Invalid token']));
    }
    $sms = json_decode(file_get_contents('php://input'), true);
    wp_insert_post(array(
        'guid' => 'gwapi-receive-sms-' . $sms['id'],
        'post_status' => 'publish',
        'post_type' => 'gwapi-receive-sms',
        'post_category' => 'gwapi',
        'meta_input' => $sms
    ));
    header('Content-type: application/json');
    die(json_encode(['success' => true]));
}

/**
 * Get receive-sms token
 * If sms-inbox feature is enabled the saved token is used
 * otherwise evertime we reload the page a new token is generated.
 * @return string
 */
function _gwapi_receive_sms_token()
{
    $key = 'gwapi_receive_sms_token';
    if (get_option('gwapi_sms_inbox_enable')) {
        $token = get_option($key);
    }
    if (!isset($token)) {
        $token = wp_generate_password(32, false);
        update_option($key, $token, false);
    }
    return $token;
}

function _gwapi_receive_sms_url()
{
    return admin_url('admin-ajax.php?action=gwapi_receive_sms&token=' . _gwapi_receive_sms_token());
}

add_action('wp_ajax_priv_gwapi_receive_sms', '_gwapi_receive_sms_json_handler');
add_action('wp_ajax_nopriv_gwapi_receive_sms', '_gwapi_receive_sms_json_handler');
