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
 * @copyright Copyright: (c) 2016-2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Integrations;

use SkyVerge\WooCommerce\PluginFramework\v5_12_0 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Pre-Orders Integration
 *
 * @since 2.4.0
 */
class Pre_Orders extends Framework\SV_WC_Payment_Gateway_Integration_Pre_Orders {


	/**
	 * Processes a pre-order payment when the pre-order is released.
	 *
	 * Overridden here to handle PayPal transactions.
	 *
	 * @since 2.4.0
	 *
	 * @param \WC_Order $order original order containing the pre-order
	 */
	public function process_release_payment( $order ) {

		try {

			// set order defaults
			$order = $this->get_gateway()->get_order( $order->get_id() );

			// order description
			$order->description = sprintf( esc_html__( '%s - Pre-Order Release Payment for Order %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), esc_html( Framework\SV_WC_Helper::get_site_name() ), $order->get_order_number() );

			// token is required
			if ( ! $order->payment->token ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Payment token missing/invalid.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
			}

			// perform the transaction
			if ( $this->get_gateway()->is_credit_card_gateway() || $this->get_gateway()->is_paypal_gateway() ) {

				if ( $this->get_gateway()->perform_credit_card_charge( $order ) ) {
					$response = $this->get_gateway()->get_api()->credit_card_charge( $order );
				} else {
					$response = $this->get_gateway()->get_api()->credit_card_authorization( $order );
				}

			} elseif ( $this->get_gateway()->is_echeck_gateway() ) {
				$response = $this->get_gateway()->get_api()->check_debit( $order );
			}

			// success! update order record
			if ( $response->transaction_approved() ) {

				$last_four = substr( $order->payment->account_number, -4 );

				// order note based on gateway type
				if ( $this->get_gateway()->is_credit_card_gateway() ) {

					$message = sprintf(
						__( '%s %s Pre-Order Release Payment Approved: %s ending in %s (expires %s)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						$this->get_gateway()->get_method_title(),
						$this->get_gateway()->perform_credit_card_authorization( $order ) ? 'Authorization' : 'Charge',
						Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( ( ! empty( $order->payment->card_type ) ? $order->payment->card_type : 'card' ) ),
						$last_four,
						( ! empty( $order->payment->exp_month) && ! empty( $order->payment->exp_year ) ? $order->payment->exp_month . '/' . substr( $order->payment->exp_year, -2 ) : 'n/a' )
					);

				} elseif ( $this->get_gateway()->is_echeck_gateway() ) {

					// account type (checking/savings) may or may not be available, which is fine
					$message = sprintf( esc_html__( '%s eCheck Pre-Order Release Payment Approved: %s ending in %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), $this->get_gateway()->get_method_title(), Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( ( ! empty( $order->payment->account_type ) ? $order->payment->account_type : 'bank' ) ), $last_four );

				} else {

					$message = sprintf(
					/* translators: Placeholders: %s - payment method title, like PayPal */
					esc_html__( '%s Pre-Order Release Payment Approved', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						$this->get_gateway()->get_method_title()
					);
				}

				// adds the transaction id (if any) to the order note
				if ( $response->get_transaction_id() ) {
					$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-gateway-paypal-powered-by-braintree' ), $response->get_transaction_id() );
				}

				$order->add_order_note( $message );
			}

			if ( $response->transaction_approved() || $response->transaction_held() ) {

				// add the standard transaction data
				$this->get_gateway()->add_transaction_data( $order, $response );

				// allow the concrete class to add any gateway-specific transaction data to the order
				$this->get_gateway()->add_payment_gateway_transaction_data( $order, $response );

				// if the transaction was held (ie fraud validation failure) mark it as such
				if ( $response->transaction_held() || ( $this->get_gateway()->supports( Framework\SV_WC_Payment_Gateway::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->get_gateway()->perform_credit_card_authorization( $order ) ) ) {

					$status_text = $response->get_status_message();
					if( $this->get_gateway()->supports(Framework\SV_WC_Payment_Gateway::FEATURE_CREDIT_CARD_AUTHORIZATION ) && $this->get_gateway()->perform_credit_card_authorization( $order )  ) {
						$status_text = esc_html__( 'Authorization only transaction', 'woocommerce-gateway-paypal-powered-by-braintree' );
					}

					$this->get_gateway()->mark_order_as_held(
						$order,
						$status_text,
						$response
					);

					wc_reduce_stock_levels( $order->get_id() );

				// otherwise complete the order
				} else {

					$order->payment_complete();
				}

			} else {

				// failure
				throw new Framework\SV_WC_Payment_Gateway_Exception( sprintf( '%s: %s', $response->get_status_code(), $response->get_status_message() ) );

			}

		} catch ( Framework\SV_WC_Plugin_Exception $e ) {

			// Mark order as failed
			$this->get_gateway()->mark_order_as_failed( $order, sprintf( esc_html__( 'Pre-Order Release Payment Failed: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), $e->getMessage() ) );

		}
	}


}
