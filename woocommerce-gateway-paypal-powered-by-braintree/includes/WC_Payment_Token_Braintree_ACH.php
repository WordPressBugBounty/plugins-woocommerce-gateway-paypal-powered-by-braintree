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
 * @package   WC-Braintree/Gateway
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce ACH Direct Debit Payment Token provided by Braintree.
 *
 * Representation of a payment token for ACH Direct Debit accounts.
 *
 * @since 3.7.0
 */
class WC_Payment_Token_Braintree_ACH extends \WC_Payment_Token {

	/** Token type identifier */
	const TOKEN_TYPE = 'Braintree_ACH';

	/**
	 * Payment Token Type.
	 *
	 * @var string
	 */
	protected $type = self::TOKEN_TYPE;

	/**
	 * ACH Direct Debit payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'bank_name' => '',
		'last_four' => '',
	];


	/**
	 * Gets the last four digits of the bank account.
	 * When in 'view' context, returns formatted string with bank name if available.
	 *
	 * @since 3.7.0
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_last_four( $context = 'view' ) {
		return $this->get_prop( 'last_four', $context );
	}


	/**
	 * Gets the bank name.
	 *
	 * @since 3.7.0
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_bank_name( $context = 'view' ) {
		return $this->get_prop( 'bank_name', $context );
	}


	/**
	 * Sets the last four digits of the bank account.
	 * Setter accepts 'last4' (from framework) and maps to 'last_four' (in extra_data).
	 *
	 * @since 3.7.0
	 * @param string $last4 Last four digits of the account.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last_four', $last4 );
	}


	/**
	 * Sets the last four digits of the bank account.
	 *
	 * @since 3.7.0
	 * @param string $last_four Last four digits of the account.
	 */
	public function set_last_four( $last_four ) {
		$this->set_prop( 'last_four', $last_four );
	}


	/**
	 * Sets the bank name.
	 *
	 * @since 3.7.0
	 * @param string $bank_name Bank name.
	 */
	public function set_bank_name( $bank_name ) {
		$this->set_prop( 'bank_name', $bank_name );
	}


	/**
	 * Gets the display name for the payment method.
	 *
	 * @since 3.7.0
	 *
	 * @return string Display name for the payment method.
	 */
	public function get_nickname() {
		$bank_name = $this->get_bank_name();
		$last_four = $this->get_last_four();

		if ( $bank_name && $last_four ) {
			return sprintf(
				/* translators: 1: ACH bank name, 2: ACH last 4 digits of the account */
				__( '%1$s &nbsp;&nbsp;&nbsp; • • • %2$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$bank_name,
				$last_four
			);
		} elseif ( $last_four ) {
			return sprintf(
				/* translators: %s: ACH last 4 digits of the account */
				__( 'ACH Direct Debit &nbsp;&nbsp;&nbsp; •••%s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$last_four
			);
		}

		return __( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}
}
