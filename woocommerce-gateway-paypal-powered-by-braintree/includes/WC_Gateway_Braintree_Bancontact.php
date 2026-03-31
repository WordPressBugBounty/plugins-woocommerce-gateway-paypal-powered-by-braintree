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
 * @package   WC-Braintree/Gateway/Bancontact
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Bancontact Gateway Class.
 *
 * @since 3.9.0
 */
class WC_Gateway_Braintree_Bancontact extends WC_Gateway_Braintree_Local_Payment {


	/**
	 * Initialize the gateway.
	 *
	 * @since 3.9.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::BANCONTACT_GATEWAY_ID,
			[
				'method_title'       => __( 'Braintree (Bancontact)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to pay using Bancontact via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'currencies'         => [ 'EUR' ],
			]
		);
	}


	/**
	 * Gets the Braintree SDK payment type string.
	 *
	 * @inheritDoc
	 */
	public function get_local_payment_type(): string {

		return 'bancontact';
	}


	/**
	 * Gets the human-readable display name.
	 *
	 * @inheritDoc
	 */
	public function get_local_payment_display_name(): string {

		return 'Bancontact';
	}


	/**
	 * Gets the supported billing countries.
	 *
	 * @inheritDoc
	 */
	public function get_supported_countries(): array {

		return [ 'BE' ];
	}


	/**
	 * Gets the supported currencies.
	 *
	 * @inheritDoc
	 */
	public function get_supported_currencies(): array {

		return [ 'EUR' ];
	}


	/**
	 * Gets the default payment method title.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_default_title() {

		return __( 'Bancontact', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Gets the default payment method description.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	protected function get_default_description() {

		return __( 'Pay securely using Bancontact', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}
}
