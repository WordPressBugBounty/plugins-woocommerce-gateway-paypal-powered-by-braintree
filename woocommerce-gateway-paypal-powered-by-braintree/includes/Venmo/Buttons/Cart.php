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
 * @package   WC-Braintree/Buttons
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Venmo\Buttons;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Gateway_Braintree_Venmo;

defined( 'ABSPATH' ) || exit;

/**
 * Cart page button class.
 *
 * @since 3.5.0
 */
class Cart extends Abstract_Button {


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_Venmo_Cart_Handler';
	}


	/**
	 * Checks if this button should be enabled or not.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	protected function is_enabled() {

		return $this->get_gateway()->cart_checkout_enabled();
	}


	/**
	 * Adds any actions and filters needed for the button.
	 *
	 * @since 3.5.0
	 */
	protected function add_button_hooks() {

		parent::add_button_hooks();

		// Initialize cart-specific hooks on the wp action.
		add_action( 'wp', [ $this, 'init_cart' ] );

		// enqueue cart button scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_cart_scripts' ] );
	}


	/**
	 * Initializes the cart page button.
	 *
	 * @since 3.5.0
	 */
	public function init_cart() {

		if ( ! is_cart() ) {
			return;
		}

		// Render Venmo button after other express checkout buttons (priority 20).
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'render' ], 20 );
	}


	/**
	 * Enqueues cart button scripts.
	 *
	 * @since 3.5.0
	 */
	public function enqueue_cart_scripts() {

		if ( ! is_cart() ) {
			return;
		}

		$this->enqueue_scripts();

		// Enqueue custom cart button handler.
		wp_enqueue_script(
			'wc-braintree-venmo-cart',
			$this->get_gateway()->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-venmo-cart.min.js',
			array( 'jquery', 'braintree-js-client', 'braintree-js-venmo', 'braintree-js-data-collector' ),
			\WC_Braintree\WC_Braintree::VERSION,
			true
		);
	}


	/**
	 * Validates the WC API request.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	protected function is_wc_api_request_valid() {

		return (bool) wp_verify_nonce( Framework\SV_WC_Helper::get_posted_value( 'wp_nonce' ), 'wc_' . $this->get_gateway()->get_id() . '_cart_set_payment_method' );
	}


	/**
	 * Gets the total amount the button should charge.
	 *
	 * @since 3.5.0
	 *
	 * @return float
	 */
	protected function get_button_total() {

		return WC()->cart->total;
	}


	/**
	 * Gets any additional JS handler params needed for this button.
	 *
	 * @since 3.6.0
	 *
	 * @return array
	 */
	protected function get_additional_js_handler_params() {

		$payment_usage = $this->get_gateway()->cart_contains_subscription() ? WC_Gateway_Braintree_Venmo::PAYMENT_METHOD_USAGE_MULTI : WC_Gateway_Braintree_Venmo::PAYMENT_METHOD_USAGE_SINGLE;

		return [
			'payment_usage' => $payment_usage,
		];
	}


	/**
	 * Gets the ID of this script handler.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_id() {

		return $this->get_gateway()->get_id() . '_cart';
	}
}
