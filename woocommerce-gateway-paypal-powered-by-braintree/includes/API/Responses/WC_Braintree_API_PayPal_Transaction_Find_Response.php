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
 * @package   WC-Braintree/Gateway/API/Responses/PayPal-Transaction-Find
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Payment_Method;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API PayPal Transaction Find Response Class
 *
 * Handles parsing PayPal transaction find responses where the response
 * is a direct Braintree\Transaction object, not wrapped in a result object.
 *
 * @since 3.4.0
 */
class WC_Braintree_API_PayPal_Transaction_Find_Response extends WC_Braintree_API_Transaction_Find_Response {

	/**
	 * Get the authorization code
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#authorization_id
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string 6 character credit card authorization code
	 */
	public function get_authorization_code() {

		return $this->response->paypalDetails->authorizationId ?? null;
	}

	/**
	 * Get the PayPal payment token created during this transaction
	 *
	 * @return \WC_Braintree_Payment_Method
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If token is missing.
	 */
	public function get_payment_token() {

		if ( empty( $this->response->paypalDetails->token ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Required PayPal token is missing or empty!', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$data = array(
			'default'     => false, // tokens created as part of a transaction can't be set as default.
			'type'        => WC_Braintree_Payment_Method::PAYPAL_TYPE,
			'payer_email' => $this->get_payer_email(),
			'payer_id'    => $this->get_payer_id(),
		);

		return new WC_Braintree_Payment_Method( $this->response->paypalDetails->token, $data );
	}

	/**
	 * Get the email address associated with the PayPal account used for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#payer_email
	 *
	 * @return string
	 */
	public function get_payer_email() {

		return $this->response->paypalDetails->payerEmail ?? null;
	}

	/**
	 * Get the payer ID associated with the PayPal account used for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#payer_id
	 *
	 * @return string
	 */
	public function get_payer_id() {

		return $this->response->paypalDetails->payerId ?? null;
	}

	/**
	 * Get the payment ID for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#payment_id
	 *
	 * @return string
	 */
	public function get_payment_id() {

		return $this->response->paypalDetails->paymentId ?? null;
	}

	/**
	 * Get the debug ID for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#paypal_details
	 *
	 * @return string
	 */
	public function get_debug_id() {

		return $this->response->paypalDetails->debugId ?? null;
	}

	/**
	 * Get the capture ID for this transaction. For transactions that were
	 * authorized and then captured, this is not available on the transaction
	 * details by default, but can be found on the transaction's submitForSettlement
	 * array under the key "captureId"
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#capture_id
	 *
	 * @return string
	 */
	public function get_capture_id() {

		return $this->response->paypalDetails->captureId ?? null;
	}

	/**
	 * Get the refund ID for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#refund_id
	 *
	 * @return string
	 */
	public function get_refund_id() {

		return $this->response->paypalDetails->refundId ?? null;
	}

	/**
	 * Get the PayPal description for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#description
	 *
	 * @return string
	 */
	public function get_description() {

		return $this->response->paypalDetails->description ?? null;
	}

	/**
	 * Get the transaction fee amount
	 *
	 * @return string|null
	 */
	public function get_transaction_fee_amount() {
		return $this->response->paypalDetails->transactionFeeAmount ?? null;
	}
}
