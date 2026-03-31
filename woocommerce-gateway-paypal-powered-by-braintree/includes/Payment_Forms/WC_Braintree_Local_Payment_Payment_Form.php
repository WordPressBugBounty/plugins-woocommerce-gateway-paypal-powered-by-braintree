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
 * @package   WC-Braintree/Gateway/Payment-Form/Local-Payment
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Payment_Forms;

use SkyVerge\WooCommerce\PluginFramework\v6_0_1 as Framework;

defined( 'ABSPATH' ) || exit;

/**
 * Braintree Local Payment Payment Form.
 *
 * Shared payment form for all local payment method gateways.
 * Each gateway renders instructional text and hidden inputs.
 *
 * @since 3.9.0
 *
 * @method \WC_Braintree\WC_Gateway_Braintree_Local_Payment get_gateway()
 */
class WC_Braintree_Local_Payment_Payment_Form extends WC_Braintree_Payment_Form {


	/**
	 * Adds hooks for rendering the payment form.
	 *
	 * Moves JS rendering from wp_footer to the payment_form_end hook so that
	 * the handler initialization code is included in the payment form HTML.
	 *
	 * The parent class uses wp_footer which only fires once on initial page
	 * load. LPM gateways appear dynamically when the billing country changes
	 * via WooCommerce's update_checkout AJAX, so the init JS must be part of
	 * the payment fragment that gets replaced.
	 *
	 * @since 3.9.0
	 *
	 * @see WC_Braintree_Local_Payment_Payment_Form::render_js()
	 */
	protected function add_hooks() {

		parent::add_hooks();

		$gateway_id = $this->get_gateway()->get_id();

		remove_action( 'wp_footer', [ $this, 'render_js' ], 5 );
		add_action( "wc_{$gateway_id}_payment_form_end", [ $this, 'render_js' ], 5 );
	}


	/**
	 * Renders the handler initialization JS as an inline script tag.
	 *
	 * Overrides the parent to output an inline script tag instead of using
	 * wc_enqueue_js(). The framework's payment_form_end hook uses wc_enqueue_js()
	 * which buffers JS for wp_footer — this doesn't work during AJAX because
	 * wp_footer never fires. An inline script tag is included in the AJAX
	 * fragment response and executed by jQuery when the DOM is replaced.
	 *
	 * @since 3.9.0
	 *
	 * @see WC_Braintree_Local_Payment_Payment_Form::add_hooks()
	 */
	public function render_js() {

		$gateway = $this->get_gateway();

		if ( ! $gateway->is_available() || ! $gateway->is_payment_form_page() ) {
			return;
		}

		$gateway_id = $gateway->get_id();

		if ( in_array( $gateway_id, $this->payment_form_js_rendered, true ) ) {
			return;
		}

		$this->payment_form_js_rendered[] = $gateway_id;

		?>
		<script type="text/javascript">jQuery(function($){<?php echo $this->get_safe_handler_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>});</script>
		<?php
	}


	/**
	 * Gets the handler instantiation JS.
	 *
	 * Adds a guard to skip initialization if the handler already exists. This
	 * prevents duplicate instances when the checkout payment fragment is
	 * replaced on subsequent update_checkout AJAX calls while the gateway
	 * remains available.
	 *
	 * @since 3.9.0
	 *
	 * @param array  $additional_args Additional handler arguments.
	 * @param string $handler_name    Handler class name.
	 * @param string $object_name     Global object name for the handler instance.
	 * @return string
	 */
	protected function get_handler_js( array $additional_args = [], $handler_name = '', $object_name = '' ) {

		if ( ! $object_name ) {
			$object_name = $this->get_js_handler_object_name();
		}

		return 'if (window.' . esc_js( $object_name ) . ') { return; } '
			. parent::get_handler_js( $additional_args, $handler_name, $object_name );
	}


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_Local_Payments_Handler';
	}


	/**
	 * Returns the JS params passed to the payment form handler script.
	 *
	 * @since 3.9.0
	 *
	 * @see WC_Braintree_Payment_Form::get_payment_form_handler_js_params()
	 * @return array
	 */
	public function get_payment_form_handler_js_params() {

		$params  = parent::get_payment_form_handler_js_params();
		$gateway = $this->get_gateway();

		return array_merge(
			$params,
			[
				'enabled'             => $gateway->is_available(),
				'merchant_account_id' => $gateway->get_merchant_account_id(),
				'fallback_url'        => wc_get_checkout_url(),
				'local_payment_type'  => $gateway->get_local_payment_type(),
			]
		);
	}


	/**
	 * Renders the payment form description.
	 *
	 * Overrides the base to skip the test amount field. For LPMs, test
	 * behavior is controlled in Braintree's off-site simulator.
	 *
	 * @since 3.9.0
	 */
	public function render_payment_form_description() {

		echo wp_kses_post( $this->get_payment_form_description_html() );
	}


	/**
	 * Renders the local payment method payment fields.
	 *
	 * Renders instructional text and hidden inputs for amount, currency, and payment ID.
	 * The payment flow is triggered via the order button.
	 *
	 * @since 3.9.0
	 */
	public function render_payment_fields() {

		parent::render_payment_fields();

		$gateway    = $this->get_gateway();
		$gateway_id = $gateway->get_id();
		?>

		<p class="wc-braintree-lpm-instructions">
			<?php
			printf(
				/* translators: Placeholders: %s - the local payment method display name (e.g. "BLIK", "Przelewy24"). */
				esc_html__( 'When placing your order, you will need to complete your payment with %s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				esc_html( $gateway->get_local_payment_display_name() )
			);
			?>
		</p>

		<input type="hidden" name="<?php echo esc_attr( 'wc_' . $gateway_id . '_amount' ); ?>" value="<?php echo esc_attr( Framework\SV_WC_Helper::number_format( $this->get_order_total(), 2 ) ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( 'wc_' . $gateway_id . '_currency' ); ?>" value="<?php echo esc_attr( get_woocommerce_currency() ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( 'wc_' . $gateway_id . '_payment_id' ); ?>" />

		<?php
	}
}
