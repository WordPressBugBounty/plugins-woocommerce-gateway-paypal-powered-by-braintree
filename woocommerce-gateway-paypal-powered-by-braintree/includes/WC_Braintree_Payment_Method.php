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
 * @package   WC-Braintree/Gateway/Payment-Method
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Payment_Token_Braintree_PayPal;
use WC_Braintree\WC_Payment_Token_Braintree_Venmo;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Payment Method Class.
 *
 * Extends the framework Payment Token class to provide Braintree-specific functionality like billing addresses and PayPal support.
 *
 * @since 3.0.0
 */
class WC_Braintree_Payment_Method extends Framework\SV_WC_Payment_Gateway_Payment_Token {


	/** Credit card payment method type */
	const CREDIT_CARD_TYPE = 'credit_card';

	/** Paypal payment method type */
	const PAYPAL_TYPE = 'paypal';

	/** ACH Direct Debit payment method type */
	const ACH_TYPE = 'ach';

	/** Venmo payment method type */
	const VENMO_TYPE = 'venmo';


	/**
	 * Gets the billing address ID associated with the credit card.
	 *
	 * @since 3.0.0
	 * @return string|null
	 */
	public function get_billing_address_id() {

		return ! empty( $this->data['billing_address_id'] ) ? $this->data['billing_address_id'] : null;
	}


	/**
	 * Determines if the payment method is for a PayPal account.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_paypal_account() {

		return self::PAYPAL_TYPE === ( $this->data['type'] ?? null );
	}


	/**
	 * Determines if the payment method is for a Venmo account.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	public function is_venmo_account() {

		return self::VENMO_TYPE === ( $this->data['type'] ?? null );
	}


	/**
	 * Determines if the payment method is for an ACH Direct Debit account.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_ach_account() {

		return self::ACH_TYPE === ( $this->data['type'] ?? null );
	}


	/**
	 * Overrides the standard type full method to change the type text to the email address associated with the PayPal account,
	 * the username associated with the Venmo account, or "ACH Direct Debit" for ACH accounts.
	 *
	 * @since 3.0.0
	 *
	 * @return string|void
	 */
	public function get_type_full() {

		if ( $this->is_paypal_account() ) {
			return $this->get_payer_email();
		}

		if ( $this->is_venmo_account() ) {
			return $this->get_venmo_username();
		}

		if ( $this->is_ach_account() ) {
			return __( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		return parent::get_type_full();
	}


	/**
	 * Gets the email associated with the PayPal account
	 *
	 * @since 3.0.0
	 *
	 * @return string|null
	 */
	public function get_payer_email() {

		return ! empty( $this->data['payer_email'] ) ? $this->data['payer_email'] : null;
	}


	/**
	 * Gets the payer ID associated with the PayPal account
	 *
	 * @since 3.0.0
	 *
	 * @return string|null
	 */
	public function get_payer_id() {

		return ! empty( $this->data['payer_id'] ) ? $this->data['payer_id'] : null;
	}


	/**
	 * Gets the username associated with the Venmo account.
	 *
	 * @since 3.6.0
	 *
	 * @return string|null
	 */
	public function get_venmo_username() {

		return ! empty( $this->data['username'] ) ? $this->data['username'] : null;
	}


	/**
	 * Gets the user ID associated with the Venmo account.
	 *
	 * @since 3.6.0
	 *
	 * @return string|null
	 */
	public function get_venmo_user_id() {

		return $this->data['user_id'] ?? null;
	}


	/**
	 * Gets the display name (nickname) for the payment method.
	 *
	 * For ACH tokens, delegates to the WooCommerce core token's get_nickname() method
	 * which returns a formatted string with bank name and last four digits.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_nickname() {

		$wc_token = $this->get_woocommerce_payment_token();

		// For ACH tokens, use the WooCommerce core token's get_nickname() which includes bank name + last four.
		if ( $wc_token instanceof WC_Payment_Token_Braintree_ACH && method_exists( $wc_token, 'get_nickname' ) ) {
			return $wc_token->get_nickname();
		}

		return parent::get_nickname();
	}


	/**
	 * Gets the last four digits of the payment method.
	 *
	 * For ACH accounts, returns empty since the last four is already displayed
	 * in the nickname/title column along with the bank name.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_last_four() {

		// For ACH accounts, don't display last four in the details column
		// since it's already shown in the title/nickname.
		if ( $this->is_ach_account() ) {
			return '';
		}

		return parent::get_last_four();
	}


	/**
	 * Gets the framework token type based on the type of the associated WooCommerce core token.
	 *
	 * @since 2.5.0
	 *
	 * @param \WC_Payment_Token $token WooCommerce core token.
	 *
	 * @return string
	 */
	protected function get_type_from_woocommerce_payment_token( \WC_Payment_Token $token ) {

		if ( $token instanceof WC_Payment_Token_Braintree_PayPal ) {
			return self::PAYPAL_TYPE;
		}

		if ( $token instanceof WC_Payment_Token_Braintree_ACH ) {
			return self::ACH_TYPE;
		}

		if ( $token instanceof WC_Payment_Token_Braintree_Venmo ) {
			return self::VENMO_TYPE;
		}

		return parent::get_type_from_woocommerce_payment_token( $token );
	}


	/**
	 * Creates the WooCommerce core payment token object that store the data of this framework token.
	 *
	 * @since 2.5.0
	 *
	 * @return \WC_Payment_Token
	 */
	protected function make_new_woocommerce_payment_token() {

		if ( $this->is_paypal_account() ) {
			return new WC_Payment_Token_Braintree_PayPal();
		}

		if ( $this->is_ach_account() ) {
			return new WC_Payment_Token_Braintree_ACH();
		}

		if ( $this->is_venmo_account() ) {
			return new WC_Payment_Token_Braintree_Venmo();
		}

		return parent::make_new_woocommerce_payment_token();
	}
}
