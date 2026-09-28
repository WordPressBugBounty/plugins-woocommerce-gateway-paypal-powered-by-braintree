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
 * @package   WC-Braintree/Gateway/Credit-Card
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use SkyVerge\WooCommerce\PluginFramework\v6_2_4 as Framework;
use SkyVerge\WooCommerce\PluginFramework\v6_2_4\Helpers\OrderHelper;
use WC_Braintree\Payment_Forms\WC_Braintree_Hosted_Fields_Payment_Form;
use WC_Order;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree Credit Card Gateway Class
 *
 * @since 3.0.0
 */
#[AllowDynamicProperties]
class WC_Gateway_Braintree_Credit_Card extends WC_Gateway_Braintree {


	/**
	 * 3D Secure standard mode.
	 *
	 * @var string
	 */
	const THREED_SECURE_MODE_STANDARD = 'standard';

	/**
	 * 3D Secure strict mode.
	 *
	 * @var string
	 */
	const THREED_SECURE_MODE_STRICT = 'strict';

	/**
	 * WC session key used to signal that the next 3D Secure verification
	 * should force a challenge, because a prior attempt in this checkout
	 * session failed specifically because authentication was required.
	 *
	 * @var string
	 */
	const SESSION_KEY_FORCE_3D_SECURE_CHALLENGE = 'wc_braintree_credit_card_force_3ds_challenge';

	/**
	 * Require CSC field.
	 *
	 * @var string
	 */
	protected $require_csc;

	/**
	 * Fraud tool to use.
	 *
	 * @var string
	 */
	protected $fraud_tool;

	/**
	 * Kount merchant ID.
	 *
	 * @var string
	 */
	protected $kount_merchant_id;

	/**
	 * 3D Secure enabled.
	 *
	 * @var string
	 */
	protected $threed_secure_enabled;

	/**
	 * 3D Secure mode, standard or strict.
	 *
	 * @var string
	 */
	protected $threed_secure_mode;

	/**
	 * 3D Secure card types.
	 *
	 * @var array
	 */
	protected $threed_secure_card_types = array();

	/**
	 * 3D Secure available.
	 *
	 * @var bool
	 */
	protected $threed_secure_available;

	/**
	 * Whether this gateway can store credentials.
	 *
	 * The Credit Card gateway is permitted to store its own Braintree connection credentials.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	protected function can_gateway_store_credentials(): bool {
		return true;
	}

	/**
	 * Whether Fastlane is enabled.
	 *
	 * @since 3.7.0
	 *
	 * @var bool
	 */
	protected $enable_fastlane;

	/**
	 * Response currently being handled by do_transaction_failed_result(), if it
	 * is a 3D Secure "authentication required" soft decline. Used to force the
	 * customer-facing message through regardless of the "Detailed Decline
	 * Messages" setting; see is_detailed_customer_decline_messages_enabled().
	 *
	 * @since 3.12.0
	 *
	 * @var Framework\SV_WC_Payment_Gateway_API_Response|null
	 */
	private $authentication_required_decline_response;

	/**
	 * The customer-facing message for the 3D Secure "authentication required" soft
	 * decline currently being handled by do_transaction_failed_result(), if any.
	 *
	 * Captured here because mark_order_as_failed() (vendor) computes this same
	 * message but only queues it via wc_add_notice() for non-REST requests, so
	 * process_payment() has no other way to recover it for block/Store API checkout.
	 *
	 * @since 3.12.0
	 *
	 * @var string|null
	 */
	private $authentication_required_decline_message;

	/**
	 * Initialize the gateway
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		$supports = [
			self::FEATURE_PRODUCTS,
			self::FEATURE_CARD_TYPES,
			self::FEATURE_PAYMENT_FORM,
			self::FEATURE_TOKENIZATION,
			self::FEATURE_CREDIT_CARD_CHARGE,
			self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
			self::FEATURE_CREDIT_CARD_AUTHORIZATION,
			self::FEATURE_CREDIT_CARD_CAPTURE,
			self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
			self::FEATURE_REFUNDS,
			self::FEATURE_VOIDS,
			self::FEATURE_CUSTOMER_ID,
			self::FEATURE_ADD_PAYMENT_METHOD,
			self::FEATURE_TOKEN_EDITOR,
			self::FEATURE_APPLE_PAY,
			self::FEATURE_GOOGLE_PAY,
		];

		parent::__construct(
			WC_Braintree::CREDIT_CARD_GATEWAY_ID,
			wc_braintree(),
			array(
				'method_title'       => __( 'PayPal Enterprise Payments (Credit Card)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to securely pay using their credit card via PayPal Enterprise Payments.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'supports'           => $supports,
				'payment_type'       => self::PAYMENT_TYPE_CREDIT_CARD,
				'environments'       => $this->get_braintree_environments(),
				'shared_settings'    => $this->shared_settings_names,
				'card_types'         => array(
					'VISA'    => 'Visa',
					'MC'      => 'MasterCard',
					'AMEX'    => 'American Express',
					'DISC'    => 'Discover',
					'DINERS'  => 'Diners',
					'MAESTRO' => 'Maestro',
					'JCB'     => 'JCB',
				),
			)
		);

		// sanitize admin options before saving.
		add_filter( 'woocommerce_settings_api_sanitized_fields_braintree_credit_card', array( $this, 'filter_admin_options' ) );

		// get the client token via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token', array( $this, 'ajax_get_client_token' ) );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', array( $this, 'ajax_get_client_token' ) );

		// check whether the next 3D Secure verification should force a challenge, via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_force_3d_secure_challenge', array( $this, 'ajax_should_force_3d_secure_challenge' ) );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_force_3d_secure_challenge', array( $this, 'ajax_should_force_3d_secure_challenge' ) );

		// Disable fail on duplicate payment method for test environment.
		add_filter( 'wc_braintree_api_vault_request_credit_card_options', array( $this, 'disable_fail_on_duplicate_payment_method' ) );

		// Add Fastlane dashboard link after settings are loaded.
		add_filter( 'woocommerce_settings_api_form_fields_' . $this->get_id(), array( $this, 'add_fastlane_dashboard_link' ) );

		// Enqueue wp-api-fetch ahead of every gateway's own asset enqueueing (priority
		// 10), since they all share the 'wc-braintree' script handle and whichever
		// gateway's enqueue_gateway_assets() runs first enqueues it with no deps.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wp_api_fetch' ), 5 );
	}


	/**
	 * Enqueue credit card method specific scripts, currently:
	 *
	 * + Fraud tool library
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		if ( $this->is_available() && $this->is_payment_form_page() ) {

			parent::enqueue_gateway_assets();

			wp_enqueue_script( 'braintree-js-hosted-fields', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/hosted-fields.min.js', array(), WC_Braintree::VERSION, true );

			if ( $this->is_3d_secure_enabled() ) {
				wp_enqueue_script( 'braintree-js-3d-secure', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/three-d-secure.min.js', array(), WC_Braintree::VERSION, true );
			}

			// Load Fastlane SDK and handler script if enabled.
			if ( $this->is_fastlane_enabled() && $this->should_enqueue_gateway_assets() ) {
				wp_enqueue_script( 'braintree-js-fastlane', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/fastlane.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );

				// Load asset file for Fastlane script dependencies.
				$asset_path   = $this->get_plugin()->get_plugin_path() . '/assets/js/frontend/wc-braintree-fastlane.asset.php';
				$version      = WC_Braintree::VERSION;
				$dependencies = array( 'jquery', 'braintree-js-fastlane', 'wc-braintree' );

				if ( file_exists( $asset_path ) ) {
					$asset        = require $asset_path;
					$version      = isset( $asset['version'] ) ? $asset['version'] : $version;
					$dependencies = array_merge( $dependencies, isset( $asset['dependencies'] ) ? $asset['dependencies'] : array() );
				}

				// Enqueue our Fastlane handler script.
				wp_enqueue_script(
					'wc-braintree-fastlane',
					$this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-fastlane.min.js',
					$dependencies,
					$version,
					true
				);

				// Enqueue Fastlane styles.
				wp_enqueue_style(
					'wc-braintree-fastlane',
					$this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-braintree-fastlane.min.css',
					array(),
					WC_Braintree::VERSION
				);
			}

			// Advanced/kount fraud tool.
			if ( $this->is_advanced_fraud_tool_enabled() ) {

				// Enqueue braintree-data.js library (registered in WC_Gateway_Braintree::register_gateway_assets()).
				wp_enqueue_script( 'braintree-js-data-collector' );

				// Adjust the script tag to add async attribute.
				add_filter( 'clean_url', array( $this, 'adjust_fraud_script_tag' ) );

				// This script must be rendered to the page before the braintree-data.js library, hence priority 1.
				add_action( 'wp_print_footer_scripts', array( $this, 'render_fraud_js' ), 1 );
			}
		}
	}


	/**
	 * Enqueues wp-api-fetch, which wc-braintree.coffee's get_force_3d_secure_challenge()
	 * calls directly as window.wp.apiFetch.
	 *
	 * Hooked at an earlier priority than enqueue_gateway_assets() (priority 10, shared by
	 * every Braintree gateway) because all gateways enqueue the same 'wc-braintree' script
	 * handle, and whichever gateway's enqueue_gateway_assets() runs first enqueues it with
	 * no dependencies - there's no way to declare a dependency on it afterward.
	 *
	 * @since 3.12.0
	 */
	public function enqueue_wp_api_fetch() {

		if ( $this->is_available() && $this->is_payment_form_page() && $this->is_3d_secure_enabled() ) {
			wp_enqueue_script( 'wp-api-fetch' );
		}
	}


	/**
	 * Gets the payment form JS localized script params.
	 *
	 * Adds a couple of name params to the framework base.
	 *
	 * @since 2.3.4
	 *
	 * @return array
	 */
	protected function get_payment_form_js_localized_script_params(): array {

		$params = parent::get_payment_form_js_localized_script_params();

		$params['first_name_unsupported_characters'] = esc_html__( 'First name contains unsupported characters', 'woocommerce-gateway-paypal-powered-by-braintree' );
		$params['last_name_unsupported_characters']  = esc_html__( 'Last name contains unsupported characters', 'woocommerce-gateway-paypal-powered-by-braintree' );

		// Add card brand icon URLs for Fastlane.
		$params['card_icons'] = array(
			'visa'       => $this->get_payment_method_image_url( 'visa' ),
			'mastercard' => $this->get_payment_method_image_url( 'mastercard' ),
			'amex'       => $this->get_payment_method_image_url( 'amex' ),
			'discover'   => $this->get_payment_method_image_url( 'discover' ),
			'jcb'        => $this->get_payment_method_image_url( 'jcb' ),
			'maestro'    => $this->get_payment_method_image_url( 'maestro' ),
			'dinersclub' => $this->get_payment_method_image_url( 'dinersclub' ),
			'default'    => $this->get_payment_method_image_url( 'card' ),
		);

		return $params;
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 2.4.0
	 *
	 * @return \WC_Braintree\Payment_Forms\WC_Braintree_Hosted_Fields_Payment_Form
	 */
	protected function init_payment_form_instance() {

		return new WC_Braintree_Hosted_Fields_Payment_Form( $this );
	}


	/**
	 * Add credit card method specific form fields, currently:
	 *
	 * + Fraud tool settings
	 *
	 * @since 3.0.0
	 * @see WC_Gateway_Braintree::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fraud_tool_options = array(
			'basic'    => esc_html__( 'Basic', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'advanced' => esc_html__( 'Advanced (must also enable advanced fraud tools in your Braintree control panel)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		);

		// Kount is only available for manual API connections.
		if ( $this->is_kount_supported() ) {
			$fraud_tool_options['kount_direct'] = esc_html__( 'Kount Direct (need to contact Braintree support to activate this)', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		$fields = array(

			// fraud tools.
			'fraud_settings_title' => array(
				'title' => esc_html__( 'Fraud Settings', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'  => 'title',
			),
			'fraud_tool'           => array(
				'title'       => esc_html__( 'Fraud Tool', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'select',
				'class'       => 'js-fraud-tool wc-enhanced-select',
				'desc_tip'    => esc_html__( 'Select the fraud tool you want to use. Basic is enabled by default and requires no additional configuration. Advanced requires you to enable advanced fraud tools in your Braintree control panel. To use Kount Direct you must contact Braintree support.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				/* translators: Placeholders %1$s - opening HTML <a> link tag, closing HTML </a> link tag */
				'description' => sprintf( esc_html__( 'Read more details on fraud and verification tools in the extension %1$sdocumentation%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ), '<a href="' . esc_url( $this->get_plugin()->get_documentation_url() ) . '#fraud-and-verification-tools">', '</a>' ),
				'options'     => $fraud_tool_options,
			),
			'kount_merchant_id'    => array(
				'title'    => esc_html__( 'Kount merchant ID', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'     => 'text',
				'class'    => 'js-kount-merchant-id',
				'desc_tip' => esc_html__( 'Speak with your account management team at Braintree to get this.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			),
		);

		$fields = array_merge( $fields, $this->get_3d_secure_fields() );

		$fields['fastlane_settings_title'] = [
			'title' => esc_html__( 'PayPal Fastlane', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type'  => 'title',
		];

		$fields['enable_fastlane'] = [
			'title'       => esc_html__( 'Enable Fastlane', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Enable Fastlane when available', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'description' => esc_html__( 'Fastlane must be activated in your PayPal Fastlane Dashboard. This setting only controls whether Fastlane is used on your site once it is enabled in PayPal.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'default'     => 'no',
		];

		return array_merge( parent::get_method_form_fields(), $fields );
	}


	/**
	 * Gets the 3D Secure settings fields.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	protected function get_3d_secure_fields() {

		// Braintree declares 3D Secure support for AMEX, Maestro, MasterCard, and Visa.
		$card_types = $default_card_types = array(
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX       => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX ),
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MAESTRO    => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MAESTRO ),
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_MASTERCARD ),
			Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA       => Framework\SV_WC_Payment_Gateway_Helper::payment_type_to_name( Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_VISA ),
		);

		// exclude American Express by default, since that requires additional merchant configuration, but still let people enabled it.
		unset( $default_card_types[ Framework\SV_WC_Payment_Gateway_Helper::CARD_TYPE_AMEX ] );

		$fields = array(
			'threed_secure_title'             => array(
				'title'       => esc_html__( '3D Secure', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'title',
				/* translators: Placeholders %1$s - opening HTML <a> link tag, closing HTML </a> link tag */
				'description' => sprintf( esc_html__( '3D Secure benefits cardholders and merchants by providing an additional layer of verification using Verified by Visa, MasterCard SecureCode, and American Express SafeKey. %1$sLearn more about 3D Secure%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ), '<a href="' . esc_url( $this->get_plugin()->get_documentation_url() ) . '#3d-secure' . '">', '</a>' ),
			),
			'threed_secure_mode'              => array(
				'title'   => esc_html__( 'Level', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'label'   => esc_html__( 'Only accept payments when the liability is shifted', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'default' => self::THREED_SECURE_MODE_STANDARD,
				'options' => array(
					self::THREED_SECURE_MODE_STANDARD => esc_html__( 'Standard', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					self::THREED_SECURE_MODE_STRICT   => esc_html__( 'Strict', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				),
			),
			'threed_secure_card_types'        => array(
				'title'       => esc_html__( 'Supported Card Types', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => esc_html__( '3D Secure validation will only occur for these cards.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'default'     => array_keys( $default_card_types ),
				'options'     => $card_types,
			),
			'threed_secure_request_challenge' => array(
				'title'       => esc_html__( 'Always Request Challenge', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Always request a 3D Secure challenge for one-off transactions', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				/* translators: Placeholders %1$s - opening HTML <a> link tag, %2$s - closing HTML </a> link tag */
				'description' => sprintf( esc_html__( 'When disabled, one-off transactions are handled by your Braintree %1$s3D Secure Rules Manager%2$s, which can grant Low Value or TRA exemptions and skip 3D Secure where eligible. Card-on-file setup, such as subscription sign-ups and saving a card for future use, will still always request a challenge regardless of this setting. Note that a granted exemption does not shift chargeback liability to Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ), '<a href="https://developer.paypal.com/braintree/docs/guides/3d-secure/rules-manager/" target="_blank" rel="noopener noreferrer">', '</a>' ),
				'default'     => 'yes',
			),
		);

		return $fields;
	}


	/**
	 * Override the standard CSC setting to instead indicate that it's a combined
	 * Display & Require CSC setting. Braintree doesn't allow the CSC field to be
	 * present without also requiring it to be populated.
	 *
	 * @since 3.0.0
	 * @param array $form_fields gateway form fields.
	 * @return array $form_fields gateway form fields
	 */
	protected function add_csc_form_fields( $form_fields ) {

		$form_fields['require_csc'] = array(
			'title'   => esc_html__( 'Card Verification (CSC)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'label'   => esc_html__( 'Display and Require the Card Security Code (CVV/CID) field on checkout', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type'    => 'checkbox',
			'default' => 'yes',
		);

		return $form_fields;
	}


	/**
	 * Returns true if the CSC field should be displayed and required at checkout
	 *
	 * @since 3.0.0
	 */
	public function is_csc_required() {

		return 'yes' === $this->require_csc;
	}


	/**
	 * Override the standard CSC enabled method to return the value of the csc_required()
	 * check since enabled/required is the same for Braintree
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function csc_enabled() {

		return $this->is_csc_required();
	}


	/**
	 * Render credit card method specific JS to the settings page, currently:
	 *
	 * + Hide/show Fraud tool kount merchant ID setting
	 *
	 * @since 3.0.0
	 * @see WC_Gateway_Braintree::admin_options()
	 */
	public function admin_options() {

		parent::admin_options();

		// Fraud tool show/hide (Kount merchant ID row) is handled in wc-braintree.js.

		// 3D Secure setting handler
		ob_start();
		?>
		( function( $ ) {
			$( function() {
				if ( ! <?php echo (int) $this->is_3d_secure_available(); ?> ) {
					$( '#woocommerce_braintree_credit_card_threed_secure_title' ).hide().next( 'p' ).hide().next( 'table' ).hide();
				}
			} );
		} )( jQuery );
		<?php
		$javascript = ob_get_clean();
		WC_Braintree::enqueue_inline_script( 'wc-braintree-credit-card-admin-threedsecure', $javascript );
	}


	/**
	 * Returns true if the payment nonce is provided when not using a saved
	 * payment token. Note this can't be moved to the parent class because
	 * validation is payment-type specific.
	 *
	 * @since 3.0.0
	 * @param boolean $is_valid true if the fields are valid, false otherwise.
	 * @return boolean true if the fields are valid, false otherwise
	 */
	protected function validate_credit_card_fields( $is_valid ) {

		return $this->validate_payment_nonce( $is_valid );
	}


	/**
	 * Returns true if the payment nonce is provided when using a saved payment method
	 * and CSC is required.
	 *
	 * @since 3.2.0
	 * @param string $csc the card security code.
	 * @return bool
	 */
	protected function validate_csc( $csc ) {

		return $this->validate_payment_nonce( true );
	}


	/**
	 * Add credit card specific data to the order, primarily for 3DS support
	 *
	 * 1) $order->payment->is_3ds_required - require 3DS for every transaction
	 * 2) $order->payment->use_3ds_nonce - use nonce instead of token for transaction
	 *
	 * @since 3.0.0
	 * @param \WC_Order|int $order order.
	 * @return \WC_Order
	 */
	public function get_order( $order ) {

		$order = parent::get_order( $order );

		$payment = OrderHelper::get_payment( $order );

		// Ensure the card type is normalized to FW format.
		if ( empty( $payment->card_type ) ) {
			$payment->card_type = Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-card-type' ) );

			// Set payment info on the order object.
			OrderHelper::set_payment( $order, $payment );
		}

		// Add information for 3DS transactions, note that server-side verification
		// has already been checked in validate_fields() and passed.
		if ( $this->is_3d_secure_enabled() && Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-3d-secure-enabled' ) && ( ! $payment->card_type || $this->card_type_supports_3d_secure( $payment->card_type ) ) ) {

			// Indicate if 3DS should be required for every transaction -- note
			// this will result in a gateway rejection for *every* transaction
			// that doesn't have a liability shift.
			$payment->is_3ds_required = $this->is_3d_secure_liability_shift_always_required();

			// When using a saved payment method for a transaction that has been
			// 3DS verified, indicate the nonce should be used instead, which
			// passes the 3DS verification details to Braintree.
			if ( Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-3d-secure-verified' ) && ! empty( $payment->token ) && ! empty( $payment->nonce ) ) {
				$payment->use_3ds_nonce = true;
			}

			// Set payment info on the order object.
			OrderHelper::set_payment( $order, $payment );
		}

		return $order;
	}


	/**
	 * Overrides the parent method to set the payment information that is
	 * usually set prior to payment with a direct gateway. Because Braintree uses
	 * a nonce, we don't have access to the card info (last four, expiry date, etc)
	 * until after the transaction is processed.
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::do_credit_card_transaction()
	 * @param \WC_Order                                          $order the order object.
	 * @param \WC_Braintree_API_Credit_Card_Transaction_Response $response optional credit card transaction response.
	 * @return \WC_Braintree_API_Credit_Card_Transaction_Response
	 * @throws Framework\SV_WC_Plugin_Exception
	 */
	protected function do_credit_card_transaction( $order, $response = null ) {

		if ( is_null( $response ) ) {

			$response = $this->perform_credit_card_charge( $order ) ? $this->get_api()->credit_card_charge( $order ) : $this->get_api()->credit_card_authorization( $order );

			if ( $response->transaction_approved() ) {
				$payment                 = OrderHelper::get_payment( $order );
				$payment->account_number = $response->get_masked_number();
				$payment->last_four      = $response->get_last_four();
				$payment->card_type      = Framework\SV_WC_Payment_Gateway_Helper::card_type_from_account_number( $response->get_masked_number() );
				$payment->exp_month      = $response->get_exp_month();
				$payment->exp_year       = $response->get_exp_year();

				// Set payment info on the order object.
				OrderHelper::set_payment( $order, $payment );
			}
		}

		return parent::do_credit_card_transaction( $order, $response );
	}


	/**
	 * Adds any gateway-specific transaction data to the order, for credit cards
	 * this is:
	 *
	 * + risk data (if available)
	 * + 3D Secure data (if available)
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::add_transaction_data()
	 * @param \WC_Order                                          $order the order object.
	 * @param \WC_Braintree_API_Credit_Card_Transaction_Response $response transaction response.
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		// add risk data.
		if ( $this->is_advanced_fraud_tool_enabled() && $response->has_risk_data() ) {
			$this->update_order_meta( $order, 'risk_id', $response->get_risk_id() );
			$this->update_order_meta( $order, 'risk_decision', $response->get_risk_decision() );
		}

		// add 3D secure data.
		if ( $this->is_3d_secure_enabled() && $response->has_3d_secure_info() ) {
			$this->update_order_meta( $order, 'threeds_status', $response->get_3d_secure_status() );
		}
	}


	/** Apple Pay Methods *********************************************************************************************/


	/**
	 * Gets the order for Apple Pay transactions.
	 *
	 * @since 2.2.0
	 *
	 * @param \WC_Order                                                  $order order object.
	 * @param Framework\SV_WC_Payment_Gateway_Apple_Pay_Payment_Response $response response object.
	 * @return \WC_Order
	 */
	public function get_order_for_apple_pay( \WC_Order $order, Framework\SV_WC_Payment_Gateway_Apple_Pay_Payment_Response $response ) {

		$order = parent::get_order_for_apple_pay( $order, $response );

		$payment = OrderHelper::get_payment( $order );

		/** @var \WC_Braintree\Apple_Pay\API\Payment_Response $response */
		$payment->nonce = $response->get_braintree_nonce();

		// Set payment info on the order object.
		OrderHelper::set_payment( $order, $payment );

		return $order;
	}

	/** Google Pay Methods *********************************************************************************************/

	/**
	 * Gets the order for Google Pay transactions.
	 *
	 * @since 3.4.0
	 *
	 * @param \WC_Order   $order order object.
	 * @param mixed|array $response authorized payment response data.
	 * @return \WC_Order
	 */
	public function get_order_for_google_pay( WC_Order $order, $response ): WC_Order {

		$order = parent::get_order_for_google_pay( $order, $response );

		$payment = OrderHelper::get_payment( $order );

		// SkyVerge does not have a Google Pay response object wrapper, so we need to parse the tokenization data manually.
		if ( isset( $response ) && is_array( $response ) ) {
			$token = json_decode( $response['paymentMethodData']['tokenizationData']['token'] ?? '', true );
			if ( is_array( $token ) && isset( $token['androidPayCards'][0] ) ) {
				$payment->nonce = $token['androidPayCards'][0]['nonce'] ?? null;

				// Set payment info on the order object.
				OrderHelper::set_payment( $order, $payment );
			}
		}

		return $order;
	}

	/** Refund/Void feature ***************************************************/


	/**
	 * Void a transaction instead of refunding when it has a submitted for settlement
	 * status. Note that only credit card transactions are eligible for this, as
	 * PayPal transactions are settled immediately
	 *
	 * @since 3.0.0
	 * @param \WC_Order                  $order order.
	 * @param \WC_Braintree_API_Response $response refund response.
	 * @return bool true if the transaction should be transaction
	 */
	protected function maybe_void_instead_of_refund( $order, $response ) {

		// Braintree conveniently returns a validation error code that indicates a void can be performed instead of refund.
		return $response->has_validation_errors() && in_array( \Braintree\Error\Codes::TRANSACTION_CANNOT_REFUND_UNLESS_SETTLED, array_keys( $response->get_validation_errors() ) );
	}


	/** Add Payment Method feature ********************************************/


	/**
	 * Save verification transactional data when a customer
	 * adds a new credit via the add payment method flow
	 *
	 * @since 3.0.0
	 * @param \WC_Braintree_API_Customer_Response|\WC_Braintree_API_Payment_Method_Response $response Payment method response.
	 * @return array
	 */
	protected function get_add_payment_method_payment_gateway_transaction_data( $response ) {

		$data = array();

		// transaction ID.
		if ( $response->get_transaction_id() ) {
			$data['trans_id'] = $response->get_transaction_id();
		}

		if ( $this->is_advanced_fraud_tool_enabled() && $response->has_risk_data() ) {
			$data['risk_id']       = $response->get_risk_id();
			$data['risk_decision'] = $response->get_risk_decision();
		}

		return $data;
	}


	/** Fraud Tool feature ****************************************************/


	/**
	 * Renders the fraud tool script.
	 *
	 * Note this is hooked to load at high priority (1) so that it's rendered prior to the braintree.js/braintree-data.js scripts being loaded
	 *
	 * @link https://developers.braintreepayments.com/guides/advanced-fraud-tools/overview
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	public function render_fraud_js() {

		$environment = 'BraintreeData.environments.' . ( $this->is_test_environment() ? 'sandbox' : 'production' );

		if ( $this->is_kount_direct_enabled() && $this->get_kount_merchant_id() ) {
			$environment .= '.withId( kount_id )'; // kount_id will be defined before this is output.
		}

		// TODO: consider moving this to it's own file.

		?>
		<script>
			( function( $ ) {

				var form_id;
				var kount_id = '<?php echo esc_js( $this->get_kount_merchant_id() ); ?>';

				if ( $( 'form.checkout' ).length ) {

					// checkout page
					// WC does not set a form ID, use an existing one if available
					form_id = $( 'form.checkout' ).attr( 'id' ) || 'checkout';

					// otherwise set it ourselves
					if ( 'checkout' === form_id ) {
						$( 'form.checkout' ).attr( 'id', form_id );
					}

				} else if ( $( 'form#order_review' ).length ) {

					// checkout > pay page
					form_id = 'order_review'

				} else if ( $( 'form#add_payment_method' ).length ) {

					// add payment method page
					form_id = 'add_payment_method'
				}

				if ( ! form_id ) {
					return;
				}

				window.onBraintreeDataLoad = function () {
					BraintreeData.setup( '<?php echo esc_js( $this->get_merchant_id() ); ?>', form_id, <?php echo esc_js( $environment ); ?> );
				}

			} ) ( jQuery );
		</script>
		<?php
	}


	/**
	 * Add an async attribute to the braintree-data.js script tag, there's no
	 * way to do this when enqueuing so it must be done manually here
	 *
	 * @since 3.0.0
	 * @param string $url cleaned URL from esc_url().
	 * @return string
	 */
	public function adjust_fraud_script_tag( $url ) {

		if ( Framework\SV_WC_Helper::str_exists( $url, 'braintree-data.js' ) ) {

			$url = "{$url}' async='true";
		}

		return $url;
	}


	/**
	 * Return the enabled fraud tool setting, either 'basic', 'advanced', or
	 * 'kount_direct'
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_fraud_tool() {

		return $this->fraud_tool;
	}


	/**
	 * Return true if advanced fraud tools are enabled (either advanced or
	 * kount direct)
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_advanced_fraud_tool_enabled() {

		return 'advanced' === $this->get_fraud_tool() || 'kount_direct' === $this->get_fraud_tool();
	}


	/**
	 * Return true if the Kount Direct fraud tool is enabled
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_kount_direct_enabled() {

		return $this->is_kount_supported() && 'kount_direct' === $this->get_fraud_tool();
	}


	/**
	 * Get the Kount merchant ID, only used when the Kount Direct fraud tool
	 * is enabled
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_kount_merchant_id() {

		return $this->kount_merchant_id;
	}


	/**
	 * Determines if Kount is supported.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public function is_kount_supported() {

		return $this->is_connected_manually();
	}


	/** 3D Secure feature *****************************************************/


	/**
	 * Determines if 3D Secure is available for the merchant account.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_available() {

		if ( null === $this->threed_secure_available ) {

			// we assume this is true so users aren't locked out when there are API issues.
			$this->threed_secure_available = true;

			if ( $this->is_configured() ) {

				// try and get the remote merchant configuration so the settings accurately display which services are available.
				try {

					$response = $this->get_api()->get_merchant_configuration();

					$this->threed_secure_available = $response->is_3d_secure_enabled();

				} catch ( Framework\SV_WC_API_Exception $exception ) {

					// there was a problem with the API, so nothing we can do but log the issues.
					$this->add_debug_message( "Could not determine the merchant's 3D Secure configuration. {$exception->getMessage()}" );
				}
			}
		}

		return $this->threed_secure_available;
	}


	/**
	 * Determines if 3D secure is enabled.
	 *
	 * We've removed the 3D Secure setting, and its availability is determined by the connected account, however this
	 * allows users to disable it completely via a filter should they want to.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_enabled() {

		/**
		 * Filters whether 3D Secure is enabled.
		 *
		 * @since 2.2.0
		 *
		 * @param bool $enabled whether 3D Secure is enabled
		 */
		return apply_filters( 'wc_' . $this->get_id() . '_enable_3d_secure', true );
	}


	/**
	 * Determines if 3D Secure is in strict mode.
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_strict() {

		return self::THREED_SECURE_MODE_STRICT === $this->get_3d_secure_mode();
	}


	/**
	 * Gets the currently configured 3D Secure mode.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_3d_secure_mode() {

		return $this->threed_secure_mode;
	}


	/**
	 * Return true if a liability shift is required for *every* 3DS-eligible
	 * transaction (even for those where liability shift wasn't possible, e.g.
	 * the cardholder was not enrolled)
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_liability_shift_always_required() {

		/**
		 * Braintree Credit Card Always Require 3D Secure Liability Shift Filter.
		 *
		 * Allow actors to require a liability shift for every 3DS-eligible
		 * transaction, regardless of whether it was possible or not.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $require
		 * @param \WC_Gateway_Braintree_Credit_Card $this instance
		 * @return bool true to require the liability shift
		 */
		return (bool) apply_filters( 'wc_' . $this->get_id() . '_always_require_3ds_liability_shift', false, $this );
	}


	/**
	 * Determines whether a 3D Secure challenge should be requested by default
	 * for one-off transactions.
	 *
	 * When true (the default, for backward compatibility), `challengeRequested`
	 * is always sent to Braintree, which overrides the merchant's 3D Secure
	 * Rules Manager configuration. When false, Rules Manager governs
	 * challenge, exemption (LVE/TRA), and skip behavior for one-off
	 * transactions.
	 *
	 * Card-on-file establishment flows (subscription sign-up, saving a card
	 * for future use) always request a challenge regardless of this setting.
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function is_3d_secure_challenge_requested_by_default() {

		$request_challenge = 'yes' === $this->get_option( 'threed_secure_request_challenge', 'yes' );

		/**
		 * Braintree Credit Card Request 3D Secure Challenge Filter.
		 *
		 * Allow actors to override whether a 3D Secure challenge is requested
		 * by default for one-off transactions. Returning false lets Braintree's
		 * 3D Secure Rules Manager govern challenge, exemption, and skip
		 * behavior instead.
		 *
		 * @since 3.12.0
		 *
		 * @param bool $request_challenge
		 * @param \WC_Gateway_Braintree_Credit_Card $gateway instance
		 */
		return (bool) apply_filters( 'wc_' . $this->get_id() . '_request_3ds_challenge', $request_challenge, $this );
	}


	/**
	 * Handles a failed transaction response.
	 *
	 * When the failure is a 3D Secure "authentication required" soft decline
	 * (processor response code 2099), the customer-facing message is always
	 * shown, since it is actionable and not sensitive, unlike the decline
	 * details normally gated behind the "Detailed Decline Messages" setting.
	 *
	 * @since 3.12.0
	 *
	 * @see SV_WC_Payment_Gateway_Direct::do_transaction_failed_result()
	 *
	 * @param \WC_Order                                    $order    The order.
	 * @param Framework\SV_WC_Payment_Gateway_API_Response $response The transaction response.
	 * @return bool
	 */
	protected function do_transaction_failed_result( \WC_Order $order, Framework\SV_WC_Payment_Gateway_API_Response $response ) {

		$is_authentication_required_decline = $this->is_3d_secure_authentication_required_decline( $response );

		$this->authentication_required_decline_response = $is_authentication_required_decline ? $response : null;
		$this->authentication_required_decline_message  = $is_authentication_required_decline ? $response->get_user_message() : null;

		if ( $is_authentication_required_decline ) {
			$this->set_force_3d_secure_challenge_on_retry();
		}

		try {
			return parent::do_transaction_failed_result( $order, $response );
		} finally {
			$this->authentication_required_decline_response = null;
		}
	}


	/**
	 * Recovers the 3D Secure "authentication required" decline message for block
	 * checkout, since mark_order_as_failed() (vendor) only queues it via
	 * wc_add_notice() for non-REST requests.
	 *
	 * @since 3.12.0
	 *
	 * @see WC_Gateway_Braintree::process_payment()
	 *
	 * @param int|string $order_id Order ID.
	 * @return array associative array with members 'result' and 'redirect'
	 */
	public function process_payment( $order_id ) {

		$this->authentication_required_decline_message = null;

		$result = parent::process_payment( $order_id );

		if ( isset( $result['result'] ) && 'failure' === $result['result'] && $this->authentication_required_decline_message ) {
			$result['message'] = $this->authentication_required_decline_message;
		}

		return $result;
	}


	/**
	 * Determines whether detailed decline messages should be shown to the customer.
	 *
	 * @since 3.12.0
	 *
	 * @see WC_Gateway_Braintree_Credit_Card::do_transaction_failed_result()
	 *
	 * @return bool
	 */
	public function is_detailed_customer_decline_messages_enabled() {

		if ( null !== $this->authentication_required_decline_response ) {
			return true;
		}

		return parent::is_detailed_customer_decline_messages_enabled();
	}


	/**
	 * Determines whether the given response is a 3D Secure "authentication
	 * required" soft decline, meaning the issuer requires 3D Secure
	 * authentication for the transaction to succeed.
	 *
	 * This can surface two ways:
	 *
	 * - Processor response code `2099`: the transaction reached the processor
	 *   and the issuer soft-declined it, asking for authentication that didn't
	 *   happen. This is the case merchants actually hit, and only once they
	 *   disable "Always Request Challenge" so that frictionless flows and
	 *   Rules Manager exemptions come into play.
	 * - Status code `three_d_secure`: Braintree rejected the transaction at the
	 *   gateway level before it reached the processor. This requires 3D Secure
	 *   to be marked required on the transaction, which only happens when
	 *   is_3d_secure_liability_shift_always_required() is filtered on (it is
	 *   false by default), or when a Rules Manager rule demands it - so it is
	 *   the narrower of the two.
	 *
	 * Returns false when 3D Secure is disabled for this gateway. Without that
	 * guard the customer would be told to "complete the extra verification
	 * step" for a decline whose step can never be presented, and the retry flag
	 * would ask for a challenge that no verification will ever run - an
	 * unbreakable decline loop with misleading copy.
	 *
	 * @since 3.12.0
	 *
	 * @link https://articles.braintreepayments.com/control-panel/transactions/gateway-rejections#3-d-secure
	 * @link https://developer.paypal.com/braintree/docs/reference/general/processor-responses/authorization-responses/#2099
	 *
	 * @param Framework\SV_WC_Payment_Gateway_API_Response $response the transaction response.
	 * @return bool
	 */
	protected function is_3d_secure_authentication_required_decline( Framework\SV_WC_Payment_Gateway_API_Response $response ) {

		if ( ! $this->is_3d_secure_enabled() ) {
			return false;
		}

		return in_array( (string) $response->get_status_code(), array( 'three_d_secure', '2099' ), true );
	}


	/**
	 * Determines if the passed card type supports 3D Secure.
	 *
	 * This checks the card types configured in the settings.
	 *
	 * @since 2.2.0
	 *
	 * @param string $card_type card type.
	 * @return bool
	 */
	public function card_type_supports_3d_secure( $card_type ) {

		return in_array( Framework\SV_WC_Payment_Gateway_Helper::normalize_card_type( $card_type ), $this->get_3d_secure_card_types(), true );
	}


	/**
	 * Gets the card types to validate with 3D Secure.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	public function get_3d_secure_card_types() {

		return (array) $this->get_option( 'threed_secure_card_types' );
	}


	/**
	 * Get a payment nonce for an existing payment token so that 3D Secure verification
	 * can be performed on a saved payment method
	 *
	 * @link https://developers.braintreepayments.com/guides/3d-secure/server-side/php#vaulted-credit-card-nonces
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Braintree_Payment_Method $token payment method.
	 * @return string nonce
	 */
	public function get_3d_secure_nonce_for_token( $token ) {
		$token_data = $this->get_3d_secure_data_for_token( $token );
		return $token_data['nonce'] ?? null;
	}

	/**
	 * Get a payment nonce and bin data for an existing payment token
	 * So that 3D Secure verification can be performed on a saved payment method
	 *
	 * @link https://developers.braintreepayments.com/guides/3d-secure/server-side/php#vaulted-credit-card-nonces
	 *
	 * @since 3.0.4
	 *
	 * @param \WC_Braintree_Payment_Method $token payment method.
	 * @return array 3d secure nonce and bin data
	 */
	public function get_3d_secure_data_for_token( $token ) {
		$data = array(
			'nonce' => null,
			'bin'   => null,
		);

		try {

			$result = $this->get_api()->get_nonce_from_payment_token( $token->get_id() );

			$data['nonce'] = $result->get_nonce();
			$data['bin']   = $result->get_bin();

		} catch ( Framework\SV_WC_Plugin_Exception $e ) {
			$this->add_debug_message( $e->getMessage(), 'error' );
		}

		return $data;
	}


	/**
	 * If 3D Secure is enabled, perform validation of the provided nonce. This
	 * complements the client-side check and must be performed server-side. Note
	 * that this is done in validate_fields() and not a later validation check
	 * as 3D Secure transactions also apply when using a saved payment token.
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::validate_fields()
	 * @return bool true if 3DS validations pass (or 3DS not enabled)
	 */
	public function validate_fields() {

		// this is the entry point of every payment attempt for both classic checkout and the
		// Store API (see Automattic\WooCommerce\StoreApi\Legacy::process_legacy_payment()), so
		// clear any force-a-challenge flag here rather than when it's read. The flag then only
		// ever reflects the attempt that just happened, and can't go stale if a verification
		// never asks for it. do_transaction_failed_result() re-sets it as needed below.
		$this->clear_force_3d_secure_challenge_flag();

		$is_valid = parent::validate_fields();

		// no additional validation if 3D Secure was disabled
		// we check both the gateway method (filtered) and if the client-side JS validated 3D Secure (hidden input).
		if ( ! $is_valid || ! $this->is_3d_secure_enabled() || ! Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-3d-secure-enabled' ) ) {
			return $is_valid;
		}

		$card_type = Framework\SV_WC_Helper::get_posted_value( 'wc-' . $this->get_id_dasherized() . '-card-type' );

		// nonce must always be present for validation.
		if ( Framework\SV_WC_Helper::get_posted_value( 'wc_braintree_credit_card_payment_nonce' ) && ( ! $card_type || $this->card_type_supports_3d_secure( $card_type ) ) ) {

			$error = false;

			try {

				$payment_method = $this->get_api()->get_payment_method_from_nonce( Framework\SV_WC_Helper::get_posted_value( 'wc_braintree_credit_card_payment_nonce' ) );

				if ( $payment_method->has_3d_secure_info() ) {

					$decline_statuses = [
						'authenticate_signature_verification_failed',
						'authenticate_failed',
					];

					if ( $this->is_3d_secure_strict() ) {

						$decline_statuses = array_merge(
							$decline_statuses,
							[
								'unsupported_card',
								'lookup_error',
								'lookup_not_enrolled',
								'authentication_unavailable',
								'authenticate_unable_to_authenticate',
								'authenticate_error',
							]
						);

						if ( $payment_method->get_3d_secure_liability_shift_possible() && ! $payment_method->get_3d_secure_liability_shifted() ) {
							$decline_statuses[] = 'lookup_enrolled';
						}
					}

					if ( in_array( $payment_method->get_3d_secure_status(), $decline_statuses, true ) ) {
						$error = $this->get_3d_secure_decline_message( $payment_method->get_3d_secure_status() );
					}
				}
			} catch ( Framework\SV_WC_Plugin_Exception $e ) {

				$this->add_debug_message( $e->getMessage(), 'error' );

				$error = esc_html__( 'Oops, there was a temporary payment error. Please try another payment method or contact us to complete your transaction.', 'woocommerce-gateway-paypal-powered-by-braintree' );
			}

			if ( $error ) {
				wc_add_notice( $error, 'error' );
				$is_valid = false;
			}
		}

		return $is_valid;
	}


	/**
	 * Gets the customer-facing message for a declined 3D Secure status
	 * encountered during validate_fields().
	 *
	 * This is a separate, earlier check than do_transaction_failed_result():
	 * a payment method nonce that already carries a failed 3D Secure status
	 * (e.g. the cardholder failed the authentication challenge) is rejected
	 * here before a transaction is ever attempted, so
	 * is_3d_secure_authentication_required_decline() is never reached for
	 * this case.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status the 3D Secure status, e.g. `authenticate_failed`.
	 * @return string
	 */
	protected function get_3d_secure_decline_message( $status ) {

		if ( $this->is_3d_secure_authentication_failed_status( $status ) ) {
			return esc_html__( 'Your card issuer requires additional verification for this payment. Please try again to complete the extra verification step.', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		return esc_html__( 'We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Determines whether the given 3D Secure status means the cardholder
	 * attempted the challenge and failed it, as opposed to the other
	 * decline_statuses checked in validate_fields(), which are lookup/support
	 * issues unrelated to a failed authentication attempt.
	 *
	 * @since 3.12.0
	 *
	 * @param string $status the 3D Secure status, e.g. `authenticate_failed`.
	 * @return bool
	 */
	protected function is_3d_secure_authentication_failed_status( $status ) {

		return in_array( $status, array( 'authenticate_failed', 'authenticate_signature_verification_failed' ), true );
	}


	/**
	 * Flags, via the customer's WC session, that the next 3D Secure
	 * verification attempt should force a challenge.
	 *
	 * Set only when the authorization itself was declined because
	 * authentication was required (a 2099/three_d_secure decline in
	 * do_transaction_failed_result()), so a retry gives the customer a real
	 * chance to authenticate instead of repeating the same non-forcing
	 * challenge decision.
	 *
	 * Deliberately not set for the pre-transaction authenticate_failed
	 * statuses that validate_fields() rejects: those mean a challenge was
	 * already presented and the cardholder failed it, which is not the
	 * "declined because no challenge happened" case this exists to recover
	 * from. Those statuses still get the actionable message via
	 * get_3d_secure_decline_message().
	 *
	 * @since 3.12.0
	 */
	protected function set_force_3d_secure_challenge_on_retry() {

		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY_FORCE_3D_SECURE_CHALLENGE, true );
		}
	}


	/**
	 * Determines whether the force-a-challenge flag is currently set.
	 *
	 * Reading never consumes the flag, so this is safe to call as often as
	 * needed. The flag is instead cleared at the start of each payment attempt
	 * by clear_force_3d_secure_challenge_flag().
	 *
	 * @since 3.12.0
	 *
	 * @return bool
	 */
	public function has_force_3d_secure_challenge_flag() {

		return (bool) ( WC()->session ? WC()->session->get( self::SESSION_KEY_FORCE_3D_SECURE_CHALLENGE, false ) : false );
	}


	/**
	 * Clears the force-a-challenge flag.
	 *
	 * Called at the start of every payment attempt, so a flag left over from
	 * an earlier attempt can't force an unnecessary challenge later in the
	 * same session - e.g. when the customer abandons checkout after the
	 * decline, or pays with another gateway, and only returns to the card
	 * form some orders later. do_transaction_failed_result() re-sets it if
	 * this attempt hits the authentication-required decline too.
	 *
	 * @since 3.12.0
	 */
	protected function clear_force_3d_secure_challenge_flag() {

		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY_FORCE_3D_SECURE_CHALLENGE, false );
		}
	}


	/**
	 * AJAX handler: whether the next 3D Secure verification should force a challenge.
	 *
	 * Verifies the shared `wp_rest` nonce. On failure, emulates the REST API's
	 * own `rest_cookie_invalid_nonce` response: HTTP 403 with a matching error body.
	 *
	 * @internal
	 *
	 * @since 3.12.0
	 */
	public function ajax_should_force_3d_secure_challenge() {

		$nonce = '';

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {

			wp_send_json(
				array(
					'code'    => 'rest_cookie_invalid_nonce',
					'message' => __( 'Cookie check failed', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'data'    => array( 'status' => 403 ),
				),
				403
			);
		}

		wp_send_json_success(
			array(
				'force_challenge' => $this->has_force_3d_secure_challenge_flag(),
			)
		);
	}


	/**
	 * Disable fail on duplicate payment method for test environment.
	 *
	 * @param array $options The credit card options.
	 * @return array
	 */
	public function disable_fail_on_duplicate_payment_method( $options ) {
		if ( $this->is_test_environment() && isset( $options['failOnDuplicatePaymentMethod'] ) ) {
			$options['failOnDuplicatePaymentMethod'] = false;
		}
		return $options;
	}

	/**
	 * Adds the Fastlane dashboard link to the enable_fastlane field description.
	 *
	 * This filter runs after settings are loaded, so we can access merchant ID.
	 *
	 * @since 3.7.0
	 *
	 * @param array $form_fields The form fields array.
	 * @return array
	 */
	public function add_fastlane_dashboard_link( $form_fields ) {
		if ( ! isset( $form_fields['enable_fastlane'] ) ) {
			return $form_fields;
		}

		$dashboard_url = $this->get_fastlane_dashboard_url();

		if ( $dashboard_url ) {
			$form_fields['enable_fastlane']['description'] .= ' <a href="' . esc_url( $dashboard_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Manage Fastlane in PayPal Dashboard', 'woocommerce-gateway-paypal-powered-by-braintree' ) . '</a>.';
		}

		return $form_fields;
	}

	/**
	 * Gets the Fastlane Dashboard URL.
	 *
	 * @since 3.7.0
	 *
	 * @return string|null The dashboard URL or null if merchant ID is not available.
	 */
	protected function get_fastlane_dashboard_url() {
		$merchant_id = $this->get_merchant_id();

		if ( empty( $merchant_id ) ) {
			return null;
		}

		$environment = $this->get_environment();

		if ( 'sandbox' === $environment ) {
			return sprintf( 'https://sandbox.braintreegateway.com/merchants/%s/customer-checkout', $merchant_id );
		}

		return sprintf( 'https://braintreegateway.com/merchants/%s/customer-checkout', $merchant_id );
	}

	/**
	 * Determines whether PayPal Fastlane is enabled.
	 *
	 * Fastlane is only available for guest shoppers. Logged-in users will see
	 * the regular hosted credit card fields to prevent the store user identity
	 * from conflicting with the Fastlane identity.
	 *
	 * Fastlane is also disabled when the cart contains a subscription and
	 * checkout requires manual password creation (i.e. "Allow subscription
	 * customers to create an account during checkout" is enabled but "Send
	 * password setup link" is not). In that case WooCommerce renders a password
	 * field that Fastlane's UI cannot accommodate.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public function is_fastlane_enabled() {

		// Fastlane is only available to guest shoppers.
		if ( is_user_logged_in() ) {
			return false;
		}

		if ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
			return false;
		}

		// Disable Fastlane when subscription checkout requires manual password creation.
		if (
			$this->cart_contains_subscription()
			&& 'yes' === get_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' )
			&& 'yes' !== get_option( 'woocommerce_registration_generate_password' )
		) {
			return false;
		}

		return 'yes' === $this->enable_fastlane;
	}
}
