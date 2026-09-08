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
 * @package   WC-Braintree/Gateway/Payment-Form
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Payment_Forms;

use SkyVerge\WooCommerce\PluginFramework\v6_2_4 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Abstract Payment Form
 *
 * @since 3.0.0
 */
abstract class WC_Braintree_Payment_Form extends Framework\SV_WC_Payment_Gateway_Payment_Form {


	use Test_Mode_UI_Trait;


	/**
	 * Sets up the class.
	 *
	 * Overridden here to avoid calling get_tokens() on construct.
	 *
	 * @since 2.4.0
	 *
	 * @param Framework\SV_WC_Payment_Gateway|Framework\SV_WC_Payment_Gateway_Direct $gateway gateway for form.
	 */
	public function __construct( $gateway ) {

		parent::__construct( $gateway );

		$this->gateway = $gateway;

		// hook up rendering.
		$this->add_hooks();
	}


	/**
	 * Adds hooks for rendering the payment form.
	 *
	 * Overridden here to move the location of the payment form JS enqueue
	 *
	 * @since 2.4.0
	 */
	protected function add_hooks() {

		parent::add_hooks();

		$gateway_id = $this->get_gateway()->get_id();

		remove_action( "wc_{$gateway_id}_payment_form_end", [ $this, 'render_js' ], 5 );
		add_action( 'wp_footer', [ $this, 'render_js' ], 5 );
	}


	/**
	 * Renders the payment form
	 *
	 * Overridden here to attempt to load tokens before render rather than on form construct.
	 *
	 * @since 2.4.0
	 */
	public function render() {

		// maybe load tokens.
		$this->get_tokens();

		parent::render();
	}


	/**
	 * Renders the payment form description, including the shared test-mode UI
	 * block when the gateway is in a test environment.
	 *
	 * The shared test-mode UI (including the test-amount input) is suppressed
	 * on the Add Payment Method page.
	 *
	 * @link https://developers.braintreepayments.com/reference/general/testing/php
	 *
	 * @since 3.0.0
	 */
	public function render_payment_form_description() {

		parent::render_payment_form_description();

		if ( is_add_payment_method_page() ) {
			return;
		}

		$this->render_test_mode_ui();
	}


	/**
	 * Mirrors the framework implementation while suppressing the legacy
	 * "TEST MODE ENABLED" banner - replaced by the Test badge
	 *
	 * @since 3.11.0
	 *
	 * @return string Payment form description HTML (admin description only).
	 */
	public function get_payment_form_description_html() {

		$description = '';

		if ( $this->get_gateway()->get_description() ) {
			$description .= '<p>' . wp_kses_post( $this->get_gateway()->get_description() ) . '</p>';
		}

		/**
		 * Payment Gateway Payment Form Description.
		 *
		 * Filters the HTML rendered for payment form description.
		 *
		 * @since 4.0.0
		 *
		 * @param string                                      $description
		 * @param Framework\SV_WC_Payment_Gateway_Payment_Form $this        payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_description', $description, $this );
	}


	/**
	 * Render a hidden input for the payment nonce and device_data before the
	 * credit card/PayPal fields. This is populated by the payment form javascript
	 * when it receives a nonce from Braintree.
	 *
	 * @since 3.0.0
	 */
	public function render_payment_fields() {

		$gateway_id     = $this->get_gateway()->get_id_dasherized();
		$device_data_id = "wc-{$gateway_id}-device-data";

		?>
		<input type="hidden" id="<?php echo esc_attr( 'wc_' . $this->get_gateway()->get_id() . '_payment_nonce' ); ?>" name="<?php echo esc_attr( 'wc_' . $this->get_gateway()->get_id() . '_payment_nonce' ); ?>" />
		<input type="hidden" id="<?php echo esc_attr( $device_data_id ); ?>" name="<?php echo esc_attr( 'wc_braintree_device_data' ); ?>" />
		<?php

		parent::render_payment_fields();
	}

	/**
	 * Get gateway-specific JS params that are passed to the payment form handler script
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_payment_form_handler_js_params() {

		return [
			'integration_error_message' => esc_html__( 'Currently unavailable. Please try a different payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'payment_error_message'     => esc_html__( 'Oops, something went wrong. Please try a different payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
		];
	}


	/**
	 * Render JS to instantiate the Braintree-specific payment form handler class.
	 * Note that this intentionally does not instantiate the standard payment
	 * form handler, as Braintree replaces it entirely.
	 *
	 * @since 3.0.0
	 */
	public function render_js() {

		// bail if not on a payment form page.
		if ( ! $this->get_gateway()->is_available() || ! $this->get_gateway()->is_payment_form_page() ) {
			return;
		}

		parent::render_js();
	}


	/**
	 * Gets the order total for both checkout and order pay page instances.
	 *
	 * @since 3.3.2-1
	 */
	public function get_order_total() {

		if ( is_checkout_pay_page() ) {

			$order = wc_get_order( $this->get_gateway()->get_checkout_pay_page_order_id() );
			return $order->get_total();

		} else {

			return WC()->cart->total;
		}
	}


	/**
	 * Determines whether the current request is authorized to see the pay-for-order's data.
	 *
	 * Returns true when the requester is the order's logged-in owner (for orders with a
	 * customer_id), or when they supply a valid order key in the URL (for guest orders).
	 * Mirrors the gate the credit-card hosted-fields form has applied since SIRT PR #1035.
	 *
	 * @since 3.11.0
	 *
	 * @return bool
	 */
	protected function can_view_pay_page_order(): bool {

		if ( ! is_checkout_pay_page() ) {
			return false;
		}

		$order_id = $this->get_gateway()->get_checkout_pay_page_order_id();

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		if ( $order->get_customer_id() ) {
			return (int) $order->get_customer_id() === (int) get_current_user_id();
		}

		return $order->key_is_valid( sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}


	/**
	 * Renders the order's billing/shipping data as hidden inputs on the pay-for-order page.
	 *
	 * The pay-for-order surface (form#order_review) does not render the usual billing
	 * fields, so gateway JS that reads them via the DOM (LPM, 3DS card flows, etc.)
	 * gets empty values. Mirror the order's address into hidden inputs using the same
	 * names/IDs the regular checkout uses, gated by ownership verification.
	 *
	 * @since 3.11.0
	 *
	 * @return void
	 */
	protected function render_pay_page_billing_inputs() {

		if ( ! $this->can_view_pay_page_order() ) {
			return;
		}

		$order = wc_get_order( $this->get_gateway()->get_checkout_pay_page_order_id() );

		// name= vs id= matches what gateway JS reads (input[name=...] vs #id).
		echo '<input type="hidden" name="billing_first_name" value="' . esc_attr( $order->get_billing_first_name( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_last_name" value="' . esc_attr( $order->get_billing_last_name( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_phone" value="' . esc_attr( $order->get_billing_phone( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_address_1" value="' . esc_attr( $order->get_billing_address_1( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_address_2" value="' . esc_attr( $order->get_billing_address_2( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_postcode" value="' . esc_attr( $order->get_billing_postcode( 'edit' ) ) . '" />';
		echo '<input type="hidden" name="billing_email" value="' . esc_attr( $order->get_billing_email( 'edit' ) ) . '" />';

		echo '<input type="hidden" id="billing_city" value="' . esc_attr( $order->get_billing_city( 'edit' ) ) . '" />';
		echo '<input type="hidden" id="billing_state" value="' . esc_attr( $order->get_billing_state( 'edit' ) ) . '" />';
		echo '<input type="hidden" id="billing_country" value="' . esc_attr( $order->get_billing_country( 'edit' ) ) . '" />';

		if ( $order->has_shipping_address() ) {

			echo '<input type="hidden" name="shipping_first_name" value="' . esc_attr( $order->get_shipping_first_name( 'edit' ) ) . '" />';
			echo '<input type="hidden" name="shipping_last_name" value="' . esc_attr( $order->get_shipping_last_name( 'edit' ) ) . '" />';
			echo '<input type="hidden" name="shipping_address_1" value="' . esc_attr( $order->get_shipping_address_1( 'edit' ) ) . '" />';
			echo '<input type="hidden" name="shipping_address_2" value="' . esc_attr( $order->get_shipping_address_2( 'edit' ) ) . '" />';
			echo '<input type="hidden" name="shipping_city" value="' . esc_attr( $order->get_shipping_city( 'edit' ) ) . '" />';
			echo '<input type="hidden" name="shipping_postcode" value="' . esc_attr( $order->get_shipping_postcode( 'edit' ) ) . '" />';

			echo '<input type="hidden" id="shipping_state" value="' . esc_attr( $order->get_shipping_state( 'edit' ) ) . '" />';
			echo '<input type="hidden" id="shipping_country" value="' . esc_attr( $order->get_shipping_country( 'edit' ) ) . '" />';
		}
	}


	/**
	 * Gets the JS handler arguments.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	protected function get_js_handler_args() {

		$args = array_merge(
			[
				'id'                 => $this->get_gateway()->get_id(),
				'id_dasherized'      => $this->get_gateway()->get_id_dasherized(),
				'name'               => $this->get_gateway()->get_method_title(),
				'debug'              => $this->get_gateway()->debug_log(),
				'type'               => str_replace( '-', '_', $this->get_gateway()->get_payment_type() ),
				'client_token_nonce' => wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_get_client_token' ),
				'is_block_theme'     => wp_is_block_theme(),
				'card_tokens'        => array_merge(
					$this->get_gateway()->get_payment_tokens_handler()->get_apple_pay_card_tokens(),
					$this->get_gateway()->get_payment_tokens_handler()->get_google_pay_card_tokens()
				),
			],
			$this->get_payment_form_handler_js_params()
		);

		return $args;
	}
}
