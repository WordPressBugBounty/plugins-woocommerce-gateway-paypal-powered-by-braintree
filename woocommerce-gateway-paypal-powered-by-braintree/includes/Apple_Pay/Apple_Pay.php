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

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\Apple_Pay\Frontend;
use WC_Braintree\Apple_Pay\API\Payment_Response;
use WC_Braintree\Integrations\AvaTax;
use WC_Braintree\WC_Braintree_Express_Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * The Braintree Apple Pay base handler.
 *
 * @since 2.2.0
 */
class Apple_Pay extends Framework\SV_WC_Payment_Gateway_Apple_Pay {

	use WC_Braintree_Express_Checkout;

	/**
	 * Initializes the frontend handler.
	 *
	 * @since 2.2.0
	 */
	protected function init_frontend() {

		$this->frontend = new Frontend( $this->get_plugin(), $this );
		// Runs at priority 11 to ensure that the button is moved after the framework's init fires.
		add_action( 'wp', array( $this, 'post_init' ), 11 );

		// Initialize session data for Blocks checkout when recalculating totals.
		add_action( 'wp_ajax_wc_' . $this->get_processing_gateway()->get_id() . '_apple_pay_recalculate_totals', array( $this, 'maybe_init_blocks_session' ), 5 );
		add_action( 'wp_ajax_nopriv_wc_' . $this->get_processing_gateway()->get_id() . '_apple_pay_recalculate_totals', array( $this, 'maybe_init_blocks_session' ), 5 );
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
	 * Initializes Apple Pay session data for Blocks checkout.
	 *
	 * When using WooCommerce Blocks checkout, the payment request data isn't initialized
	 * in the session like it is for classic checkout via Skyverge. This method ensures
	 * the session data is always fresh and matches the current cart state. This method
	 * is hooked to run at a higher priority than the AJAX handler that recalculates
	 * totals so that the data is available when needed.
	 *
	 * @since 3.5.0
	 */
	public function maybe_init_blocks_session() {
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			$payment_request = $this->get_cart_payment_request( WC()->cart );
			$this->store_payment_request( $payment_request );
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

			// Create user account if guest and account creation is enabled.
			$user_id = 0;
			if ( $this->should_create_account_for_guest() ) {
				$user_id = $this->create_user_account( $payment_response );
				$this->log( 'Created user account for the guest user with id: ' . $user_id );
			}

			$order = Framework\Payment_Gateway\External_Checkout\Orders::create_order( WC()->cart, array( 'created_via' => 'apple_pay' ) );

			// Set the user ID if account was created.
			if ( $user_id > 0 ) {
				$order->set_customer_id( $user_id );
			}

			$order->set_payment_method( $this->get_processing_gateway() );

			$order->add_order_note( __( 'Apple Pay payment authorized.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );

			$order->set_billing_address( $payment_response->get_billing_address() );
			$order->set_shipping_address( $payment_response->get_shipping_address() );

			$order->save();

			// Integrate with AvaTax if enabled.
			// Forcing tax calculation here to ensure taxes are calculated for Apple Pay orders originating from the product page.
			AvaTax::calculate_order_tax( $order );

			if ( class_exists( '\WC_Subscriptions_Checkout' ) ) {
				\WC_Subscriptions_Checkout::process_checkout( $order->get_id() );
			}

			// add Apple Pay response data to the order.
			add_filter( 'wc_payment_gateway_' . $this->get_processing_gateway()->get_id() . '_get_order', array( $this, 'add_order_data' ) );

			$result = $this->get_processing_gateway()->process_payment( $order->get_id() );

			if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( 'Gateway processing error.' );
			}

			// Log in the newly created user AFTER successful payment processing.
			// This ensures the session data remains intact during payment processing.
			if ( $user_id > 0 && ! is_user_logged_in() ) {
				wc_set_customer_auth_cookie( $user_id );
			}

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
	 * @return \WC_Braintree\Apple_Pay\API\Payment_Response
	 */
	protected function build_payment_response( $data ) {

		return new Payment_Response( $data );
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


	/**
	 * Creates a user account from the Apple Pay payment response.
	 *
	 * @since 3.4.0
	 *
	 * @param Payment_Response $payment_response The Apple Pay payment response.
	 * @return int User ID if created successfully.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If user already exists or account creation fails.
	 */
	protected function create_user_account( $payment_response ) {
		$billing_address = $payment_response->get_billing_address();
		$email           = $billing_address['email'] ?? '';

		if ( empty( $email ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Email address is required to create an account.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		if ( email_exists( $email ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'An account with this email address already exists. Please log in to continue.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$first_name = $billing_address['first_name'] ?? '';
		$last_name  = $billing_address['last_name'] ?? '';

		$user_id = wc_create_new_customer(
			$email,
			'',
			'',
			array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			/* translators: %s: error message from user creation */
			throw new Framework\SV_WC_Payment_Gateway_Exception( sprintf( esc_html__( 'Could not create user account: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), esc_html( $user_id->get_error_message() ) ) );
		}

		return $user_id;
	}
}
