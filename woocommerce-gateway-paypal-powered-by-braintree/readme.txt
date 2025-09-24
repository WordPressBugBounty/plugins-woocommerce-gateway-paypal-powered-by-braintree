=== Braintree for WooCommerce Payment Gateway ===
Contributors: woocommerce, automattic, skyverge
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal, braintree
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.4.1
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

= 3.4.1 - 2025-09-24 =
* Fix - Error when placing orders with 3DS disabled on the Braintree account.
* Fix - Credit Card payments fail with Zero-Amount line items.

= 3.4.0 - 2025-09-15 =
* Add - Automatic customer account creation for subscription purchases through Apple Pay when the user is not logged in.
* Add - Ability to view transaction data in order page.
* Add - Include level 2 and level 3 data when creating transactions.
* Dev - Bump WooCommerce "tested up to" version 10.2.
* Dev - Bump WooCommerce minimum supported version to 10.0.

= 3.3.0 - 2025-09-08 =
* Add - Handle incoming webhook events.
* Add - Implement a feature flag system to enable controlled rollout of new features.
* Fix - Limit the Apple Pay `displayName` (store name) to a maximum of 64 characters to comply with Apple’s requirements.
* Fix - Remove duplicate HTML element IDs.
* Fix - Accessibility: Improve display of credit card field labels to avoid cropping.
* Fix - Disabled `failOnDuplicatePaymentMethod` in sandbox mode.
* Dev - Bump WooCommerce "tested up to" version 10.1.
* Dev - Bump WooCommerce minimum supported version to 9.9.
* Dev - Bump WordPress minimum supported version to 6.7.
* Dev - Add PHPUnit test setup and run PHPUnit tests for every PR.
* Dev - Update the dev setup instructions in the README.
* Dev - Fix e2e test Github Actions workflow.
* Dev - Add pre-commit hooks to enforce phpcs rules.

= 3.2.9 - 2025-07-14 =
* Add - Consent checkbox for vaulting Apple Pay cards for Subscription products.
* Fix - Ensure that 'Pay with PayPal' functions properly in Block Checkout when using the Safari browser.
* Dev - Applied PHPCS auto-fixes and documentation comment updates.
* Dev - Bump WooCommerce "tested up to" version 10.0.
* Dev - Bump WooCommerce minimum supported version to 9.8.

= 3.2.8 - 2025-06-25 =
* Fix - Upgrade SkyVerge Framework from 5.12.7 to 5.15.10 which helps resolve a few fatal errors around Subscriptions.
* Fix - Correct HTML markup of Dynamic Descriptors section on settings page.
* Fix - JavaScript error on block cart and checkout pages.
* Dev - Bump WooCommerce "tested up to" version 9.9.
* Dev - Bump WooCommerce minimum supported version to 9.7.
* Dev - Ensure JavaScript dependencies are correct.

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
