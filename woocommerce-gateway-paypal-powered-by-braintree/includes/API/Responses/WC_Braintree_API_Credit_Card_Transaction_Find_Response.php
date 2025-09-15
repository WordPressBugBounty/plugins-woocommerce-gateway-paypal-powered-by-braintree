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
 * @package   WC-Braintree/Gateway/API/Responses/Credit-Card-Transaction-Find
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use Braintree\PaymentInstrumentType;
use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Payment_Method;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API Credit Card Transaction Find Response Class
 *
 * Handles parsing credit card transaction find responses where the response
 * is a direct Braintree\Transaction object, not wrapped in a result object.
 *
 * @since 3.4.0
 */
class WC_Braintree_API_Credit_Card_Transaction_Find_Response extends WC_Braintree_API_Transaction_Find_Response {

	/**
	 * Get the authorization code
	 *
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string 6 character credit card authorization code
	 */
	public function get_authorization_code() {

		return $this->response->processorAuthorizationCode ?? null;
	}

	/**
	 * Get the credit card payment token created during this transaction
	 *
	 * @return \WC_Braintree_Payment_Method
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If token is missing.
	 */
	public function get_payment_token() {

		if ( empty( $this->response->{$this->get_instrument_property()}->token ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Required credit card token is missing or empty!', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$data = array(
			'default'            => false, // tokens created as part of a transaction can't be set as default.
			'type'               => WC_Braintree_Payment_Method::CREDIT_CARD_TYPE,
			'last_four'          => $this->get_last_four(),
			'card_type'          => $this->get_card_type(),
			'exp_month'          => $this->get_exp_month(),
			'exp_year'           => $this->get_exp_year(),
			'billing_address_id' => $this->get_billing_address_id(),
		);

		return new WC_Braintree_Payment_Method( $this->response->{$this->get_instrument_property()}->token, $data );
	}

	/**
	 * Returns the property name for the payment instrument details.
	 *
	 * @return string
	 */
	public function get_instrument_property() {
		$instrument_type = $this->response->paymentInstrumentType;

		if ( PaymentInstrumentType::APPLE_PAY_CARD === $instrument_type ) {
			return 'applePayCardDetails';
		}

		return 'creditCardDetails';
	}

	/**
	 * Get the card type used for this transaction
	 *
	 * @return string
	 */
	public function get_card_type() {

		// note that creditCardDetails->cardType is not used here as it is already prettified (e.g. American Express instead of amex).
		return Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $this->get_bin() );
	}

	/**
	 * Get the BIN (bank identification number), AKA the first 6 digits of the card
	 * number. Most useful for identifying the card type.
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php#bin
	 *
	 * @return string
	 */
	public function get_bin() {

		return $this->response->{$this->get_instrument_property()}->bin ?? null;
	}

	/**
	 * Get the masked card number, which is the first 6 digits followed by
	 * 6 asterisks then the last 4 digits. This complies with PCI security standards.
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/response/transaction/php#masked_number
	 *
	 * @return string
	 */
	public function get_masked_number() {

		return $this->response->{$this->get_instrument_property()}->maskedNumber ?? null;
	}

	/**
	 * Get the last four digits of the card number used for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#last_4
	 *
	 * @return string
	 */
	public function get_last_four() {

		return $this->response->{$this->get_instrument_property()}->last4 ?? null;
	}

	/**
	 * Get the expiration month (MM) of the card number used for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#expiration_month
	 *
	 * @return string
	 */
	public function get_exp_month() {

		return $this->response->{$this->get_instrument_property()}->expirationMonth ?? null;
	}

	/**
	 * Get the expiration year (YYYY) of the card number used for this transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#expiration_year
	 *
	 * @return string
	 */
	public function get_exp_year() {

		return $this->response->{$this->get_instrument_property()}->expirationYear ?? null;
	}

	/**
	 * Get the billing address ID associated with the credit card token added
	 * during the transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/response/transaction/php#id
	 *
	 * @return string
	 */
	public function get_billing_address_id() {

		return $this->response->billingDetails->id ?? null;
	}

	/** 3D Secure feature *****************************************************/

	/**
	 * Returns true if 3D Secure information is present for the transaction
	 *
	 * @return bool
	 */
	public function has_3d_secure_info() {

		return isset( $this->response->threeDSecureInfo ) && ! empty( $this->response->threeDSecureInfo );
	}

	/**
	 * Returns the 3D secure statuses
	 *
	 * @return string
	 */
	public function get_3d_secure_status() {

		return $this->has_3d_secure_info() ? $this->response->threeDSecureInfo->status : null;
	}

	/**
	 * Returns true if liability was shifted for the 3D secure transaction
	 *
	 * @return bool
	 */
	public function get_3d_secure_liability_shifted() {

		return $this->has_3d_secure_info() ? $this->response->threeDSecureInfo->liabilityShifted : null;
	}

	/**
	 * Returns true if a liability shift was possible for the 3D secure transaction
	 *
	 * @return bool
	 */
	public function get_3d_secure_liability_shift_possible() {

		return $this->has_3d_secure_info() ? $this->response->threeDSecureInfo->liabilityShiftPossible : null;
	}

	/**
	 * Returns true if the card was enrolled in a 3D secure program
	 *
	 * @return bool
	 */
	public function get_3d_secure_enrollment() {

		return $this->has_3d_secure_info() && 'Y' === $this->response->threeDSecureInfo->enrolled;
	}
}
