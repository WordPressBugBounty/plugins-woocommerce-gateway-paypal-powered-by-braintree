<?php
/**
 * Braintree Local Payment Cart and Checkout Blocks Support
 *
 * @package WC-Braintree/Gateway/Blocks-Support
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Local Payment method Blocks integration.
 *
 * Each active LPM gateway gets its own instance of this class, but all
 * instances share the same JS bundle. The shared JS discovers active
 * LPMs via `window.wcBraintreeLocalPayments.gatewayIds` and registers
 * each one as a separate WC Blocks payment method.
 *
 * @since 3.9.0
 */
final class WC_Gateway_Braintree_Local_Payment_Blocks_Support extends WC_Gateway_Braintree_Blocks_Support {

	/**
	 * Shared script handle used by all LPM Blocks Support instances.
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE = 'wc-braintree-local-payments-blocks-integration';

	/**
	 * Constructor.
	 *
	 * @since 3.9.0
	 *
	 * @param WC_Gateway_Braintree_Local_Payment $gateway The LPM gateway instance.
	 */
	public function __construct( WC_Gateway_Braintree_Local_Payment $gateway ) {
		$this->name                    = $gateway->get_id();
		$this->asset_path              = WC_Braintree::instance()->get_plugin_path() . '/assets/js/blocks/local-payments.asset.php';
		$this->script_url              = WC_Braintree::instance()->get_plugin_url() . '/assets/js/blocks/local-payments.min.js';
		$this->additional_dependencies = array(
			'braintree-js-local-payments', // Registered in WC_Gateway_Braintree_Local_Payment::register_gateway_assets().
			'wc-braintree-utils',          // Registered in WC_Gateway_Braintree_Local_Payment::register_gateway_assets().
		);
	}

	/**
	 * Determines if this payment method should be active for Blocks.
	 *
	 * Checks that the gateway is enabled and that the store currency is
	 * supported. Does NOT check billing country — that is dynamic at
	 * checkout and handled by JS `canMakePayment`.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateway = $this->get_gateway();

		if ( ! $gateway ) {
			return false;
		}

		if ( ! $gateway->is_enabled() ) {
			return false;
		}

		return in_array( get_woocommerce_currency(), $gateway->get_supported_currencies(), true );
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * Uses a shared script handle so the JS loads once even when multiple
	 * LPM support classes are registered.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$gateway = $this->get_gateway();

		if ( $gateway ) {
			$gateway->register_gateway_assets();
		}

		// Use the shared handle instead of the per-gateway default.
		$version      = WC_Braintree::VERSION;
		$dependencies = array_merge( array( 'braintree-js-client' ), $this->additional_dependencies );

		if ( file_exists( $this->asset_path ) ) {
			$asset        = require $this->asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && is_array( $asset['dependencies'] ?? null )
				? array_merge( $dependencies, $asset['dependencies'] )
				: $dependencies;
		}

		wp_register_script(
			'braintree-js-client',
			'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js',
			[],
			WC_Braintree::VERSION,
			true
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			$this->script_url,
			$dependencies,
			$version,
			true
		);

		wp_set_script_translations(
			self::SCRIPT_HANDLE,
			'woocommerce-gateway-paypal-powered-by-braintree'
		);

		// Add the gateway IDs inline script once (after the handle is registered).
		// This must happen here rather than during registration because
		// wp_add_inline_script requires the handle to already be registered.
		static $gateway_ids_added = false;

		if ( ! $gateway_ids_added ) {
			$gateway_ids_added = true;
			$lpm_ids           = array();
			$gateways          = WC()->payment_gateways->payment_gateways();

			foreach ( $gateways as $gw ) {
				if ( $gw instanceof WC_Gateway_Braintree_Local_Payment ) {
					$lpm_ids[] = $gw->get_id();
				}
			}

			if ( ! empty( $lpm_ids ) ) {
				wp_add_inline_script(
					self::SCRIPT_HANDLE,
					'window.wcBraintreeLocalPayments = ' . wp_json_encode( array( 'gatewayIds' => $lpm_ids ) ) . ';',
					'before'
				);
			}
		}

		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @since 3.9.0
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = $this->get_gateway();

		$icon_url = '';
		if ( $gateway instanceof WC_Gateway_Braintree_Local_Payment ) {
			$icon_url = $gateway->get_local_payment_icon_url();
		}

		return array_merge(
			parent::get_payment_method_data(),
			array(
				'client_token_nonce'   => wp_create_nonce( 'wc_' . $this->name . '_get_client_token' ),
				'merchant_account_id'  => $gateway ? $gateway->get_merchant_account_id() : '',
				'local_payment_type'   => $gateway ? $gateway->get_local_payment_type() : '',
				'display_name'         => $gateway ? $gateway->get_local_payment_display_name() : '',
				'supported_countries'  => $gateway ? $gateway->get_supported_countries() : array(),
				'supported_currencies' => $gateway ? $gateway->get_supported_currencies() : array(),
				'icon_url'             => $icon_url,
				'redirect_url'         => wc_get_checkout_url(),
			)
		);
	}
}
