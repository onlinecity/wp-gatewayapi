=== GatewayAPI ===
Contributors: onlinecity
Donate link:
Tags: sms, recipients, groups, mobile, phone
Requires at least: 4.0
Tested up to: 4.5.2
Stable tag: 1.1.1
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

= Getting Started =

There's more information in the "Installation"-tab, but you could also just watch this video tutorial, showing you how
to get set up and sending your first SMS:

https://vimeo.com/168035068



== Installation ==

This section describes how to install the plugin and get it working.

1. If you haven't already, then go to GatewayAPI.com and create a free account.
1. Install and activate the plugin.
1. Go to "Settings » GatewayAPI Settings" and add an OAuth key and associated secret from your GatewayAPI.com account.
1. (Optional) Enable the sending UI and then go to "SMS'es » Create SMS" and try to send an SMS to yourself, verifying that all is setup correctly.

We've also produced this quick video tutorial to help you get started:

https://vimeo.com/168035068

== Frequently Asked Questions ==

= How well does this plugin handle 10.000+'s of recipients =

Theoretically it works, but it's still in the early days for this plugin and this also much depends on your hosting providers setup. The plugin can usually easily handle 10.000 recipients at a time, but we'd recommend splitting into multiple SMS'es for now.

== Screenshots ==
1. Set up your OAuth key and secret here. This settings page is available for administrators only.
2. If the SMS Sending UI is enabled, this is how you can send a SMS from the backend.

== Changelog ==

= 1.1.1 =
* Bugfix: Enqueuing of front-end scripts and CSS didn't work for guests.

= 1.1.0 =
* Editable custom fields for recipients: Settings page now features a custom fields editor for recipient forms with drag and drop re-ordering.
* Short code generator: Possible to generate shortcodes from the backend for signup, update, unsubscribe and send SMS.
* Tags-support for all custom fields.
* Import subscribers, including custom fields, from spreadsheet. Support updating existing subscribers and keeps existing groups for existing subscribers.
* Bugfix: SMS message in UI counting counted wrong for 153-160 characters long SMS and also counted some special chars wrong.
* Bugfix: Manually added recipients %NAME%-tag didn't work.


= 1.0.1 =
* Cosmetical changes: A few inconsistencies has been fixed in the naming and the documentation.
* Tidied up a bit: If the sending UI is not enabled, even less code is now executed on each request.

= 1.0 =
* Inital version.

== How to use ==

Either use the UI, which should be pretty self-explainatory, or use the programmers API. See the "Installation"-tab for
a quick how-to.

= Programmers API: `gwapi_send_sms` =

Send an SMS to one or multiple recipients by calling `gwapi_send_sms` with the following arguments

- $message (string) A string containing the message to be sent.
- $recipients (array|string) A single recipient or a list of recipients.
- $sender (string, *optional*) Sender text (11 chars or 15 digits)
- $destaddr (string, *optional*) Type of SMS - Can be MOBILE (regular SMS) or DISPLAY (shown immediately on phone and usually not stored, also knows as a Flash SMS)

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