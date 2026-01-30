<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php
/**
 * Programmers API - ie. easy access to creating SMS'es etc.
 */


/**
 * Send a mobile message to a single recipient using the GatewayAPI Messaging API.
 *
 * @param string $message The message to be sent.
 * @param string $recipient An MSISDN (string containing digits only, e.g., "4512345678").
 * @param string $sender Sender text (3-18 characters).
 * @param array $options Optional. An associative array with optional parameters:
 *                       - 'priority' (string): Message priority. Default 'normal'.
 *                       - 'reference' (string|null): Client-provided reference. Default NULL.
 *                       - 'expiration' (int): Expires in number of seconds (1-432000). Default 432000.
 *                       - 'label' (string|null): Label for the message. Default NULL.
 * @return object|WP_Error On success, returns an object with msg_id, recipient, and reference.
 *                         On failure, returns a WP_Error.
 */
function gatewayapi_send_mobile_message($message, $recipient, $sender, $options = [])
{
  // If API version is set to 'sms', use the SMS API instead.
  $api_version = get_option('gwapi_api_version', 'sms');
  if ($api_version === 'sms') {
    $res = gatewayapi_send_sms($message, [$recipient], $sender, 'MOBILE', 'UTF8', $options);
    if (is_wp_error($res)) return $res;

    // Return a mock response that matches the Messaging API format as closely as possible
    return (object)[
      'id' => $res,
      'recipient' => $recipient
    ];
  }

  // Validate required parameters
  if (empty($message)) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Message cannot be empty.');
  }
  if (empty($recipient) || !preg_match('/^\d+$/', $recipient)) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Recipient must be a string containing digits only (MSISDN).');
  }
  if (empty($sender)) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Sender cannot be empty.');
  }

  $is_digits_only = preg_match('/^\d+$/', $sender);
  if ($is_digits_only) {
    if (strlen($sender) > 18) {
      return new WP_Error('GWAPI_INVALID_PARAM', 'Sender cannot be more than 18 digits.');
    }
  } else {
    if (strlen($sender) > 11) {
      return new WP_Error('GWAPI_INVALID_PARAM', 'Sender cannot be more than 11 characters when it contains non-digit characters.');
    }
  }

  // Set default options
  $defaults = [
    'priority' => 'normal',
    'reference' => null,
    'expiration' => 432000,
    'label' => null,
  ];
  $options = array_merge($defaults, $options);

  // Validate expiration
  if ($options['expiration'] < 1 || $options['expiration'] > 432000) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Expiration must be between 1 and 432000 seconds.');
  }

  // Handle tag replacement if tags are provided
  if (isset($options['tags']) && is_array($options['tags'])) {
    foreach ($options['tags'] as $tag => $value) {
      $message = str_replace($tag, $value, $message);
    }
  }

  // Build the request body
  $req = [
    'message' => htmlspecialchars_decode(stripslashes($message)),
    'recipient' => $recipient,
    'sender' => $sender,
    'priority' => $options['priority'],
    'expiration' => $options['expiration'],
  ];

  // Add optional fields if set
  if ($options['reference'] !== null) {
    $req['reference'] = $options['reference'];
  }
  if ($options['label'] !== null) {
    $req['label'] = $options['label'];
  }

  // Apply filter for customization
  $req = apply_filters('gwapi_send_mobile_message_request', $req);

  // Determine API URL based on setup
  $uriBySetup = [
    'com' => 'https://messaging.gatewayapi.com/mobile/single',
    'eu' => 'https://messaging.gatewayapi.eu/mobile/single',
  ];
  $uri = $uriBySetup[get_option('gwapi_setup') ?: 'com'];

  // Get the API token
  $token = get_option('gwapi_token');

  // Send the request using wp_remote_post
  $res = wp_remote_post($uri, [
    'headers' => [
      'Authorization' => 'Token ' . $token,
      'Content-Type' => 'application/json',
      'user-agent' => 'wp-gatewayapi/' . GATEWAYAPI_VERSION
    ],
    'body' => json_encode($req)
  ]);

  // Handle WP_Error from wp_remote_post
  if (is_wp_error($res)) {
    return new WP_Error('TECH_FAIL', $res->get_error_message());
  }

  // Check response code
  $response_code = wp_remote_retrieve_response_code($res);
  $response_body = wp_remote_retrieve_body($res);

  // Success: 2xx response
  if ($response_code >= 200 && $response_code < 300) {
    return json_decode($response_body);
  }

  // Error: non-2xx response
  $error = json_decode($response_body);
  if ($error && isset($error->message)) {
    $error_message = $error->message;
    if (isset($error->code)) {
      $error_message .= "\nCode " . $error->code;
    }
    if (isset($error->incident_uuid)) {
      $error_message .= "\nUUID: " . $error->incident_uuid;
    }
    return new WP_Error('GWAPI_FAIL', $error_message);
  }

  return new WP_Error('GWAPI_FAIL', 'HTTP ' . $response_code . "\n" . $response_body);
}


/**
 * Send multiple mobile messages using the GatewayAPI Messaging API.
 *
 * @param array $messages An array of up to 1000 messages. Each message is an associative array with:
 *                        - 'message' (string): The message to be sent.
 *                        - 'recipient' (string): An MSISDN (string containing digits only).
 *                        - 'sender' (string): Sender text (3-18 characters).
 *                        - 'priority' (string, optional): Message priority. Default 'normal'.
 *                        - 'reference' (string|null, optional): Client-provided reference. Default NULL.
 *                        - 'expiration' (int, optional): Expires in number of seconds (1-432000). Default 432000.
 *                        - 'label' (string|null, optional): Label for the message. Default NULL.
 * @return object|WP_Error On success, returns the JSON response object.
 *                         On failure, returns a WP_Error.
 */
function gatewayapi_send_mobile_messages($messages)
{
  // If API version is set to 'sms', use the SMS API instead.
  $api_version = get_option('gwapi_api_version', 'sms');
  if ($api_version === 'sms') {
    // Collect all tags and prepare recipients for a single call
    $recipients_with_tags = [];
    $message = '';
    $sender = '';

    foreach ($messages as $msg) {
      if (empty($message)) $message = $msg['message'];
      if (empty($sender)) $sender = isset($msg['sender']) ? $msg['sender'] : '';

      $msisdn = $msg['recipient'];
      $tags = isset($msg['tags']) ? $msg['tags'] : [];
      $recipients_with_tags[$msisdn] = $tags;
    }

    $res = gatewayapi_send_sms($message, $recipients_with_tags, $sender, 'MOBILE', 'UTF8', []);
    if (is_wp_error($res)) return $res;

    return (object)[
      'ids' => [$res]
    ];
  }

  // Validate messages array
  if (!is_array($messages) || empty($messages)) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Messages must be a non-empty array.');
  }
  if (count($messages) > 1000) {
    return new WP_Error('GWAPI_INVALID_PARAM', 'Maximum 1000 messages allowed per request.');
  }

  // Build the messages array for the request
  $formatted_messages = [];
  foreach ($messages as $index => $msg) {
    // Validate required fields
    if (empty($msg['message'])) {
      return new WP_Error('GWAPI_INVALID_PARAM', "Message at index $index: message cannot be empty.");
    }
    if (empty($msg['recipient']) || !preg_match('/^\d+$/', $msg['recipient'])) {
      return new WP_Error('GWAPI_INVALID_PARAM', "Message at index $index: recipient must be a string containing digits only (MSISDN).");
    }
    $is_digits_only = preg_match('/^\d+$/', $msg['sender']);
    if ($is_digits_only) {
      if (strlen($msg['sender']) > 18) {
        return new WP_Error('GWAPI_INVALID_PARAM', "Message at index $index: sender cannot be more than 18 digits.");
      }
    } else {
      if (strlen($msg['sender']) > 11) {
        return new WP_Error('GWAPI_INVALID_PARAM', "Message at index $index: sender cannot be more than 11 characters when it contains non-digit characters.");
      }
    }

    // Build the message object
    $msg_text = htmlspecialchars_decode(stripslashes($msg['message']));
    if (isset($msg['tags']) && is_array($msg['tags'])) {
      foreach ($msg['tags'] as $tag => $value) {
        $msg_text = str_replace($tag, $value, $msg_text);
      }
    }

    $formatted_msg = [
      'message' => $msg_text,
      'recipient' => $msg['recipient'],
      'sender' => $msg['sender'],
    ];

    // Add optional fields with defaults
    $formatted_msg['priority'] = isset($msg['priority']) ? $msg['priority'] : 'normal';
    $formatted_msg['expiration'] = isset($msg['expiration']) ? $msg['expiration'] : 432000;

    // Validate expiration
    if ($formatted_msg['expiration'] < 1 || $formatted_msg['expiration'] > 432000) {
      return new WP_Error('GWAPI_INVALID_PARAM', "Message at index $index: expiration must be between 1 and 432000 seconds.");
    }

    // Add optional fields if set
    if (isset($msg['reference']) && $msg['reference'] !== null) {
      $formatted_msg['reference'] = $msg['reference'];
    }
    if (isset($msg['label']) && $msg['label'] !== null) {
      $formatted_msg['label'] = $msg['label'];
    }

    $formatted_messages[] = $formatted_msg;
  }

  // Build the request body
  $req = [
    'messages' => $formatted_messages,
  ];

  // Apply filter for customization
  $req = apply_filters('gwapi_send_mobile_messages_request', $req);

  // Determine API URL based on setup
  $uriBySetup = [
    'com' => 'https://messaging.gatewayapi.com/mobile/multi',
    'eu' => 'https://messaging.gatewayapi.eu/mobile/multi',
  ];
  $uri = $uriBySetup[get_option('gwapi_setup') ?: 'com'];

  // Get the API token
  $token = get_option('gwapi_token');

  // Send the request using wp_remote_post
  $res = wp_remote_post($uri, [
    'headers' => [
      'Authorization' => 'Token ' . $token,
      'Content-Type' => 'application/json',
      'user-agent' => 'wp-gatewayapi/' . GATEWAYAPI_VERSION
    ],
    'body' => json_encode($req)
  ]);

  // Handle WP_Error from wp_remote_post
  if (is_wp_error($res)) {
    return new WP_Error('TECH_FAIL', $res->get_error_message());
  }

  // Check response code
  $response_code = wp_remote_retrieve_response_code($res);
  $response_body = wp_remote_retrieve_body($res);

  // Success: 2xx response
  if ($response_code >= 200 && $response_code < 300) {
    return json_decode($response_body);
  }

  // Error: non-2xx response
  $error = json_decode($response_body);
  if ($error && isset($error->message)) {
    $error_message = $error->message;
    if (isset($error->code)) {
      $error_message .= "\nCode " . $error->code;
    }
    if (isset($error->incident_uuid)) {
      $error_message .= "\nUUID: " . $error->incident_uuid;
    }
    return new WP_Error('GWAPI_FAIL', $error_message);
  }

  return new WP_Error('GWAPI_FAIL', 'HTTP ' . $response_code . "\n" . $response_body);
}


/**
 * DEPRECATED! Please switch to gatewayapi_send_mobile_message instead for single recipient messages.
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
 * @param string $message A string containing the message to be sent.
 * @param array|string $recipients A single recipient or a list of recipients.
 * @param string $sender Sender text (11 chars or 15 digits)
 * @param string $destaddr Type of SMS - Can be MOBILE (regular SMS) or DISPLAY (shown immediately on phone and usually not stored - also called a Flash SMS)
 * @param array $encoding The $message must always be in UTF-8. Read more about the different encodings available here: https://gatewayapi.com/docs/appendix.html#term-ucs2
 * @param array $additional_options Additional options, such as "label", "priority" etc.. Can be used to override any other options set by this function.
 * @return int|WP_Error ID of message in gatewayapi.com on success
 *
 * @deprecated Use gatewayapi_send_mobile_message instead for single recipient messages.
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
    'message' => htmlspecialchars_decode(stripslashes($message)),
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
      'msisdn' => preg_replace('/\D/', '', $msisdn),
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
  $uriBySetup = [
      'com' => ['https://gatewayapi.com/rest/mtsms'],
      'eu' => ['https://gatewayapi.eu/rest/mtsms']
  ];

  $ts = time() - 3;
  $uris = $uriBySetup[get_option('gwapi_setup') ? : 'com'];

  $token = get_option('gwapi_token');

  foreach ($uris as $i => $uri) {
    if ($token) {
      $auth = 'Token ' . $token;
    } else {
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
    }

    $res = wp_remote_request($uri, $q = [
      'method' => 'POST',
      'headers' => [
        'Authorization' => $auth,
        'Content-Type' => 'application/json',
        'user-agent' => 'wp-gatewayapi'
      ],
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
