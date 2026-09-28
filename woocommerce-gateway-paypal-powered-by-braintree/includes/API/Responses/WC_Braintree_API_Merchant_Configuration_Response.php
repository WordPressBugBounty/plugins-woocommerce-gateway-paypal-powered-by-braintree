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
 * @package   WC-Braintree/Gateway/API/Requests/Client-Token
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Responses;

use SkyVerge\WooCommerce\PluginFramework\v6_2_4 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API Merchant configuration response.
 *
 * This is always preceded by a client token request, as that contains all of the information necessary for the
 * merchant account.
 *
 * @since 2.2.0
 */
class WC_Braintree_API_Merchant_Configuration_Response extends Framework\SV_WC_API_JSON_Response {


	/**
	 * Determines if 3D Secure is enabled for the merchant account.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_enabled() {

		return (bool) $this->threeDSecureEnabled;
	}


	/**
	 * Determines if PayPal is enabled for the merchant account.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_paypal_enabled() {

		return (bool) $this->paypalEnabled;
	}


	/**
	 * Determines if a PayPal sandbox account is linked to the merchant's Braintree sandbox account.
	 *
	 * Braintree sandbox accounts without a linked PayPal sandbox account report
	 * `environmentNoNetwork: true` in their PayPal configuration, which causes the
	 * PayPal Checkout JS SDK to refuse to initialize.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_paypal_sandbox_account_linked() {

		if ( ! isset( $this->paypal->environmentNoNetwork ) ) {
			// assume the account is linked if the configuration is incomplete, so merchants aren't shown false warnings.
			return true;
		}

		return true !== $this->paypal->environmentNoNetwork;
	}


	// TODO: we should be able to check for Apple Pay too.
}
