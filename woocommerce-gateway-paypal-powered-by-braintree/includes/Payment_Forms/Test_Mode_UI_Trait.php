<?php
/**
 * Shared renderer for the Braintree test-mode UI block in classic checkout.
 *
 * @package WC-Braintree/Gateway/Payment-Form
 */

namespace WC_Braintree\Payment_Forms;

defined( 'ABSPATH' ) or exit;

trait Test_Mode_UI_Trait {

	/**
	 * Render the redesigned test-mode UI block.
	 *
	 * Emits nothing when the gateway is not in a test environment, so callers
	 * may invoke it unconditionally.
	 */
	public function render_test_mode_ui(): void {
		$gateway = $this->get_gateway();

		if ( ! $gateway->is_test_environment() ) {
			return;
		}

		$input_id      = 'wc-' . $gateway->get_id_dasherized() . '-test-amount';
		$testing_guide = 'https://developers.braintreepayments.com/reference/general/testing/php#test-amounts';
		$is_cc         = method_exists( $gateway, 'is_credit_card_gateway' )
			&& $gateway->is_credit_card_gateway();

		?>
		<div class="wc-braintree-test-mode">
			<p>
				<?php
				printf(
					/* translators: %1$s opening anchor tag, %2$s closing anchor tag */
					esc_html__( 'Enter a %1$stest amount%2$s to trigger a specific error, or leave blank.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'<a href="' . esc_url( $testing_guide ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>

			<p class="form-row">
				<label for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'Test amount', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
				</label>
				<input type="text" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" />
			</p>

			<?php if ( $is_cc ) : ?>
				<p>
					<?php esc_html_e( 'Use test card', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
					<span class="wc-braintree-test-mode-copy">
						<span data-wc-braintree-copy-value="4111 1111 1111 1111">4111 1111 1111 1111</span>
						<button
							type="button"
							class="wc-braintree-test-mode-copy__button"
							data-wc-braintree-copy
							aria-label="<?php echo esc_attr__( 'Copy test card number', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>"
							hidden
						>
							<span aria-hidden="true">&#128203;</span>
							<span class="wc-braintree-sr-only" data-wc-braintree-copy-feedback aria-live="polite"></span>
						</button>
					</span>
					<?php
					printf(
						/* translators: %1$s opening anchor tag, %2$s closing anchor tag. Leading space is intentional — the sentence continues after the card-number span. */
						esc_html__( ' or refer to our %1$stesting guide%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						'<a href="' . esc_url( $testing_guide ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
