<?php
/**
 * Braintree Venmo Cart and Checkout Blocks Support
 *
 * @package WC-Braintree/Gateway/Blocks-Support
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Venmo payment method Blocks integration
 *
 * @since 3.5.0
 */
final class WC_Gateway_Braintree_Venmo_Blocks_Support extends WC_Gateway_Braintree_Blocks_Support {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name       = 'braintree_venmo';
		$this->asset_path = WC_Braintree::instance()->get_plugin_path() . '/assets/js/blocks/venmo.asset.php';
		$this->script_url = WC_Braintree::instance()->get_plugin_url() . '/assets/js/blocks/venmo.min.js';
	}

	/**
	 * Initializes the payment method.
	 */
	public function initialize() {
		parent::initialize();

		add_filter( 'woocommerce_saved_payment_methods_list', array( $this, 'add_braintree_venmo_saved_payment_methods' ), 10, 2 );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$params                   = array();
		$payment_gateways         = WC()->payment_gateways->payment_gateways();
		$gateway                  = $payment_gateways[ $this->name ];
		$payment_form             = $this->get_payment_form_instance();
		$is_checkout_confirmation = false;
		if ( $payment_form ) {
			$params                   = $payment_form->get_payment_form_handler_js_params();
			$is_checkout_confirmation = $payment_form->is_checkout_confirmation();
		}

		$checkout_confirmation_description = '';
		if ( $is_checkout_confirmation ) {
			$checkout_confirmation_description = $gateway->get_description();
		}

		return array_merge(
			parent::get_payment_method_data(),
			$params,
			array(
				'client_token_nonce'                => wp_create_nonce( 'wc_' . $this->name . '_get_client_token' ),
				'set_payment_method_nonce'          => wp_create_nonce( 'wc_' . $this->name . '_cart_set_payment_method' ),
				'debug'                             => $gateway->debug_log(),
				'is_checkout_confirmation'          => $is_checkout_confirmation,
				'checkout_confirmation_description' => $checkout_confirmation_description,
				'plugin_url'                        => $gateway->get_plugin()->get_plugin_url(),
				'cart_checkout_enabled'             => is_cart() && $gateway->cart_checkout_enabled(),
				'cart_handler_url'                  => add_query_arg( 'wc-api', get_class( $gateway ), home_url() ),
			),
		);
	}

	/**
	 * Manually add braintree save tokens to the saved payment methods list.
	 *
	 * @param array $saved_methods The saved payment methods.
	 * @param int   $customer_id The customer ID.
	 * @return array $saved_methods Modified saved payment methods.
	 */
	public function add_braintree_venmo_saved_payment_methods( $saved_methods, $customer_id ) {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$payment_form     = $this->get_payment_form_instance();

		// Check if we are on the checkout confirmation page and if so, don't show saved payment methods.
		if ( $payment_form && $payment_form->is_checkout_confirmation() ) {
			$saved_methods[ $this->name ] = array();
			return $saved_methods;
		}

		// If we don't have tokenization support, no saved tokens can be used.
		if ( ! isset( $payment_gateways[ $this->name ]->settings['tokenization'] ) || 'yes' !== $payment_gateways[ $this->name ]->settings['tokenization'] ) {
			return $saved_methods;
		}

		$tokens = $payment_gateways[ $this->name ]->get_payment_tokens_handler()->get_tokens( $customer_id );

		if ( ! $tokens ) {
			return $saved_methods;
		}

		$saved_tokens = array();

		foreach ( $tokens as $token ) {
			// Skip if not a Venmo token.
			if ( ! method_exists( $token, 'is_venmo_account' ) || ! $token->is_venmo_account() ) {
				continue;
			}

			$saved_tokens[] = array(
				'method'     => array(
					'gateway' => $this->name,
					'last4'   => $token->get_venmo_username(),
					'brand'   => __( 'Venmo Account', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				),
				'expires'    => '',
				'is_default' => $token->is_default(),
				'actions'    => array(),
				'tokenId'    => $token->get_id(),
			);
		}

		$saved_methods[ $this->name ] = $saved_tokens;

		return $saved_methods;
	}
}
