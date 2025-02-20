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
 * @package   WC-Braintree/Gateway/Payment-Form/PayPal
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use WC_Braintree\PayPal\Buttons\Abstract_Button;
use SkyVerge\WooCommerce\PluginFramework\v5_12_7 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree PayPal Payment Form
 *
 * @since 3.0.0
 *
 * @method \WC_Gateway_Braintree_PayPal get_gateway()
 */
class WC_Braintree_PayPal_Payment_Form extends WC_Braintree_Payment_Form {


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_PayPal_Payment_Form_Handler';
	}


	/**
	 * Return the JS params passed to the the payment form handler script
	 *
	 * @since 3.0.0
	 * @see WC_Braintree_Payment_Form::get_payment_form_handler_js_params()
	 * @return array
	 */
	public function get_payment_form_handler_js_params() {

		$params = parent::get_payment_form_handler_js_params();

		$default_button_styles = array(
			'label'   => 'pay',
			'shape'   => $this->get_gateway()->get_button_shape(),
			'color'   => $this->get_gateway()->get_button_color(),
			'layout'  => 'vertical',
			'tagline' => false,
		);

		$size = $this->get_gateway()->get_button_size();

		// tweak the styles a bit for better display on the Add Payment Method page
		if ( is_add_payment_method_page() ) {
			$default_button_styles['label'] = 'paypal';
			$size = 'medium';
		}

		if ( 'responsive' !== $size && ! empty( $button_sizes[ $size ] ) ) {
			$default_button_styles['height'] = $this->get_gateway()->get_button_height( $size );
		}

		/**
		 * Filters the PayPal button style parameters.
		 *
		 * @see https://developer.paypal.com/docs/checkout/integration-features/customize-button
		 *
		 * @since 2.1.0
		 *
		 * @param array $styles style parameters
		 */
		$button_styles = apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_button_styles', $default_button_styles );

		// PayPal requires at least medium-size buttons for the vertical layout, so force that to prevent JS errors after filtering
		if ( isset( $button_styles['layout'], $button_styles['size'] ) && 'vertical' === $button_styles['layout'] && 'small' === $button_styles['size'] ) {
			$button_styles['size'] = 'medium';
		}

		if ( isset( $button_styles['size'] ) && ! isset( $button_styles['height'] ) ) {
			$button_styles['height'] = $this->get_gateway()->get_button_height( $button_styles['size'] );
			unset( $button_styles['size'] );
		}

		// allows the buyer country to be forced during the PayPal SDK loading on test environments
		$force_buyer_country = $this->get_gateway()->should_force_buyer_country_on_loading_sdk() ? get_user_meta( wp_get_current_user()->ID, 'billing_country', true ) : null;

		// gets the disabled funding options
		$disabled_funding_options = $this->get_disabled_funding_options();

		$params = array_merge( $params, [
			'is_test_environment'             => $this->get_gateway()->is_test_environment(),
			'is_paypal_pay_later_enabled'     => $this->get_gateway()->is_paypal_pay_later_enabled() && ! in_array( 'paylater', $disabled_funding_options ),
			'is_paypal_card_enabled'          => $this->get_gateway()->is_paypal_card_enabled(),
			'paypal_disabled_funding_options' => $disabled_funding_options,
			'force_buyer_country'             => $force_buyer_country,
			'must_login_message'              => esc_html__( 'Please click the "PayPal" button below to log into your PayPal account before placing your order.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'must_login_add_method_message'   => esc_html__( 'Please click the "PayPal" button below to log into your PayPal account before adding your payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'button_styles'                   => wp_parse_args( $button_styles, $default_button_styles ), // ensure all expected parameters are present after filtering to avoid JS errors
			'cart_payment_nonce'              => $this->get_cart_nonce(),
			'paypal_intent'                   => WC_Gateway_Braintree_PayPal::TRANSACTION_TYPE_AUTHORIZATION === $this->get_gateway()->get_transaction_type() ? 'authorize' : 'capture',
		] );

		return $params;
	}


	/**
	 * Gets the cart nonce from the session, if any.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	public function get_cart_nonce() {

		return WC()->session->get( 'wc_braintree_paypal_cart_nonce', '' );
	}


	/**
	 * Determines if the current view is at Checkout, confirming the cart PayPal purchase.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function is_checkout_confirmation() {

		return is_checkout() && $this->get_gateway()->is_available() && $this->get_cart_nonce();
	}


	/**
	 * Renders the payment form description.
	 *
	 * Overridden to bail if confirming a cart order.
	 *
	 * @since 2.0.0
	 */
	public function render_payment_form_description() {

		if ( ! $this->is_checkout_confirmation() ) {
			parent::render_payment_form_description();
		}
	}


	/**
	 * Renders the saved payment methods.
	 *
	 * Overridden to bail if confirming a cart order.
	 *
	 * @since 2.0.0
	 */
	public function render_saved_payment_methods() {

		if ( ! $this->is_checkout_confirmation() ) {
			parent::render_saved_payment_methods();
		}
	}


	/**
	 * Gets the saved method title.
	 *
	 * Adds special handling to ensure PayPal accounts display their email address if no nickname is set.
	 *
	 * @since 2.2.5
	 *
	 * @param WC_Braintree_Payment_Method $token token object
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
			$title .= sprintf( '<img src="%1$s" alt="%2$s" title="%2$s" width="30" height="20" style="width: 30px; height: 20px;" />', esc_url( $image_url ), esc_attr( $type ) );
		}

		$title .= '</span>';

		/**
		 * Payment Gateway Payment Form Payment Method Title.
		 *
		 * Filters the text/HTML rendered for a saved payment method, like "Amex ending in 6666".
		 *
		 * @since 2.0.0
		 *
		 * @param string $title
		 * @param \WC_Braintree_Payment_Method $token
		 * @param \WC_Braintree_PayPal_Payment_Form $this payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_payment_method_title', $title, $token, $this );
	}


	/**
	 * Render the PayPal container div, which is replaced by the PayPal button
	 * when the frontend JS executes. This also renders 3 hidden inputs:
	 *
	 * 1) wc_braintree_paypal_amount - order total
	 * 2) wc_braintree_paypal_currency - active store currency
	 * 3) wc_braintree_paypal_locale - site locale
	 *
	 * Note these are rendered as hidden inputs and not passed to the script constructor
	 * because these will be refreshed and re-rendered when the checkout updates,
	 * which is important for the accuracy of things like the order total.
	 *
	 * Also note that the order total is used for rendering info inside the PayPal
	 * modal and _not_ for actual processing for the transaction, so there's no
	 * security concerns here.
	 *
	 * @since 3.0.0
	 */
	public function render_payment_fields() {

		parent::render_payment_fields();

		$order_total     = $this->get_order_total();
		$container_style = $this->get_gateway()->get_button_container_style();

		?>

		<?php if ( $this->get_gateway()->is_paypal_pay_later_enabled() ) : ?>
			<div id="wc_braintree_paypal_pay_later_messaging_container"<?php echo wp_kses( $container_style, '' ); ?> <?php echo wp_kses( $this->get_gateway()->get_pay_later_messaging_style_attributes(), '' );?>></div>
		<?php endif; ?>

		<div id="wc_braintree_paypal_container" <?php echo wp_kses( $container_style, 'style' ); ?>></div>

		<input type="hidden" name="wc_braintree_paypal_amount" value="<?php echo esc_attr( Framework\SV_WC_Helper::number_format( $order_total, 2 ) ); ?>" />
		<input type="hidden" name="wc_braintree_paypal_currency" value="<?php echo esc_attr( get_woocommerce_currency() ); ?>" />
		<input type="hidden" name="wc_braintree_paypal_locale" value="<?php echo esc_attr( $this->get_gateway()->get_safe_locale() ); ?>" />

		<?php
	}


	/**
	 * Gets the disabled funding options.
	 *
	 * @since 2.6.0
	 *
	 * @return array
	 */
	protected function get_disabled_funding_options() {

		/**
		 * Filters the PayPal disabled funding options.
		 *
		 * @since 2.6.0
		 *
		 * @param array $disabled_funding_options list of current disabled funding options
		 * @param \WC_Braintree_PayPal_Payment_Form $this payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_disabled_funding_options', $this->get_gateway()->get_disabled_funding_sources(), $this );
	}


}
