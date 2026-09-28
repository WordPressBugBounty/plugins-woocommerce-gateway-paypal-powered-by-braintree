=== PayPal Enterprise Payments (formerly Braintree) for WooCommerce ===
Contributors: woocommerce, automattic, skyverge
Tags: paypal, braintree, woocommerce, payments, ecommerce
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.12.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The official PayPal Enterprise Payments extension for WooCommerce. Accept credit cards, PayPal, Apple Pay, Google Pay, Venmo, ACH Direct Debit, BNPL, local payment methods, and more — with Fastlane accelerated checkout built in.

== Description ==

Accept **credit cards, Apple Pay, Google Pay, PayPal, Venmo, ACH Direct Debit, BNPL**, local payment methods, and more with **PayPal Enterprise Payments (formerly Braintree) for WooCommerce** — with **Fastlane** accelerated checkout built in. Customers can save their card details or link a PayPal account for an even faster checkout experience.

= Features =

* **No redirects** — keep customers on your site for payment, reducing the risk of abandoned carts.
* **Security first**; PCI compliant, with 3D Secure verification and Strong Customer Authentication (SCA).
* **Express checkout options**, including Buy Now and PayPal Checkout buttons. Customers can save their card details, link a PayPal account, or pay with Apple Pay.
* **Optimized order management**; process refunds, void transactions, and capture charges from your WooCommerce dashboard.
* **Route payments in certain currencies** to different Braintree accounts (requires currency switcher).
* **Compatible** with WooCommerce Subscriptions and WooCommerce Pre-Orders.

= Safe and secure — every time =

Braintree's secure Hosted Fields provide a **seamless** way for customers to enter payment info on your site without redirecting them to PayPal.

It's [PCI compliant](https://listings.pcisecuritystandards.org/documents/Understanding_SAQs_PCI_DSS_v3.pdf) and supports **SCA** and **3D Secure** verification, so you always meet security requirements — without sacrificing flexibility. Plus, Braintree’s [fraud tools](https://articles.braintreepayments.com/guides/fraud-tools/overview) protect your business by helping **detect and prevent fraud**.

= Even faster checkouts =

Customers can **save their credit and debit card details** or **link a PayPal account** to fast-forward checkout the next time they shop with you. Adding **PayPal Checkout** and **Buy Now** buttons to your product, cart, and checkout pages makes purchasing simpler and quicker, too.

= Get paid upfront and earn recurring revenue =

Take charge of how you sell online. PayPal Enterprise Payments (formerly Braintree) supports [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) — the perfect solution for earning **recurring revenue**. It's also compatible with [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/), enabling you to accept payment **upfront** or as products ship.

== Frequently Asked Questions ==

= Where can I find documentation? =

You’ve come to the right place. [Our documentation](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/) for PayPal Enterprise Payments (formerly Braintree) for WooCommerce includes detailed setup instructions, troubleshooting tips, and more.

= Does this extension work with credit cards, or just PayPal? =

Yes! PayPal Enterprise Payments (formerly Braintree) for WooCommerce supports credit cards, PayPal, Apple Pay, Google Pay, Venmo, ACH Direct Debit, BNPL, and local payment methods.

= Does it support subscriptions? =

Yes! PayPal Enterprise Payments (formerly Braintree) supports tokenization (required for recurring payments) and is compatible with [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/).

= Which currencies are supported? =

Support is available for 25 currencies, [wherever Braintree is available](https://www.paypal.com/us/webapps/mpp/country-worldwide). You can use your store’s native currency or add multiple merchant IDs to process other currencies via different Braintree accounts. To manage multiple currencies, you’ll need a free or paid **currency switcher**, such as [Aelia Currency Switcher](https://aelia.co/shop/currency-switcher-woocommerce/) (requires purchase).

= Can non-US merchants use this extension? =

Yes! It’s supported in [all countries where Braintree is available](https://www.paypal.com/us/webapps/mpp/country-worldwide).

= Does it support testing and production modes? =

Yes; sandbox mode is available so you can test the payment process without activating live transactions. Woo-hoo!

= Credit card payments are working, but PayPal is not — why? =

You may need to [enable PayPal in your Braintree account](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#my-credentials-are-correct-but-i-still-dont-see-paypal-at-checkout-whats-going-on).

= Can I use this extension for PayPal only? =

Sure thing! See our instructions on [using PayPal Enterprise Payments without credit cards](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#using-paypal-without-credit-cards).

= Will it work with my site’s theme? =

This extension should work with any WooCommerce-compatible theme, but you might need to [customize your theme](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#theme-issues) for a perfect fit.

= Where can I get support, report bugs, or request new features? =

First, [review our documentation](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/) for troubleshooting tips and answers to common questions. If you need further assistance, please get in touch via the [official support forum](https://wordpress.org/support/plugin/woocommerce-gateway-paypal-powered-by-braintree/).

== Screenshots ==

1. Enter PayPal Enterprise Payments credentials
2. Credit card gateway settings
3. Advanced credit card gateway settings
4. PayPal gateway settings
5. Checkout with PayPal directly from the cart
6. Checkout with PayPal directly from the product page

== Changelog ==

= 3.12.0 - 2026-09-28 =
* Add - Allow merchants to disable the forced 3D Secure challenge on one-off credit card transactions so Braintree's 3D Secure Rules Manager can grant SCA exemptions (LVE/TRA) or skip 3D Secure where eligible.
* Fix - Credit Card 3DS no longer triggers with validation errors on the checkout page for Classic Checkout flow.
* Fix - Credit Card and PayPal gateways now load correctly on pages using the Classic Checkout block when block checkout is the default.
* Fix - Show actionable guidance when PayPal sandbox mode is missing a linked PayPal sandbox account, and prevent stale payment error notices from blocking block-based checkout after a failed payment.
* Fix - Show a clear, actionable message and force a new 3D Secure challenge on retry when a credit card payment is declined because card authentication was required.
* Fix - Restore manual entry for the Merchant Account ID field when no eligible accounts are found, and hide local payment methods at checkout when they have no usable merchant account for their currency.
* Fix - Prevent the merchant account ID dropdown from auto-selecting and saving the first account when none was chosen.
* Fix - Google Pay now properly collects customer phone number on orders.
* Fix - Default the credentials source to an already configured gateway when a gateway has no Braintree credentials of its own.
* Fix - Filters the payment method title for the admin order screen to remove any HTML tags.
* Fix - The Google Pay button on the Cart and Checkout blocks now responds to clicks and opens the Google Pay window.
* Fix - Shipping options and totals now update to match the address selected in the Google Pay window on the Cart and Checkout blocks.
* Dev - Align PHP and JS translator comments for the Test badge " mode" suffix so POT generation no longer warns.

= 3.11.2 - 2026-09-08 =
* Fix - Improvements to the admin payment token editor.
* Fix - Stop showing the conflicting connection settings notice when a store is correctly configured with manual API credentials and only an unused OAuth token remains.
* Dev - Upgrade SkyVerge Framework from 6.2.1 to 6.2.4.
* Dev - Update the E2E test setup to seed subscription products using the WooCommerce Subscriptions 9.0 purchase-options model.
* Dev - Bump WordPress "Tested up to" to 7.1.
* Dev - Bump WooCommerce "tested up to" version 11.1.
* Dev - Bump WooCommerce minimum supported version to 10.9.
* Dev - Update the PHP_CodeSniffer development dependency to 3.13.6.

= 3.11.1 - 2026-08-05 =
* Fix - Credit Card 3DS no longer triggers with validation errors on the block checkout page.
* Fix - Prevent a warning on the block checkout caused by the classic Fastlane script loading when it shouldn't.
* Fix - Warn merchants when PayPal Enterprise Payments connection settings are in a conflicting state after a database sync.
* Fix - Show a non-dismissible admin notice when previously stored OAuth credentials are missing, and ask merchants to contact support to reconnect.
* Dev - Upgrade SkyVerge Framework from 6.0.1 to 6.2.1.
* Dev - Bump WooCommerce "tested up to" version 11.0.
* Dev - Bump WooCommerce minimum supported version to 10.8.
* Dev - Bump WordPress minimum supported version to 6.9.
* Dev - Remove stub cart_contains_subscription() method in WC_Gateway_Braintree_Venmo.
* Dev - Update the Apple Pay, Credit Card, PayPal, and Venmo block checkout integrations to use current WooCommerce Blocks checkout hooks instead of deprecated ones.
* Dev - Miscellaneous improvements to PHPUnit tests.
* Dev - Refactor E2E workflow to use matrix strategy.
* Dev - Upgrade Node.js from v20 to v24 and npm from v10 to v11.
* Dev - Update Composer dependencies to modernize developer experience.
* Dev - Add a spell-check GitHub Actions workflow and fix typos.
* Dev - Update WPCS to 3.4.1 to pick up the fix for GHSA-3pwp-g2mj-5p3v.

= 3.11.0 - 2026-06-15 =
* Update - Rebrand the plugin title and related display surfaces from "Braintree" to "PayPal Enterprise Payments".
* Tweak - Improved error messages for local payment methods.
* Tweak - Improve the UI for test-mode payment methods in checkout pages.
* Fix - Ensure Local Payment Methods work properly on the pay-for-order page.
* Fix - Hide Google Pay express button when Google Pay is disabled on the merchant's Braintree account.
* Dev - Move the credentials inheritance settings JavaScript from inline PHP to a standalone admin script for improved maintainability.
* Dev - Bump WooCommerce "tested up to" version 10.8.
* Dev - Bump WooCommerce minimum supported version to 10.6.
* Dev - Add additional E2E tests for better coverage.

= 3.10.0 - 2026-04-22 =
* Add - Introduce `wc_braintree_get_remote_configuration` filter to allow merchants to provide hardcoded remote configurations and improve performance with gateway configuration caching.
* Fix - Apple Pay and Google Pay buttons on block-based Cart and Checkout pages now respect the "Allow on" display settings.
* Fix - Change the gateway currency notice to include a link to the gateway settings.
* Fix - Replaced `wc_enqueue_js` with `wp_add_inline_script` according to the recommended WordPress core script patterns.
* Dev - Replace deep relative imports with webpack aliases for improved readability and maintainability.
* Dev - Remove the deprecated LPM feature-flag check from Blocks checkout registration.
* Dev - Fix ESLint import resolver configuration and fix multiple ESLint violations.
* Dev - Bump WooCommerce "tested up to" version 10.7.
* Dev - Bump WooCommerce minimum supported version to 10.5.
* Dev - Bump WordPress "Tested up to" to 7.0.
* Dev - Add additional E2E tests for better coverage.

= 3.9.0 - 2026-03-30 =
* Add - EPS local payment gateway
* Add - iDEAL local payment gateway.
* Add - P24 local payment method gateway.
* Add - Bancontact local payment gateway.
* Add - BLIK local payment method gateway.
* Add - MyBank local payment gateway.
* Add - Make LPM gateways generally accessible.
* Add - WooCommerce Blocks checkout support for Local Payment Methods.
* Add - Show admin notice when a local payment method gateway is missing a Merchant Account ID for its supported currencies.
* Fix - Deprecation messages caused by dynamic properties in PHP 8.2.
* Dev - Upgrade SkyVerge Framework from 5.15.10 to 6.0.1.
* Dev - Add compatibility for PHP 8.4.
* Dev - Bump WooCommerce "tested up to" version 10.6.
* Dev - Bump WooCommerce minimum supported version to 10.4.
* Dev - Bump Wordpress minimum supported version to 6.8.
* Dev - Centralize braintree-js-data-collector script registration.
* Dev - Add per-method Local Payment Method gateway infrastructure.
* Dev - Github workflow to run JS unit tests on each PR.
* Dev - Add additional E2E tests for better coverage.

= 3.8.0 - 2026-03-03 =
* Add - Make ACH gateway generally accessible.
* Add - Make Fastlane generally available without requiring the early access toggle.
* Add - Support for ACH in blocks checkout.
* Add - Fastlane support to blocks checkout.
* Add - Style extraction for Fastlane card component to match the active theme's checkout styling.
* Fix - "View Transaction Details" button functionality when using legacy order screen.
* Fix - Reduce L3 cooldown period from 3 months to 1 day and add `wc_braintree_level3_bank_declined_cooldown_window` filter.
* Fix - Add a guard to prevent `assert()` failures when rendering checkout.
* Fix - Resolve "_doing_it_wrong" notice caused by direct order property access during credit card transactions.
* Fix - Show standard shipping fields when a Fastlane member has no saved shipping address.
* Fix - Show card input fields when a Fastlane member has no saved cards in their profile.
* Fix - Update build code to ensure all translatable strings are included.
* Update - Remove email confirmation modal for autofilled emails in Fastlane checkout.
* Tweak - keep billing fields visible when Fastlane returns incomplete address data.
* Dev - Extract shared Fastlane utility functions into a shared module for reuse across classic and blocks checkout.

= 3.7.0 - 2026-02-02 =
* Add - Make Venmo gateway generally available.
* Add - PayPal Fastlane integration for accelerated checkout on shortcode checkout pages.
* Add - Introduce a checkbox to enable Fastlane Early Access Payment method.
* Add - Email confirmation modal for Fastlane checkout when email is pre-filled.
* Add - Remove Fastlane feature flag.
* Add - ACH Direct Debit support for subscriptions.
* Add - Support for fetching account configuration data from Braintree.
* Add - Show a notice on the gateway settings page if gateway is not enabled in any available merchant account.
* Add - Merchant account ID dropdown to select a merchant account ID for the gateway based on the selected account configuration and currency.
* Add - Full mandate details for ACH.
* Add - ACH/SEPA webhook events handler for payment status updates.
* Update - Restrict manual connection settings to Credit Card and PayPal.
* Fix - Subscription renewals when using Fastlane.
* Fix - Improve compatibility with the Avatax plugin when using express checkouts.
* Fix - Prevent manual credential input on child gateways when no parent gateway credentials are configured.
* Fix - Shipping fields when using Fastlane with a product that doesn't require shipping.
* Fix - Limit Fastlane availability to only the guest shoppers.
* Fix - Billing name not being prefilled when authenticating as a Fastlane member.
* Fix - Preserve Fastlane address field edit mode across WooCommerce checkout updates.
* Fix - Show a better description for subscriptions being paid using ACH.
* Fix - Add some missing PHP direct access checks.
* Dev - Bump WooCommerce "tested up to" version 10.5.
* Dev - Bump WooCommerce minimum supported version to 10.3.
* Dev - Upgrade woocommerce/plugin-check-action to v1.1.5.
* Dev - Automatic formatting on pre-commit.
* Dev - Format codebase with wp-scripts.

[See changelog for all versions](https://plugins.svn.wordpress.org/woocommerce-gateway-paypal-powered-by-braintree/trunk/changelog.txt).
