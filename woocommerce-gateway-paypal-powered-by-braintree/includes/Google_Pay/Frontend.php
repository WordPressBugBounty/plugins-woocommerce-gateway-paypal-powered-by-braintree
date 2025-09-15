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
 * @package   WC-Braintree/Gateway/Google-Pay
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Google_Pay;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree;
use WC_Braintree\WC_Braintree_Express_Checkout_Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Google Pay Frontend Handler
 *
 * @since 3.4.0
 */
class Frontend extends Framework\Payment_Gateway\External_Checkout\Google_Pay\Frontend {

	use WC_Braintree_Express_Checkout_Frontend;

	/**
	 * Gets the JavaScript handler class name.
	 *
	 * @since 3.4.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {
		return 'WC_Braintree_Google_Pay_Handler';
	}


	/**
	 * Enqueues the Google Pay scripts.
	 *
	 * @since 3.4.0
	 */
	public function enqueue_scripts() {
		parent::enqueue_scripts();

		wp_register_script( 'google-pay-js', 'https://pay.google.com/gp/p/js/pay.js', array(), WC_Braintree::VERSION, true );

		// braintree.js library.
		wp_register_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', array( 'google-pay-js' ), WC_Braintree::VERSION, true );

		// Braintree-specific Google Pay scripts.
		wp_register_script( 'braintree-js-google-pay', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/google-payment.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );

		wp_register_script( 'wc-braintree-google-pay-js', $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-google-pay.min.js', array( 'jquery', 'braintree-js-google-pay' ), $this->get_plugin()->get_version(), true );

		if ( ! parent::should_enqueue_scripts() ) {
			return;
		}

		// Enqueue the JS handler.
		wp_enqueue_script( 'wc-braintree-google-pay-js' );
	}


	/**
	 * Gets the JavaScript handler arguments.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	protected function get_js_handler_args() {
		$args = parent::get_js_handler_args();

		$gateway = $this->get_plugin()->get_gateway( WC_Braintree::CREDIT_CARD_GATEWAY_ID );
		$sdk     = $gateway->get_sdk();

		$args['store_name']                     = get_bloginfo( 'name' );
		$args['force_tokenization']             = $this->is_tokenization_forced();
		$args['braintree_client_authorization'] = $sdk->clientToken()->generate();

		return $args;
	}
}
