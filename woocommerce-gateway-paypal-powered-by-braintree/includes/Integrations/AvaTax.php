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
 * @package   WC-Braintree/Integrations
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Integrations;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;

defined( 'ABSPATH' ) || exit;

/**
 * AvaTax Integration
 *
 * Provides compatibility between Braintree express checkout methods (Apple Pay, Google Pay)
 * and the WooCommerce AvaTax plugin for tax calculation.
 *
 * @since 3.7.0
 */
class AvaTax {

	/**
	 * Constructs the class.
	 *
	 * @since 3.7.0
	 */
	public function __construct() {
		// Only initialize if AvaTax is active.
		if ( ! $this->is_avatax_active() ) {
			return;
		}

		$this->add_hooks();
	}

	/**
	 * Checks if AvaTax plugin is active.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	protected function is_avatax_active(): bool {
		return function_exists( 'wc_avatax' );
	}

	/**
	 * Adds action and filter hooks.
	 *
	 * @since 3.7.0
	 */
	protected function add_hooks() {
		// Enable AvaTax tax calculation for Google Pay AJAX requests.
		// AvaTax has built-in support for Apple Pay but not for Google Pay.
		add_filter( 'wc_avatax_cart_needs_calculation', array( $this, 'enable_calculation_for_google_pay' ) );
	}

	/**
	 * Determines whether AvaTax should calculate taxes for Google Pay AJAX requests.
	 *
	 * AvaTax has built-in support for Apple Pay AJAX requests (checks for '_apple_pay_' in action)
	 * but does not have similar support for Google Pay. This filter enables tax calculation
	 * during Google Pay shipping address selection and other Google Pay AJAX operations.
	 *
	 * @since 3.7.0
	 *
	 * @param bool $needs_calculation Whether AvaTax should calculate taxes.
	 * @return bool
	 */
	public function enable_calculation_for_google_pay( $needs_calculation ) {
		if ( wp_doing_ajax() ) {
			$action = Framework\SV_WC_Helper::get_requested_value( 'action' );
			if ( false !== strpos( $action, '_google_pay_' ) ) {
				return true;
			}
		}

		return $needs_calculation;
	}

	/**
	 * Calculates and applies AvaTax taxes to an order.
	 *
	 * This method should be called after order creation for express checkout flows
	 * (Apple Pay, Google Pay) where the standard checkout flow doesn't properly
	 * apply cart taxes to the order.
	 *
	 * This method uses AvaTax's estimate_tax() which calls calculate_order_tax()
	 * with update_item_taxes=true to properly update order line items and totals.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order The order to calculate taxes for.
	 */
	public static function calculate_order_tax( $order ) {
		if ( function_exists( 'wc_avatax' ) && wc_avatax()->get_order_handler() ) {
			wc_avatax()->get_order_handler()->estimate_tax( $order );
		} else {
			// Fallback for standard WooCommerce tax or other tax plugins.
			$order->calculate_totals( true );
		}
	}
}
