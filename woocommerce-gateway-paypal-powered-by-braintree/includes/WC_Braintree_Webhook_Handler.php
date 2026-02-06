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

use Automattic\WooCommerce\Enums\OrderStatus;
use Braintree;
use Braintree\WebhookNotification;
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
			status_header( 400 );
			exit;
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
			Logger::error(
				'[Webhook] Error parsing webhook notification due to invalid signature',
				[
					'message' => $e->getMessage(),
				]
			);
			status_header( 400 );
			exit;

		} catch ( Throwable $e ) {
			Logger::error(
				'[Webhook] Error parsing webhook notification',
				[
					'message' => $e->getMessage(),
				]
			);
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
		$kind = $event_data->kind ?? null;

		// Log the webhook for debugging.
		Logger::debug( '[Webhook] Webhook received: ' . $kind, [ 'event_data' => $event_data ] );

		// Handle different webhook types.
		switch ( $kind ) {
			case WebhookNotification::CHECK:
				break;

			case WebhookNotification::TRANSACTION_SETTLED:
				$this->handle_transaction_settled( $event_data );
				break;

			case WebhookNotification::TRANSACTION_SETTLEMENT_DECLINED:
				$this->handle_transaction_settlement_declined( $event_data );
				break;

			default:
				// Unknown webhook type, just log it.
				Logger::error( '[Webhook] Unknown webhook type: ' . $kind, [ 'event_data' => $event_data ] );
				break;
		}
	}

	/**
	 * Handle transaction settled webhook.
	 *
	 * This webhook is triggered when a transaction has been settled (funds successfully transferred).
	 * This is particularly important for ACH/SEPA transactions which take several days to settle.
	 *
	 * @since 3.7.0
	 *
	 * @param object $event_data Webhook event data.
	 */
	protected function handle_transaction_settled( object $event_data ): void {
		$transaction = $event_data->transaction ?? null;

		if ( ! $transaction ) {
			Logger::error( '[Webhook] Transaction settled webhook missing transaction data', [ 'event_data' => $event_data ] );
			return;
		}

		$transaction_id = $transaction->id ?? null;

		if ( ! $transaction_id ) {
			Logger::error( '[Webhook] Transaction settled webhook missing transaction ID', [ 'event_data' => $event_data ] );
			return;
		}

		// Find the order by transaction ID.
		$orders = wc_get_orders(
			[
				'transaction_id' => $transaction_id,
				'limit'          => 1,
			]
		);

		if ( empty( $orders ) ) {
			Logger::warning( '[Webhook] No order found for settled transaction', [ 'transaction_id' => $transaction_id ] );
			return;
		}

		$order = $orders[0];

		// Check if order is already paid (completed or processing by default, but can be filtered with woocommerce_order_is_paid_statuses).
		$paid_statuses = wc_get_is_paid_statuses();
		if ( $order->has_status( $paid_statuses ) ) {
			Logger::warning(
				"[Webhook] Order #{$order->get_id()} already paid ({$order->get_status()}), skipping settlement update",
				[
					'transaction_id' => $transaction_id,
					'order'          => $order,
				]
			);
			return;
		}

		// Get additional transaction details for the order note.
		$amount   = $transaction->amount ?? '';
		$currency = $transaction->currencyIsoCode ?? '';

		// Validate that the transaction amount and currency match the order.
		$order_total    = $order->get_total();
		$order_currency = $order->get_currency();

		if ( $currency !== $order_currency || (float) $amount !== (float) $order_total ) {
			Logger::error(
				"[Webhook] Amount mismatch for settled transaction - Order #{$order->get_id()}",
				[
					'order_id'       => $order->get_id(),
					'transaction_id' => $transaction_id,
					'currency'       => $currency,
					'amount'         => $amount,
					'order_currency' => $order_currency,
					'order_amount'   => $order_total,
				]
			);

			// Build a detailed order note.
			$note_parts = [
				sprintf(
					/* translators: %s - Order ID */
					__( 'Amount mismatch for settled transaction - Order #%s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					$order->get_id()
				),
			];

			if ( $amount && $currency ) {
				$note_parts[] = sprintf(
					/* translators: %1$s - Currency, %2$s - Amount */
					__( 'Settled Amount: %1$s %2$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					$currency,
					$amount,
				);
			}

			$order_note = implode( "\n", $note_parts );
			$order->add_order_note( $order_note );
			return;
		}

		/**
		 * Filters whether to complete payment when transaction is settled.
		 *
		 * @since 3.7.0
		 *
		 * @param bool      $should_complete_payment Whether to mark the payment as complete. Default true.
		 * @param \WC_Order $order                   The order object.
		 * @param string    $transaction_id          The Braintree transaction ID.
		 * @param object    $event_data              The webhook event data.
		 */
		$should_complete_payment = apply_filters(
			'wc_braintree_webhook_should_complete_settlement',
			true,
			$order,
			$transaction_id,
			$event_data
		);

		if ( ! $should_complete_payment ) {
			Logger::info( "[Webhook] Settlement processing blocked by filter for order #{$order->get_id()}" );
			return;
		}

		// Build a detailed order note.
		$note_parts = [
			sprintf(
				/* translators: %s - Braintree transaction ID */
				__( 'Transaction settled (Transaction ID: %s)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$transaction_id
			),
		];

		if ( $amount && $currency ) {
			$note_parts[] = sprintf(
				/* translators: %1$s - Currency, %2$s - Amount */
				__( 'Amount: %1$s %2$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$currency,
				$amount,
			);
		}

		$order_note = implode( "\n", $note_parts );

		/**
		 * Filters the order note for settled transaction.
		 *
		 * @since 3.7.0
		 *
		 * @param string    $order_note     The order note text.
		 * @param \WC_Order $order          The order object.
		 * @param object    $transaction    The Braintree transaction object.
		 * @param object    $event_data     The webhook event data.
		 */
		$order_note = apply_filters(
			'wc_braintree_webhook_transaction_settled_order_note',
			$order_note,
			$order,
			$transaction,
			$event_data
		);

		// Mark the payment as complete.
		$order->payment_complete( $transaction_id );
		$order->add_order_note( $order_note );

		Logger::info(
			"[Webhook] Transaction settled successfully for order #{$order->get_id()}",
			[
				'order_id'       => $order->get_id(),
				'transaction_id' => $transaction_id,
				'amount'         => $amount,
				'currency'       => $currency,
			]
		);

		/**
		 * Fires after a transaction is successfully settled.
		 *
		 * @since 3.7.0
		 *
		 * @param \WC_Order $order          The order object.
		 * @param string    $transaction_id The Braintree transaction ID.
		 * @param object    $transaction    The Braintree transaction object.
		 * @param object    $event_data     The webhook event data.
		 */
		do_action(
			'wc_braintree_webhook_transaction_settled',
			$order,
			$transaction_id,
			$transaction,
			$event_data
		);
	}

	/**
	 * Handle transaction settlement declined webhook.
	 *
	 * This webhook is triggered when a transaction settlement is declined by the bank.
	 * Common reasons include insufficient funds, account closed, or invalid account information.
	 *
	 * @since 3.7.0
	 *
	 * @param object $event_data Webhook event data.
	 */
	protected function handle_transaction_settlement_declined( object $event_data ): void {
		$transaction = $event_data->transaction ?? null;

		if ( ! $transaction ) {
			Logger::error( '[Webhook] Transaction settlement declined webhook missing transaction data', [ 'event_data' => $event_data ] );
			return;
		}

		$transaction_id = $transaction->id ?? null;

		if ( ! $transaction_id ) {
			Logger::error( '[Webhook] Transaction settlement declined webhook missing transaction ID', [ 'event_data' => $event_data ] );
			return;
		}

		// Find the order by transaction ID.
		$orders = wc_get_orders(
			[
				'transaction_id' => $transaction_id,
				'limit'          => 1,
			]
		);

		if ( empty( $orders ) ) {
			Logger::warning( '[Webhook] No order found for declined settlement', [ 'transaction_id' => $transaction_id ] );
			return;
		}

		$order = $orders[0];

		// Don't update if order is already in a final/terminal state.
		// @link https://woocommerce.com/document/managing-orders/order-statuses.
		$final_statuses = [
			OrderStatus::PROCESSING,
			OrderStatus::COMPLETED,
			OrderStatus::FAILED,
			OrderStatus::CANCELLED,
			OrderStatus::REFUNDED,
		];
		if ( $order->has_status( $final_statuses ) ) {
			Logger::warning(
				"[Webhook] Order #{$order->get_id()} already in final status ({$order->get_status()}), skipping decline update",
				[
					'transaction_id' => $transaction_id,
					'order'          => $order,
				]
			);
			return;
		}

		/**
		 * Filters whether to fail an order when a transaction settlement is declined.
		 *
		 * @since 3.7.0
		 *
		 * @param bool   $should_fail_order Whether to mark the order as failed. Default true.
		 * @param \WC_Order $order           The order object.
		 * @param string $transaction_id     The Braintree transaction ID.
		 * @param object $event_data         The webhook event data.
		 */
		$should_fail_order = apply_filters(
			'wc_braintree_webhook_should_fail_declined_settlement',
			true,
			$order,
			$transaction_id,
			$event_data
		);

		if ( ! $should_fail_order ) {
			Logger::info( "[Webhook] Settlement decline processing blocked by filter for order #{$order->get_id()}" );
			return;
		}

		// Extract decline reason from transaction data.
		$decline_reasons = [];

		if ( isset( $transaction->status ) ) {
			$decline_reasons[] = sprintf( 'Status: %s', $transaction->status );
		}

		if ( isset( $transaction->processorSettlementResponseCode ) ) {
			$decline_reasons[] = sprintf(
				/* translators: %s - Processor response code */
				__( 'Processor response code: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$transaction->processorSettlementResponseCode,
			);
		}

		if ( isset( $transaction->processorSettlementResponseText ) ) {
			$decline_reasons[] = sprintf(
				/* translators: %s - Processor response */
				__( 'Processor response: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$transaction->processorSettlementResponseText,
			);
		}

		if ( isset( $transaction->gatewayRejectionReason ) ) {
			$decline_reasons[] = sprintf(
				/* translators: %s - rejection reason */
				__( 'Gateway rejection reason: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$transaction->gatewayRejectionReason,
			);
		}

		// Build detailed order note.
		$note_parts = [
			sprintf(
				/* translators: %s - Braintree transaction ID */
				__( 'Transaction settlement declined (Transaction ID: %s)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$transaction_id
			),
		];

		if ( ! empty( $decline_reasons ) ) {
			$note_parts[] = implode( "\n", $decline_reasons );
		}

		// Include bank account details if available for merchant reference.
		if ( isset( $transaction->usBankAccount ) ) {
			$bank_account = $transaction->usBankAccount;
			$note_parts[] = sprintf(
				/* translators: %1$s - Bank account type, %2$s - Account's last 4 digits */
				__( 'Bank account: %1$s ending in %2$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$bank_account->accountType ?? 'Account',
				$bank_account->last4 ?? '****'
			);
		}

		$note_parts[] = __( 'Please contact the customer to resolve the payment issue.', 'woocommerce-gateway-paypal-powered-by-braintree' );

		$order_note = implode( "\n", $note_parts );

		/**
		 * Filters the order note for declined transaction settlement.
		 *
		 * @since 3.7.0
		 *
		 * @param string    $order_note     The order note text.
		 * @param \WC_Order $order          The order object.
		 * @param object    $transaction    The Braintree transaction object.
		 * @param object    $event_data     The webhook event data.
		 */
		$order_note = apply_filters(
			'wc_braintree_webhook_settlement_declined_order_note',
			$order_note,
			$order,
			$transaction,
			$event_data
		);

		// Update order status to failed.
		$order->update_status(
			'failed',
			$order_note
		);

		Logger::warning(
			"[Webhook] Transaction settlement declined for order #{$order->get_id()}",
			[
				'order_id'       => $order->get_id(),
				'transaction_id' => $transaction_id,
				'status'         => $transaction->status ?? 'unknown',
				'decline_reason' => $decline_reasons,
			]
		);

		/**
		 * Fires after a transaction settlement is declined.
		 *
		 * @since 3.7.0
		 *
		 * @param \WC_Order $order          The order object.
		 * @param string    $transaction_id The Braintree transaction ID.
		 * @param object    $transaction    The Braintree transaction object.
		 * @param object    $event_data     The webhook event data.
		 */
		do_action(
			'wc_braintree_webhook_settlement_declined',
			$order,
			$transaction_id,
			$transaction,
			$event_data
		);
	}
}
