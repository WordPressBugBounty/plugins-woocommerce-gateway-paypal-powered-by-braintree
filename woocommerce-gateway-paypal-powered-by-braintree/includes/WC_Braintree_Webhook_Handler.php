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
 * needs please refer to https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/
 *
 * @package   WC-Braintree/Gateway
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use Braintree;
use Throwable;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Webhook Handler.
 *
 * Handles incoming webhooks from Braintree.
 *
 * If a webhook takes longer than 30 seconds to respond, it is considered a timeout and will be retried.
 * Braintree will resend webhook notifications every hour for up to 3 hours in sandbox, or up to 24 hours in production,
 * until the webhook responds with a successful HTTPS response code (i.e. '2xx') within 30 seconds.
 *
 * @see https://developer.paypal.com/braintree/docs/guides/webhooks/parse/php/
 *
 * @since 3.3.0
 */
class WC_Braintree_Webhook_Handler {

	/**
	 * Single instance of the Webhook Handler.
	 *
	 * @var WC_Braintree_Webhook_Handler single instance of the Webhook Handler.
	 */
	protected static $instance;

	/**
	 * Gateway class instance.
	 *
	 * @var WC_Gateway_Braintree_Credit_Card
	 */
	protected WC_Gateway_Braintree_Credit_Card $gateway;

	/**
	 * Webhook Handler Instance, ensures only one instance is/can be loaded.
	 *
	 * @since 3.3.0
	 *
	 * @return WC_Braintree_Webhook_Handler
	 */
	public static function instance(): WC_Braintree_Webhook_Handler {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 3.3.0
	 */
	private function __construct() {
		add_action( 'woocommerce_api_wc_braintree', [ $this, 'handle_webhook' ] );
	}

	/**
	 * Handle incoming webhook requests.
	 *
	 * @since 3.3.0
	 */
	public function handle_webhook(): void {
		// Webhooks from Braintree don't use WordPress nonces; but we validate the payload signature.
		// phpcs:disable WordPress.Security.NonceVerification

		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		if ( 'wc_braintree' !== sanitize_text_field( wp_unslash( $_GET['wc-api'] ?? '' ) ) ) {
			return;
		}

		if ( ! isset( $_POST['bt_signature'], $_POST['bt_payload'] ) ) {
			return;
		}

		$bt_signature = sanitize_text_field( wp_unslash( $_POST['bt_signature'] ) );

		// We use `sanitize_textarea_field()` here instead of `sanitize_text_field()` because
		// we need to preserve the newlines, otherwise the payload signature check will fail.
		$bt_payload = sanitize_textarea_field( wp_unslash( $_POST['bt_payload'] ) );

		// phpcs:enable WordPress.Security.NonceVerification

		$plugin = WC_Braintree::instance();
		// TODO: This is temporary, while we refactor the plugin to have one main gateway with several payment methods (like PayPal Payments, Stripe, etc).
		// Otherwise, if we allow different gateways to have their own set of keys, we need to check the webhook signature with each gateway configuration.
		$this->gateway = $plugin->get_gateway( WC_Braintree::CREDIT_CARD_GATEWAY_ID );

		$sdk = new Braintree\Gateway(
			[
				'environment' => $this->gateway->get_environment(),
				'merchantId'  => $this->gateway->get_merchant_id(),
				'publicKey'   => $this->gateway->get_public_key(),
				'privateKey'  => $this->gateway->get_private_key(),
			]
		);

		try {
			// Verify and decode the webhook notification.
			$webhook_notification = $sdk->webhookNotification()->parse( $bt_signature, $bt_payload );

			$this->process_webhook( $webhook_notification );

		} catch ( Braintree\Exception\InvalidSignature $e ) {
			WC_Braintree::instance()->log( 'Error parsing webhook notification due to invalid signature: ' . $e->getMessage() );
			status_header( 400 );
			exit;

		} catch ( Throwable $e ) {
			WC_Braintree::instance()->log( 'Error parsing webhook notification: ' . $e->getMessage() );
			status_header( 500 );
			exit;
		}

		// Return success.
		status_header( 200 );
		exit;
	}

	/**
	 * Process the webhook data.
	 *
	 * @since 3.3.0
	 *
	 * @param object $event_data Webhook event data.
	 */
	protected function process_webhook( object $event_data ): void {
		// Log the webhook for debugging.
		// The plugin logger wrapper does not support using context to log structured data, so we default to `print_r()` until we refactor the logger.
		WC_Braintree::instance()->log( 'Webhook received: ' . print_r( $event_data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		// Handle different webhook types.
		$kind = $event_data->kind ?? null;
		switch ( $kind ) {
			case 'check':
				$this->handle_check( $event_data );
				break;

			default:
				// Unknown webhook type, just log it.
				WC_Braintree::instance()->log( 'Unknown webhook type: ' . $kind );
				break;
		}
	}

	/**
	 * Handle test webhook.
	 *
	 * @since 3.3.0
	 *
	 * @param object $event_data Webhook event data.
	 */
	protected function handle_check( object $event_data ): void {
		WC_Braintree::instance()->log( 'Test Check webhook processed: ' . $event_data->kind );
	}
}
