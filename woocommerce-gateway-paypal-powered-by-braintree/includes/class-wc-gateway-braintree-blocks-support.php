<?php
/**
 * Braintree Cart and Checkout Blocks Support
 *
 * @package WC-Braintree/Gateway/Blocks-Support
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Braintree Blocks integration
 *
 * @since 3.0.0
 */
abstract class WC_Gateway_Braintree_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Path to assets
	 *
	 * @var string
	 */
	protected $asset_path;

	/**
	 * URL of script
	 *
	 * @var string
	 */
	protected $script_url;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways[ $this->name ]->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = $this->asset_path;
		$version      = WC_Braintree::VERSION;
		$script_name  = 'wc-' . $this->name . '-blocks-integration';
		$dependencies = array();
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			$script_name,
			$this->script_url,
			$dependencies,
			$version,
			true
		);
		wp_set_script_translations(
			$script_name,
			'woocommerce-gateway-paypal-powered-by-braintree'
		);
		return array( $script_name );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'               => $this->get_setting( 'title' ),
			'description'         => $this->get_setting( 'description' ),
			'supports'            => $this->get_supported_features(),
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'show_saved_cards'    => $this->get_show_saved_cards(),
			'show_save_option'    => $this->get_show_save_option(),
			'tokenization_forced' => $this->tokenization_forced(),
		);
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways[ $this->name ]->supports;
	}

	/**
	 * Determine if store allows save payment information to be saved during checkout.
	 *
	 * @return bool True if merchant allows shopper to save payment information during checkout.
	 */
	private function get_show_saved_cards() {
		$payment_form = $this->get_payment_form_instance();
		if ( $payment_form ) {
			return $payment_form->tokenization_allowed();
		}
		return false;
	}

	/**
	 * Determine if the checkbox to enable the user to save their payment method should be shown.
	 *
	 * @return bool True if the save payment checkbox should be displayed to the user.
	 */
	private function get_show_save_option() {
		return $this->get_show_saved_cards() && ! $this->tokenization_forced();
	}

	/**
	 * Determine if tokenization is forced.
	 *
	 * @return bool True if tokenization is forced.
	 */
	private function tokenization_forced() {
		$payment_form = $this->get_payment_form_instance();
		if ( $payment_form ) {
			return $payment_form->tokenization_forced();
		}
		return false;
	}

	/**
	 * Returns the payment form instance.
	 */
	protected function get_payment_form_instance() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$gateway          = $payment_gateways[ $this->name ];
		return $gateway->get_payment_form_instance();
	}
}
