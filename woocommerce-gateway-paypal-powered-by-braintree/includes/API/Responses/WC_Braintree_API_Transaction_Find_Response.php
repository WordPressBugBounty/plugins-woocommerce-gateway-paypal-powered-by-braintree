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
 * @package   WC-Braintree/Gateway/API/Responses/Transaction-Find
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use Braintree\Transaction;
use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API Abstract Transaction Find Response Class
 *
 * Provides common functionality for transaction find responses where the response
 * is a direct Braintree\Transaction object, not wrapped in a result object.
 *
 * @since 3.4.0
 */
abstract class WC_Braintree_API_Transaction_Find_Response extends WC_Braintree_API_Response implements
	Framework\SV_WC_Payment_Gateway_API_Response,
	Framework\SV_WC_Payment_Gateway_API_Authorization_Response,
	Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response,
	Framework\SV_WC_Payment_Gateway_API_Customer_Response {

	/** Braintree's CSC match value */
	const CSC_MATCH = 'M';

	/**
	 * Gets the response transaction ID
	 *
	 * @see SV_WC_Payment_Gateway_API_Response::get_transaction_id()
	 * @return string transaction id
	 */
	public function get_transaction_id() {

		return $this->response->id ?? null;
	}

	/**
	 * Returns the result of the AVS check
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#avs_error_response_code
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_avs_result()
	 * @return string result of the AVS check, if any
	 */
	public function get_avs_result() {

		if ( ! empty( $this->response->avsErrorResponseCode ) ) {

			return 'error:' . $this->response->avsErrorResponseCode;

		} else {

			return $this->response->avsPostalCodeResponseCode . ':' . $this->response->avsStreetAddressResponseCode;
		}
	}

	/**
	 * Returns the result of the CSC check
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_csc_result()
	 * @return string result of CSC check
	 */
	public function get_csc_result() {

		return $this->response->cvvResponseCode ?? null;
	}

	/**
	 * Returns true if the CSC check was successful
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#cvv_response_code
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::csc_match()
	 * @return boolean true if the CSC check was successful
	 */
	public function csc_match() {

		return $this->get_csc_result() === self::CSC_MATCH;
	}

	/**
	 * Return the customer ID for the request
	 *
	 * @return string|null
	 */
	public function get_customer_id() {

		return $this->response->customerDetails->id ?? null;
	}

	/** Risk Data feature *****************************************************/

	/**
	 * Returns true if the transaction has risk data present. If this is not
	 * present, advanced fraud tools are not enabled (and set to "show") in
	 * the merchant's Braintree account and/or not enabled within plugin settings
	 *
	 * @return bool
	 */
	public function has_risk_data() {

		return isset( $this->response->riskData );
	}

	/**
	 * Get the risk ID for this transaction
	 *
	 * @return string|null
	 */
	public function get_risk_id() {

		return $this->response->riskData->id ?? null;
	}

	/**
	 * Get the risk decision for this transaction, one of: 'not evaulated',
	 * 'approve', 'review', 'decline'
	 *
	 * @return string|null
	 */
	public function get_risk_decision() {

		return $this->response->riskData->decision ?? null;
	}

	/**
	 * Override parent method to handle direct transaction response
	 *
	 * @return object|null Transaction object or null
	 */
	protected function get_transaction_object() {
		// For find responses, the response IS the transaction directly.
		return $this->response;
	}

	/**
	 * Get the transaction type (sale, authorization, etc.)
	 *
	 * @return string|null
	 */
	public function get_transaction_type() {
		return $this->response->type ?? null;
	}

	/**
	 * Get the merchant account ID
	 *
	 * @return string|null
	 */
	public function get_merchant_account_id() {
		return $this->response->merchantAccountId ?? null;
	}

	/**
	 * Get 3D Secure information
	 *
	 * @return array|null
	 */
	public function get_three_d_secure_info() {
		if ( ! isset( $this->response->threeDSecureInfo ) || empty( $this->response->threeDSecureInfo ) ) {
			return null;
		}

		$three_ds = $this->response->threeDSecureInfo;

		return [
			'status'                   => $three_ds->status ?? null,
			'liability_shifted'        => $three_ds->liabilityShifted ?? null,
			'liability_shift_possible' => $three_ds->liabilityShiftPossible ?? null,
			'enrolled'                 => $three_ds->enrolled ?? null,
		];
	}


	/**
	 * Get the payment instrument type
	 *
	 * @return string|null
	 */
	public function get_payment_instrument_type() {
		return $this->response->paymentInstrumentType ?? null;
	}

	/**
	 * Gets the transaction status
	 *
	 * @return string|null
	 */
	public function get_status() {
		return $this->response->status ?? null;
	}
}
