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
 * @package   WC-Braintree/Gateway/Venmo
 * @author    WooCommerce
 * @copyright Copyright (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use WC_Braintree\Venmo\Buttons;

defined( 'ABSPATH' ) || exit;

/**
 * Braintree Venmo Gateway Class
 *
 * @since 3.5.0
 */
class WC_Gateway_Braintree_Venmo extends WC_Gateway_Braintree {


	/** Venmo payment type */
	const PAYMENT_TYPE_VENMO = 'venmo';

	/** Payment method usage - single use (for simple products) */
	const PAYMENT_METHOD_USAGE_SINGLE = 'single_use';

	/** Payment method usage - multi use (for subscriptions, enables vaulting) */
	const PAYMENT_METHOD_USAGE_MULTI = 'multi_use';


	/**
	 * Venmo button handler instances.
	 *
	 * @var Buttons\Abstract_Button[]
	 */
	protected $button_handlers = [];


	/**
	 * Initialize the gateway
	 *
	 * @since 3.5.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::VENMO_GATEWAY_ID,
			wc_braintree(),
			array(
				'method_title'       => __( 'Braintree (Venmo)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to securely pay using their Venmo account via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'supports'           => array(
					self::FEATURE_PRODUCTS,
					self::FEATURE_PAYMENT_FORM,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
					self::FEATURE_DETAILED_CUSTOMER_DECLINE_MESSAGES,
					self::FEATURE_REFUNDS,
					self::FEATURE_VOIDS,
					self::FEATURE_CUSTOMER_ID,
				),
				'payment_type'       => self::PAYMENT_TYPE_VENMO,
				'environments'       => $this->get_braintree_environments(),
				'shared_settings'    => $this->shared_settings_names,
				'currencies'         => [ 'USD' ],
			)
		);

		$this->init_venmo_buttons();

		// Enable display of Venmo payment methods in My Account.
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'set_brand_info_in_payment_method_list' ], 10, 2 );

		// Sanitize admin options before saving.
		add_filter( 'woocommerce_settings_api_sanitized_fields_braintree_venmo', [ $this, 'filter_admin_options' ] );
		// Get the client token via AJAX.
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token', array( $this, 'ajax_get_client_token' ) );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', array( $this, 'ajax_get_client_token' ) );
	}


	/**
	 * Initializes any Venmo buttons that may be required on the current page.
	 *
	 * This method is called early in the WordPress lifecycle (during __construct), so we
	 * initialize all potentially needed handlers. Each handler will determine whether to
	 * actually render based on the current page context in their own initialization logic.
	 *
	 * @since 3.5.0
	 */
	protected function init_venmo_buttons() {

		if ( ! $this->is_available() ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// Initialize handlers that might be needed. Each handler checks its own
		// enabled state and current page context before actually hooking into actions.
		if ( $this->product_page_buy_now_enabled() ) {
			$this->button_handlers['product'] = new Buttons\Product( $this );
		}

		if ( $this->cart_checkout_enabled() ) {
			$this->button_handlers['cart'] = new Buttons\Cart( $this );
		}
	}


	/**
	 * Enqueues Venmo gateway specific scripts.
	 *
	 * @since 3.5.0
	 *
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		if ( ! $this->is_available() || ! $this->is_payment_form_page() ) {
			return;
		}

		parent::enqueue_gateway_assets();

		// Enqueue Braintree Venmo SDK.
		wp_enqueue_script( 'braintree-js-venmo', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/venmo.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );
		wp_enqueue_script( 'braintree-js-data-collector', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/data-collector.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );

		// Load dependencies from webpack asset file.
		$asset_path   = $this->get_plugin()->get_plugin_path() . '/assets/js/frontend/wc-braintree-venmo.asset.php';
		$version      = WC_Braintree::VERSION;
		$dependencies = array( 'braintree-js-client', 'braintree-js-venmo', 'braintree-js-data-collector' );

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = array_merge( $dependencies, isset( $asset['dependencies'] ) ? $asset['dependencies'] : array() );
		}

		// Enqueue custom Venmo payment form handler.
		wp_enqueue_script(
			'wc-braintree-venmo-payment-form',
			$this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-venmo.min.js',
			$dependencies,
			$version,
			true
		);

		// Enqueue Venmo styles.
		wp_enqueue_style(
			'wc-braintree-venmo',
			$this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-venmo.min.css',
			array(),
			WC_Braintree::VERSION
		);
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 3.5.0
	 *
	 * @return \WC_Braintree\Payment_Forms\WC_Braintree_Venmo_Payment_Form
	 */
	protected function init_payment_form_instance() {
		return new Payment_Forms\WC_Braintree_Venmo_Payment_Form( $this );
	}


	/**
	 * Gets the method form fields.
	 *
	 * Overrides parent to exclude Merchant Account IDs section (Venmo only supports USD)
	 * and phone/URL dynamic descriptor fields (Venmo only supports name descriptor).
	 *
	 * @since 3.5.0
	 *
	 * @see WC_Gateway_Braintree::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$form_fields = parent::get_method_form_fields();

		// Remove Merchant Account IDs section (Venmo only supports USD).
		unset( $form_fields['merchant_account_id_title'] );
		unset( $form_fields['merchant_account_id_fields'] );

		// Remove phone and URL dynamic descriptors (not supported for Venmo, only name is supported).
		unset( $form_fields['phone_dynamic_descriptor'] );
		unset( $form_fields['url_dynamic_descriptor'] );

		// Update the name descriptor field with Venmo-specific description.
		// Venmo has simpler requirements: alphanumeric + +-. and spaces, no company*product format.
		if ( isset( $form_fields['name_dynamic_descriptor'] ) ) {
			$form_fields['name_dynamic_descriptor']['desc_tip'] = __( 'The dynamic descriptor name for Venmo transactions. Only alphanumeric characters and +, -, . (period), and spaces are allowed. Any other characters will cause the descriptor to be excluded. The full descriptor (including Venmo prefix and business name) will be truncated to 22 characters.', 'woocommerce-gateway-paypal-powered-by-braintree' );
			// Remove the validation icon class since Venmo uses different validation.
			$form_fields['name_dynamic_descriptor']['class'] = '';
		}

		// Add button settings after charge/auth settings (if they exist).
		$position = array_search( 'charge_virtual_orders', array_keys( $form_fields ), true );
		if ( false === $position ) {
			// If charge_virtual_orders doesn't exist, add at the end.
			$position = count( $form_fields );
		} else {
			++$position;
		}

		$button_fields = [
			'button_display_title'   => [
				'type'  => 'title',
				'title' => esc_html__( 'Button Display', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			],
			'venmo_button_locations' => [
				'title'       => esc_html__( 'Allow Venmo on', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'width: 350px;',
				'description' => esc_html__( 'Venmo is always available on the checkout page when the gateway is enabled.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'options'     => [
					'product' => esc_html__( 'Product Pages', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'cart'    => esc_html__( 'Cart Page', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'default'     => [ 'product', 'cart' ],
			],
		];

		// Insert button fields at the calculated position.
		$form_fields = array_slice( $form_fields, 0, $position, true ) +
			$button_fields +
			array_slice( $form_fields, $position, null, true );

		return $form_fields;
	}


	/**
	 * Determines if the current page contains a payment form.
	 *
	 * @since 3.5.0
	 * @return bool
	 */
	public function is_payment_form_page() {

		$product         = wc_get_product( get_the_ID() );
		$is_product_page = $product instanceof \WC_Product;

		return parent::is_payment_form_page() || is_cart() || ( $is_product_page && $this->product_page_buy_now_enabled() );
	}


	/**
	 * Determines if cart checkout is enabled.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function cart_checkout_enabled() {

		$locations = $this->get_option( 'venmo_button_locations', [ 'product', 'cart' ] );
		$enabled   = is_array( $locations ) && in_array( 'cart', $locations, true );

		/**
		 * Filters whether cart checkout is enabled.
		 *
		 * @since 3.5.0
		 *
		 * @param bool $enabled whether cart checkout is enabled in the settings
		 * @param \WC_Braintree\WC_Gateway_Braintree_Venmo $gateway gateway object
		 */
		return (bool) apply_filters( 'wc_braintree_venmo_cart_checkout_enabled', $enabled, $this );
	}


	/**
	 * Determines if buy now buttons should be added to the product pages.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function product_page_buy_now_enabled() {

		$locations = $this->get_option( 'venmo_button_locations', [ 'product', 'cart' ] );
		$enabled   = is_array( $locations ) && in_array( 'product', $locations, true );

		/**
		 * Filters whether product page buy now buttons are enabled.
		 *
		 * @since 3.5.0
		 *
		 * @param bool $enabled whether product buy now buttons are enabled in the settings
		 * @param \WC_Braintree\WC_Gateway_Braintree_Venmo $gateway gateway object
		 */
		return (bool) apply_filters( 'wc_braintree_venmo_product_buy_now_enabled', $enabled, $this );
	}


	/**
	 * Gets the array of instantiated button handlers.
	 *
	 * @since 3.5.0
	 *
	 * @return Buttons\Abstract_Button[]
	 */
	public function get_button_handlers() {

		return $this->button_handlers;
	}


	/**
	 * Gets the default title for the payment method.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	protected function get_default_title() {
		return __( 'Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Gets the default description for the payment method.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	protected function get_default_description() {
		return __( 'Complete your purchase using Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Gets the description for the payment method.
	 *
	 * If we're in checkout and we have authorized Venmo, override the
	 * description to guide users to placing the order.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_description() {

		// Check if we're on the checkout page with a pre-authorized payment from cart/product pages.
		if ( is_checkout() && WC()->session && WC()->session->get( 'wc_braintree_venmo_cart_nonce', '' ) ) {
			$place_order_text = __( 'Place Order', 'woocommerce' ); //phpcs:ignore WordPress.WP.I18n.TextDomainMismatch

			/**
			 * Filters the Place Order button label used in the Venmo checkout description.
			 *
			 * @since 3.5.0
			 *
			 * @param string $place_order_text The Place Order button text.
			 */
			$place_order_text = apply_filters( 'wc_braintree_checkout_place_order_label', $place_order_text );
			// Override with the pre-authorization confirmation message.
			return sprintf(
				// translators: %s is the label for the Place Order button.
				__( 'Your payment has been authorized with Venmo. Click the %s button below to confirm the order.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$place_order_text
			);
		}

		return parent::get_description();
	}


	/**
	 * Tweaks the display of Venmo payment methods in My Account > Payment Methods to set brand info.
	 *
	 * @since 3.6.0
	 *
	 * @param array             $item       Payment method list item.
	 * @param \WC_Payment_Token $core_token WooCommerce payment token.
	 * @return array
	 */
	public function set_brand_info_in_payment_method_list( $item, $core_token ) {

		if ( ! $core_token instanceof WC_Payment_Token_Braintree_Venmo ) {
			return $item;
		}

		// Customize the method brand to show Venmo username.
		$username = $core_token->get_username();
		if ( $username ) {
			/* translators: %s: Venmo username */
			$item['method']['brand'] = sprintf( __( 'Venmo - %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), $username );
		} else {
			$item['method']['brand'] = __( 'Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		// Change "Delete" to "Unlink" for consistency with PayPal.
		if ( isset( $item['actions']['delete'] ) ) {
			$item['actions']['delete']['name'] = __( 'Unlink', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		return $item;
	}


	/**
	 * Override the default icon to set a Venmo-specific one.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public function get_icon() {

		$icon_html = sprintf(
			'<img src="%s" alt="%s" style="max-height: 26px;" />',
			esc_url( wc_braintree()->get_plugin_url() . '/assets/images/blue_venmo_acceptance_mark.svg' ),
			esc_attr__( 'Venmo', 'woocommerce-gateway-paypal-powered-by-braintree' )
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
	 * @since 3.5.0
	 *
	 * @param string $type The payment method type.
	 * @return string
	 */
	public function get_payment_method_image_url( $type ) {
		return wc_braintree()->get_plugin_url() . '/assets/images/blue_venmo_acceptance_mark.svg';
	}


	/**
	 * Processes a Venmo transaction.
	 *
	 * @since 3.5.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return \WC_Braintree\API\Responses\Transaction_Response
	 */
	protected function do_venmo_transaction( \WC_Order $order ) {
		// Venmo always uses charge (sale) transactions, no authorization-only.
		return $this->get_api()->credit_card_charge( $order );
	}


	/**
	 * Validates the name dynamic descriptor for Venmo.
	 *
	 * Venmo has simpler validation requirements than credit cards/PayPal:
	 * - Only alphanumeric characters and +-.  (space included) are allowed
	 * - No company*product format required
	 * - Maximum 22 characters (though Braintree will truncate as needed)
	 *
	 * @link https://developer.paypal.com/braintree/docs/reference/request/transaction/sale/php#venmo
	 * @since 3.6.0
	 *
	 * @param string $value Optional. The value to validate. Defaults to the saved setting.
	 * @return bool
	 */
	public function is_name_dynamic_descriptor_valid( $value = '' ) {

		if ( ! $value ) {
			$value = $this->get_name_dynamic_descriptor();
		}

		// Empty is considered valid (descriptor is optional).
		if ( empty( $value ) ) {
			return true;
		}

		// Venmo only allows alphanumeric characters and +-.  (space included).
		// Any other characters will cause the descriptor to be excluded.
		if ( preg_match( '/[^a-zA-Z0-9+\-. ]/', $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the cart contains a subscription product.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	public function cart_contains_subscription() { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found 
		// Call parent method, but keep this method as it existed in this class before the parent,
		// so we want to keep the older @since method documentation for this method.
		return parent::cart_contains_subscription();
	}

	/**
	 * Checks if an order contains a subscription.
	 *
	 * @since 3.6.0
	 *
	 * @param \WC_Order $order The order object.
	 * @return bool
	 */
	protected function order_contains_subscription( $order ) {

		if ( ! $this->get_plugin()->is_subscriptions_active() || ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return wcs_order_contains_subscription( $order, 'any' );
	}


	/**
	 * Checks if the current product is a subscription.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	public function product_is_subscription() {

		if ( ! $this->get_plugin()->is_subscriptions_active() || ! class_exists( 'WC_Subscriptions_Product' ) || ! method_exists( 'WC_Subscriptions_Product', 'is_subscription' ) ) {
			return false;
		}

		$product = wc_get_product();
		if ( ! $product ) {
			return false;
		}

		return \WC_Subscriptions_Product::is_subscription( $product );
	}
}
