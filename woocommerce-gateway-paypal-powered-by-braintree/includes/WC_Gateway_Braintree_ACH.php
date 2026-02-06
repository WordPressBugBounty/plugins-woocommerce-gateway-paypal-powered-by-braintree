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
 * @package   WC-Braintree/Gateway/ACH
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use Automattic\WooCommerce\Enums\OrderStatus;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree ACH Gateway Class
 *
 * @since 3.7.0
 */
class WC_Gateway_Braintree_ACH extends WC_Gateway_Braintree {

	/** ACH payment type */
	const PAYMENT_TYPE_ACH = 'ach';

	/**
	 * Initialize the gateway
	 *
	 * @since 3.7.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::ACH_GATEWAY_ID,
			wc_braintree(),
			array(
				'method_title'       => __( 'Braintree (ACH Direct Debit) - [Early Access]', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to securely pay using ACH Direct Debit via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_PAYMENT_FORM,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_CUSTOMER_ID,
					self::FEATURE_ADD_PAYMENT_METHOD,
					self::FEATURE_TOKEN_EDITOR,
				),
				'payment_type'       => self::PAYMENT_TYPE_ACH,
				'environments'       => $this->get_braintree_environments(),
				'shared_settings'    => $this->shared_settings_names,
				'currencies'         => array( 'USD' ),
			)
		);

		// Sanitize admin options before saving.
		add_filter( 'woocommerce_settings_api_sanitized_fields_braintree_ach', [ $this, 'filter_admin_options' ] );

		// Enable display of ACH payment methods in My Account.
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'set_brand_info_in_payment_method_list' ], 10, 2 );

		// Adjust the admin token editor to support ACH accounts.
		add_filter( 'wc_payment_gateway_braintree_ach_token_editor_fields', [ $this, 'adjust_token_editor_fields' ] );

		// Get the client token via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );
	}


	/**
	 * Enqueues ACH gateway specific scripts.
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

		// Enqueue Braintree ACH library.
		wp_enqueue_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', [], WC_Braintree::VERSION, true );
		wp_enqueue_script( 'braintree-js-ach', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/us-bank-account.min.js', [ 'braintree-js-client' ], WC_Braintree::VERSION, true );
		wp_enqueue_script( 'braintree-js-data-collector', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/data-collector.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );

		// Load dependencies from webpack asset file.
		$asset_path   = $this->get_plugin()->get_plugin_path() . '/assets/js/frontend/wc-braintree-ach.asset.php';
		$version      = WC_Braintree::VERSION;
		$dependencies = array( 'braintree-js-client', 'braintree-js-ach', 'braintree-js-data-collector' );

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = $asset['version'] ?? $version;
			$dependencies = array_merge( $dependencies, $asset['dependencies'] ?? [] );
		}

		// Enqueue custom ACH Direct Debit payment form handler.
		wp_enqueue_script(
			'wc-braintree-ach-payment-form',
			$this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-ach.min.js',
			$dependencies,
			$version,
			true
		);

		// Enqueue ACH Direct Debit styles.
		wp_enqueue_style(
			'wc-braintree-ach',
			$this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-ach.min.css',
			array(),
			WC_Braintree::VERSION
		);
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 3.7.0
	 *
	 * @return \WC_Braintree\Payment_Forms\WC_Braintree_ACH_Payment_Form
	 */
	protected function init_payment_form_instance() {

		return new Payment_Forms\WC_Braintree_ACH_Payment_Form( $this );
	}


	/**
	 * Gets the method form fields.
	 *
	 * Overrides parent to exclude Merchant Account IDs section (ACH only supports USD).
	 *
	 * @since 3.7.0
	 *
	 * @see WC_Gateway_Braintree::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$form_fields = parent::get_method_form_fields();

		// Note that we keep the merchant account fields for ACH, as the default merchant account
		// may not support ACH.

		// Remove dynamic descriptors section (not supported for ACH).
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

		return __( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );
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

		return __( 'Pay securely using ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Override the default icon to set an ACH-specific one.
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_icon() {

		$icon_html = sprintf(
			'<img src="%s" alt="%s" style="max-height: 26px;" />',
			esc_url( wc_braintree()->get_plugin_url() . '/assets/images/ach.png' ),
			esc_attr__( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' )
		);

		/**
		 * Filters the gateway icon HTML.
		 *
		 * @since 3.5.0
		 *
		 * @param string $icon_html The icon HTML.
		 * @param string $gateway_id The gateway ID.
		 */
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->get_id() );
	}


	/**
	 * Gets the payment method image URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $type The payment method type.
	 * @return string
	 */
	public function get_payment_method_image_url( $type ) {
		return wc_braintree()->get_plugin_url() . '/assets/images/ach.png';
	}


	/**
	 * Override parent to indicate ACH handles tokenization internally.
	 *
	 * ACH has a unique flow where tokenization happens during the verification
	 * step within do_ach_transaction(), not as a separate framework-managed step.
	 * Returning false prevents the SkyVerge framework from attempting to tokenize before the
	 * transaction, which would consume the one-time-use nonce.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function tokenize_before_sale() {
		return false;
	}


	/**
	 * Override parent to indicate ACH handles tokenization internally.
	 *
	 * ACH tokenization occurs during verification within do_ach_transaction(),
	 * not as part of the charge response. Returning false prevents the SkyVerge framework
	 * from trying to extract token information from the charge response.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function tokenize_with_sale() {
		return false;
	}


	/**
	 * Processes an ACH Direct Debit transaction.
	 *
	 * @since 3.7.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return \WC_Braintree\API\Responses\Transaction_Response
	 */
	protected function do_ach_transaction( \WC_Order $order ) {

		// Only verify and tokenize if using a new bank account (not a saved token).
		if ( empty( $order->payment->token ) ) {

			// Verify account and get payment token.
			$response = $this->get_api()->verify_ach_direct_debit_account( $order );

			if ( ! $response->transaction_approved() ) {
				return $response;
			}

			// Set the token from verification response.
			$payment_token         = $response->get_payment_token();
			$order->payment->token = $payment_token->get_id();

			// Save the token to the user's payment methods if tokenization is enabled.
			if ( $this->supports_tokenization() && $this->get_payment_tokens_handler()->should_tokenize() && 0 !== (int) $order->get_user_id() ) {
				$this->get_payment_tokens_handler()->add_token( $order->get_user_id(), $payment_token );
			}
		}

		// Create the ACH charge transaction (using either the newly verified token or the saved token).
		$charge_response = $this->get_api()->ach_charge( $order );

		// If successful, set order to on-hold (ACH settlements take 3-5 business days).
		if ( $charge_response->transaction_approved() ) {
			// Set order status to on-hold - the parent class will see this and skip payment_complete().
			$order->update_status(
				OrderStatus::ON_HOLD,
				__( 'ACH Direct Debit payment submitted. Awaiting bank settlement (3-5 business days).', 'woocommerce-gateway-paypal-powered-by-braintree' )
			);
		}

		return $charge_response;
	}


	/**
	 * Tweaks the display of ACH payment methods in My Account > Payment Methods to set brand info.
	 *
	 * @since 3.7.0
	 *
	 * @param array             $item       Payment method list item.
	 * @param \WC_Payment_Token $core_token WooCommerce payment token.
	 * @return array
	 */
	public function set_brand_info_in_payment_method_list( $item, $core_token ) {

		if ( ! $core_token instanceof WC_Payment_Token_Braintree_ACH ) {
			return $item;
		}

		// Unset any existing icon to prevent WooCommerce from using the default echeck icon.
		unset( $item['method']['icon'] );

		// Set the icon to use the ACH icon instead of the default echeck icon.
		$item['method']['icon'] = sprintf(
			'<img src="%s" alt="%s" style="max-height: 26px;" />',
			esc_url( $this->get_payment_method_image_url( 'ach' ) ),
			esc_attr__( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' )
		);

		$item['method']['brand'] = __( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );

		// Override the payment method type to prevent WooCommerce from displaying "echeck".
		$item['method']['type'] = __( 'ACH Direct Debit', 'woocommerce-gateway-paypal-powered-by-braintree' );

		// Change "Delete" to "Unlink" for consistency with PayPal.
		if ( isset( $item['actions']['delete'] ) ) {
			$item['actions']['delete']['name'] = __( 'Unlink', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		return $item;
	}


	/**
	 * Adjusts the token editor fields for ACH accounts.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public function adjust_token_editor_fields() {

		return [
			'id'        => [
				'label'    => __( 'Token ID', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'editable' => false,
				'required' => true,
			],
			'bank_name' => [
				'label'    => __( 'Bank Name', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'editable' => false,
			],
			'last_four' => [
				'label'      => __( 'Last Four', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'editable'   => false,
				'attributes' => [
					'pattern'   => '[0-9]{4}',
					'maxlength' => 4,
				],
			],
		];
	}
}
