# GatewayAPI

> [!TIP]
> If you just want to use the plugin, you can search for **GatewayAPI** from within the WordPress plugin directory or download it from the [official repository here](https://wordpress.org/plugins/gatewayapi/).

![GatewayAPI Banner](https://ps.w.org/gatewayapi/assets/banner-1544x500.png)

Send SMS notifications for WooCommerce orders, create SMS campaigns, manage contacts, and add two-factor authentication - powered by GatewayAPI.com.

## 📝 Description

GatewayAPI for WordPress is a powerful SMS plugin that combines WooCommerce transactional SMS notifications with a high-performance campaign and contact management system.

The plugin allows you to automatically send SMS messages when WooCommerce orders change status, as well as create and send personalized SMS campaigns to large contact lists.

You can also add an extra layer of security to your WordPress site with SMS-based two-factor authentication, helping protect your login process with verification codes sent directly to your mobile device.

Version 2 is a complete rewrite of the plugin, with a strong focus on performance, reliability, and modern WordPress standards.

All SMS messages are delivered via GatewayAPI.com, one of Europe’s leading SMS gateways.

## ⭐ Main Features

### 🛒 WooCommerce SMS Notifications

* Send automatic SMS messages based on WooCommerce order status changes
* Separate message templates for customer and internal recipients
* Fully configurable per order status
* Supports all standard WooCommerce order statuses

### 🔐 Two-Factor Authentication

* Add an extra layer of security to your WordPress login
* SMS-based verification codes sent automatically during login
* Can be limited to specific roles and phone countries
* Works with standard WordPress authentication
* Uses GatewayAPI's reliable SMS delivery

### 📢 SMS Campaigns & Broadcasts

* Send SMS campaigns to large contact lists
* High throughput and stable delivery using Action Scheduler
* Designed to work reliably even on shared web hosting
* Background processing

### 📇 Contact & List Management

* Manage contacts directly in WordPress
* Import and export contacts using CSV files
* Store custom fields per contact
* Reusable contact lists for campaigns
* Use contact fields for mail-merged SMS content

### ✉️ Personalized Message Templates

* Mail-merge support for campaigns and WooCommerce messages
* Use dynamic placeholders such as customer data, order data, and custom fields
* Supports both GSM and Unicode SMS
* Configurable sender ID per message

### 🚀 Built for Reliability & Scale

* Uses Action Scheduler (the same job system used by WooCommerce)
* Non-blocking background processing
* Suitable for very large campaigns (100,000+ recipients)
* No PHP timeouts or long-running requests

## ⚠️ Coming from v1.x? Some features are gone

The following features are not part of this plugin anymore:

* Receiving or processing incoming SMS messages
* Contact Form 7-integration

These features had very low usage and were removed to simplify the plugin. If you need this, consider the legacy version of the plugin (any version before 2.0).

## 🌍 About GatewayAPI.com

* Founded in 1999
* One of Europe’s leading SMS providers
* Offices in Copenhagen, Odense, and Aalborg, Denmark
* Hundreds of millions of SMS messages delivered yearly
* No subscriptions – pay only for what you send
* Fast and reliable SMS delivery worldwide

## 🔧 Installation

1. Create a free account at [https://gatewayapi.com/](https://gatewayapi.com/)
2. Install and activate the plugin
3. Go to **GatewayAPI → Settings**
4. Add your API Token from your GatewayAPI.com account
5. Play around! Send a test campaign or setup a WooCommerce Order hook

## 🚀 Getting Started

### 🛍️ WooCommerce Notifications

After installation, you can enable SMS notifications per WooCommerce order status.
Each status can send messages to customers, internal recipients, or both.

Place a test order to confirm everything is working as expected.

### 📨 Campaign Messaging

You can create SMS campaigns without WooCommerce:

* Import contacts via CSV or add them manually
* Organize contacts into lists
* Create a personalized SMS campaign

### 🔐 Two-Factor Authentication

To add SMS-based two-factor authentication to your WordPress site:

* Go to **GatewayAPI → Settings → Two-Factor**
* Enable two-factor authentication
* Configure which user roles should use two-factor authentication
* Optionally limit to specific phone countries for security
* Set a grace period if needed

## ❓ Frequently Asked Questions

### Can this plugin handle very large SMS campaigns?

Yes. Campaigns are processed using Action Scheduler, ensuring stable delivery, high throughput, and retries — even on shared hosting environments.

### Does this plugin support personalized SMS messages?

Yes. You can use dynamic placeholders from contacts and WooCommerce orders to send fully personalized, mail-merged SMS messages.

### Is WooCommerce required?

WooCommerce is only required for order-based SMS notifications.
Campaign messaging and contact management work without WooCommerce.

## 🔗 External services

This plugin connects to the GatewayAPI.com service to send SMS messages. This external service is required for the plugin’s primary functionality, which is delivering transactional and campaign SMS messages.

When sending an SMS, the plugin transmits the recipient phone number, message content, and related metadata (such as sender ID) to GatewayAPI.com. For WooCommerce notifications, order-related data may be used to generate the message content before it is sent.

No data is sent to GatewayAPI.com unless an SMS is actively being sent by the site administrator or triggered by configured WooCommerce events.

This service is provided by GatewayAPI ApS.

Privacy policy, terms and conditions, data processing agreement, and security certifications (including ISAE 3000 and ISAE 3402) are available at:
[https://gatewayapi.com/security-and-compliance/](https://gatewayapi.com/security-and-compliance/)

## 🛠️ Development

To develop for this plugin, you'll need a local environment.

### Requirements

*   **Local WordPress install:** Running **without HTTPS** (otherwise you will get CORS issues as the plugin creates an iframe which loads content from Vite's dev-server which doesn't have a certificate).
*   **Repository location:** Checkout this repository into `wp-content/plugins/gatewayapi`.

### Setting up the Admin UI

The admin interface is built with Vue.js and needs a development server running for hot reload etc.

1.  Navigate to the admin-ui directory:
    ```bash
    cd wp-content/plugins/gatewayapi/admin-ui
    ```
2.  Install dependencies:
    ```bash
    npm install
    ```
3.  Start the development server:
    ```bash
    npm run dev
    ```

### WordPress Configuration

To tell the plugin to use the development server for the admin UI assets, add the following line to your `wp-config.php`:

```php
define('GATEWAYAPI_DEVSERVER', 'localhost:5099');
```

### Pro-tip: Vue Devtools

We highly recommend installing the [Vue devtools extension](https://devtools.vuejs.org/getting-started/installation) in your browser for a much better development experience.
