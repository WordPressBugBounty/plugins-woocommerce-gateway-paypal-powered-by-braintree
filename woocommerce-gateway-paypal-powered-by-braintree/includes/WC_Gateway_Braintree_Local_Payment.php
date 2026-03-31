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
 * @package   WC-Braintree/Gateway/Local-Payment
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Abstract Braintree Local Payment Gateway Class.
 *
 * Base class for all local payment method gateways (e.g. BLIK, P24).
 * Each concrete subclass represents a single local payment method and
 * appears as its own WooCommerce gateway at checkout.
 *
 * @since 3.9.0
 */
abstract class WC_Gateway_Braintree_Local_Payment extends WC_Gateway_Braintree {


	/** Local payment type used by the SkyVerge framework for transaction dispatch. */
	const PAYMENT_TYPE_LOCAL_PAYMENT = 'local_payment';


	/**
	 * Gets the local payment type string used by the Braintree SDK.
	 *
	 * @since 3.9.0
	 *
	 * @return string e.g. 'blik', 'p24'
	 */
	abstract public function get_local_payment_type(): string;


	/**
	 * Gets the human-readable display name for this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string e.g. 'BLIK', 'Przelewy24'
	 */
	abstract public function get_local_payment_display_name(): string;


	/**
	 * Gets the billing countries supported by this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string[] ISO country codes, e.g. ['PL']
	 */
	abstract public function get_supported_countries(): array;


	/**
	 * Gets the currencies supported by this local payment method.
	 *
	 * @since 3.9.0
	 *
	 * @return string[] Currency codes, e.g. ['PLN', 'EUR']
	 */
	abstract public function get_supported_currencies(): array;


	/**
	 * Initialize the gateway.
	 *
	 * @since 3.9.0
	 *
	 * @param string $gateway_id The gateway ID.
	 * @param array  $args       Additional constructor arguments to merge with defaults.
	 */
	public function __construct( string $gateway_id, array $args = [] ) {

		$defaults = [
			'supports'        => [
				self::FEATURE_PRODUCTS,
				self::FEATURE_PAYMENT_FORM,
				self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
				self::FEATURE_REFUNDS,
				self::FEATURE_VOIDS,
				self::FEATURE_CUSTOMER_ID,
			],
			'payment_type'    => self::PAYMENT_TYPE_LOCAL_PAYMENT,
			'environments'    => $this->get_braintree_environments(),
			'shared_settings' => $this->shared_settings_names,
		];

		parent::__construct(
			$gateway_id,
			wc_braintree(),
			array_merge( $defaults, $args )
		);

		// Sanitize admin options before saving.
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->get_id(), [ $this, 'filter_admin_options' ] );

		// Get the client token via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', [ $this, 'ajax_get_client_token' ] );

		// Always enqueue LPM assets on checkout, even when this gateway is not
		// currently available. LPM gateways appear dynamically when the customer
		// changes their billing country, so scripts must already be loaded.
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_lpm_assets' ] );
	}


	/**
	 * Registers base and LPM-specific scripts.
	 *
	 * Does not call parent::register_gateway_assets() because it gates
	 * registration behind is_available(), which includes the customer billing
	 * country check. LPM gateways need scripts pre-registered regardless of
	 * the current country because they appear dynamically when the customer
	 * changes their billing country at checkout.
	 *
	 * @since 3.9.0
	 */
	public function register_gateway_assets() {

		$plugin_url  = $this->get_plugin()->get_plugin_url();
		$plugin_path = $this->get_plugin()->get_plugin_path();
		$sdk_version = WC_Braintree::BRAINTREE_JS_SDK_VERSION;
		$version     = WC_Braintree::VERSION;

		// Base Braintree dependencies (idempotent if already registered by another gateway).
		wp_register_script( 'braintree-js-latinise', $plugin_url . '/assets/js/frontend/latinise.min.js', [], $version, true );
		wp_register_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . $sdk_version . '/js/client.min.js', [], $version, true );

		// Braintree utils (needed by Blocks integration; idempotent if already registered by another gateway).
		$utils_asset_path = $plugin_path . '/assets/js/frontend/wc-braintree-utils.asset.php';
		$utils_version    = $version;
		$utils_deps       = array( 'braintree-js-client' );

		if ( file_exists( $utils_asset_path ) ) {
			$utils_asset   = require $utils_asset_path;
			$utils_version = $utils_asset['version'] ?? $utils_version;
			$utils_deps    = array_merge( $utils_deps, $utils_asset['dependencies'] ?? array() );
		}

		wp_register_script( 'wc-braintree-utils', $plugin_url . '/assets/js/frontend/wc-braintree-utils.min.js', $utils_deps, $utils_version, true );

		// Braintree Local Payments SDK.
		wp_register_script( 'braintree-js-local-payments', 'https://js.braintreegateway.com/web/' . $sdk_version . '/js/local-payment.min.js', [ 'braintree-js-client' ], $version, true );

		// Shared LPM form handler.
		$asset_path   = $plugin_path . '/assets/js/frontend/wc-braintree-local-payments.asset.php';
		$dependencies = [ 'braintree-js-client', 'braintree-js-local-payments' ];

		if ( file_exists( $asset_path ) ) {
			$asset   = require $asset_path;
			$version = isset( $asset['version'] ) ? $asset['version'] : $version;
			if ( is_array( $asset['dependencies'] ?? null ) ) {
				$dependencies = array_merge( $dependencies, $asset['dependencies'] );
			}
		}

		wp_register_script( 'wc-braintree-local-payments-payment-form', $plugin_url . '/assets/js/frontend/wc-braintree-local-payments.min.js', $dependencies, $version, true );

		// Shared LPM styles.
		wp_register_style( 'wc-braintree-local-payments', $plugin_url . '/assets/css/frontend/wc-local-payments.min.css', [], WC_Braintree::VERSION );
	}


	/**
	 * Enqueues local payment gateway scripts and styles.
	 *
	 * All local payment gateways share the same JS handler and CSS.
	 * wp_enqueue_script is idempotent, so multiple gateways won't duplicate assets.
	 *
	 * @since 3.9.0
	 *
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		if ( ! $this->is_available() || ! $this->is_payment_form_page() ) {
			return;
		}

		parent::enqueue_gateway_assets();
		$this->enqueue_lpm_scripts();
	}


	/**
	 * Enqueues the shared LPM scripts and styles.
	 *
	 * @since 3.9.0
	 */
	protected function enqueue_lpm_scripts() {

		wp_enqueue_script( 'wc-braintree-local-payments-payment-form' );
		wp_enqueue_style( 'wc-braintree-local-payments' );
	}


	/**
	 * Enqueues LPM assets on checkout pages regardless of gateway availability.
	 *
	 * The framework's enqueue_scripts() skips assets for unavailable gateways.
	 * However, LPM gateways appear dynamically when the billing country changes,
	 * so scripts must already be loaded before the gateway becomes available.
	 *
	 * Skips enqueuing when the gateway is disabled or the store currency is
	 * unsupported, since those conditions won't change during checkout.
	 *
	 * @since 3.9.0
	 *
	 * @internal Hooked to wp_enqueue_scripts.
	 */
	public function maybe_enqueue_lpm_assets() {

		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currencies(), true ) ) {
			return;
		}

		if ( ! $this->is_payment_form_page() ) {
			return;
		}

		$this->register_gateway_assets();
		$this->enqueue_lpm_scripts();
	}


	/**
	 * Determines if the gateway is available for use.
	 *
	 * Checks the store currency and the customer's billing country against
	 * this payment method's supported values.
	 *
	 * When the checkout page uses WC Blocks, the billing country check is
	 * skipped. WC Blocks checks the Store API's `paymentMethods` list
	 * *before* calling the JS `canMakePayment` callback, so the gateway
	 * must remain in that list for the client-side country filtering to
	 * ever run.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( ! parent::is_available() ) {
			return false;
		}

		$store_currency = get_woocommerce_currency();

		if ( ! in_array( $store_currency, $this->get_supported_currencies(), true ) ) {
			return false;
		}

		if ( ! WC()->customer ) {
			return false;
		}

		// When using blocks checkout, skip the billing country check.
		// The JS canMakePayment callback handles country filtering dynamically.
		// is_blocks_page() covers the initial page render (cart data hydration).
		// REST_REQUEST covers Store API calls (checkout submission, cart updates).
		if ( WC_Braintree::is_blocks_page() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}

		$customer_country = WC()->customer->get_billing_country();

		if ( ! in_array( $customer_country, $this->get_supported_countries(), true ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 3.9.0
	 *
	 * @return \WC_Braintree\Payment_Forms\WC_Braintree_Local_Payment_Payment_Form
	 */
	protected function init_payment_form_instance() {

		return new Payment_Forms\WC_Braintree_Local_Payment_Payment_Form( $this );
	}


	/**
	 * Gets the method form fields.
	 *
	 * Removes dynamic descriptor fields which are not supported for local payments.
	 *
	 * @since 3.9.0
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


	/**
	 * Gets the gateway icon HTML.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	public function get_icon() {

		$icon_html = sprintf(
			'<img class="wc-braintree-local-payments-icon %s" src="%s" alt="%s" />',
			esc_attr( 'wc-' . $this->get_id_dasherized() . '-icon' ),
			esc_url( $this->get_local_payment_icon_url() ),
			esc_attr( $this->get_local_payment_display_name() )
		);

		/**
		 * Filters the gateway icon HTML.
		 *
		 * @since 3.9.0
		 *
		 * @param string $icon_html The icon HTML.
		 * @param string $gateway_id The gateway ID.
		 */
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->get_id() );
	}


	/**
	 * Gets the URL to the gateway icon image.
	 *
	 * Defaults to an SVG in assets/images/. Subclasses can override
	 * this to use a different format or path.
	 *
	 * @since 3.9.0
	 *
	 * @return string
	 */
	public function get_local_payment_icon_url(): string {

		return wc_braintree()->get_plugin_url() . '/assets/images/' . $this->get_local_payment_type() . '.svg';
	}


	/**
	 * Processes a local payment transaction.
	 *
	 * Called by the framework via the payment_type dispatch (do_{payment_type}_transaction).
	 *
	 * @since 3.9.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return \WC_Braintree\API\Responses\WC_Braintree_API_PayPal_Transaction_Response
	 */
	protected function do_local_payment_transaction( \WC_Order $order ) {

		// Set the correct payment method title on the order.
		$title = sprintf(
			/* translators: %s - Local payment method display name (e.g. "BLIK", "Przelewy24"). */
			__( '%s (Braintree Local Payment Method)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			$this->get_local_payment_display_name()
		);
		$order->set_payment_method_title( $title );
		$order->save();

		// Create the LPM charge transaction.
		return $this->get_api()->local_payments_charge( $order );
	}
}
