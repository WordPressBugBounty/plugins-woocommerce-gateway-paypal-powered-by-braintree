=== Braintree for WooCommerce Payment Gateway ===
Contributors: woocommerce, automattic, skyverge
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal, braintree
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.2.7
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Accept PayPal, Credit Cards, and Debit Cards on your WooCommerce store.

== Description ==

Accept **all major cards, Apple Pay**, and **PayPal** directly with PayPal Braintree for WooCommerce. Customers can save their card details or link a PayPal account for an even faster checkout experience.

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

Take charge of how you sell online. PayPal Braintree supports [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) — the perfect solution for earning **recurring revenue**. It's also compatible with [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/), enabling you to accept payment **upfront** or as products ship.

== Frequently Asked Questions ==

= Where can I find documentation? =

You’ve come to the right place. [Our documentation](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/) for PayPal Braintree for WooCommerce includes detailed setup instructions, troubleshooting tips, and more.

= Does this extension work with credit cards, or just PayPal? =

Both! PayPal Braintree for WooCommerce supports payments with credit cards and PayPal. (You can also [enable Apple Pay](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#support-apple-pay).)

= Does it support subscriptions? =

Yes! PayPal Braintree supports tokenization (required for recurring payments) and is compatible with [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/).

= Which currencies are supported? =

Support is available for 25 currencies, [wherever Braintree is available](https://www.paypal.com/us/webapps/mpp/country-worldwide). You can use your store’s native currency or add multiple merchant IDs to process other currencies via different Braintree accounts. To manage multiple currencies, you’ll need a free or paid **currency switcher**, such as [Aelia Currency Switcher](https://aelia.co/shop/currency-switcher-woocommerce/) (requires purchase).

= Can non-US merchants use this extension? =

Yes! It’s supported in [all countries where Braintree is available](https://www.paypal.com/us/webapps/mpp/country-worldwide).

= Does it support testing and production modes? =

Yes; sandbox mode is available so you can test the payment process without activating live transactions. Woo-hoo!

= Credit card payments are working, but PayPal is not — why? =

You may need to [enable PayPal in your Braintree account](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#my-credentials-are-correct-but-i-still-dont-see-paypal-at-checkout-whats-going-on).

= Can I use this extension for PayPal only? =

Sure thing! See our instructions on [using PayPal Braintree without credit cards](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#using-paypal-without-credit-cards).

= Will it work with my site’s theme? =

This extension should work with any WooCommerce-compatible theme, but you might need to [customize your theme](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#theme-issues) for a perfect fit.

= Where can I get support, report bugs, or request new features? =

First, [review our documentation](https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/) for troubleshooting tips and answers to common questions. If you need further assistance, please get in touch via the [official support forum](https://wordpress.org/support/plugin/woocommerce-gateway-paypal-powered-by-braintree/).

== Screenshots ==

1. Enter Braintree credentials
2. Credit card gateway settings
3. Advanced credit card gateway settings
4. PayPal gateway settings
5. Checkout with PayPal directly from the cart
6. Checkout with PayPal directly from the product page

== Changelog ==

= 3.2.7 - 2025-04-14 =
* Fix - PayPal payment issue in Woo 9.8 Block Checkout.
* Dev - Bump WooCommerce "tested up to" version 9.8.
* Dev - Bump WooCommerce minimum supported version to 9.6.
* Dev - Bump WordPress "tested up to" version 6.8.
* Dev - Update all third-party actions our workflows rely on to use versions based on specific commit hashes.
* Dev - Disabled warning checks from WordPress Plugin Check Action.

= 3.2.6 - 2025-02-18 =
* Fix - Ensure payment methods display the correct buttons and statuses in the new WooCommerce Payments settings.
* Dev - Bump WooCommerce "tested up to" version 9.7.
* Dev - Bump WooCommerce minimum supported version to 9.5.
* Dev - Bump WordPress minimum supported version to 6.6.
* Dev - Refresh WPORG assets and readme copy.
* Dev - Add the WordPress Plugin Check GitHub Action.

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
