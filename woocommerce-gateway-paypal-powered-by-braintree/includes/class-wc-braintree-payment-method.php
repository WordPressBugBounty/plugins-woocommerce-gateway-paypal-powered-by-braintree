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

use SkyVerge\WooCommerce\PluginFramework\v5_12_7 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Payment Method Class.
 *
 * Extends the framework Payment Token class to provide Braintree-specific functionality like billing addresses and PayPal support.
 *
 * @since 3.0.0
 */
class WC_Braintree_Payment_Method extends Framework\SV_WC_Payment_Gateway_Payment_Token {


	/** credit card payment method type */
	const CREDIT_CARD_TYPE = 'credit_card';

	/** paypal payment method type */
	const PAYPAL_TYPE = 'paypal';


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

		return self::PAYPAL_TYPE === $this->data['type'];
	}


	/**
	 * Overrides the standard type full method to change the type text to the email address associated with the PayPal account.
	 *
	 * @since 3.0.0
	 *
	 * @return string|void
	 */
	public function get_type_full() {

		return $this->is_paypal_account() ? $this->get_payer_email() : parent::get_type_full();
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
	 * Gets the framework token type based on the type of the associated WooCommerce core token.
	 *
	 * @since 2.5.0
	 *
	 * @param \WC_Payment_Token $token WooCommerce core token
	 *
	 * @return string
	 */
	protected function get_type_from_woocommerce_payment_token( \WC_Payment_Token $token ) {

		if ( $token instanceof \WC_Payment_Token_Braintree_PayPal ) {
			return self::PAYPAL_TYPE;
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
			return new \WC_Payment_Token_Braintree_PayPal();
		}

		return parent::make_new_woocommerce_payment_token();
	}


}
