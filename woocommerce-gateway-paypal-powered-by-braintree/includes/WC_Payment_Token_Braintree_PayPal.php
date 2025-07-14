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
 * @copyright Copyright: (c) 2016-2021, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce PayPal Payment Token provided by Braintree.
 *
 * Representation of a payment token for PayPal accounts.
 *
 * @since 2.5.0
 */
class WC_Payment_Token_Braintree_PayPal extends \WC_Payment_Token {


	/**
	 * Payment Token Type.
	 *
	 * @var string
	 */
	protected $type = 'Braintree_PayPal';

	/**
	 * PayPal payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'payer_email' => '',
		'payer_id'    => '',
	];
}
