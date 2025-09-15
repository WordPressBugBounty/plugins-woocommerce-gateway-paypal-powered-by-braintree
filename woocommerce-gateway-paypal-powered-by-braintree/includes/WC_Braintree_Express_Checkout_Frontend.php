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
 * Trait for Braintree Express Checkout Frontend
 *
 * @since 3.4.0
 */
trait WC_Braintree_Express_Checkout_Frontend {

	/**
	 * Determines if tokenization should be forced for Digital Wallets
	 * depending on the page on which they're used.
	 *
	 * @since 3.2.0
	 *
	 * @return boolean
	 */
	protected function is_tokenization_forced(): bool {
		if ( ! $this->get_plugin()->is_subscriptions_active() ) {
			return false;
		}

		if ( ( is_cart() || is_checkout() ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		$product = wc_get_product();

		// Check if page is single product page and product type is subscription.
		if ( is_product() && $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if guest accounts can be created during checkout for subscriptions.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function can_create_account_for_guest() {
		// Check if WooCommerce allows guest checkout.
		if ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
			return false;
		}

		// Check if subscription customers can create accounts during checkout.
		return (
			'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) ||
			'yes' === get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' )
		);
	}

	/**
	 * Determines if the external checkout should be allowed for guest users.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	protected function should_allow_guest_checkout(): bool {
		// If tokenization is not forced, allow checkout.
		if ( ! $this->is_tokenization_forced() ) {
			return true;
		}

		// If tokenization is forced but guest accounts can be created, allow checkout.
		return $this->can_create_account_for_guest();
	}

	/**
	 * Determines if the external checkout frontend should be initialized on a product page.
	 *
	 * @since 3.2.0
	 *
	 * @param array $locations configured display locations.
	 * @return bool
	 */
	protected function should_init_on_product_page( $locations = array() ): bool {
		// Always allow for logged-in users.
		if ( is_user_logged_in() ) {
			return parent::should_init_on_product_page( $locations );
		}

		// For guest users, check if we should allow checkout.
		return $this->should_allow_guest_checkout() && parent::should_init_on_product_page( $locations );
	}

	/**
	 * Determines if the external checkout frontend should be initialized on a cart page.
	 *
	 * @since 3.2.0
	 *
	 * @param array $locations configured display locations.
	 * @return bool
	 */
	protected function should_init_on_cart_page( $locations = array() ): bool {
		// Always allow for logged-in users.
		if ( is_user_logged_in() ) {
			return parent::should_init_on_cart_page( $locations );
		}

		// For guest users, check if we should allow checkout.
		return $this->should_allow_guest_checkout() && parent::should_init_on_cart_page( $locations );
	}

	/**
	 * Determines if the external checkout frontend should be initialized on a checkout page.
	 *
	 * @since 3.2.0
	 *
	 * @param array $locations configured display locations.
	 * @return bool
	 */
	protected function should_init_on_checkout_page( $locations = array() ): bool {
		// Always allow for logged-in users.
		if ( is_user_logged_in() ) {
			return parent::should_init_on_checkout_page( $locations );
		}

		// For guest users, check if we should allow checkout.
		return $this->should_allow_guest_checkout() && parent::should_init_on_checkout_page( $locations );
	}
}
