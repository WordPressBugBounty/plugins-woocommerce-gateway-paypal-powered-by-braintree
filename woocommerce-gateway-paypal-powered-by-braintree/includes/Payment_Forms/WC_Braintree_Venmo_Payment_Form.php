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
 * @package   WC-Braintree/Gateway/Payment-Form/Venmo
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Payment_Forms;

use WC_Braintree\WC_Gateway_Braintree_Venmo;

defined( 'ABSPATH' ) || exit;

/**
 * Braintree Venmo Payment Form
 *
 * @since 3.5.0
 *
 * @method \WC_Braintree\WC_Gateway_Braintree_Venmo get_gateway()
 */
class WC_Braintree_Venmo_Payment_Form extends WC_Braintree_Payment_Form {


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_Venmo_Checkout_Handler';
	}


	/**
	 * Return the JS params passed to the payment form handler script
	 *
	 * @since 3.5.0
	 * @see WC_Braintree_Payment_Form::get_payment_form_handler_js_params()
	 * @return array
	 */
	public function get_payment_form_handler_js_params() {

		$params = parent::get_payment_form_handler_js_params();

		// Use multi_use for subscriptions to enable vaulting, single_use for simple products.
		$payment_usage = $this->get_gateway()->cart_contains_subscription() ? WC_Gateway_Braintree_Venmo::PAYMENT_METHOD_USAGE_MULTI : WC_Gateway_Braintree_Venmo::PAYMENT_METHOD_USAGE_SINGLE;

		$params = array_merge(
			$params,
			[
				'enabled'            => $this->get_gateway()->is_available(),
				'payment_usage'      => $payment_usage,
				'cart_payment_nonce' => $this->get_cart_nonce(),
			]
		);

		return $params;
	}


	/**
	 * Gets the cart nonce from the session, if any.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_cart_nonce() {

		return WC()->session->get( 'wc_braintree_venmo_cart_nonce', '' );
	}


	/**
	 * Determines if the current view is at Checkout, confirming the cart Venmo purchase.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function is_checkout_confirmation() {

		return is_checkout() && $this->get_gateway()->is_available() && $this->get_cart_nonce();
	}


	/**
	 * Renders the saved payment methods.
	 *
	 * Overridden to bail if confirming a cart order.
	 *
	 * @since 3.5.0
	 */
	public function render_saved_payment_methods() {

		if ( ! $this->is_checkout_confirmation() ) {
			parent::render_saved_payment_methods();
		}
	}


	/**
	 * Render the Venmo payment fields.
	 *
	 * Renders hidden inputs for Venmo nonce, device data, and username,
	 * plus the Venmo button container.
	 *
	 * @since 3.5.0
	 */
	public function render_payment_fields() {

		parent::render_payment_fields();

		?>

		<div class="wc-braintree-venmo-button-container">
			<button type="button" id="wc_braintree_venmo_button" class="wc-braintree-venmo-button">
				<img src="<?php echo esc_url( $this->get_gateway()->get_plugin()->get_plugin_url() . '/assets/images/white_venmo_logo.svg' ); ?>" alt="<?php esc_attr_e( 'Pay with Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>" />
			</button>
		</div>

		<?php
	}


	/**
	 * Gets the saved payment method title for display on checkout.
	 *
	 * Overridden to show the Venmo username.
	 *
	 * @since 3.6.0
	 *
	 * @param \WC_Braintree\WC_Braintree_Payment_Method $token Payment token.
	 * @return string
	 */
	protected function get_saved_payment_method_title( $token ) {

		$image_url = $token->get_image_url();
		$type      = $token->get_type_full();

		$title = '<span class="title">';

		if ( $token->get_nickname() ) {
			$title .= '<span class="nickname">' . esc_html( $token->get_nickname() ) . '</span>';
		} else {
			$title .= esc_html( $type );
		}

		if ( $image_url ) {
			$title .= sprintf( '<img src="%1$s" alt="%2$s" title="%2$s" width="30" height="20" />', esc_url( $image_url ), esc_attr( $type ) );
		}

		$title .= '</span>';

		/**
		 * Payment Gateway Payment Form Payment Method Title.
		 *
		 * Filters the HTML used to display a saved Venmo payment method on checkout.
		 *
		 * @since 3.6.0
		 *
		 * @param string $title the payment method title HTML
		 * @param \WC_Braintree\WC_Braintree_Payment_Method $token the payment token associated with this method
		 * @param \WC_Braintree\Payment_Forms\WC_Braintree_Venmo_Payment_Form $this instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_payment_method_title', $title, $token, $this );
	}


	/**
	 * Gets the "Use new payment method" radio input HTML.
	 *
	 * Overridden to display "Use a new Venmo account" instead of "Use a new bank account".
	 *
	 * @since 3.6.0
	 *
	 * @return string
	 */
	protected function get_use_new_payment_method_input_html() {

		// input.
		$html = sprintf(
			'<input type="radio" id="wc-%1$s-use-new-payment-method" name="wc-%1$s-payment-token" class="js-sv-wc-payment-token js-wc-%1$s-payment-token" style="width:auto; margin-right: .5em;" value="" %2$s />',
			esc_attr( $this->get_gateway()->get_id_dasherized() ),
			checked( $this->default_new_payment_method(), true, false )
		);

		// label.
		$html .= sprintf(
			'<label style="display:inline;" for="wc-%s-use-new-payment-method">%s</label>',
			esc_attr( $this->get_gateway()->get_id_dasherized() ),
			esc_html__( 'Use a new Venmo account', 'woocommerce-gateway-paypal-powered-by-braintree' )
		);

		/**
		 * Payment Gateway Payment Form New Payment Method Input HTML.
		 *
		 * Filters the HTML rendered for the "Use a new Venmo account" radio button.
		 *
		 * @since 3.6.0
		 *
		 * @param string $html the input HTML
		 * @param \WC_Braintree\Payment_Forms\WC_Braintree_Venmo_Payment_Form $this payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_new_payment_method_input_html', $html, $this );
	}
}
