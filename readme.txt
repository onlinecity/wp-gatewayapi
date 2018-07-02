=== GatewayAPI ===
Contributors: onlinecity
Donate link:
Tags: sms, recipients, groups, mobile, phone
Requires at least: 4.0
Tested up to: 4.9.6
Stable tag: 1.4.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Send SMS'es from the WordPress-backend or via the programmers API.

== Description ==

This plugin enables you to send SMS'es straight from the WordPress backend or via the programmers API.

All you need, is to create a free account at [GatewayAPI.com](https://gatewayapi.com), which includes €2 of credit.

If you would like to send SMS'es from the backend, the plugin provides UI's for:

**Backend:**

- Use WordPress admin to manage and send - or do it all from PHP.
- Manage and group recipients.
- Send to single recipients or groups.
- Change sender text, type of SMS - completely unbranded SMS'es.
- Dynamically use meta-data within your SMS'es.
- Track the delivery status of an SMS.
- Automatic synchronization of recipients and WordPress users, including selected metadata.
- Add unlimited meta fields to your recipients: 11 content types to pick from!

**Receive SMS'es:**

- Reeive SMS'es sent from the phones of your visitors and others.
- View received messages from the backend, via the "SMS Inbox".
- Handle incoming SMS'es from WordPress-hooks (for programmers).
- Create automated replies based on incoming text.

**Integration in your website:**

- Short-code generator, creating sign up, update and unsubscribe forms.
- Public forms feature CAPTCHA and two-factor flows.
- Custom security? Create a "Send SMS" form via a shortcode.

**Contact Form 7-integration:**

- Create signup/update/unsubscribe/send SMS-forms in Contact Form 7.
- Send SMS auto-replies on any form.
- Full integration with wizard-style shortcode tag generation.

**Easy to get started:**

- Complete step-by-step user guide with many screenshots.
- The plugin has helpful texts all around.
- Live chat support and mail support from GatewayAPI.com.

**Backed by high quality, low price EU-based SMS-gateway:**

- GatewayAPI.com has sent 180+ million SMS'es.
- We regularly send over 1.500 SMS'es per second.
- Headquarters in Copenhagen, Denmark.
- Company founded in 1999.
- Free support, no subscription AND unbeatable prices.
- Most SMS'es are delivered within 0,3 second.

If you would prefer to disable the UI-features and do all the sending from code, then that's possible as well. For this purpose you can use the method `gwapi_send_sms` which accepts arguments for message, recipient(s), sender-text and type of SMS.

= NEW IN VERSION 1.4: PROPER CONTACT FORM 7-INTEGRATION =

This version sports better integration with Contact Form 7, as well as the possibility to create "Send SMS"-forms in Contact Form 7.


= Getting Started =

We have created a number of short tutorials, demonstrating how to get started and use the various features. Watch the following video to see how to set everything up and send your first SMS:

https://vimeo.com/168035068

[Click here for more videos](https://wordpress.org/plugins/gatewayapi/installation/).


== Installation ==

This section describes how to install the plugin and get it working.

1. If you haven't already, then go to [GatewayAPI.com](https://gatewayapi.com) and create a free account.
1. Install and activate the plugin.
1. Go to "Settings » GatewayAPI Settings" and add an OAuth key and associated secret from your GatewayAPI.com account.
1. (Optional) Enable the sending UI and then go to "SMS'es » Create SMS" and try to send an SMS to yourself, verifying that all is setup correctly.

We've also produced a number of videos to help you get started:

= Getting started =

This quick tutorial shows you how to get GatewayAPI Plugin for WordPress installed and configured, and goes as far as to send of an actual SMS.

https://vimeo.com/179720894

= Recipients, groups and extra fields =

This quick tutorial shows you how to create groups, add recipients to the groups, as well as how to add custom fields to the recipients, allowing for any extra data to be stored and used in SMS'es.

https://vimeo.com/179720962

= Built-in forms for sign up, update and unsubscribe =

This tutorial shows you how to create forms for the public, using the built-in UI for creating "shortcodes" (tiny pieces of code which can be pasted into any WordPress-page).

https://vimeo.com/179721068

= Importing recipients from spreadsheets =

This tutorial shows you how to import recipients from any spreadsheet and into the recipients database of the GatewayAPI. It even shows how to overwrite and update the database, import extra fields from spreadsheets and how to add the recipients into groups. It works with any spreadsheet app (Excel, Numbers, LibreOffice, Google Docs - you name it!).

https://vimeo.com/179721183


== Frequently Asked Questions ==

= How well does this plugin handle 10.000+'s of recipients =

Theoretically it works, but it's still in the early days for this plugin and this also much depends on your hosting providers setup. The plugin can usually easily handle 10.000 recipients at a time, but we'd recommend splitting into multiple SMS'es for now.

== Screenshots ==
1. Set up your OAuth key and secret here. This settings page is available for administrators only.
2. If the SMS Sending UI is enabled, this is how you can send a SMS from the backend.
3. Contact Form 7: GatewayAPI automatically adds extra controls for supporting signup/update/unsubscribe in the Contact Form 7 form builder.
4. Contact Form 7: Creating a "recipient groups" selection field.

== Changelog ==

= 1.4.2 =
* Fix: Improved handling of huge lists of recipients (ie. 1.000+ recipients in one SMS)

= 1.4.1 =
* Fix: List of countries is now always correctly parsed, even when the JSON-file (which is fetched via AJAX) does not have right mime-type.

= 1.4.0 =
Note: **v1.4.0 may break your Countact Form 7-forms containing GatewayAPI-fields**, as the shortode-syntax has slightly changed for most of our fields. We needed to do this change to fix multiple bugs and inconsistencies. Please re-add the GatewayAPI-fields to your Contact Form 7-forms when updating the plugin.

* Contact Form 7:
  * Ability to send SMS'es from the frontend.
  * Updating a subscriber: Forms now only updates groups specifically selected for the form.
  * Bugfixes and code cleanup, improved shortcode syntax.

* Complete user guide for the system (available online).
* Export of recipients to Excel and CSV-formats.

= 1.3.3 =
* Contact Form 7: Added support for forms which also contained a reCaptcha-field.
* Code cleanup

= 1.3.2 =
* Bugfix: A menu item had gone missing in the backend.

= 1.3.1 =
* New UI for creating automated actions. Currently supports "autoreply" based on keyword.

= 1.3.0 =
* Support for receiving SMS'es added, including setup-wizard and inbox.

= 1.2.3 =
* Bugfix: The new verification SMS for Contact Form 7 had a typo, breaking verification SMS'es.
* Missing translations: The popup-messages related to verification were not translated to danish.

= 1.2.2 =
* Better international phone numbers support: Prefixed 0's in the phone number itself (between country prefix and phone number) is now correctly working.

= 1.2.1 =
* User synchronization:
 * No longer requires a country code meta field, instead allowing a default country code when this value is missing.
 * Now possible to trigger a "one time" synchronization.
* Contact Form 7:
 * Now possible to send an SMS-reply on form success automatically.
 * SMS-validation for signup, before allowing the form to submit successfully.
 * Bugfix: Validation now works for all GatewayAPI-fields.

= 1.2.0 =
* Contact Form 7: Integration supporting signup, update and unsubscribe forms for frontend. Two-factor flow possible for update-flow.

= 1.1.6 =
* Bugfix: Safari-specific issue with SMS-counter.
* Bugfix: Hidden required fields might break the settings-pages.
* Updated translations for Danish.

= 1.1.5 =
* Support for automatic synchronization of WordPress users => recipients (one-way) including all meta fields and groups.

= 1.1.4 =
* Update WordPress Extension-page.

= 1.1.3 =
Bugfixes:
* Options page didn't load initially after update.
* CSS/JS missing.
* A few notices squashed.

= 1.1.2 =
* Bugfix: Final step of signup failed could fail if anonymous user.

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

= Most users: User Guide =

Most topics are shown in a step-by-step style with numerous screenshots in our User Guide - even quite advanced topics.

[Open the User Guide](https://github.com/onlinecity/wp-gatewayapi/wiki/User-Guide)


= Advanced: Programmers API=

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
