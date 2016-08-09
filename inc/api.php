<?php
/**
 * Programmers API - ie. easy access to creating SMS'es etc.
 */


/**
 * Send an SMS to one or multiple recipients.
 *
 * The recipients may be either:
 * - A integer or string, containing an MSISDN (CC + number, digits only).
 *   Example number: Country code 45. Phone number: 12 34 56 78.
 *   Resulting MSISDN: "4512345678".
 * - An array containing MSISDN (see above).
 * - An array in which MSISDN's are keys and their values are arrays of tags.
 *   Example in JSON:
 *   { "4512345678": { "%NAME%": "John Doe", "%GENDER%": "Male" } }
 *
 * @param string $message A string containing the message to be sent.
 * @param array|string $recipients A single recipient or a list of recipients.
 * @param string $sender Sender text (11 chars or 15 digits)
 * @param string $destaddr Type of SMS - Can be MOBILE (regular SMS) or DISPLAY (shown immediately on phone and usually not stored - also called a Flash SMS)
 * @return int|WP_Error ID of message in gatewayapi.com on success
 */
function gwapi_send_sms($message, $recipients, $sender='', $destaddr='MOBILE')
{
    // PREPARE THE RECIPIENTS
    // ======================

    // extract tags and recipients and prepare in a consistent format
    $allTags = [];
    $recipients_formatted = [];
    if (is_string($recipients) || is_int($recipients)) $recipients_formatted[ $recipients ] = [];
    if (is_array($recipients)) {
        foreach($recipients as $i => $j) {
            if (is_array($j)) {
                $recipients_formatted[$i] = $j;
                foreach($j as $tag => $value) {
                    $allTags[] = $tag;
                }
            } else {
                $recipients_formatted[$j] = [];
            }
        }
    }

    // build the request
    $req = [
        'recipients' => [ ],
        'message' => $message,
        'destaddr' => $destaddr,
        'tags' => $allTags
    ];
    $sender = $sender ? : get_option('gwapi_default_sender');
    if ($sender) {
        $req['sender'] = $sender;
    }


    foreach($recipients_formatted as $msisdn => $tags) {
        $rec = [
            'msisdn' => gwapi_bigint($msisdn),
            'tagvalues' => []
        ];
        foreach($allTags as $t) {
            $rec['tagvalues'][] = isset($tags[$t]) ? $tags[$t] : '';
        }
        $req['recipients'][] = $rec;
    }

    // SEND THE SMS
    // ============

    // possible URIs
    $uris = ['https://gatewayapi.com/rest/mtsms', 'https://badssl.gatewayapi.com/rest/mtsms', 'http://badssl.gatewayapi.com/rest/mtsms'];

    $ts = time()-3;
    foreach($uris as $i=>$uri) {
        // Variables for OAuth 1.0a Signature
        $consumer_key = rawurlencode(get_option('gwapi_key'));
        $secret = rawurlencode(get_option('gwapi_secret'));
        $nonce = rawurlencode(uniqid());
        $ts = rawurlencode($ts+$i);

        // OAuth 1.0a - Signature Base String
        $oauth_params = array(
            'oauth_consumer_key' => $consumer_key,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $ts,
            'oauth_version' => '1.0'
        );


        $sbs = 'POST&' . rawurlencode($uri) . '&';
        $sbsA = [];
        foreach($oauth_params as $key=>$val) {
            $sbsA[] = $key . '%3D' . $val ;
        }
        $sbs .= implode('%26', $sbsA);

        // OAuth 1.0a - Sign SBS with secret
        $sig = base64_encode(hash_hmac('sha1', $sbs, $secret . '&', true));
        $oauth_params['oauth_signature'] = rawurlencode($sig);

        // Construct Authorization header
        $auth = 'OAuth ';
        $authA = [];
        foreach($oauth_params as $key=>$val) {
            $authA[] = $key . '="' . $val . '"';
        }
        $auth .= implode(', ', $authA);

        $res = wp_remote_request($uri, $q=[
            'method' => 'POST',
            'headers' => ['Authorization' => $auth, 'Content-Type' => 'application/json'],
            'body' => json_encode($req)
        ]);

        // not an error - hurray!
        if (!is_wp_error($res)) {
            if ($res['response']['code'] == 200) {
                return current(json_decode($res['body'])->ids);
            }
            return new WP_Error('tech_fail', json_encode($res['body']));
        }

        // error: BUT no reason to try another URL as this is not communications related
        if (is_wp_error($res) && !isset($res->errors['http_request_failed'])) return new WP_Error('tech_fail', json_encode($res['body']));
    }

    return new WP_Error('tech_fail', 'No valid transports.');
}


/**
 * For 32 bit machines, return an integer but as a string, as 32 bit is not enough to store 15 digit long MSISDNs.
 * On 64 bit, just cast to int.
 *
 * @param $long_str
 */
function gwapi_bigint($long_str)
{
    if (PHP_INT_SIZE === 4) $long_str = ltrim(preg_replace('/D+/', '', $long_str),'0');
    else $long_str = (int)$long_str;

    return $long_str;
}