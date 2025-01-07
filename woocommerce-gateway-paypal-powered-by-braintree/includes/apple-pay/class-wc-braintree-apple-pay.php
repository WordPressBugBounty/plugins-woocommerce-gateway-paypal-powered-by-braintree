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

namespace WC_Braintree;

use SkyVerge\WooCommerce\PluginFramework\v5_12_7 as Framework;

defined( 'ABSPATH' ) || exit;

/**
 * The Braintree Apple Pay base handler.
 *
 * @since 2.2.0
 */
class Apple_Pay extends Framework\SV_WC_Payment_Gateway_Apple_Pay {


	/**
	 * Initializes the frontend handler.
	 *
	 * @since 2.2.0
	 */
	protected function init_frontend() {

		$this->frontend = new Apple_Pay\Frontend( $this->get_plugin(), $this );
		// Runs at priority 11 to ensure that the button is moved after the framework's init fires.
		add_action( 'wp', array( $this, 'post_init' ), 11 );
	}

	/**
	 * Modify Apple Pay button after framework has been initialized.
	 *
	 * Moves the Apple Pay button to the new location following the framework's initialization.
	 * As the framework uses a protected function for determining the locations in which buttons
	 * are displayed, we determine whether it has registered the actions and move them if required.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-paypal-powered-by-braintree/pull/535
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
	 * Enqueues assets for the Apple Pay button CSS.
	 *
	 * @since 3.2.2
	 */
	public function enqueue_assets() {
		if ( 'yes' !== get_option( 'sv_wc_apple_pay_enabled' ) ) {
			return;
		}

		$css_path = $this->get_plugin()->get_plugin_path() . '/assets/css/frontend/wc-apply-pay.min.css';
		$version  = $this->get_plugin()->get_assets_version();

		if ( is_readable( $css_path ) ) {
			$css_url = $this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-apply-pay.min.css';

			wp_enqueue_style( 'wc-braintree-apply-pay', $css_url, array(), $version );
		}
	}

	/**
	 * Gets a single product payment request.
	 *
	 * @since 3.2.0
	 * @see SV_WC_Payment_Gateway_Apple_Pay::build_payment_request()
	 *
	 * @param \WC_Product $product product object.
	 * @param bool        $in_cart whether to generate a cart for this request.
	 * @return array
	 *
	 * @throws Framework\SV_WC_Payment_Gateway_Exception For active pre-orders products.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception For unsupported product types.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception For products that cannot be purchased.
	 */
	public function get_product_payment_request( \WC_Product $product, $in_cart = false ) {

		if ( ! is_user_logged_in() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		// no pre-order "charge upon release" products.
		if ( $this->get_plugin()->is_pre_orders_active() && \WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Not available for pre-order products that are set to charge upon release.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		// only simple and subscription products.
		if ( ! ( $product->is_type( 'simple' ) || $product->is_type( 'subscription' ) ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Buy Now is only available for simple and subscription products', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		// if this product can't be purchased, bail.
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->has_enough_stock( 1 ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Product is not available for purchase.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		if ( $in_cart ) {

			WC()->cart->empty_cart();

			WC()->cart->add_to_cart( $product->get_id() );

			$request = $this->get_cart_payment_request( WC()->cart );

		} else {

			$request = $this->build_payment_request( $product->get_price(), array( 'needs_shipping' => $product->needs_shipping() ) );

			$stored_request = $this->get_stored_payment_request();

			$stored_request['product_id'] = $product->get_id();

			$this->store_payment_request( $stored_request );
		}

		/**
		 * Filters the Apple Pay Buy Now JS payment request.
		 *
		 * @since 3.2.0
		 * @param array $request request data
		 * @param \WC_Product $product product object
		 */
		return apply_filters( 'wc_braintree_apple_pay_buy_now_payment_request', $request, $product );
	}

	/**
	 * Gets a payment request based on WooCommerce cart data.
	 *
	 * @since 3.2.0
	 * @see SV_WC_Payment_Gateway_Apple_Pay::build_payment_request()
	 *
	 * @param \WC_Cart $cart cart object.
	 * @return array
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If cart contains pre-orders.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If cart contains multiple shipments.
	 */
	public function get_cart_payment_request( \WC_Cart $cart ) {

		if ( $this->get_plugin()->is_pre_orders_active() && \WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Cart contains pre-orders.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$cart->calculate_totals();

		if ( count( WC()->shipping->get_packages() ) > 1 ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Apple Pay cannot be used for multiple shipments.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$args = array(
			'line_totals'    => $this->get_cart_totals( $cart ),
			'needs_shipping' => $cart->needs_shipping(),
		);

		// build it!
		$request = $this->build_payment_request( $cart->total, $args );

		/**
		 * Filters the Apple Pay cart JS payment request.
		 *
		 * @since 3.2.0
		 * @param array $args the cart JS payment request
		 * @param \WC_Cart $cart the cart object
		 */
		return apply_filters( 'wc_braintree_apple_pay_cart_payment_request', $request, $cart );
	}

	/**
	 * Processes the payment after an Apple Pay authorization.
	 *
	 * This method creates a new order and calls the gateway for processing.
	 *
	 * @since 3.2.0
	 *
	 * @return array
	 * @throws \Exception When Apple Payment fails.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception For invalid response data.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception When there is a gatway processing error.
	 */
	public function process_payment() {

		$order = null;

		try {

			$payment_response = $this->get_stored_payment_response();

			if ( ! $payment_response ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( 'Invalid payment response data' );
			}

			$this->log( "Payment Response:\n" . $payment_response->to_string_safe() . "\n" );

			$order = Framework\Payment_Gateway\External_Checkout\Orders::create_order( WC()->cart, array( 'created_via' => 'apple_pay' ) );

			$order->set_payment_method( $this->get_processing_gateway() );

			$order->add_order_note( __( 'Apple Pay payment authorized.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );

			$order->set_address( $payment_response->get_billing_address(), 'billing' );
			$order->set_address( $payment_response->get_shipping_address(), 'shipping' );

			$order->save();

			if ( class_exists( '\WC_Subscriptions_Checkout' ) ) {
				\WC_Subscriptions_Checkout::process_checkout( $order->get_id() );
			}

			// add Apple Pay response data to the order.
			add_filter( 'wc_payment_gateway_' . $this->get_processing_gateway()->get_id() . '_get_order', array( $this, 'add_order_data' ) );

			$result = $this->get_processing_gateway()->process_payment( $order->get_id() );

			if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( 'Gateway processing error.' );
			}

			$user_id = $order->get_user_id();

			if ( $user_id ) {
				$this->update_customer_addresses( $user_id, $payment_response );
			}

			$this->clear_payment_data();

			return $result;

		} catch ( \Exception $e ) {

			if ( $order ) {

				$order->add_order_note(
					sprintf(
						/* translators: Placeholders: %s - the error message */
						__( 'Apple Pay payment failed. %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						$e->getMessage()
					)
				);
			}

			throw $e;
		}
	}

	/**
	 * Builds a new payment request.
	 *
	 * Overridden to remove some properties that are set by Braintree from account configuration.
	 *
	 * @since 2.2.0
	 *
	 * @param float|int $amount payment amount.
	 * @param array     $args payment args.
	 * @return array
	 */
	public function build_payment_request( $amount, $args = array() ) {

		$request = parent::build_payment_request( $amount, $args );

		// these values are populated by the Braintree SDK.
		unset(
			$request['currencyCode'],
			$request['countryCode'],
			$request['merchantCapabilities'],
			$request['supportedNetworks']
		);

		return $request;
	}


	/**
	 * Builds a payment response object based on an array of data.
	 *
	 * @since 2.2.0
	 *
	 * @param string $data response data as a JSON string.
	 *
	 * @return Apple_Pay\API\Payment_Response
	 */
	protected function build_payment_response( $data ) {

		return new Apple_Pay\API\Payment_Response( $data );
	}


	/**
	 * Determines if a local Apple Pay certificate is required.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function requires_certificate() {

		return false;
	}


	/**
	 * Determines if a merchant ID is required.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function requires_merchant_id() {

		return false;
	}
}
