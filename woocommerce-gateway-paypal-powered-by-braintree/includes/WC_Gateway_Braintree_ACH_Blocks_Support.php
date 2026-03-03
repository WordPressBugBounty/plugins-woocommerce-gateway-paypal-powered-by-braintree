<?php
/**
 * Braintree ACH Cart and Checkout Blocks Support
 *
 * @package WC-Braintree/Gateway/Blocks-Support
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree ACH payment method Blocks integration
 *
 * @since 3.8.0
 */
final class WC_Gateway_Braintree_ACH_Blocks_Support extends WC_Gateway_Braintree_Blocks_Support {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->name                    = 'braintree_ach';
		$this->asset_path              = WC_Braintree::instance()->get_plugin_path() . '/assets/js/blocks/ach.asset.php';
		$this->script_url              = WC_Braintree::instance()->get_plugin_url() . '/assets/js/blocks/ach.min.js';
		$this->additional_dependencies = array(
			'braintree-js-ach', // Registered in WC_Gateway_Braintree_ACH::register_gateway_assets().
			'braintree-js-data-collector', // Registered in WC_Gateway_Braintree::register_gateway_assets().
			'wc-braintree-ach-payment-form', // Registered in WC_Gateway_Braintree_ACH::register_gateway_assets().
			'wc-braintree-utils', // Registered in WC_Gateway_Braintree::register_gateway_assets().
		);
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_enqueue_style(
			'wc-braintree-ach-blocks-style',
			WC_Braintree::instance()->get_plugin_url() . '/assets/js/blocks/ach.css',
			array(),
			WC_Braintree::VERSION
		);

		$gateway = $this->get_gateway();
		if ( $gateway ) {
			$gateway->register_gateway_assets();
		}

		return parent::get_payment_method_script_handles();
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$params       = array();
		$gateway      = $this->get_gateway();
		$payment_form = $this->get_payment_form_instance();

		if ( $payment_form ) {
			$params = $payment_form->get_payment_form_handler_js_params();
		}

		// Get mandate data from the payment form.
		$mandate_data = array();
		if ( $payment_form && method_exists( $payment_form, 'get_mandate_data' ) ) {
			$mandate_data = $payment_form->get_mandate_data();
		}

		return array_merge(
			parent::get_payment_method_data(),
			$params,
			array(
				'client_token_nonce'         => wp_create_nonce( 'wc_' . $this->name . '_get_client_token' ),
				'cart_contains_subscription' => $gateway ? $gateway->cart_contains_subscription() : false,
				'debug'                      => $gateway ? $gateway->debug_log() : false,
				'integration_error_message'  => esc_html__( 'An error occurred while loading the ACH payment form. Please try again or use a different payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'payment_error_message'      => esc_html__( 'An error occurred while processing your ACH payment. Please try again.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'mandate_data'               => $mandate_data,
				'store_name'                 => WC_Braintree::get_braintree_store_name(),
				'payment_methods_link'       => wc_get_account_endpoint_url( 'payment-methods' ),
				'place_order_text'           => esc_html__( 'Place order', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			)
		);
	}
}
