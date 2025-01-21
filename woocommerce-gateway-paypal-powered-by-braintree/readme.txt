=== Braintree for WooCommerce Payment Gateway ===
Contributors: woocommerce, automattic, skyverge
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal, braintree
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 3.2.5
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept PayPal, Credit Cards, and Debit Cards on your WooCommerce store.

== Description ==

The Braintree for WooCommerce gateway lets you accept **credit cards and PayPal payments** on your WooCommerce store via Braintree. Customers can save their credit card details or link a PayPal account to their WooCommerce user account for fast and easy checkout.

With this gateway, you can **securely sell your products** online using Hosted Fields, which help you meet security requirements without sacrificing flexibility or an integrated checkout process. Hosted Fields, similar to iFrames, are hosted on PayPal's servers but fit inside the checkout form elements on your site, providing a **secure, seamless** means for customers to share their payment information.

Braintree for WooCommerce supports tokenization, letting your customers save their credit cards or connect their PayPal account for faster, easier subsequent checkouts. The gateway also supports <a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">WooCommerce Subscriptions</a> to let you sell products with recurring billing and <a href="https://woocommerce.com/products/woocommerce-pre-orders/" target="_blank">WooCommerce Pre-Orders</a>, which supports accepting payments for upcoming products as they ship or up-front but it's important to understand that enabling tokenization in the settings will create a billing agreement ID. This billing agreement ID is necessary for future transactions when tokenization is enabled. If a user does not intend to use the same payment method for future transactions, they can disable tokenization to avoid creating a billing agreement ID.

= Powering Advanced Payments =

Braintree for WooCommerce provides several advanced features for transaction processing and payment method management.

- Meets [PCI Compliance SAQ-A](https://www.pcisecuritystandards.org/documents/Understanding_SAQs_PCI_DSS_v3.pdf) standards
- Supports [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/), and [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/)
- Customers can securely save credit cards or link PayPal accounts to your site
- Easily process refunds, void transactions, and capture charges right from WooCommerce
- Route payments in different currencies to different Braintree accounts (requires currency switcher)
- Supports Braintree's [extensive suite of fraud tools](https://articles.braintreepayments.com/guides/fraud-tools/overview)
- Supports 3D Secure
- Includes express checkout options like Buy Now buttons on product pages and PayPal Connect buttons in the Cart
- ...and much more!

== Installation ==

= Minimum Requirements =

- PHP 7.4+ (you can see this under <strong>WooCommerce &gt; Status</strong>)</li>
- WooCommerce 8.1+
- WordPress 6.2+
- An SSL certificate
- cURL support (most hosts have this enabled by default)

= Installation =

[Click here for instructions on installing plugins on your WordPress site.](https://wordpress.org/support/article/managing-plugins/#installing-plugins) We recommend using automatic installation as the simplest method.

= Updating =

Automatic updates should work like a charm, though we do recommend creating a backup of your site before updating, just in case.

If you do encounter an issue after updating, you may need to flush site permalinks by going to **Settings > Permalinks** and clicking **Save Changes**. That will usually return things to normal!

== Frequently Asked Questions ==

= Where can I find documentation? =

Great question! [Click here to review Braintree for WooCommerce documentation.](https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/) This documentation includes detailed setup instructions and information about using the gateway's features.

= Does this plugin work with credit cards, or just PayPal? =

This plugin supports payments with credit cards and PayPal.

= Does this plugin support recurring payment, like for subscriptions? =

Yes! This plugin supports tokenization, which is required for recurring payments such as those created with [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/).

= What currencies are supported? =

This plugin supports all countries in which Braintree is available. You can use your native currency, or you can add multiple merchant IDs to process different currencies via different Braintree accounts. To use multi-currency, your site must use a **currency switcher** to adjust the order currency (may require purchase). We’ve tested this plugin with the [Aelia Currency Switcher](https://aelia.co/shop/currency-switcher-woocommerce/) (requires purchase).

= Can non-US merchants use this plugin? =

Yes! This plugin supports all countries where Braintree is available.

= Does this plugin support testing and production modes? =

Yes! This plugin includes a production and sandbox mode so you can test without activating live payments.

= Credit cards are working fine, but PayPal's not working. What's going on? =

It sounds like you may need to enable PayPal on your Braintree account. [Click here for instructions on enabling PayPal in your Braintree control panel.](https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#section-6)

= Can I use this plugin just for PayPal? =

Sure thing! [Click here for instructions on setting up this gateway to only accept PayPal payments.](https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree#section-10)

= Will this plugin work with my site's theme? =

Braintree for WooCommerce should work nicely with any WooCommerce compatible theme (such as [Storefront](http://www.woocommerce.com/storefront/)), but may require some styling for a perfect fit. For assistance with theme customization, please visit the [WooCommerce Codex](https://docs.woocommerce.com/documentation/plugins/woocommerce/woocommerce-codex/).

= Where can I get support, request new features, or report bugs? =

First, please [check out our plugin documentation](https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree) to see if that addresses any of your questions or concerns.

If not, please get in touch with us through the [plugin forums](https://wordpress.org/support/plugin/woocommerce-gateway-paypal-powered-by-braintree/)!

== Screenshots ==

1. Enter Braintree credentials
2. Credit card gateway settings
3. Advanced credit card gateway settings
4. PayPal gateway settings
5. Checkout with PayPal directly from the cart
6. Checkout with PayPal directly from the product page

== Changelog ==

= 3.2.5 - 2025-01-20 =
* Add - Request a cardholder challenge on all transactions.
* Dev - Bump WooCommerce "tested up to" version 9.6.
* Dev - Bump WooCommerce minimum supported version to 9.4.
* Dev - Use the `@woocommerce/e2e-utils-playwright` NPM package for E2E tests.

= 3.2.4 - 2025-01-06 =
* Fix - PHP 8.3 deprecation notices caused by the use of dynamic properties.
* Dev - Update Braintree SDK from 6.7.0 to 6.21.0.
* Dev - Update SkyVerge framework from 5.12.0 to 5.12.7.
* Dev - Bump WooCommerce "tested up to" version 9.5.
* Dev - Bump WooCommerce minimum supported version to 9.3.
* Dev - Resolve some E2E errors.

= 3.2.3 - 2024-11-25 =
* Dev - Bump WordPress "tested up to" version 6.7.

= 3.2.2 - 2024-10-28 =
* Fix - Fatal error processing admin subscription renewals when using legacy order storage.
* Fix - Apple Pay styling issues on Product and Cart pages.
* Fix - PayPal button width on the Product page.
* Tweak - Change "Buy with Apple Pay" text to "Subscribe with Apple Pay" for Subscription products.
* Dev - Bump WooCommerce "tested up to" version 9.4.
* Dev - Bump WooCommerce minimum supported version to 9.2.
* Dev - Bump WordPress minimum supported version to 6.5.
* Dev - Ensure that E2E tests pass in the latest WooCommerce version.

= 3.2.1 - 2024-09-23 =
* Fix - Update documentation link that was incorrect.
* Dev - Bump WooCommerce "tested up to" version 9.3.
* Dev - Bump WooCommerce minimum supported version to 9.1.

= 3.2.0 - 2024-08-13 =
* Add - Support for Apple Pay when purchasing Subscription products.
* Fix - Credit card input boxes not visible.
* Dev - Bump WooCommerce "tested up to" version 9.2.
* Dev - Bump WooCommerce minimum supported version to 9.0.

= 3.1.7 - 2024-07-22 =
* Dev - Bump WooCommerce "tested up to" version 9.1.
* Dev - Bump WooCommerce minimum supported version to 8.9.
* Dev - Bump WordPress minimum supported version to 6.4.
* Dev - Bump WordPress "tested up to" version 6.6.
* Dev - Update NPM packages and node version to v20 to modernize developer experience.
* Dev - Fix QIT E2E tests and add support for a few new test types.
* Dev - Exclude the Woo Comment Hook `@since` sniff.

= 3.1.6 - 2024-05-20 =
* Dev - Bump WooCommerce "tested up to" version 8.9.
* Dev - Bump WooCommerce minimum supported version to 8.7.

= 3.1.5 - 2024-03-25 =
* Dev - Bump WooCommerce "tested up to" version 8.7.
* Dev - Bump WooCommerce minimum supported version to 8.5
* Dev - Bump WordPress "tested up to" version 6.5.
* Dev - Update documentation around why billing agreements are being created for one-time purchases.
* Fix - Ensure that the order status updates to 'refunded' only once after a successful refund.
* Fix - Missing dependencies error on non-payment pages when advanced fraud tool is enabled.
* Fix - Make the error notice UI consistent with Block Cart/Checkout UI.

= 3.1.4 - 2024-03-11 =
* Tweak - Move PayPal buttons below "add to cart" button on product pages.
* Dev - Bump WooCommerce "tested up to" version 8.6.
* Dev - Bump WooCommerce minimum supported version to 8.4.
* Dev - Bump WordPress minimum supported version to 6.3.
* Fix - Saved payment methods no longer appear in the Block checkout when tokenization is disabled.

= 3.1.3 - 2024-02-05 =
* Add - Cart and Checkout block support for PayPal Express Checkout.
* Dev - Bump WooCommerce "tested up to" version 8.5.
* Dev - Bump WooCommerce minimum supported version to 8.3.
* Dev - Bump WordPress minimum supported version to 6.3.

= 3.1.2 - 2024-01-22 =
* Fix - Ensure correct functionality of dynamic descriptor name validation.

= 3.1.1 - 2024-01-09 =
* Dev - Declare compatibility with Product Editor.
* Dev - Declare compatibility with WooCommerce Blocks.
* Dev - Bump WooCommerce "tested up to" version 8.4.
* Dev - Bump WooCommerce minimum supported version to 8.2.
* Tweak - Bump PHP "tested up to" version 8.3.

= 3.1.0 - 2023-12-04 =
* Dev - Update PHPCS and PHPCompatibility GitHub Actions.
* Tweak - Admin settings colour to match admin theme colour scheme.

= 3.0.9 - 2023-11-20 =
* Dev - Added critical flows end-to-end tests.
* Dev - Bump Woocommerce "requires at least" 8.1.
* Dev - Bump Woocommerce "tested up to" version 8.3.
* Dev - Bump WordPress "tested up to" version 6.4.
* Dev - Bump WordPress minimum supported version to 6.2.

= 3.0.8 - 2023-10-30 =
* Fix - Ensure Braintree block checkout works with FSE themes.
* Fix - Prevent PHP warnings if no Credit Card logos are displayed.

= 3.0.7 - 2023-10-23 =
* Dev - Bump WooCommerce "tested up to" version 8.1.
* Dev - Bump WooCommerce minimum supported version to 7.9.
* Tweak - Bump `skyverge/wc-plugin-framework` from 5.10.15 to 5.11.8.
* Tweak - Bump minimum PHP version from 7.3 to 7.4.

= 3.0.6 - 2023-09-18 =
* Tweak - Payment method text for subscriptions via the PayPal button gateway.
* Dev - Bump WordPress "tested up to" version to 6.3.
* Dev - Bump WooCommerce "tested up to" version 7.9.
* Dev - Bump WooCommerce minimum supported version to 7.7.

= 3.0.5 - 2023-08-29 =
* Fix - Link to merchant account IDs documentation within the settings pages.

= 3.0.4 - 2023-07-25 =
* Fix - Check whether wc_get_notices function exists before using it.
* Dev - Add Playwright end-to-end tests.
* Dev - Bump Braintree SDK from 3.73.1 to 3.94.0.

= 3.0.3 - 2023-07-05 =
* Dev - Bump WooCommerce "tested up to" version 7.8.
* Dev - Bump WooCommerce minimum supported version from 6.8 to 7.2.
* Dev - Bump WordPress minimum supported version from 5.8 to 6.1.
* Dev - Ensure translations are properly defined.
* Dev - Remove deprecated class aliases for framework classes renamed in 2.4.0.
* Dev - Resolve coding standards issues.
* Fix - Admin can now save multiple merchant Account IDs.

= 3.0.2 - 2023-05-24 =
* Add – Support for Cart and Checkout blocks.
* Dev – Bump WooCommerce minimum supported version from 6.0 to 6.8.
* Dev – Bump WooCommerce “tested up to” version 7.4.
* Dev – Bump WooCommerce “tested up to” version 7.6.
* Dev – Bump WordPress minimum supported version from 5.6 to 5.8.
* Dev – Bump WordPress “tested up to” version 6.2.

= 3.0.1 - 2023-04-04 =
* Dev – Build with `Gulp` instead of using `skyverge/sake`

= 3.0.0 - 2023-03-16 =
- Dev - Bump WooCommerce "tested up to" version 7.3.0.
- Dev - Resolve linting issues.

= 2.9.1 - 2022-12-19 =
*  Added – Warning about Braintree payment method at User delete confirmation screen.
*  Fix – Don’t delete the payment method at Braintree if website is staging environment.
*  Fix – Billing address details do not get autofilled on the checkout page when using express checkout.
*  Update – Node version from v12 to v16.
*  Update – Npm version to v8.

= 2.9.0 - 2022-11-01 =
* Add – Support for High-performance Order Storage (“HPOS”).
* Add – Declare compatibility with High-Performance Order Storage (“HPOS”).
* Fix – Display more detailed error messages on checkout.

= 2.8.0 - 2022-10-12 =
* Add - Support for 3DS2 / EMV 3DS cards.
* Fix - Upgrade Braintree PHP SDK from v3.34.0 to v6.7.0.
* Tweak - Bump minimum WP version from 4.4 to 5.6.
* Tweak - Bump minimum PHP version from 5.4 to 7.3.
* Tweak - Bump minimum WC version from 3.0.9 to 6.0.
* Tweak - Bump WC tested up to version to 6.7.

= 2.7.0 - 2022-09-06 =
* Add - PayPal Pay Later support to buyers from Italy and Spain.

= 2.6.5 - 2022-06-14 =
* Tweak - Update development tools
* Tweak - Bump "WordPress tested up to" version to 6.0

= 2.6.4 - 2022-04-04 =
* Fix – Improve Subscriptions with WooCommerce Payments feature compatibility with Braintree (PayPal) Buttons
* Tweak – Fraud tools setting description improvements

= 2.6.3 - 2022-03-16 =
* Fix - is_ajax deprecation message
* Fix - URL for dynamic descriptors documentation in settings page
* Fix - Don't show "- OR -" if Apple Pay enabled but not available in current browser

= 2.6.2 - 2021-11-16 =
* Feature - Add support for disabling funding methods
* Feature - Allow updating of expiration dates for credit cards in 'My Account'
* Tweak - Update 'device data' capture inner workings

[See changelog for all versions](https://plugins.svn.wordpress.org/woocommerce-gateway-paypal-powered-by-braintree/trunk/changelog.txt).

== Upgrade Notice ==

= 2.1.0 =
* Feature - Upgrade to the latest Braintree JavaScript SDK for improved customer experience, reliability, and error handling

= 2.0.4 =
* Fix - Prevent a fatal error when completing pre-orders
* Fix - Prevent JavaScript errors when applying a 100%-off coupon at checkout

= 1.2.4 =
* Fix - Free subscription trials not allowed.
* Fix - Subscription recurring billing after free trial not working.
