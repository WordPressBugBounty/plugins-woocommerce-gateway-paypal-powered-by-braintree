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

use Exception;
use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Express_Checkout;
use WC_Cart;
use WC_Pre_Orders_Cart;
use WC_Pre_Orders_Product;
use WC_Product;
use WC_Subscriptions_Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * The Braintree Google Pay base handler.
 *
 * @since 3.4.0
 */
class Google_Pay extends Framework\Payment_Gateway\External_Checkout\Google_Pay\Google_Pay {

	use WC_Braintree_Express_Checkout;

	/**
	 * Initializes the frontend handler.
	 *
	 * @since 3.4.0
	 */
	protected function init_frontend() {
		$this->frontend = new Frontend( $this->get_plugin(), $this );
		// Runs at priority 11 to ensure that the button is moved after the framework's init fires.
		add_action( 'wp', array( $this, 'post_init' ), 11 );
	}


	/**
	 * Enqueues assets for the Google Pay button CSS.
	 *
	 * @since 3.4.0
	 */
	public function enqueue_assets() {
		if ( 'yes' !== get_option( 'sv_wc_google_pay_enabled' ) ) {
			return;
		}

		$css_path = $this->get_plugin()->get_plugin_path() . '/assets/css/frontend/wc-google-pay.min.css';
		$version  = $this->get_plugin()->get_assets_version();

		if ( is_readable( $css_path ) ) {
			$css_url = $this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-google-pay.min.css';

			wp_enqueue_style( 'wc-braintree-google-pay', $css_url, array(), $version );
		}
	}


	/**
	 * Checks if all products in the cart can be purchased using Google Pay.
	 *
	 * @since 3.4.0
	 *
	 * @param \WC_Cart $cart cart object.
	 *
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If cart contains pre-orders.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If cart contains multiple shipments.
	 */
	public function validate_cart( WC_Cart $cart ) {

		if ( $this->get_plugin()->is_pre_orders_active() && WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Cart contains pre-orders.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$cart->calculate_totals();

		if ( count( WC()->shipping->get_packages() ) > 1 ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Google Pay cannot be used for multiple shipments.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}
	}


	/**
	 * Checks if a single product can be purchased using Google Pay.
	 *
	 * @since 3.4.0
	 *
	 * @param \WC_Product $product product object.
	 *
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If product is not available for purchase.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If product is not a simple or subscription product.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If product is not a pre-order product that is set to charge upon release.
	 */
	public function validate_product( WC_Product $product ) {

		// no pre-order "charge upon release" products.
		if ( $this->get_plugin()->is_pre_orders_active() && WC_Pre_Orders_Product::product_is_charged_upon_release( $product ) ) {
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
	}


	/**
	 * Processes the payment after a Google Pay authorization.
	 *
	 * This method creates a new order and calls the gateway for processing.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed  $payment_data Payment data returned by Google Pay.
	 * @param string $product_id Product ID, if we are on a Product page.
	 *
	 * @return array
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If gateway processing error.
	 * @throws Exception If other error occurs.
	 */
	public function process_payment( $payment_data, $product_id ) {

		$order = null;

		try {

			$this->log( "Payment Method Response:\n" . $payment_data . "\n" );

			$payment_data = json_decode( $payment_data, true );

			$this->store_payment_response( $payment_data );

			// if this is a single product page, make sure the cart gets populated.
			$this->add_product_to_cart( $product_id );

			// Create user account if guest and account creation is enabled.
			$user_id = 0;
			if ( $this->should_create_account_for_guest() ) {
				$user_id = $this->create_user_account( $payment_data );
				$this->log( 'Created user account for the guest user with id: ' . $user_id );
			}

			$order = Framework\Payment_Gateway\External_Checkout\Orders::create_order( WC()->cart, [ 'created_via' => 'google_pay' ] );

			// Set the user ID if a new account was created.
			if ( $user_id > 0 ) {
				$order->set_customer_id( $user_id );
			}

			$order->set_payment_method( $this->get_processing_gateway() );

			// if we got to this point, the payment was authorized by Google Pay
			// from here on out, it's up to the gateway to not screw things up.
			$order->add_order_note( __( 'Google Pay payment authorized.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );

			if ( ! empty( $payment_data['paymentMethodData']['info']['billingAddress'] ) ) {

				$billing_address_data = $payment_data['paymentMethodData']['info']['billingAddress'];

				$billing_address = $this->prepare_address( $billing_address_data );

				$order->set_address( $billing_address, 'billing' );

				$order->set_billing_phone( isset( $billing_address_data['phoneNumber'] ) ? $billing_address_data['phoneNumber'] : '' );
			}

			$order->set_billing_email( isset( $payment_data['email'] ) ? $payment_data['email'] : '' );

			if ( ! empty( $payment_data['shippingAddress'] ) ) {

				$shipping_address_data = $payment_data['shippingAddress'];

				$shipping_address = $this->prepare_address( $shipping_address_data );

				$order->set_address( $shipping_address, 'shipping' );
			}

			$order->save();

			if ( class_exists( '\WC_Subscriptions_Checkout' ) ) {
				WC_Subscriptions_Checkout::process_checkout( $order->get_id() );
			}

			// add Google Pay response data to the order.
			add_filter( 'wc_payment_gateway_' . $this->get_processing_gateway()->get_id() . '_get_order', [ $this, 'add_order_data' ] );

			if ( $this->is_test_mode() ) {
				$result = $this->process_test_payment( $order );
			} else {
				$result = $this->get_processing_gateway()->process_payment( $order->get_id() );
			}

			if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Gateway processing error.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
			}

			// Log in the newly created user AFTER successful payment processing.
			// This ensures the session data remains intact during payment processing.
			if ( $user_id > 0 && ! is_user_logged_in() ) {
				wc_set_customer_auth_cookie( $user_id );
			}

			return $result;

		} catch ( Exception $e ) {

			if ( $order ) {

				$order->add_order_note(
					sprintf(
						/* Translators: Placeholders: %s - the error message */
						__( 'Google Pay payment failed. %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						$e->getMessage()
					)
				);
			}

			throw $e;
		}
	}


	/**
	 * Prepare an address to WC formatting.
	 *
	 * @since 3.4.0
	 *
	 * @param array $address_data The Google Pay address data.
	 * @return array WC formatted address.
	 */
	protected function prepare_address( array $address_data ): array {

		if ( ! empty( $address_data['name'] ) ) {
			$first_name = strstr( $address_data['name'], ' ', true );
			$last_name  = trim( strstr( $address_data['name'], ' ' ) );
		}

		$address = [
			'first_name' => $first_name ?? '',
			'last_name'  => $last_name ?? '',
			'address_1'  => $address_data['address1'] ?? '',
			'address_2'  => $address_data['address2'] ?? '',
			'city'       => $address_data['locality'] ?? '',
			'state'      => $address_data['administrativeArea'] ?? '',
			'postcode'   => $address_data['postalCode'] ?? '',
			'country'    => strtoupper( $address_data['countryCode'] ?? '' ),
		];

		return $address;
	}


	/**
	 * Creates a user account from the Google Pay payment response data.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $payment_data Payment data returned by Google Pay.
	 * @return int User ID if created successfully.
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If user already exists or account creation fails.
	 */
	protected function create_user_account( $payment_data ) {
		$email = $payment_data['email'] ?? '';

		if ( empty( $email ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Email address is required to create an account.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		if ( email_exists( $email ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'An account with this email address already exists. Please log in to continue.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$billing_address = [
			'first_name' => '',
			'last_name'  => '',
		];

		if ( ! empty( $payment_data['paymentMethodData']['info']['billingAddress'] ) ) {
			$billing_address_data = $payment_data['paymentMethodData']['info']['billingAddress'];
			$billing_address      = $this->prepare_address( $billing_address_data );
		}

		$user_id = wc_create_new_customer(
			$email,
			'',
			'',
			array(
				'first_name' => $billing_address['first_name'] ?? '',
				'last_name'  => $billing_address['last_name'] ?? '',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			/* translators: %s: error message from user creation */
			throw new Framework\SV_WC_Payment_Gateway_Exception( sprintf( esc_html__( 'Could not create user account: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), esc_html( $user_id->get_error_message() ) ) );
		}

		return $user_id;
	}
}
