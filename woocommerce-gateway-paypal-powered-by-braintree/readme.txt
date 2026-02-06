=== Braintree for WooCommerce Payment Gateway ===
Contributors: woocommerce, automattic, skyverge
Tags: ecommerce, e-commerce, commerce, woothemes, wordpress ecommerce, store, sales, sell, shop, shopping, cart, checkout, configurable, paypal, braintree
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.7.0
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
* Dev - Format codebase with wp-scipts.

= 3.6.0 - 2025-12-10 =
* Add - Venmo payment method support to the block checkout page.
* Add - Venmo payment method support to the block cart page
* Add - Subscription support for Venmo.
* Add - Admin notices for enabled gateways that don't support the current store currency.
* Add - Dynamic descriptor name support for Venmo gateway.
* Add - Adds filter `wc_braintree_is_level3_data_allowed` to disable adding Level3 in transaction the request.
* Update - Make Google Pay generally available.
* Fix - Venmo payment method label in the My Account subscriptions list.
* Fix - Apple Pay vaulting consent checkbox is shown when Apple Pay is unavailable.
* Fix - Prevent selecting unsupported shipping addresses in Apple Pay on shortcode checkout.
* Fix - Resolve Level 2/3 line item validation error for PayPal transactions with discounts in EUR stores.
* Fix - Hide Apple Pay and Google Pay tabs on non-Credit Card gateway settings.
* Fix - Editing saved non-credit-card payment methods.
* Fix - Early access gateway names in the Plugins page.
* Tweak - Don't show an error when the shopper closes the Venmo QR modal.
* Dev - Bump WordPress "tested up to" version 6.9.
* Dev - Bump WooCommerce "tested up to" version 10.4.
* Dev - Bump WooCommerce minimum supported version to 10.2.
* Dev - Extract common/shared classic checkout form handling code to a common base class.
* Dev - Fix ESLint configuration for plugin text domain and Braintree global.
* Dev - Add JavaScript unit testing runner pipeline.
* Dev - Enforce ESLint on new JS changes.

= 3.5.1 - 2025-11-18 =
* Fix - Fix missing assets in the 3.5.0 release package

= 3.5.0 - 2025-11-17 =

**Important Fixes and Updates**

* Add - Apple Pay express payment support for WooCommerce Blocks Cart and Checkout pages.
* Add - Show Webhook URL configuration information.
* Fix - Allow changing shipping method in the Apple Pay modal.
* Fix - Issue with empty `_billing_address_index` in `wp_postmeta` table when using Apple Pay.
* Fix - Fatal error when trying to register Blocks before the class is available.
* Dev - Bump WooCommerce "tested up to" version 10.3.
* Dev - Bump WooCommerce minimum supported version to 10.1.
* Dev - Update Braintree PHP SDK from 6.21.0 to 6.28.0.
* Dev - Update Braintree JS SDK from 3.94.0 to 3.129.1.

**Internal Changes and Early Access Features**

* Add - Add Early Access support for new payment methods (can be enabled on the WooCommerce Features page).
* Add - Early access support for Google Pay.
* Add - Early access support for Venmo.
* Add - Google Pay express payment support on product pages.
* Add - Google Pay express payment support for WooCommerce Cart and Checkout block pages.
* Add - Allow Google Pay to be used for Subscriptions.
* Add - Automatic customer account creation for subscription purchases through Google Pay when the user is not logged in.
* Tweak - Update Google Pay button height to match Apple Pay button.
* Tweak - Add support for using different logging severities and additional structured data.
* Dev - Prevent duplicate pages/products when setting up the test environment.
* Dev - Updates to our QIT GitHub Action workflow.
* Dev - Add new deploy workflow.

[See changelog for all versions](https://plugins.svn.wordpress.org/woocommerce-gateway-paypal-powered-by-braintree/trunk/changelog.txt).
