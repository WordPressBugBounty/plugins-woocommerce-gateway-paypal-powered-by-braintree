<?php
/**
 * Braintree CreditCard Cart and Checkout Blocks Support
 *
 * @package WC-Braintree/Gateway/Blocks-Support
 */

use SkyVerge\WooCommerce\PluginFramework\v5_12_7 as Framework;

/**
 * Braintree CreditCard payment method Blocks integration
 *
 * @since 3.0.0
 */
final class WC_Gateway_Braintree_Credit_Card_Blocks_Support extends WC_Gateway_Braintree_Blocks_Support {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name       = 'braintree_credit_card';
		$this->asset_path = WC_Braintree::instance()->get_plugin_path() . '/assets/js/blocks/credit-card.asset.php';
		$this->script_url = WC_Braintree::instance()->get_plugin_url() . '/assets/js/blocks/credit-card.min.js';

		// Get the saved token 3DS nonce via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->name . '_get_token_data', array( $this, 'ajax_get_token_data' ) );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$params           = array();
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$gateway          = $payment_gateways[ $this->name ];
		$payment_form     = $this->get_payment_form_instance();
		if ( $payment_form ) {
			$params = $payment_form->get_payment_form_handler_js_params();
		}

		return array_merge(
			parent::get_payment_method_data(),
			$params,
			array(
				'is_test_environment'        => $gateway->is_test_environment(),
				'client_token_nonce'         => wp_create_nonce( 'wc_' . $this->name . '_get_client_token' ),
				'token_data_nonce'           => wp_create_nonce( 'wc_' . $this->name . '_get_token_data' ),
				'is_advanced_fraud_tool'     => $gateway->is_advanced_fraud_tool_enabled(),
				'cart_contains_subscription' => $this->cart_contains_subscription(),
				'order_total_for_3ds'        => ( $payment_form ) ? $payment_form->get_order_total_for_3d_secure() : 0,
				'debug'                      => $gateway->debug_log(),
				'icons'                      => $this->get_icons(),
				'fields_error_messages'      => array(
					'card_number_required'         => esc_html__( 'Card number is required', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'card_number_invalid'          => esc_html__( 'Card number is invalid', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'card_cvv_required'            => esc_html__( 'Card security code is required', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'card_cvv_invalid'             => esc_html__( 'Card security code is invalid (must be 3 or 4 digits)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'card_expirationDate_required' => esc_html__( 'Card expiration date is required', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'card_expirationDate_invalid'  => esc_html__( 'Card expiration date is invalid', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				),
			),
		);
	}

	/**
	 * Determines if the cart contains a subscription.
	 */
	private function cart_contains_subscription() {
		if ( wc_braintree()->is_subscriptions_active() && class_exists( 'WC_Subscriptions_Cart' ) ) {
			return WC_Subscriptions_Cart::cart_contains_subscription();
		}
		return false;
	}

	/**
	 * Gets token data via AJAX.
	 */
	public function ajax_get_token_data() {
		check_ajax_referer( 'wc_' . $this->name . '_get_token_data', 'nonce' );

		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$gateway          = $payment_gateways[ $this->name ];
		if ( ! $gateway || ! $gateway->is_available() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Gateway is not available', 'woocommerce-gateway-paypal-powered-by-braintree' ) ) );
		}

		try {
			$token_id   = isset( $_POST['token_id'] ) ? wc_clean( wp_unslash( $_POST['token_id'] ) ) : '';
			$core_token = WC_Payment_Tokens::get_tokens(
				array(
					'user_id'    => get_current_user_id(),
					'token_id'   => $token_id,
					'gateway_id' => $this->name,
				)
			);

			if ( empty( $core_token ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Payment error, please try another payment method or contact us to complete your transaction.', 'woocommerce-gateway-paypal-powered-by-braintree' ) ) );
			}
			$core_token = current( $core_token );

			$token = new Framework\SV_WC_Payment_Gateway_Payment_Token( $core_token->get_token(), $core_token );
			$nonce = '';

			if ( $gateway->card_type_supports_3d_secure( $token->get_card_type() ) ) {
				$nonce_data = $gateway->get_3d_secure_data_for_token( $token );
				$nonce      = $nonce_data['nonce'] ?? '';
				$bin        = $nonce_data['bin'] ?? '';
			}

			wp_send_json_success(
				array(
					'token' => $core_token->get_token(),
					'nonce' => $nonce,
					'bin'   => $bin,
				)
			);

		} catch ( Exception $e ) {

			$gateway->add_debug_message( $e->getMessage(), 'error' );

			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Gets the card icons.
	 */
	private function get_icons() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$gateway          = $payment_gateways[ $this->name ];
		$card_types       = $gateway->get_card_types();
		$card_icons       = array();

		foreach ( $card_types as $card_type ) {
			$card_type                = Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( $card_type );
			$card_icons[ $card_type ] = array(
				'alt' => $card_type,
				'src' => $gateway->get_payment_method_image_url( $card_type ),
			);
		}
		return $card_icons;
	}
}
