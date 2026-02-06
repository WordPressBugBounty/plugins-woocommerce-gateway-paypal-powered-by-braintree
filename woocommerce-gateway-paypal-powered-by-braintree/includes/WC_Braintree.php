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
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use SkyVerge\WooCommerce\PluginFramework\v5_15_10\SV_WC_Payment_Gateway_Payment_Token;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce Gateway Braintree Main Plugin Class.
 *
 * @since 2.0.0
 */
class WC_Braintree extends Framework\SV_WC_Payment_Gateway_Plugin {


	/** plugin version number */
	const VERSION = '3.7.0'; // WRCS: DEFINED_VERSION.

	/** Braintree JS SDK version  */
	const BRAINTREE_JS_SDK_VERSION = '3.129.1';

	/** @var WC_Braintree single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'braintree';

	/** credit card gateway class name */
	const CREDIT_CARD_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_Credit_Card';

	/** credit card gateway ID */
	const CREDIT_CARD_GATEWAY_ID = 'braintree_credit_card';

	/** PayPal gateway class name */
	const PAYPAL_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_PayPal';

	/** PayPal gateway ID */
	const PAYPAL_GATEWAY_ID = 'braintree_paypal';

	/** Venmo gateway class name */
	const VENMO_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_Venmo';

	/** Venmo gateway ID */
	const VENMO_GATEWAY_ID = 'braintree_venmo';

	/** ACH gateway class name */
	const ACH_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_ACH';

	/** ACH gateway ID */
	const ACH_GATEWAY_ID = 'braintree_ach';

	/** SEPA gateway class name */
	const SEPA_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_SEPA';

	/** SEPA gateway ID */
	const SEPA_GATEWAY_ID = 'braintree_sepa';

	/** Local Payments gateway class name */
	const LOCAL_PAYMENTS_GATEWAY_CLASS_NAME = 'WC_Braintree\\WC_Gateway_Braintree_Local_Payments';

	/** Local Payments gateway ID */
	const LOCAL_PAYMENTS_GATEWAY_ID = 'braintree_local_payments';

	/**
	 * Initializes the plugin
	 *
	 * @since 2.0
	 */
	public function __construct() {

		$gateways = array(
			self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
			self::PAYPAL_GATEWAY_ID      => self::PAYPAL_GATEWAY_CLASS_NAME,
			self::VENMO_GATEWAY_ID       => self::VENMO_GATEWAY_CLASS_NAME,
		);

		// Add ACH gateway if feature flag is enabled.
		if ( WC_Braintree_Feature_Flags::is_ach_enabled() ) {
			$gateways[ self::ACH_GATEWAY_ID ] = self::ACH_GATEWAY_CLASS_NAME;
		}

		// Add SEPA gateway if feature flag is enabled.
		if ( WC_Braintree_Feature_Flags::is_sepa_enabled() ) {
			$gateways[ self::SEPA_GATEWAY_ID ] = self::SEPA_GATEWAY_CLASS_NAME;
		}

		// Add Local Payments gateway if feature flag is enabled.
		if ( WC_Braintree_Feature_Flags::are_local_payments_enabled() ) {
			$gateways[ self::LOCAL_PAYMENTS_GATEWAY_ID ] = self::LOCAL_PAYMENTS_GATEWAY_CLASS_NAME;
		}

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'text_domain'        => 'woocommerce-gateway-paypal-powered-by-braintree',
				'gateways'           => $gateways,
				'require_ssl'        => false,
				'supports'           => array(
					self::FEATURE_CAPTURE_CHARGE,
					self::FEATURE_MY_PAYMENT_METHODS,
					self::FEATURE_CUSTOMER_ID,
				),
				'supported_features' => array(
					'hpos'   => true,
					'blocks' => array(
						'cart'     => true,
						'checkout' => true,
					),
				),
				'dependencies'       => array(
					'php_extensions' => array( 'curl', 'dom', 'hash', 'openssl', 'SimpleXML', 'xmlwriter' ),
				),
			)
		);

		// include required files
		$this->includes();

		// handle Braintree Auth connect/disconnect
		add_action( 'admin_init', [ $this, 'handle_auth_connect' ] );
		add_action( 'admin_init', [ $this, 'handle_auth_disconnect' ] );

		// Filter the payment method for subscriptions. Runs late to ensure it runs after the SkyVerge Subscriptions integration.
		add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_filter_my_subscriptions_payment_method' ), 15, 2 );
		add_action( 'woocommerce_payment_token_class', array( $this, 'filter_payment_token_classname' ), 10, 2 );
		add_filter( 'woocommerce_saved_payment_methods_list', array( $this, 'add_brand_information' ), 99 );
	}

	/**
	 * Adds the `WC_Braintree` namespace when the token id from the Braintree gateway and other than Credit Card.
	 *
	 * @param string $class_name Payment token class.
	 * @param string $type       Token type.
	 *
	 * @return string
	 */
	public function filter_payment_token_classname( $class_name, $type ) {
		$braintree_token_types = [
			WC_Payment_Token_Braintree_PayPal::TOKEN_TYPE,
			WC_Payment_Token_Braintree_Venmo::TOKEN_TYPE,
			WC_Payment_Token_Braintree_ACH::TOKEN_TYPE,
		];

		if ( in_array( $type, $braintree_token_types, true ) ) {
			return 'WC_Braintree\\' . $class_name;
		}

		return $class_name;
	}

	/**
	 * Adds 'brand' data to tokenized PayPal instruments.
	 *
	 * @param array $token_data Array of token data.
	 *
	 * @return array
	 */
	public function add_brand_information( $token_data ) {
		$new_token_array = array();

		foreach ( $token_data as $gateway_id => $tokens ) {
			if ( 'braintree_paypal' !== $gateway_id ) {
				continue;
			}

			foreach ( $tokens as $token ) {
				if ( ! isset( $token['method']['brand'] ) ) {
					$token['method']['brand'] = esc_html__( 'PayPal Account', 'woocommerce-gateway-paypal-powered-by-braintree' );
				}

				$new_token_array[] = $token;
			}
		}

		$token_data['braintree_paypal'] = $new_token_array;

		return $token_data;
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 3.0.6
	 *
	 * @param string           $payment_method_to_display The default payment method text to display.
	 * @param \WC_Subscription $subscription              The subscription object.
	 * @return string The subscription payment method
	 */
	public function maybe_filter_my_subscriptions_payment_method( $payment_method_to_display, $subscription ) {
		$payment_method = $subscription->get_payment_method( 'edit' );

		$supported_gateway_ids = [
			self::PAYPAL_GATEWAY_ID,
			self::VENMO_GATEWAY_ID,
			self::ACH_GATEWAY_ID,
		];

		if ( ! in_array( $payment_method, $supported_gateway_ids, true ) ) {
			return $payment_method_to_display;
		}

		$gateway_id = $payment_method;

		$gateway = $this->get_gateway( $gateway_id );
		$token   = $gateway->get_payment_tokens_handler()->get_token(
			$subscription->get_user_id(),
			$gateway->get_order_meta( $subscription, 'payment_token' )
		);

		if ( ! $token instanceof SV_WC_Payment_Gateway_Payment_Token ) {
			return $payment_method_to_display;
		}

		if ( self::PAYPAL_GATEWAY_ID === $gateway_id ) {
			return sprintf(
				/* translators: %s - PayPal email address */
				esc_html__( 'Via PayPal - %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				esc_html( $token->get_payer_email() )
			);
		}

		if ( self::VENMO_GATEWAY_ID === $gateway_id ) {
			$venmo_username = $token->get_venmo_username();
			if ( empty( $venmo_username ) ) {
				return esc_html__( 'Via Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' );
			}

			return sprintf(
				/* translators: %s - Venmo username */
				esc_html__( 'Via Venmo - %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				esc_html( $venmo_username )
			);
		}

		if ( self::ACH_GATEWAY_ID === $gateway_id ) {
			return sprintf(
				/* translators: %s - ACH account details (bank name and last four digits) */
				esc_html__( 'Via ACH Direct Debit - %s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				esc_html( $token->get_nickname() )
			);
		}

		return $payment_method_to_display;
	}


	/**
	 * Include required files
	 *
	 * @since 2.0
	 */
	public function includes() {
		// integrations
		if ( $this->is_plugin_active( 'woocommerce-product-addons.php' ) ) {
			new \WC_Braintree\Integrations\Product_Addons();
		}

		new \WC_Braintree\Integrations\AvaTax();

		// admin includes.
		if ( is_admin() ) {
			new \WC_Braintree\Admin\Order();

			// Hide Apple Pay and Google Pay tabs when viewing non-Credit Card gateway settings.
			// SkyVerge adds these tabs at priority 99, so we need to run after that.
			add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_checkout_sections' ), 100 );
		}
	}

	/**
	 * Filters the checkout sections to hide Apple Pay and Google Pay tabs when viewing non-Credit Card gateway settings.
	 *
	 * Apple Pay and Google Pay are features of the Credit Card gateway only, so their settings tabs
	 * should only be visible when viewing the Credit Card gateway settings.
	 *
	 * @since 3.6.0
	 *
	 * @param array $sections The checkout sections.
	 * @return array Filtered checkout sections.
	 */
	public function filter_checkout_sections( $sections ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only filter for display purposes.
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		// Sections where Apple Pay and Google Pay tabs should be visible.
		$allowed_sections = array(
			self::CREDIT_CARD_GATEWAY_ID,
			'apple-pay',
			'google-pay',
		);

		// Hide Apple Pay and Google Pay tabs on all other gateway settings pages.
		if ( $current_section && ! in_array( $current_section, $allowed_sections, true ) ) {
			unset( $sections['apple-pay'] );
			unset( $sections['google-pay'] );
		}

		return $sections;
	}

	/**
	 * Gets the deprecated hooks and their replacements, if any.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_deprecated_hooks() {

		$hooks = array(
			'wc_gateway_paypal_braintree_card_icons_image_url' => array(
				'version'     => '2.0.0',
				'removed'     => true,
				'replacement' => 'wc_braintree_credit_card_icon',
				'map'         => true,
			),
			'wc_gateway_paypal_braintree_sale_args' => array(
				'version'     => '2.0.0',
				'removed'     => true,
				'replacement' => 'wc_braintree_transaction_data',
				'map'         => true,
			),
			'wc_gateway_paypal_braintree_data' => array(
				'version'     => '2.0.0',
				'removed'     => true, // TODO: determine if anything can be mapped here
			),
		);

		return $hooks;
	}


	/**
	 * Initializes the plugin lifecycle handler.
	 *
	 * @since 2.2.0
	 */
	public function init_lifecycle_handler() {
		$this->lifecycle_handler = new \WC_Braintree\Lifecycle( $this );
	}


	/**
	 * Handles the Braintree Auth connection response.
	 *
	 * @since 2.0.0
	 */
	public function handle_auth_connect() {

		// if this is not a gateway settings page, bail.
		if ( ! $this->is_plugin_settings() ) {
			return;
		}

		// if there was already a successful disconnect, just display a notice.
		$connected = Framework\SV_WC_Helper::get_requested_value( 'wc_braintree_connected' );
		if ( $connected ) {

			if ( $connected ) {
				$message = esc_html__( 'Connected successfully.', 'woocommerce-gateway-paypal-powered-by-braintree' );
				$class   = 'updated';
			} else {
				$message = esc_html__( 'There was an error connecting your Braintree account. Please try again.', 'woocommerce-gateway-paypal-powered-by-braintree' );
				$class   = 'error';
			}

			$this->get_admin_notice_handler()->add_admin_notice(
				$message,
				'connection-notice',
				array(
					'dismissible'  => true,
					'notice_class' => $class,
				)
			);

			return;
		}

		$nonce = Framework\SV_WC_Helper::get_requested_value( 'wc_paypal_braintree_admin_nonce' );

		// if no nonce is present, then this probably wasn't a connection response.
		if ( ! $nonce ) {
			return;
		}

		// if there is already a stored access token, bail.
		if ( $this->get_gateway()->get_auth_access_token() ) {
			return;
		}

		// verify the nonce.
		if ( ! wp_verify_nonce( $nonce, 'connect_paypal_braintree' ) ) {
			wp_die( esc_html__( 'Invalid connection request', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$access_token = sanitize_text_field( urldecode( Framework\SV_WC_Helper::get_requested_value( 'braintree_access_token' ) ) );
		if ( $access_token ) {

			update_option( 'wc_braintree_auth_access_token', $access_token );

			list( $token_key, $environment, $merchant_id, $raw_token ) = explode( '$', $access_token );

			update_option( 'wc_braintree_auth_environment', $environment );
			update_option( 'wc_braintree_auth_merchant_id', $merchant_id );

			$connected = true;

		} else {

			$this->log( 'Could not connect to Braintree. Invalid access token', $this->get_gateway()->get_id() );

			$connected = false;
		}

		wp_safe_redirect( add_query_arg( 'wc_braintree_connected', $connected, $this->get_settings_url() ) );
		exit;
	}


	/**
	 * Handles a Braintree Auth disconnect request.
	 *
	 * @since 2.0.0
	 */
	public function handle_auth_disconnect() {

		// if this is not a gateway settings page, bail.
		if ( ! $this->is_plugin_settings() ) {
			return;
		}

		// if there was already a successful disconnect, just display a notice.
		if ( Framework\SV_WC_Helper::get_requested_value( 'wc_braintree_disconnected' ) ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				__( 'Disconnected successfully.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'disconnect-successful-notice',
				array(
					'dismissible'  => true,
					'notice_class' => 'updated',
				)
			);

			return;
		}

		// if this is not a disconnect request, bail.
		if ( ! Framework\SV_WC_Helper::get_requested_value( 'disconnect_paypal_braintree' ) ) {
			return;
		}

		$nonce = Framework\SV_WC_Helper::get_requested_value( 'wc_paypal_braintree_admin_nonce' );

		// if no nonce is present, then this probably wasn't a disconnect request.
		if ( ! $nonce ) {
			return;
		}

		// verify the nonce.
		if ( ! wp_verify_nonce( $nonce, 'disconnect_paypal_braintree' ) ) {
			wp_die( esc_html__( 'Invalid disconnect request', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		delete_option( 'wc_braintree_auth_access_token' );
		delete_option( 'wc_braintree_auth_environment' );
		delete_option( 'wc_braintree_auth_merchant_id' );

		wp_safe_redirect( add_query_arg( 'wc_braintree_disconnected', true, $this->get_settings_url() ) );
		exit;
	}


	/**
	 * Initializes the PayPal cart handler.
	 *
	 * @since 2.0.0
	 * @deprecated since 2.3.0
	 */
	public function maybe_init_paypal_cart() {

		wc_deprecated_function( __METHOD__, '2.3.0' );
	}


	/**
	 * Gets the PayPal cart handler instance.
	 *
	 * @since 2.0.0
	 * @deprecated since 2.3.0
	 */
	public function get_paypal_cart_instance() {

		wc_deprecated_function( __METHOD__, '2.3.0' );
	}


	/** Apple Pay Methods *********************************************************************************************/


	/**
	 * Initializes the Apple Pay feature.
	 *
	 * The framework requires this be enabled by filter due to the complicated setup that's usually required. Braintree
	 * makes the process a bit easier, so let's enable it by default.
	 *
	 * @since 2.2.0
	 */
	public function maybe_init_apple_pay() {

		add_filter( 'wc_payment_gateway_' . $this->get_id() . '_activate_apple_pay', '__return_true' );

		parent::maybe_init_apple_pay();
	}


	/**
	 * Builds the Apple Pay handler instance.
	 *
	 * @since 2.2.0
	 *
	 * @return \WC_Braintree\Apple_Pay
	 */
	protected function build_apple_pay_instance() {
		return new \WC_Braintree\Apple_Pay\Apple_Pay( $this );
	}


	/** Google Pay Methods *********************************************************************************************/


	/**
	 * Initializes the Google Pay feature.
	 *
	 * @since 3.4.0
	 */
	public function maybe_init_google_pay() {
		add_filter( 'wc_payment_gateway_' . $this->get_id() . '_activate_google_pay', '__return_true' );

		parent::maybe_init_google_pay();
	}

	/**
	 * Builds the Google Pay handler instance.
	 *
	 * @since 3.4.0
	 *
	 * @return \WC_Braintree\Google_Pay\Google_Pay
	 */
	protected function build_google_pay_instance() {
		return new \WC_Braintree\Google_Pay\Google_Pay( $this );
	}


	/** Admin methods ******************************************************/


	/**
	 * Render a notice for the user to select their desired export format
	 *
	 * @since 2.1.3
	 * @see SV_WC_Plugin::add_admin_notices()
	 */
	public function add_admin_notices() {

		// show any dependency notices
		parent::add_admin_notices();

		/** @var \WC_Gateway_Braintree_Credit_Card $credit_card_gateway */
		$credit_card_gateway = $this->get_gateway( self::CREDIT_CARD_GATEWAY_ID );

		if ( $credit_card_gateway->is_advanced_fraud_tool_enabled() && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'fraud-tool-notice' ) ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
						esc_html__( 'Heads up! You\'ve enabled advanced fraud tools for Braintree. Please make sure that advanced fraud tools are also enabled in your Braintree account. Need help? See the %1$sdocumentation%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'<a target="_blank" href="' . esc_url( $this->get_documentation_url() ) . '">',
					'</a>'
				), 'fraud-tool-notice', array( 'always_show_on_settings' => false, 'dismissible' => true, 'notice_class' => 'updated' )
			);
		}

		$credit_card_settings = get_option( 'woocommerce_braintree_credit_card_settings' );
		$paypal_settings      = get_option( 'woocommerce_braintree_paypal_settings' );

		// install notice
		if ( ! $this->is_plugin_settings() ) {

			if ( ( $credit_card_gateway->can_connect() && ! $credit_card_gateway->is_connected() ) && empty( $credit_card_settings ) && empty( $paypal_settings ) && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'install-notice' ) ) {

				$this->get_admin_notice_handler()->add_admin_notice(
					sprintf(
						/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
						__( 'Braintree for WooCommerce is almost ready. To get started, %1$sconnect your Braintree account%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						'<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>'
					), 'install-notice', array( 'notice_class' => 'updated' )
				);

			} elseif ( 'yes' === get_option( 'wc_braintree_legacy_migrated' ) ) {

				delete_option( 'wc_braintree_legacy_migrated' );

				$this->get_admin_notice_handler()->add_admin_notice(
					sprintf(
						/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
						__( 'Upgrade successful! WooCommerce Braintree deactivated, and Braintree for WooCommerce has been %1$sconfigured with your previous settings%2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
						'<a href="' . esc_url( $this->get_settings_url() ) . '">', '</a>'
					), 'install-notice', array( 'notice_class' => 'updated' )
				);
			}
		}

		// SSL check (only when PayPal is enabled in production mode)
		if ( isset( $paypal_settings['enabled'] ) && 'yes' === $paypal_settings['enabled'] ) {
			if ( isset( $paypal_settings['environment'] ) && 'production' === $paypal_settings['environment'] ) {

				if ( ! wc_checkout_is_https() && ! $this->get_admin_notice_handler()->is_notice_dismissed( 'ssl-recommended-notice' ) ) {

					$this->get_admin_notice_handler()->add_admin_notice( esc_html__( 'WooCommerce is not being forced over SSL -- Using PayPal with Braintree requires that checkout to be forced over SSL.', 'woocommerce-gateway-paypal-powered-by-braintree' ), 'ssl-recommended-notice' );
				}
			}
		}

		// Currency check for gateways with restricted currencies.
		$store_currency = get_woocommerce_currency();

		foreach ( $this->get_gateways() as $gateway ) {
			$gateway_settings = $this->get_gateway_settings( $gateway->get_id() );

			// Only check enabled gateways.
			if ( ! isset( $gateway_settings['enabled'] ) || 'yes' !== $gateway_settings['enabled'] ) {
				continue;
			}

			if ( $gateway->currency_is_accepted( $store_currency ) ) {
				continue;
			}

			$notice_id           = $gateway->get_id() . '-currency-notice';
			$accepted_currencies = $gateway->get_accepted_currencies();

			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - gateway title, %2$s - accepted currency/currencies, %3$s - current currency code, %4$s - <a> tag, %5$s - </a> tag */
					_n(
						'%1$s gateway only accepts payments in %2$s, but your store currency is currently set to %3$s. %4$sChange the store currency%5$s to enable this gateway at checkout.',
						'%1$s gateway only accepts payments in one of the following currencies: %2$s, but your store currency is currently set to %3$s. %4$sChange the store currency%5$s to enable this gateway at checkout.',
						count( $accepted_currencies ),
						'woocommerce-gateway-paypal-powered-by-braintree'
					),
					'<strong>' . esc_html( $gateway->get_method_title() ) . '</strong>',
					'<strong>' . esc_html( implode( ', ', $accepted_currencies ) ) . '</strong>',
					'<strong>' . esc_html( $store_currency ) . '</strong>',
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">',
					'</a>'
				),
				$notice_id,
				array(
					'dismissible'  => false,
					'notice_class' => 'notice-warning',
				)
			);
		}

		// Merchant account availability check for gateways.
		$this->add_merchant_account_availability_notice();
	}

	/**
	 * Adds a notice if the merchant account is not available for the payment gateway of the current page.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private function add_merchant_account_availability_notice() {
		// We only show this notice on the plugin settings page.
		if ( ! $this->is_plugin_settings() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( empty( $current_section ) ) {
			return;
		}

		$current_gateway = $this->get_gateway( $current_section );

		if ( ! $current_gateway ) {
			return;
		}

		$credentials_source_gateway_id = $current_gateway->get_credentials_source();
		$credentials_source_gateway    = $this->get_gateway( $credentials_source_gateway_id );

		if ( ! $credentials_source_gateway ) {
			return;
		}

		$should_suggest_other_gateway = false;

		try {
			$remote_configuration                        = WC_Braintree_Remote_Configuration::get_remote_configuration( $credentials_source_gateway_id );
			$merchant_accounts_with_source_configuration = $remote_configuration->get_merchant_accounts_by_payment_gateway( $current_gateway->get_id() );

			// Bail if the source gateway has any merchant accounts that support the current gateway.
			if ( count( $merchant_accounts_with_source_configuration ) > 0 ) {
				return;
			}

			// Get the other gateway details. If the source gateway is credit card, the other gateway will be PayPal and vice versa.
			$other_gateway_ids                   = array_diff( WC_Braintree_Remote_Configuration::SUPPORTED_GATEWAYS, [ $credentials_source_gateway_id ] );
			$other_credentials_source_gateway_id = reset( $other_gateway_ids );
			$other_credentials_source_gateway    = $this->get_gateway( $other_credentials_source_gateway_id );

			// Check if the other gateway is not using the same credentials source as the current gateway.
			// If so, suggest the other gateway.
			if ( $other_credentials_source_gateway && $other_credentials_source_gateway->get_credentials_source() !== $credentials_source_gateway_id ) {
				$should_suggest_other_gateway = true;
			}
		} catch ( \Exception $e ) {
			// If there is an error, bail without showing a notice.
			return;
		}

		$message = sprintf(
			/* translators: Placeholders: %1$s - credential source gateway title, %2$s - current payment method title. Both have a format like "Braintree (PayPal)" or "Braintree (Credit Card)". */
			esc_html__( '%1$s cannot process transactions, as none of the Braintree merchant accounts for the credentials from %2$s support this payment method.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'<strong>' . esc_html( $current_gateway->get_method_title() ) . '</strong>',
			'<strong>' . esc_html( $credentials_source_gateway->get_method_title() ) . '</strong>',
		);

		if ( $should_suggest_other_gateway ) {
			// Extract just the payment method name.
			$current_method_name = preg_match( '/\(([^)]+)\)/', $current_gateway->get_method_title(), $matches )
			? trim( preg_replace( '/\s*-.*$/', '', $matches[1] ) )
			: $current_gateway->get_method_title();

			$message .= ' ' . sprintf(
				/* translators: Placeholders: %1$s - other gateway title, %2$s - the current gateway title. Both have the format "Braintree (PayPal)" or "Braintree (Venmo)". */
				esc_html__( 'Try using the credentials for %1$s to see if that account supports %2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'<strong>' . esc_html( $other_credentials_source_gateway->get_method_title() ) . '</strong>',
				'<strong>' . esc_html( $current_method_name ) . '</strong>',
			);
		}

		$this->get_admin_notice_handler()->add_admin_notice(
			$message,
			$current_section . '-merchant-account-notice',
			array(
				'dismissible'  => false,
				'notice_class' => 'notice-error',
			)
		);
	}

	/**
	 * Adds delayed admin notices for invalid Dynamic Descriptor Name values.
	 *
	 * @since 2.1.0
	 */
	public function add_delayed_admin_notices() {

		parent::add_delayed_admin_notices();

		if ( $this->is_plugin_settings() ) {

			foreach ( $this->get_gateways() as $gateway ) {

				$settings = $this->get_gateway_settings( $gateway->get_id() );

				if ( ! empty( $settings['inherit_settings'] ) && 'yes' === $settings['inherit_settings'] ) {
					continue;
				}

				foreach ( array( 'name', 'phone', 'url' ) as $type ) {

					$validation_method = "is_{$type}_dynamic_descriptor_valid";
					$settings_key      = "{$type}_dynamic_descriptor";

					if ( ! empty( $settings[ $settings_key ] ) && is_callable( array( $gateway, $validation_method ) ) && ! $gateway->$validation_method( $settings[ $settings_key ] ) ) {

						$this->get_admin_notice_handler()->add_admin_notice(
							/* translators: Placeholders: %1$s - payment gateway name tag, %2$s - <a> tag, %3$s - </a> tag */
							sprintf( esc_html__( '%1$s: Heads up! Your %2$s dynamic descriptor is invalid and will not be used. Need help? See the %3$sdocumentation%4$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
								'<strong>' . esc_html( $gateway->get_method_title() ) . '</strong>',
								'<strong>' . esc_html( $type ) . '</strong>',
								'<a target="_blank" href="https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/#dynamic-descriptors-setup">',
								'</a>'
							), $gateway->get_id() . '-' . $type . '-dynamic-descriptor-notice', array( 'notice_class' => 'error' )
						);

						break;
					}
				}
			}
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Braintree Instance, ensures only one instance is/can be loaded
	 *
	 * @since 2.2.0
	 * @see wc_braintree()
	 * @return WC_Braintree
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Overrides the default SV framework implementation of payment methods in My Account.
	 *
	 * @since 2.6.2
	 * @return \WC_Braintree_My_Payment_Methods
	 */
	public function get_my_payment_methods_instance() {
		return new WC_Braintree_My_Payment_Methods( $this );
	}

	/**
	 * Returns the plugin name, localized
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return esc_html__( 'Braintree for WooCommerce Payment Gateway', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return WC_PAYPAL_BRAINTREE_FILE;
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 2.1
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 2.3.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {
		return 'https://wordpress.org/support/plugin/woocommerce-gateway-paypal-powered-by-braintree/';
	}


	/**
	 * Returns the plugin action links.
	 *
	 * Overrides the parent method to filter out empty action links.
	 *
	 * @since 3.6.0
	 * @see SV_WC_Payment_Gateway_Plugin::plugin_action_links()
	 * @param string[] $actions associative array of action names to anchor tags.
	 * @return string[] plugin action links
	 */
	public function plugin_action_links( $actions ) {

		$actions = parent::plugin_action_links( $actions );

		// Filter out empty action links (e.g., for non-CC/PayPal gateways).
		return array_filter( $actions );
	}


	/**
	 * Returns the "Configure Credit Card" or "Configure PayPal" plugin action
	 * links that go directly to the gateway settings page
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Plugin::get_settings_url()
	 * @param string $gateway_id the gateway identifier
	 * @return string plugin configure link
	 */
	public function get_settings_link( $gateway_id = null ) {

		// Only show action links for Credit Card and PayPal gateways.
		if ( ! in_array( $gateway_id, array( self::CREDIT_CARD_GATEWAY_ID, self::PAYPAL_GATEWAY_ID ), true ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s">%s</a>',
			$this->get_settings_url( $gateway_id ),
			self::CREDIT_CARD_GATEWAY_ID === $gateway_id ? esc_html__( 'Configure Credit Card', 'woocommerce-gateway-paypal-powered-by-braintree' ) : esc_html__( 'Configure PayPal', 'woocommerce-gateway-paypal-powered-by-braintree' )
		);
	}


	/**
	 * Determines if WooCommerce is active.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}

	/**
	 * Determines if website is staging site.
	 *
	 * This functions use WooCommerce Subscriptions 'WCS_Staging' Class to determine staging site.
	 * So, if WooCommerce Subscriptions plugin is not activated, this will always return false.
	 *
	 * @return bool
	 */
	public static function is_staging_site() {
		return ( class_exists( 'WCS_Staging', false ) && method_exists( 'WCS_Staging', 'is_duplicate_site' ) && \WCS_Staging::is_duplicate_site() );
	}

	/**
	 * Determines if we're on a page with WooCommerce Blocks.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public static function is_blocks_page() {
		return has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Gets the store name for Braintree.
	 *
	 * @since 3.7.0
	 *
	 * @return string The store name.
	 */
	public static function get_braintree_store_name() {
		$store_name = get_bloginfo( 'name' );

		/**
		 * Filters the Braintree store name.
		 *
		 * @since 3.7.0
		 *
		 * @param string $store_name The store name, which defaults to the blog name.
		 */
		return apply_filters( 'wc_braintree_store_name', $store_name );
	}

	/**
	 * Overrides the SkyVerge default logging method.
	 *
	 * @deprecated since 3.5.0. Use the Logger class helpers instead (ex: Logger::notice()).
	 *
	 * @param string $message error or message to save to log.
	 * @param string $log_id optional log id to segment the files by, defaults to plugin id.
	 */
	public function log( $message, $log_id = null ) {
		if ( is_null( $log_id ) ) {
			$log_id = $this->get_id();
		}

		Logger::notice( $message, [ 'gateway' => $log_id ] );
	}
} // end \WC_Braintree


/**
 * Returns the One True Instance of Braintree
 *
 * @since 2.2.0
 * @return WC_Braintree
 */
function wc_braintree() {
	WC_Braintree_Webhook_Handler::instance();
	return WC_Braintree::instance();
}
