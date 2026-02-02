=== GatewayAPI ===
Contributors: onlinecity
Tags: sms, woocommerce, campaigns, notifications, transactional sms
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.0.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Send SMS notifications for WooCommerce orders, create SMS campaigns, manage contacts, and add two-factor authentication - powered by GatewayAPI.com.

== 📝 Description ==

GatewayAPI for WordPress is a powerful SMS plugin that combines WooCommerce transactional SMS notifications with a high-performance campaign and contact management system.

The plugin allows you to automatically send SMS messages when WooCommerce orders change status, as well as create and send personalized SMS campaigns to large contact lists.

You can also add an extra layer of security to your WordPress site with SMS-based two-factor authentication, helping protect your login process with verification codes sent directly to your mobile device.

Version 2 is a complete rewrite of the plugin, with a strong focus on performance, reliability, and modern WordPress standards.

All SMS messages are delivered via GatewayAPI.com, one of Europe’s leading SMS gateways.

== ⭐ Main Features ==

= 🛒 WooCommerce SMS Notifications =

* Send automatic SMS messages based on WooCommerce order status changes
* Separate message templates for customer and internal recipients
* Fully configurable per order status
* Supports all standard WooCommerce order statuses

= 🔐 Two-Factor Authentication =

* Add an extra layer of security to your WordPress login
* SMS-based verification codes sent automatically during login
* Can be limited to specific roles and phone countries
* Works with standard WordPress authentication
* Uses GatewayAPI's reliable SMS delivery

= 📢 SMS Campaigns & Broadcasts =

* Send SMS campaigns to large contact lists
* High throughput and stable delivery using Action Scheduler
* Designed to work reliably even on shared web hosting
* Background processing

= 📇 Contact & List Management =

* Manage contacts directly in WordPress
* Import and export contacts using CSV files
* Store custom fields per contact
* Reusable contact lists for campaigns
* Use contact fields for mail-merged SMS content

= ✉️ Personalized Message Templates =

* Mail-merge support for campaigns and WooCommerce messages
* Use dynamic placeholders such as customer data, order data, and custom fields
* Supports both GSM and Unicode SMS
* Configurable sender ID per message

= 🚀 Built for Reliability & Scale =

* Uses Action Scheduler (the same job system used by WooCommerce)
* Non-blocking background processing
* Suitable for very large campaigns (100,000+ recipients)
* No PHP timeouts or long-running requests

== ⚠️ Coming from v1.x? Some features are gone ==

The following features are not part of this plugin anymore:

* Receiving or processing incoming SMS messages
* Contact Form 7-integration

These features had very low usage and were removed to simplify the plugin. If you need this, consider the legacy version of the plugin (any version before 2.0).

== 🌍 About GatewayAPI.com ==

* Founded in 1999
* One of Europe’s leading SMS providers
* Offices in Copenhagen, Odense, and Aalborg, Denmark
* Hundreds of millions of SMS messages delivered yearly
* No subscriptions – pay only for what you send
* Fast and reliable SMS delivery worldwide

== 🔧 Installation ==

1. Create a free account at https://gatewayapi.com/
2. Install and activate the plugin
3. Go to GatewayAPI → Settings
4. Add your API Token from your GatewayAPI.com account
5. Play around! Send a test campaign or setup a WooCommerce Order hook

== 🚀 Getting Started ==

= 🛍️ WooCommerce Notifications =

After installation, you can enable SMS notifications per WooCommerce order status.
Each status can send messages to customers, internal recipients, or both.

Place a test order to confirm everything is working as expected.

= 📨 Campaign Messaging =

You can create SMS campaigns without WooCommerce:

* Import contacts via CSV or add them manually
* Organize contacts into lists
* Create a personalized SMS campaign

= 🔐 Two-Factor Authentication =

To add SMS-based two-factor authentication to your WordPress site:

* Go to GatewayAPI → Settings → Two-Factor
* Enable two-factor authentication
* Configure which user roles should use two-factor authentication
* Optionally limit to specific phone countries for security
* Set a grace period if needed

== ❓ Frequently Asked Questions ==

= Can this plugin handle very large SMS campaigns? =

Yes. Campaigns are processed using Action Scheduler, ensuring stable delivery, high throughput, and retries — even on shared hosting environments.

= Does this plugin support personalized SMS messages? =

Yes. You can use dynamic placeholders from contacts and WooCommerce orders to send fully personalized, mail-merged SMS messages.

= Is WooCommerce required? =

WooCommerce is only required for order-based SMS notifications.
Campaign messaging and contact management work without WooCommerce.


== 🔗 External services ==

This plugin connects to the GatewayAPI.com service to send SMS messages. This external service is required for the plugin’s primary functionality, which is delivering transactional and campaign SMS messages.

When sending an SMS, the plugin transmits the recipient phone number, message content, and related metadata (such as sender ID) to GatewayAPI.com. For WooCommerce notifications, order-related data may be used to generate the message content before it is sent.

No data is sent to GatewayAPI.com unless an SMS is actively being sent by the site administrator or triggered by configured WooCommerce events.

This service is provided by GatewayAPI ApS.

Privacy policy, terms and conditions, data processing agreement, and security certifications (including ISAE 3000 and ISAE 3402) are available at:
https://gatewayapi.com/security-and-compliance/

== Screenshots ==

1. GatewayAPI authentication and general settings
2. SMS template editor with available placeholders
3. WooCommerce order status SMS configuration
4. Contact management and CSV import


== 📋 Changelog ==

= 2.0.5 =

 * Bugfix: For users upgrading from 1.x, a missing capability upgrade made the plugin unavailable until deactivate + reactivate.

= 2.0.4 =

 * Two-factor authentication has been re-added to the plugin.

= 2.0.2-2.0.3 =

 * No changes. These versions exist because we have been working on automating release processes.

= 2.0.1 =

 * Minor modifications to ensure best-practices as per WordPress guidelines.

= 2.0.0 =

 * Complete rewrite of the plugin!

= 1.8.3 =

 * Compability up to WordPress 6.3.1.

= 1.8.2 =

* Bugfix: Built in shortcode defaulted to the posts title as the name of the new recipient. Now defaults to '', as expected.

= 1.8.1 =

* Bugfix: Built in shortcode for signup did not work properly, when allowing the user to select groups.

= 1.8.0 =

* Support for using our GatewayAPI.eu-setup!
* Bugfix: 2FA login security could fail on PHP 7.4+.

= 1.7.6 =

* Bugfix: Fixing that GatewayAPI Shortcode for signup had stopped saving some meta-fields since 1.7.2.

= 1.7.5 =

* Bugfix: Default country code did not apply correctly in the country drop downs.

= 1.7.4 =

Compability with Contact Form 7.

* Bugfix: Signup via Contact Form 7 works again (upgraded to being compatible with CF7's newer window.fetch based approach of AJAX).
* Bugfix: Notices in CF7-integration (PHP) and a typo, causing an exception in frontend JS on CF7-forms.

= 1.7.3 =

Bugfix release.

* Bugfix: Saving recipient details using the UI and via Excel-importer, works properly again.
* Bugfix: SMS Reply-feature works again.

= 1.7.2 =

This release strengthens the overall security, thanks to input from an external security audit.

We highly recommend upgrading to this version, as it hardens the security of our plugin, especially on WordPress-installation with multiple users
which may have users of lower roles than editor.

* Consistent use of nonce's in AJAX requests, to prevent CSRF and prevent privilege escalation.
* Enforce correct roles for various actions, preventing potential privilege escalation.
* Improved sanitizing, validation and escaping of input and output.
* All PHP-files are now secured from direct access.
* Refactored function prefixes from `gwapi_`, `_gwapi_` and `_gatewayapi_` to `gatewayapi_` only.

= 1.7.1 =
* Bugfix: Notifications were not sent.

= 1.7.0 =
* New: Notification module: Receive SMS-notifications when various actions occur in WordPress
  * BETA: Please get in touch if you have ideas/suggestions for improving this feature.
* New: Default country code field setting (previously all country selectors defaulted to +45/Denmark).
* New: Programmer actions `gwapi_form_subscribe` and `gwapi_form_unsubscribe` added. The first is triggered when new recipients complete signup via
our shortcode-forms and the latter on unsubscription via the shortcode-forms.
* Optimization: Removed various unneeded dependencies.
* Danish translation updated, including the notification-module.
* Readme updated to inform about notification-module and other text improvements.

= 1.6.9 =
* New! Create Notifications to automatically notify recipients by SMS when a WP action is executed.

= 1.6.8 =
* Bugfix: Prefix wasn't specified for all instances of db_table usages.

= 1.6.7 =
* Bugfix: Previously imported recipients was prevented from being re-imported if they had been deleted.

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