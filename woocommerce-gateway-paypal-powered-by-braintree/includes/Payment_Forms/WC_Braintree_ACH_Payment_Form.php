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
 * @package   WC-Braintree/Gateway/Payment-Form/ACH
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Payment_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Braintree ACH Payment Form
 *
 * @since 3.7.0
 *
 * @method \WC_Braintree\WC_Gateway_Braintree_ACH get_gateway()
 */
class WC_Braintree_ACH_Payment_Form extends WC_Braintree_Payment_Form {


	/**
	 * Gets the JS handler class name.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_ACH_Handler';
	}


	/**
	 * Return the JS params passed to the payment form handler script
	 *
	 * @since 3.7.0
	 * @see WC_Braintree_Payment_Form::get_payment_form_handler_js_params()
	 * @return array
	 */
	public function get_payment_form_handler_js_params() {

		$params = parent::get_payment_form_handler_js_params();

		$is_subscription        = $this->get_gateway()->cart_contains_subscription();
		$is_tokenization_forced = $this->tokenization_forced();

		$tokens = $this->get_tokens();

		$saved_accounts = array();
		foreach ( $tokens as $token ) {
			$woo_token = $token->get_woocommerce_payment_token();
			if ( $woo_token instanceof \WC_Braintree\WC_Payment_Token_Braintree_ACH ) {
				$saved_accounts[ $token->get_id() ] = [
					'bank_name' => $woo_token->get_bank_name(),
					'last4'     => $woo_token->get_last_four(),
				];
			}
		}

		return array_merge(
			$params,
			[
				'enabled'              => $this->get_gateway()->is_available(),
				'store_name'           => \WC_Braintree\WC_Braintree::get_braintree_store_name(),
				'must_save_token'      => $is_tokenization_forced || $is_subscription,
				'place_order_text'     => __( 'Place order', 'woocommerce' ), //phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'saved_accounts'       => $saved_accounts,
				'payment_methods_link' => wc_get_account_endpoint_url( 'payment-methods' ),
			],
		);
	}


	/**
	 * Render the ACH payment fields.
	 *
	 * Renders hidden inputs for ACH nonce and device data.
	 *
	 * @since 3.7.0
	 */
	public function render_payment_fields() {

		parent::render_payment_fields();

		$cart_total_html = '';
		$cart            = \WC()->cart;
		if ( $cart && $cart instanceof \WC_Cart ) {
			$cart_total_html = $cart->get_total( 'view' );
		}
		?>

		<div id="wc-braintree-ach-container-form" class="wc-braintree-ach-container">
			<p class="form-row form-row-wide">
				<label for="wc-braintree-ach-routing-number">
					<?php esc_html_e( 'Routing number', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
					<span class="required" title="<?php esc_attr_e( 'required', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>">*</span>
				</label>
				<span class="woocommerce-input-wrapper">
					<input type="text" class="input-text" id="wc-braintree-ach-routing-number" name="wc-braintree-ach-routing-number" required>
				</span>
			</p>
			<p class="form-row form-row-wide">
				<label for="wc-braintree-ach-account-number">
					<?php esc_html_e( 'Account number', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
					<span class="required" title="<?php esc_attr_e( 'required', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>">*</span>
				</label>
				<span class="woocommerce-input-wrapper">
					<input type="text" class="input-text" id="wc-braintree-ach-account-number" name="wc-braintree-ach-account-number" required>
				</span>
			</p>
			<p class="form-row form-row-wide wc-braintree-ach-account-type-row">
				<label><?php esc_html_e( 'Account type', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?></label>
				<span class="woocommerce-input-wrapper wc-braintree-ach-account-type-options">
					<label class="wc-braintree-ach-radio-label">
						<input type="radio" id="wc-braintree-ach-account-type-checking" name="wc-braintree-ach-account-type" value="checking" checked>
						<span><?php esc_html_e( 'Checking', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?></span>
					</label>
					<label class="wc-braintree-ach-radio-label">
						<input type="radio" id="wc-braintree-ach-account-type-savings" name="wc-braintree-ach-account-type" value="savings">
						<span><?php esc_html_e( 'Savings', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?></span>
					</label>
				</span>
			</p>
		</div>

		<div id="wc-braintree-ach-container-account-info" class="wc-braintree-ach-container" style="display: none;">
			<div class="wc-braintree-ach-tokenized-state">
				<p class="wc-braintree-ach-description"></p>
				<a href="#" id="wc-braintree-ach-remove-link" class="wc-braintree-ach-remove">
					<?php esc_html_e( 'Remove', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
				</a>
			</div>
		</div>

		<div id="wc-braintree-ach-container-mandate-text" class="wc-braintree-ach-container">
		</div>

		<div id="wc-braintree-ach-container-raw-mandate-text" style="display: none;">
			<script id="wc-braintree-ach-raw-mandate-text">
				window.wc_braintree_ach_mandate_data = <?php echo wp_json_encode( $this->get_mandate_data() ); ?>;
			</script>
			<div id="wc-braintree-ach-mandate-cart-total"><?php echo $cart_total_html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
	}


	/**
	 * Get the mandate data for the ACH payment form.
	 *
	 * @since 3.7.0
	 * @return array The mandate data for the ACH payment form.
	 */
	protected function get_mandate_data(): array {
		/*
		 * This configuration applies any time tokenization is applied, including all of the following:
		 * - Tokenization is forced
		 * - The cart contains a subscription product
		 * - The user checks the "Save my details" checkbox
		 */
		$new_tokenized_data = [
			'mandate_text'    => [
				/* translators: 1: checkout button text (e.g. 'Place order'), 2: store name, 3: account holder name, 4: account type, 5: account number, 6: routing number, 7: authorization date */
				__( 'By clicking "%1$s", I authorize Braintree, a PayPal service, on behalf of %2$s to verify my bank account information using bank information and consumer reports and I authorize %2$s to store my account information on file and to initiate ACH/electronic debits for future payments, as follows: Account holder: %3$s, %4$s Account Number: %5$s, Routing Number: %6$s, Authorization Date: %7$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				/* translators: 2: store name, 8: my payment methods link */
				__( 'Any subsequent ACH/electronic debits to my account can be initiated by online confirmation. I understand that this authorization will remain in full force and effect until I notify %2$s that I wish to revoke this authorization by removing the payment method from my account at %8$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			],
			'placeholder_map' => [
				'checkout_button_text',
				'store_name',
				'account_holder_name',
				'account_type',
				'account_number',
				'routing_number',
				'authorization_date',
				'payment_methods_link',
			],
		];

		if ( $this->tokenization_forced() ) {
			return [
				'new_bank_account'   => $new_tokenized_data,
				'saved_bank_account' => [
					'mandate_text'    => [
						/* translators: 1: checkout button text (e.g. 'Place order'), 2: store name, 3: bank name, 4: last 4 digits of the account number */
						__( 'By clicking "%1$s", I authorize Braintree, a PayPal service, on behalf of %2$s to initiate recurring ACH/electronic debits against my saved account details: Financial institution: %3$s, Account number: %4$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					],
					'placeholder_map' => [
						'checkout_button_text',
						'store_name',
						'bank_name',
						'last4',
					],
				],
			];
		}

		return [
			'new_bank_account'   => [
				'mandate_text'    => [
					/* translators: 1: checkout button text (e.g. 'Place order'), 2: store name, 3: account holder name, 4: account type (Checking or Savings), 5: account number, 6: routing number, 7: cart total, e.g. $100.00, 8: authorization date */
					__( 'By clicking "%1$s", I authorize Braintree, a PayPal service, on behalf of %2$s to verify my bank account information using bank information and consumer reports and I authorize %2$s to initiate a one-time ACH/electronic debit to my account as follows: Account holder: %3$s, %4$s Account Number: %5$s, Routing Number: %6$s, Amount: %7$s, Authorization Date: %8$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'placeholder_map' => [
					'checkout_button_text',
					'store_name',
					'account_holder_name',
					'account_type',
					'account_number',
					'routing_number',
					'cart_total',
					'authorization_date',
				],
			],
			'saved_bank_account' => [
				'mandate_text'    => [
					/* translators: 1: checkout button text (e.g. 'Place order'), 2: store name, 3: bank name, 4: account number, 5: cart total, e.g. $100.00 */
					__( 'By clicking "%1$s", I authorize Braintree, a PayPal service, on behalf of %2$s to initiate a one-time ACH/electronic debit to my saved account as follows: Financial institution: %3$s, Account number: %4$s, Amount: %5$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'placeholder_map' => [
					'checkout_button_text',
					'store_name',
					'bank_name',
					'last4',
					'cart_total',
				],
			],
			'new_saved_account'  => $new_tokenized_data,
		];
	}

	/**
	 * Gets the saved payment method title for display on checkout.
	 *
	 * Overridden to show the Bank name and the las 4 digits of the account.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Braintree\WC_Braintree_Payment_Method $token Payment token.
	 * @return string
	 */
	protected function get_saved_payment_method_title( $token ) {

		$image_url = $token->get_image_url();
		$type      = $token->get_type_full();

		$title = '<span class="title">';

		if ( $image_url ) {
			$title .= sprintf( '<img src="%1$s" alt="%2$s" title="%2$s" width="30" height="20" />', esc_url( $image_url ), esc_attr( $type ) );
		}

		$nickname = $token->get_woocommerce_payment_token()->get_nickname();
		if ( $nickname ) {
			$title .= '<span class="nickname">' . esc_html( $nickname ) . '</span>';
		} else {
			$title .= esc_html( $type );
		}

		$title .= '</span>';

		/**
		 * Payment Gateway Payment Form Payment Method Title.
		 *
		 * Filters the HTML used to display a saved ACH Direct Debit payment method on checkout.
		 *
		 * @since 3.7.0
		 *
		 * @param string $title the payment method title HTML
		 * @param \WC_Braintree\WC_Braintree_Payment_Method $token the payment token associated with this method
		 * @param \WC_Braintree\Payment_Forms\WC_Braintree_ACH_Payment_Form $this instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_payment_method_title', $title, $token, $this );
	}


	/**
	 * Gets the "Use a new bank account" input HTML.
	 *
	 * Overridden to always default to "Use a new bank account" regardless of saved tokens.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	protected function get_use_new_payment_method_input_html() {

		// input.
		$html = sprintf(
			'<input type="radio" id="wc-%1$s-use-new-payment-method" name="wc-%1$s-payment-token" class="js-sv-wc-payment-token js-wc-%1$s-payment-token" style="width:auto; margin-right: .5em;" value="" %2$s />',
			esc_attr( $this->get_gateway()->get_id_dasherized() ),
			checked( true, true, false ) // Always default to "Use new".
		);

		// label.
		$html .= sprintf(
			'<label style="display:inline;" for="wc-%s-use-new-payment-method">%s</label>',
			esc_attr( $this->get_gateway()->get_id_dasherized() ),
			esc_html__( 'Use a new bank account', 'woocommerce-gateway-paypal-powered-by-braintree' )
		);

		/**
		 * Payment Gateway Payment Form New Payment Method Input HTML.
		 *
		 * Filters the HTML rendered for the "Use a new bank account" radio button.
		 *
		 * @since 3.7.0
		 *
		 * @param string $html the input HTML
		 * @param \WC_Braintree\Payment_Forms\WC_Braintree_ACH_Payment_Form $this payment form instance
		 */
		return apply_filters( 'wc_' . $this->get_gateway()->get_id() . '_payment_form_new_payment_method_input_html', $html, $this );
	}
}
