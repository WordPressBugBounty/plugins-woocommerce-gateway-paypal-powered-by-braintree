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
 * @package   WC-Braintree/Gateway/API/Responses/Venmo-Transaction
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Payment_Method;

defined( 'ABSPATH' ) || exit;

/**
 * Braintree API Venmo Transaction Response Class
 *
 * Handles parsing Venmo transaction responses
 *
 * @see https://developer.paypal.com/braintree/docs/reference/response/transaction#venmo_account
 *
 * @since 3.6.0
 */
class WC_Braintree_API_Venmo_Transaction_Response extends WC_Braintree_API_Transaction_Response {


	/**
	 * Get the authorization code
	 *
	 * @since 3.6.0
	 * @see SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string|null authorization code
	 */
	public function get_authorization_code(): ?string {

		$venmo_account = isset( $this->response->transaction->venmoAccount ) ? (array) $this->response->transaction->venmoAccount : array();
		return $venmo_account['authorizationId'] ?? null;
	}


	/**
	 * Get the Venmo payment token created during this transaction
	 *
	 * @since 3.6.0
	 * @return \WC_Braintree_Payment_Method
	 * @throws Framework\SV_WC_Payment_Gateway_Exception If token is missing.
	 */
	public function get_payment_token() {

		// VenmoAccount is an array, not an object.
		$venmo_account = isset( $this->response->transaction->venmoAccount ) ? (array) $this->response->transaction->venmoAccount : array();

		if ( empty( $venmo_account['token'] ) ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( esc_html__( 'Required Venmo token is missing or empty!', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$data = array(
			'default'  => false,
			'type'     => WC_Braintree_Payment_Method::VENMO_TYPE,
			'username' => $venmo_account['username'] ?? null,
			'user_id'  => $venmo_account['venmoUserId'] ?? null,
		);

		$token = new WC_Braintree_Payment_Method( $venmo_account['token'], $data );

		return $token;
	}
}
