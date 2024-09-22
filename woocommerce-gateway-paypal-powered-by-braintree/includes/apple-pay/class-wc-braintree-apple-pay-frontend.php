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
 * @package   WC-Braintree/Gateway/Credit-Card
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Apple_Pay;

use SkyVerge\WooCommerce\PluginFramework\v5_12_0 as Framework;

defined( 'ABSPATH' ) || exit;

/**
 * The Braintree Apple Pay frontend handler.
 *
 * @since 2.2.0
 */
class Frontend extends Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend {


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_Apple_Pay_Handler';
	}


	/**
	 * Enqueues the scripts.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend::enqueue_scripts()
	 *
	 * @since 2.2.0
	 */
	public function enqueue_scripts() {

		parent::enqueue_scripts();

		// braintree.js library.
		wp_enqueue_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . \WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', array(), \WC_Braintree::VERSION, true );

		wp_enqueue_script( 'braintree-js-apple-pay', 'https://js.braintreegateway.com/web/' . \WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/apple-pay.min.js', array( 'braintree-js-client' ), \WC_Braintree::VERSION, true );

		wp_enqueue_script( 'wc-braintree-apple-pay-js', $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-apple-pay.min.js', array( 'jquery' ), $this->get_plugin()->get_version(), true );
	}


	/**
	 * Gets the parameters to be passed to the JS handler.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend::get_js_handler_args()
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	protected function get_js_handler_args() {

		$params = parent::get_js_handler_args();

		$params['store_name']         = get_bloginfo( 'name' );
		$params['client_token_nonce'] = wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_get_client_token' );
		$params['force_tokenization'] = $this->is_tokenization_forced();

		return $params;
	}


	/**
	 * Determines if tokenization should be forced for Digital Wallets
	 * depending on the page on which they're used.
	 *
	 * @since 3.2.0
	 *
	 * @return boolean
	 */
	protected function is_tokenization_forced() {
		$product = wc_get_product();

		if ( ! $this->get_plugin()->is_subscriptions_active() ) {
			return false;
		}

		// Check if page is single product page and product type is subscription.
		if ( is_product() && $product && \WC_Subscriptions_Product::is_subscription( $product ) ) {
			return true;
		}

		if ( ( is_cart() || is_checkout() ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		return false;
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
		if ( ! is_user_logged_in() ) {
			return parent::should_init_on_product_page( $locations ) && ! $this->is_tokenization_forced();
		}

		return parent::should_init_on_product_page( $locations );
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
		if ( ! is_user_logged_in() ) {
			return parent::should_init_on_cart_page( $locations ) && ! $this->is_tokenization_forced();
		}

		return parent::should_init_on_cart_page( $locations );
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
		if ( ! is_user_logged_in() ) {
			return parent::should_init_on_checkout_page( $locations ) && ! $this->is_tokenization_forced();
		}

		return parent::should_init_on_checkout_page( $locations );
	}
}
