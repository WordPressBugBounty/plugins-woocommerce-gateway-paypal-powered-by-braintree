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
 * @package   WC-Braintree/Gateway/API/Responses/ACH-Transaction
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Payment_Method;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API ACH Transaction Response Class
 *
 * Handles parsing ACH transaction responses
 *
 * @see https://developer.paypal.com/braintree/docs/reference/response/transaction/php
 *
 * @since 3.7.0
 */
class WC_Braintree_API_ACH_Transaction_Response extends WC_Braintree_API_Transaction_Response {


	/**
	 * Get the authorization code
	 *
	 * @since 3.7.0
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string|null authorization code
	 */
	public function get_authorization_code() {

		// ACH transactions do not have authorization codes in the same way as credit cards.
		return null;
	}

	/**
	 * Check for token in various possible locations in a transaction response.
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/request/payment-method/create/php/
	 *
	 * NOTE: The online documentation does not list the full payload structure, and the SDK does not provide DTOs,
	 * so we attempt to get the token from several possible places.
	 *
	 * @return string|null
	 */
	private function get_ach_token_from_response(): ?string {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->token ) ) {
			return $this->response->transaction->usBankAccountDetails->token;
		}

		// Some responses use usBankAccount instead of usBankAccountDetails.
		if ( ! empty( $this->response->transaction->usBankAccount->token ) ) {
			return $this->response->transaction->usBankAccount->token;
		}

		// For verification responses, token is in paymentMethod.
		if ( ! empty( $this->response->paymentMethod->token ) ) {
			return $this->response->paymentMethod->token;
		}

		return null;
	}


	/**
	 * Get the ACH payment token created during this transaction
	 *
	 * @since 3.7.0
	 *
	 * @return WC_Braintree_Payment_Method
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If token is missing.
	 */
	public function get_payment_token() {

		$token = $this->get_ach_token_from_response();

		if ( empty( $token ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Required ACH token is missing or empty!', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$data = array(
			'default'      => false, // tokens created as part of a transaction can't be set as default.
			'type'         => WC_Braintree_Payment_Method::ACH_TYPE,
			'last_four'    => $this->get_last_four(),
			'bank_name'    => $this->get_bank_name(),
			'account_type' => $this->get_account_type(),
		);

		return new WC_Braintree_Payment_Method( $token, $data );
	}


	/**
	 * Get the last four digits of the bank account
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php
	 *
	 * @since 3.7.0
	 * @return string|null
	 */
	public function get_last_four() {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->last4 ) ) {
			return $this->response->transaction->usBankAccountDetails->last4;
		}

		if ( ! empty( $this->response->transaction->usBankAccount->last4 ) ) {
			return $this->response->transaction->usBankAccount->last4;
		}

		return null;
	}


	/**
	 * Get the bank account type (checking or savings)
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php
	 *
	 * @since 3.7.0
	 * @return string|null
	 */
	public function get_account_type() {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->accountType ) ) {
			return $this->response->transaction->usBankAccountDetails->accountType;
		}

		if ( ! empty( $this->response->transaction->usBankAccount->accountType ) ) {
			return $this->response->transaction->usBankAccount->accountType;
		}

		return null;
	}


	/**
	 * Get the account holder name
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php
	 *
	 * @since 3.7.0
	 * @return string|null
	 */
	public function get_account_holder_name() {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->accountHolderName ) ) {
			return $this->response->transaction->usBankAccountDetails->accountHolderName;
		}

		if ( ! empty( $this->response->transaction->usBankAccount->accountHolderName ) ) {
			return $this->response->transaction->usBankAccount->accountHolderName;
		}

		return null;
	}


	/**
	 * Get the routing number
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php
	 *
	 * @since 3.7.0
	 * @return string|null
	 */
	public function get_routing_number() {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->routingNumber ) ) {
			return $this->response->transaction->usBankAccountDetails->routingNumber;
		}

		if ( ! empty( $this->response->transaction->usBankAccount->routingNumber ) ) {
			return $this->response->transaction->usBankAccount->routingNumber;
		}

		return null;
	}


	/**
	 * Get the bank name
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php
	 *
	 * @since 3.7.0
	 * @return string|null
	 */
	public function get_bank_name() {

		if ( ! empty( $this->response->transaction->usBankAccountDetails->bankName ) ) {
			return $this->response->transaction->usBankAccountDetails->bankName;
		}

		if ( ! empty( $this->response->transaction->usBankAccount->bankName ) ) {
			return $this->response->transaction->usBankAccount->bankName;
		}

		return null;
	}
}
