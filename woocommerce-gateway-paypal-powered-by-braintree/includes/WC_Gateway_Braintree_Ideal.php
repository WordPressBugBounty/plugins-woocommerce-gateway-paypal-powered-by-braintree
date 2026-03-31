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
 * @package   WC-Braintree/Gateway/iDEAL
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree iDEAL Gateway Class.
 *
 * Handles iDEAL payments through Braintree's Local Payment Methods.
 *
 * @since 3.9.0
 */
class WC_Gateway_Braintree_Ideal extends WC_Gateway_Braintree_Local_Payment {


	/**
	 * Initialize the gateway.
	 *
	 * @since 3.9.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::IDEAL_GATEWAY_ID,
			[
				'method_title'       => __( 'Braintree (iDEAL | Wero)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to pay using iDEAL | Wero via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			]
		);
	}


	/**
	 * Gets the local payment type string used by the Braintree SDK.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	public function get_local_payment_type(): string {
		return 'ideal';
	}


	/**
	 * Gets the human-readable display name for this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	public function get_local_payment_display_name(): string {
		return 'iDEAL | Wero';
	}


	/**
	 * Gets the billing countries supported by this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string[]
	 */
	public function get_supported_countries(): array {
		return [ 'NL' ];
	}


	/**
	 * Gets the currencies supported by this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string[]
	 */
	public function get_supported_currencies(): array {
		return [ 'EUR' ];
	}


	/**
	 * Gets the default title, shown to the customer at checkout.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_default_title() {
		return __( 'iDEAL | Wero', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Gets the default description, shown to the customer at checkout.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_default_description() {
		return __( 'Pay securely using iDEAL | Wero', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}
}
