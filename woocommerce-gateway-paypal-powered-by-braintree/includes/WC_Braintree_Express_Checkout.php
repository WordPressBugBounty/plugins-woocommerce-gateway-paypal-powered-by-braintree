<?php
/**
 * WooCommerce Braintree Gateway
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Braintree Gateway to newer
 * versions in the future. If you wish to customize WooCommerce Braintree Gateway for your
 * needs please refer to http://docs.woocommerce.com/document/braintree/
 *
 * @package   WC-Braintree
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use WC_Subscriptions_Cart;
use WC_Subscriptions_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Trait for Braintree Express Checkout.
 *
 * @since 3.4.0
 */
trait WC_Braintree_Express_Checkout {

	/**
	 * Modify the Express Checkout button after framework has been initialized.
	 *
	 * Moves the Express Checkout (ApplePay, GooglePay, etc.) button to the new location following the framework's initialization.
	 * As the framework uses a protected function for determining the locations in which buttons
	 * are displayed, we determine whether it has registered the actions and move them if required.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-paypal-powered-by-braintree/pull/535
	 *
	 * @since 3.4.0
	 */
	public function post_init() {
		if ( has_action( 'woocommerce_before_add_to_cart_button', array( $this->frontend, 'maybe_render_external_checkout' ) ) ) {
			remove_action( 'woocommerce_before_add_to_cart_button', array( $this->frontend, 'maybe_render_external_checkout' ) );
			add_action( 'woocommerce_after_add_to_cart_button', array( $this->frontend, 'maybe_render_external_checkout' ) );
		}

		if ( has_action( 'woocommerce_proceed_to_checkout', array( $this->frontend, 'maybe_render_external_checkout' ) ) ) {
			remove_action( 'woocommerce_proceed_to_checkout', array( $this->frontend, 'maybe_render_external_checkout' ) );
			add_action( 'woocommerce_proceed_to_checkout', array( $this->frontend, 'maybe_render_external_checkout' ), 30 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}


	/**
	 * Checks if an account should be created for a guest user.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	protected function should_create_account_for_guest(): bool {
		if ( is_user_logged_in() ) {
			return false;
		}

		// Check if the subscription plugin is active and the cart contains a subscription.
		if ( ! $this->get_plugin()->is_subscriptions_active() || ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		// Re-run the frontend checks.
		return $this->frontend->can_create_account_for_guest();
	}
}
