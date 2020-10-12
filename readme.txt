=== GatewayAPI ===
Contributors: onlinecity
Tags: sms, two factor, security, mobile, texting
Requires at least: 4.6
Tested up to: 5.5
Stable tag: 1.6.6
License: MIT
License URI: https://opensource.org/licenses/MIT

Send SMS'es and enable SMS-based two-factor security.

== Description ==

This plugin enables you to send SMS messages straight from the WordPress backend or via the programmer’s API.

Also included is a free and easy-to-use two-factor security feature, which hardens the security of your site considerably.

All you need is the plugin and a free [GatewayAPI.com](https://gatewayapi.com/) account.


Main features:

* **📱 Send SMS messages / texts**
  *   Add custom data to recipients to mail merge.
  *   Import recipient lists from CSV/Excel.
  *   Group recipients.
  *   Bulk-sending.
  *   Easy-to-use programmer’s API.
  *   Short-codes for signup/unsubscribe/edit profile forms.
  *   Automatic integration with Contact Form 7.

* **🔐 Two-factor security**
  *   Easy-to-use: No apps needed!
  *   Easy for admins: Tick a checkbox and it just works!
  *   Military grade security!
  *   Pick roles to enable mandatory two-factor.
  *   Re-authorise at each login or remember devices for up to 30 days.

* **✊ Receive SMS messages  / texts**
  *   Use your own keyword(s) or phone numbers to receive SMS messages.
  *   View incoming messages.
  *   Auto-reply to incoming SMS messages.

**Easy to get started:**

*   Complete step-by-step user guide with several screenshots.
*   The plugin has help texts included.
*   Live chat support and mail support from GatewayAPI.com.

**SMS messages provided by one of the leading SMS Gateways in Europe**

*   GatewayAPI.com sends hundreds of millions of SMS messages each year on behalf of Google, Visma, Pfizer and many more.
*   Headquarters in Copenhagen, Denmark.
*   Company founded in 1999.
*   Free support, no subscription, AND unbeatable prices.
*   Most SMS messages are delivered within 0,3 seconds.

If you prefer to disable the UI-features and manage broadcasts from code instead, then that is possible as well. For this purpose you can use the method `gwapi_send_sms` which accepts arguments for message, recipient(s), sender-text and type of SMS.


= Getting Started =

We have created a number of short tutorials, demonstrating how to get started and use the various features. Watch the following video to see how to set everything up and send your first SMS message:

[Click here for more videos](https://wordpress.org/plugins/gatewayapi/installation/).

== Installation ==

This section describes how to install and use the plugin

1. If you haven’t already, then go to [GatewayAPI.com](https://gatewayapi.com/) and create a free account.
1. Install and activate the plugin.
1. Go to “Settings » GatewayAPI Settings” and add an OAuth key and associated secret from your GatewayAPI.com account.
1. (Optional) Enable the sending UI and then go to “SMS messages » Create SMS” and try to send an SMS to yourself, verifying that all has been set up correctly.

We’ve also produced a number of videos to help you get started:

= GETTING STARTED =

This quick tutorial shows you how to get the GatewayAPI Plugin for WordPress installed and configured and how to send an SMS.

https://vimeo.com/179720894

= RECIPIENTS, GROUPS AND EXTRA FIELDS =

This quick tutorial shows you how to create groups, add recipients to the groups, as well as how to add custom fields to the individual recipients, which allows any extra data to be stored and used in SMS messages.

https://vimeo.com/179720962

= BUILT-IN FORMS FOR SIGN UP, UPDATE AND UNSUBSCRIBE =

This tutorial shows you how to create forms for the public, using the built-in UI for creating “shortcodes” (tiny pieces of code which can be pasted into any WordPress-page).

https://vimeo.com/179721068

= IMPORTING RECIPIENTS FROM SPREADSHEETS =

This tutorial shows you how to import recipients from any spreadsheet and into the recipients database in GatewayAPI. It even shows how to overwrite and update the database, import extra fields from spreadsheets and how to add the recipients into groups. It works with any spreadsheet app (Excel, Numbers, LibreOffice, Google Docs – you name it!).


https://vimeo.com/179721183


== Frequently Asked Questions ==

= How well does this plugin handle 10,000+ recipients =

It works really well. We split large SMS broadcasts into multiple smaller requests, to ease the burden on your WordPress setup. You will see a progress bar, when you are sending to more than 500 recipients at a time.

= HELP! I’m administrator and I’m locked out of the two-factor system! =

If you don’t have a backup of the “Emergency bypass URL” from the setup-screen, then you need to dig into the database to disable the two-factor system. Your host probably has a phpMyAdmin that you can use to access it.
Then find the options-table, by default wp_options. Search for the row where the option_name is gwapi_security_enable. Simply delete the row.

== Screenshots ==
1. Set up your OAuth key and secret here. This settings page is available for administrators only.
2. If the SMS Sending UI is enabled, this is how you can send a SMS from the backend.
3. Contact Form 7: GatewayAPI automatically adds extra controls for supporting signup/update/unsubscribe in the Contact Form 7 form builder.
4. Contact Form 7: Creating a "recipient groups" selection field.

== Changelog ==

= 1.6.6 =
* Bugfix: While using recipients import, group recipients was not counted correctly after the import was completed.

= 1.6.5 =
* Improved: Import of recipients optimized.

= 1.6.4 =
* Compatible with WordPress 5.5
* Improved: Integration with Contact Form 7 now supports shortcode for mandatory fields
* Tweak: Updated UI and descriptions.

= 1.6.3 =
* Compatible with WordPress 5.4
* New! Recipient groups can now default to unchecked when using short-code
* Tweak: Minor updates to UI.

= 1.6.2 =
* Tweak: The frontend forms now use regular SMS instead of Display SMS for two-factor, as requested by multiple users.
* Bugfix: When using tags, the list of tags sent to GatewayAPI could end in a situation, with the same tag-data repeated.

= 1.6.1 =
* Removed URL in two-factor SMS as it was unnecessary and caused SMS'es to be blocked by GatewayAPI's new link-scanner.

= 1.6.0 =
* New! Custom encoding for SMS'es, allowing SMS'es with emojis and other special characters.
  * UI which detects what encoding should be used, always recommending the cheapest option (ie. most characters per SMS).
  * API-method `gwapi_send_sms` has new argument for setting encoding.
  * Fully backwards-compatible, defaulting to standard-encoding.
* Compatible with WordPress 5.1

= 1.5.2 =
* Compable with WordPress 5.0.3.
* Fix: Excel-export of recipients did not work unless "SMS Inbox" was enabled.
* Fix: Notices in Excel-export.

= 1.5.1 =
* Updated Danish translations (primarily the two-factor settings and frontend).
* Fix: Our shortcode had unintentionally been renamed. We now support both `[gwapi]` and `[gatewayapi]`.
* Fix: Two-factor-module caused fatal error on PHP 5. Also fixed general notices in two-factor module.
* Fix: Two-factor module caused fatal error when creating new WordPress-users.

= 1.5.0 =
* New! Two-factor security upgrade to your WordPress! (optional)
* New! Blacklisting of phone numbers.
* Improved: Searching recipients by phone number works.
* Improved: Better support for 10.000+ recipients + a progress bar for showing progress.

= 1.4.2 =
* Recipients:
  * Added option to blacklist phone numbers.
  * Added support for searching recipients list by phone number (previously only by name).
  * Added drop down on recipients list, for filtering by group.
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

Most topics are shown in a step-by-step style with numerous screenshots in our User Guide – even quite advanced topics.

[Open the User Guide](https://github.com/onlinecity/wp-gatewayapi/wiki/User-Guide)


= Advanced: Programmers API =

Send an SMS message to one or multiple recipients by calling `gwapi_send_sms `with the following arguments

*   $message (string) A string containing the message to be sent.
*   $recipients (array|string) A single recipient or a list of recipients.
*   $sender (string, optional) Sender text (11 chars or 15 digits)
*   $destaddr (string, optional) Type of SMS – Can be MOBILE (regular SMS) or DISPLAY (shown immediately on phone and usually not stored, also knows as a Flash SMS)

Returns the GatewayAPI.com message-ID on success and a WP_Error on failure.

The recipients-argument may consist of either:

*   An integer or string, containing an MSISDN (CC + number, digits only).
Example number: Country code 45. Phone number: 12 34 56 78.
Resulting MSISDN: “4512345678”.
*   An array containing MSISDN (see above).
*   An array in which MSISDN’s are keys and their values are arrays of tags.
Example in JSON:
{ “4512345678”: { “%NAME%”: “John Doe”, “%GENDER%”: “Male” } }

Note: SMS messages sent via `gwapi_send_sms` are NOT saved in WordPress. They are however still accessible via the traffic log on GatewayAPI.com


## **Screenshots**

Set up your OAuth key and secret here. This settings page is available for administrators only.

If the SMS Sending UI is enabled, this is how you can send an SMS from the backend.

Contact Form 7: GatewayAPI automatically adds extra controls for supporting signup/update/unsubscribe in the Contact Form 7 form builder.

Contact Form 7: Creating a "recipient groups" selection field.

