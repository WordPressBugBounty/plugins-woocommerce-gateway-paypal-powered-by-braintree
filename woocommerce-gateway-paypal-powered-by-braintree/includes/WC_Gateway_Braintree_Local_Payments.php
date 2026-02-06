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
 * @package   WC-Braintree/Gateway/Local-Payments
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Local Payments Gateway Class
 *
 * @since 3.7.0
 */
class WC_Gateway_Braintree_Local_Payments extends WC_Gateway_Braintree {

	/** Local Payments payment type */
	const PAYMENT_TYPE_LOCAL_PAYMENTS = 'local_payments';

	/**
	 * Initialize the gateway
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::LOCAL_PAYMENTS_GATEWAY_ID,
			wc_braintree(),
			array(
				'method_title'       => __( 'Braintree (Local Payment Methods) - [Early Access]', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to securely pay using Local Payment Methods via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_PAYMENT_FORM,
					self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_CUSTOMER_ID,
				),
				'payment_type'       => self::PAYMENT_TYPE_LOCAL_PAYMENTS,
				'environments'       => $this->get_braintree_environments(),
				'shared_settings'    => $this->shared_settings_names,
				'currencies'         => array( 'USD' ),
			)
		);

		// Sanitize admin options before saving.
		add_filter( 'woocommerce_settings_api_sanitized_fields_braintree_local_payments', [ $this, 'filter_admin_options' ] );

		// Get the client token via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );
	}


	/**
	 * Enqueues Local Payments gateway specific scripts.
	 *
	 * @since 3.7.0
	 *
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		if ( ! $this->is_available() || ! $this->is_payment_form_page() ) {
			return;
		}

		parent::enqueue_gateway_assets();

		// Enqueue Braintree Local Payments library.
		wp_enqueue_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', [], WC_Braintree::VERSION, true );
		wp_enqueue_script( 'braintree-js-local-payments', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/local-payment.min.js', [ 'braintree-js-client' ], WC_Braintree::VERSION, true );
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 3.7.0
	 *
	 * @return \WC_Braintree\Payment_Forms\WC_Braintree_Local_Payments_Payment_Form
	 */
	protected function init_payment_form_instance() {
		// TODO: Implement payment form.
		return null;
	}


	/**
	 * Gets the method form fields.
	 *
	 * @since 3.7.0
	 *
	 * @see WC_Gateway_Braintree::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$form_fields = parent::get_method_form_fields();

		// Remove dynamic descriptors section (not supported for Local Payments).
		unset( $form_fields['dynamic_descriptor_title'] );
		unset( $form_fields['name_dynamic_descriptor'] );
		unset( $form_fields['phone_dynamic_descriptor'] );
		unset( $form_fields['url_dynamic_descriptor'] );

		return $form_fields;
	}

	/** Getters ***************************************************************/


	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout.
	 *
	 * @see SV_WC_Payment_Gateway::get_default_title()*
	 *
	 * @since 3.7.0
	 *
	 * @return string The payment method title to show on checkout.
	 */
	protected function get_default_title() {
		return __( 'Local Payment Methods', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout.
	 *
	 * @see SV_WC_Payment_Gateway::get_default_description()
	 *
	 * @since 3.7.0
	 *
	 * @return string The payment method description to show on checkout.
	 */
	protected function get_default_description() {
		return __( 'Pay securely using Local Payment Methods', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}
}
