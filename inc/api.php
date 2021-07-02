<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
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
 * @param array $encoding The $message must always be in UTF-8. Read more about the different encodings available here: https://gatewayapi.com/docs/appendix.html#term-ucs2
 * @param array $additional_options Additional options, such as "label", "priority" etc.. Can be used to override any other options set by this function.
 * @return int|WP_Error ID of message in gatewayapi.com on success
 */
function gatewayapi_send_sms($message, $recipients, $sender = '', $destaddr = 'MOBILE', $encoding = 'UTF8', $additional_options = [])
{
  // PREPARE THE RECIPIENTS
  // ======================

  // extract tags and recipients and prepare in a consistent format
  $allTags = [];
  $recipients_formatted = [];
  if (is_string($recipients) || is_int($recipients)) $recipients_formatted[$recipients] = [];
  if (is_array($recipients)) {
    foreach ($recipients as $i => $j) {
      if (is_array($j)) {
        $recipients_formatted[$i] = $j;
        foreach ($j as $tag => $value) {
          $allTags[$tag] = $tag;
        }
      } else {
        $recipients_formatted[$j] = [];
      }
    }
  }

  // build the request
  $req = [
    'recipients' => [],
    'message' => $message,
    'destaddr' => $destaddr,
    'tags' => array_values($allTags),
    'encoding' => $encoding,
  ];
  $sender = $sender ?: get_option('gwapi_default_sender');
  if ($sender) {
    $req['sender'] = $sender;
  }


  foreach ($recipients_formatted as $msisdn => $tags) {
    $rec = [
      'msisdn' => filter_var($msisdn, FILTER_SANITIZE_NUMBER_INT),
      'tagvalues' => []
    ];
    foreach ($allTags as $t) {
      $rec['tagvalues'][] = isset($tags[$t]) ? $tags[$t] : '';
    }
    $req['recipients'][] = $rec;
  }

  // overrides?
  if ($additional_options && is_array($additional_options)) {
    $req = array_merge($req, $additional_options);
  }

  // SEND THE SMS
  // ============
  $req = apply_filters('gwapi_send_sms_request', $req);

  // possible URIs
  $uris = ['https://gatewayapi.com/rest/mtsms', 'https://badssl.gatewayapi.com/rest/mtsms', 'http://badssl.gatewayapi.com/rest/mtsms'];

  $ts = time() - 3;
  foreach ($uris as $i => $uri) {
    // Variables for OAuth 1.0a Signature
    $consumer_key = rawurlencode(get_option('gwapi_key'));
    $secret = rawurlencode(get_option('gwapi_secret'));
    $nonce = rawurlencode(uniqid(false, true));
    $ts = rawurlencode($ts + $i);

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
    foreach ($oauth_params as $key => $val) {
      $sbsA[] = $key . '%3D' . $val;
    }
    $sbs .= implode('%26', $sbsA);

    // OAuth 1.0a - Sign SBS with secret
    $sig = base64_encode(hash_hmac('sha1', $sbs, $secret . '&', true));
    $oauth_params['oauth_signature'] = rawurlencode($sig);

    // Construct Authorization header
    $auth = 'OAuth ';
    $authA = [];
    foreach ($oauth_params as $key => $val) {
      $authA[] = $key . '="' . $val . '"';
    }
    $auth .= implode(', ', $authA);

    $res = wp_remote_request($uri, $q = [
      'method' => 'POST',
      'headers' => [
        'Authorization' => $auth,
        'Content-Type' => 'application/json',
        'user-agent' => 'gatewayapi'],
      'body' => json_encode($req)
    ]);

    // not an error - hurray!
    if (!is_wp_error($res)) {
      if ($res['response']['code'] == 200) {
        return current(json_decode($res['body'])->ids);
      }
      $error_raw = $res['body'];
      $error = json_decode($error_raw);
      return new WP_Error('GWAPI_FAIL', ($error && isset($error->message) && $error->message) ? $error->message . "\nCode " . $error->code . "\nUUID: " . $error->incident_uuid : $res['response']['code'] . "\n" . $error_raw);
    }

    // error: BUT no reason to try another URL as this is not communications related
    if (is_wp_error($res) && !isset($res->errors['http_request_failed'])) return new WP_Error('TECH_FAIL', json_encode($res['body']));
  }

  return new WP_Error('TECH_FAIL', 'No valid transports.');
}

/**
 * DEPRECATED! Please switch to gatewayapi_send_sms instead! It works exactly the same.
 *
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
 * @param $message
 * @param $recipients
 * @param string $sender
 * @param string $destaddr
 * @param string $encoding
 * @param array $additional_options
 * @return int|WP_Error
 *
 * @deprecated since 1.7.2. Use gatewayapi_send_sms instead, which has identical signature, except the function name.
 */
function gwapi_send_sms($message, $recipients, $sender = '', $destaddr = 'MOBILE', $encoding = 'UTF8', $additional_options = [])
{
  return gatewayapi_send_sms($message, $recipients, $sender, $destaddr, $encoding, $additional_options);
}
