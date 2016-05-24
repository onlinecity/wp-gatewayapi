=== GatewayAPI ===
Contributors: onlinecity
Donate link:
Tags: sms, recipients, groups, mobile, phone
Requires at least: 4.0
Tested up to: 4.5.2
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Send SMS'es from the WordPress-backend or via the programmers API.

== Description ==

GatewayAPI enables you to send SMS'es straight from the WordPress backend or via the programmers API.

All you need, is to create a free account at GatewayAPI.com, which includes €2 of credit.

If you would like to send SMS'es from the backend, the plugin provides UI's for:

- Managing recipients.
- Grouping recipients.
- Sending to everyone in a group.
- Sending to manually added recipients at send time.
- Specify a sender-text.
- Use recipient-specific tags.
- Stores a list of sent SMS'es.
- Track the delivery status of an SMS.

If you would prefer to disable the UI-features and do all the sending from code, then that's possible as well. For this purpose you can use the method `gwapi_send_sms` which accepts arguments for message, recipient(s), sender-text and type of SMS.


== Installation ==

This section describes how to install the plugin and get it working.

1. If you haven't already, then go to GatewayAPI.com and create a free account.
1. Install and activate the plugin.
1. Go to "Settings » GatewayAPI Settings" and add the OAuth-credentials from your GatewayAPI.com account.
1. (Optional) Enable the sending UI and then go to "SMS'es » Create SMS" and try to send an SMS to yourself, verifying that all is setup correctly.

== Frequently Asked Questions ==

= How well does this plugin handle 10.000+'s of recipients =

Theoretically it works, but it's still in the early days for this plugin and this also much depends on your hosting providers setup. The plugin can usually easily handle 10.000 recipients at a time, but we'd recommend splitting into multiple SMS'es for now.

== Screenshots ==


== Changelog ==

= 1.0 =
* Inital version.

== How to use ==

Either use the UI, which should be pretty self-explainatory, or use the programmers API.

= Programmers API: `gwapi_send_sms` =

Send an SMS to one or multiple recipients by calling `gwapi_send_sms` with the following arguments

- $message (string) A string containing the message to be sent.
- $recipients (array|string) A single recipient or a list of recipients.
- $alpha (string, *optional*) Alpha text (11 chars or 15 digits)
- $destaddr (string, *optional*) Type of SMS - Can be MOBILE (regular SMS) or FLASH (shown immediately on phone and usually not stored)

Returns the GatewayAPI.com message-ID on success and a WP_Error on failure.

The recipients-argument may consist of either:

- An integer or string, containing an MSISDN (CC + number, digits only).
  Example number: Country code 45. Phone number: 12 34 56 78.
  Resulting MSISDN: "4512345678".
- An array containing MSISDN (see above).
- An array in which MSISDN's are keys and their values are arrays of tags.
  Example in JSON:
  { "4512345678": { "%NAME%": "John Doe", "%GENDER%": "Male" } }

*Note: SMS'es sent via `gwapi_send_sms` are NOT saved in WordPress. They are however still accessible via the traffic log on GatewayAPI.com*