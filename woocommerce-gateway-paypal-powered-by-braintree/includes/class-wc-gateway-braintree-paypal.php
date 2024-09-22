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
 * @package   WC-Braintree/Gateway/PayPal
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use WC_Braintree\PayPal\Buttons;
use SkyVerge\WooCommerce\PluginFramework\v5_12_0 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree PayPal Gateway Class
 *
 * @since 3.0.0
 */
class WC_Gateway_Braintree_PayPal extends WC_Gateway_Braintree {


	/** PayPal payment type */
	const PAYMENT_TYPE_PAYPAL = 'paypal';


	/** @var bool whether cart checkout is enabled */
	protected $enable_cart_checkout;

	/** @var bool whether buy now buttons should be added to product pages */
	protected $enable_product_buy_now;

	/** @var bool whether paypal pay later is enabled */
	protected $enable_paypal_pay_later;

	/** @var string button color */
	protected $button_color;

	/** @var string button size */
	protected $button_size;

	/** @var string button shape */
	protected $button_shape;

	/** @var Buttons\Abstract_Button[] PayPal button handler instances  */
	protected $button_handlers = [];


	/**
	 * Initialize the gateway
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		parent::__construct(
			WC_Braintree::PAYPAL_GATEWAY_ID,
			wc_braintree(),
			array(
				'method_title'       => __( 'Braintree (PayPal)', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'method_description' => __( 'Allow customers to securely pay using their PayPal account via Braintree.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'supports'           => array(
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
				),
				'payment_type'       => self::PAYMENT_TYPE_PAYPAL,
				'environments'       => $this->get_braintree_environments(),
				'shared_settings'    => $this->shared_settings_names,
			)
		);

		$this->init_paypal_buttons();

		// tweak some frontend text so it matches PayPal
		add_filter( 'gettext', array( $this, 'tweak_payment_methods_text' ), 10, 3 );

		// tweak the "Delete" link text on the My Payment Methods table to "Unlink"
		add_filter( 'woocommerce_payment_methods_list_item', [ $this, 'tweak_my_payment_methods_delete_text' ], 10, 2 );

		// tweak the admin token editor to support PayPal accounts
		add_filter( 'wc_payment_gateway_braintree_paypal_token_editor_fields', array( $this, 'adjust_token_editor_fields' ) );

		// sanitize admin options before saving
		add_filter( 'woocommerce_settings_api_sanitized_fields_braintree_paypal', array( $this, 'filter_admin_options' ) );

		// get the client token via AJAX
		add_filter( 'wp_ajax_wc_' . $this->get_id() . '_get_client_token',        array( $this, 'ajax_get_client_token' ) );
		add_filter( 'wp_ajax_nopriv_wc_' . $this->get_id() . '_get_client_token', array( $this, 'ajax_get_client_token' ) );
	}


	/**
	 * Initializes any PayPal buttons that may be required on the current page.
	 *
	 * @since 2.3.0
	 */
	protected function init_paypal_buttons() {

		if ( ! $this->is_available() ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( $this->product_page_buy_now_enabled() ) {
			$this->button_handlers['product'] = new Buttons\Product( $this );
		}

		if ( $this->cart_checkout_enabled() ) {
			$this->button_handlers['cart'] = new Buttons\Cart( $this );
		}
	}


	/**
	 * Enqueues the PayPal JS scripts
	 *
	 * @since 2.1.0
	 * @see SV_WC_Payment_Gateway::enqueue_gateway_assets()
	 */
	public function enqueue_gateway_assets() {

		if ( $this->is_available() && $this->is_payment_form_page() ) {

			parent::enqueue_gateway_assets();

			wp_enqueue_script( 'braintree-js-paypal-client', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', [], WC_Braintree::VERSION, true );
			wp_enqueue_script( 'braintree-js-paypal-checkout', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/paypal-checkout.min.js', [], WC_Braintree::VERSION, true );
			wp_enqueue_script( 'braintree-js-data-collector', 'https://js.braintreegateway.com/web/' . WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/data-collector.min.js', array( 'braintree-js-client' ), WC_Braintree::VERSION, true );
		}
	}


	/**
	 * Determines if the current page contains a payment form.
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	public function is_payment_form_page() {

		$product = wc_get_product( get_the_ID() );
		$is_product_page = $product instanceof \WC_Product;

		return parent::is_payment_form_page() || is_cart() || ( $is_product_page && $this->product_page_buy_now_enabled() );
	}


	/**
	 * Add PayPal-specific fields to the admin payment token editor
	 *
	 * @since 3.2.0
	 * @return array
	 */
	public function adjust_token_editor_fields() {

		$fields = array(
			'id' => array(
				'label'    => __( 'Token ID', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'editable' => false,
				'required' => true,
			),
			'payer_email' => array(
				'label'   => __( 'Email', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'editable' => false,
			),
		);

		return $fields;
	}


	/**
	 * Initializes the payment form handler.
	 *
	 * @since 2.4.0
	 *
	 * @return \WC_Braintree_PayPal_Payment_Form
	 */
	protected function init_payment_form_instance() {

		return new \WC_Braintree_PayPal_Payment_Form( $this );
	}


	/**
	 * Tweak two frontend strings so they match PayPal lingo instead of "Bank". This is
	 * the least hacky approach that doesn't require fairly significant refactoring
	 * of the framework code responsible for these strings, or results in an approach
	 * that won't work when the strings are translated
	 *
	 * @since 3.0.0
	 * @param string $translated_text translated text
	 * @param string $raw_text pre-translated text
	 * @param string $text_domain text domain
	 * @return string
	 */
	public function tweak_payment_methods_text( $translated_text, $raw_text, $text_domain ) {

		if ( 'woocommerce-gateway-paypal-powered-by-braintree' === $text_domain ) {

			if ( 'Use a new bank account' === $raw_text ) {

				$translated_text = __( 'Use a new PayPal account', 'woocommerce-gateway-paypal-powered-by-braintree' );

			} elseif ( 'Bank Accounts' === $raw_text ) {

				$translated_text = __( 'PayPal Accounts', 'woocommerce-gateway-paypal-powered-by-braintree' );
			}
		}

		return $translated_text;
	}


	/**
	 * Tweak the "Delete" link on the My Payment Methods actions list to "Unlink"
	 * which is more semantically correct (and less likely to cause customers
	 * to think they are deleting their actual PayPal account)
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 *
	 * @param array $item individual list item from woocommerce_saved_payment_methods_list
	 * @param \WC_Payment_Token $core_token payment token associated with this method entry
	 *
	 * @return array
	 */
	public function tweak_my_payment_methods_delete_text( $item, $core_token ) {

		if ( isset( $item['actions']['delete'] ) && $core_token instanceof \WC_Payment_Token_Braintree_PayPal ) {
			$item['actions']['delete']['name'] = __( 'Unlink', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		return $item;
	}


	/**
	 * Adds any credit card authorization/charge admin fields, allowing the
	 * administrator to choose between performing authorizations or charges.
	 *
	 * Overridden to add the Cart Checkout setting in an appropriate spot.
	 *
	 * @since 2.1.0
	 *
	 * @param array $form_fields gateway form fields
	 * @return array
	 */
	protected function add_authorization_charge_form_fields( $form_fields ) {

		$form_fields['button_appearance_title'] = [
			'type'  => 'title',
			'title' => esc_html__( 'Button Appearance', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		];

		$form_fields['button_color'] = [
			'type'  => 'select',
			'title' => esc_html__( 'Button Color', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'options' => [
				'gold'   => esc_html__( 'Gold', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'blue'   => esc_html__( 'Blue', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'silver' => esc_html__( 'Silver', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'white'  => esc_html__( 'White', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'black'  => esc_html__( 'Black', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			],
			'default' => 'gold',
			'class'   => 'wc-enhanced-select',
		];

		$form_fields['button_size'] = [
			'type'  => 'select',
			'title' => esc_html__( 'Button Size', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'options' => [
				'medium'     => esc_html__( 'Medium', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'large'      => esc_html__( 'Large', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'responsive' => esc_html__( 'Responsive', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			],
			'default' => 'responsive',
			'class'   => 'wc-enhanced-select',
		];

		$form_fields['button_shape'] = [
			'type'  => 'select',
			'title' => esc_html__( 'Button Shape', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'options' => [
				'pill' => _x( 'Pill', 'button shape option', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'rect' => _x( 'Rectangle', 'button shape option', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			],
			'default' => 'pill',
			'class'   => 'wc-enhanced-select',
		];

		if( $this->is_paypal_pay_later_supported() ) {

			$form_fields['enable_paypal_pay_later'] = [
				'title'       => esc_html__( 'PayPal Pay Later offers', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Show the Pay Later button beneath the standard PayPal button', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'desc_tip'    => esc_html__( 'Pay Later buttons and messaging are only shown to eligible buyers', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'description' => $this->get_settings_description_text(),
				'default'     => 'yes',
			];

			$form_fields['pay_later_messaging_logo_type'] = [
				'title'       => esc_html__( 'Pay Later Messaging Logo Type', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'select',
				'options'     => [
					'primary'     => esc_html__( 'Single-line PayPal logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'alternative' => esc_html__( '"PP" monogram logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'inline'      => esc_html__( 'PayPal logo inline with the content', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'none'        => esc_html__( 'No logo, text only', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'default'     => 'inline',
				'class'       => 'pay-later-field wc-enhanced-select',
			];

			$form_fields['pay_later_messaging_logo_position'] = [
				'title'       => esc_html__( 'Pay Later Messaging Logo Position', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'select',
				'options'     => [
					'left'  => esc_html__( 'Logo left of the text', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'right' => esc_html__( 'Logo right of the text', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'top'   => esc_html__( 'Logo above the text', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'default'     => 'left',
				'class'       => 'pay-later-field wc-enhanced-select',
			];

			$form_fields['pay_later_messaging_text_color'] = [
				'title'       => esc_html__( 'Pay Later Messaging Text Color', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'type'        => 'select',
				'options'     => [
					'black'      => esc_html__( 'Black text with colored logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'white'      => esc_html__( 'White text with a white logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'monochrome' => esc_html__( 'Black text with a black logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'grayscale'  => esc_html__( 'Black text with a grayscale logo', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				],
				'default'     => 'black',
				'class'       => 'pay-later-field wc-enhanced-select',
			];
		}

		$form_fields['button_preview'] = [
			'type'  => 'button_preview',
		];

		$form_fields['enable_product_buy_now'] = [
			'title' => esc_html__( 'Buy Now on Product Pages', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'label' => esc_html__( 'Add the PayPal Buy Now button to product pages.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type' => 'checkbox',
			'default' => 'yes',
		];

		$form_fields['enable_cart_checkout'] = array(
			'title'   => esc_html__( 'Enable Cart Checkout', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Allow customers to check out with PayPal from the Cart page', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'default' => 'yes',
		);

		$form_fields['disable_funding'] = array(
			'title'        => esc_html__( 'Disable funding sources', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'type'         => 'multiselect',
			'class'       => 'wc-enhanced-select',
			'default'      => array(),
			'desc_tip'     => true,
			'description'  => esc_html__(
				'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
				'woocommerce-paypal-payments'
			),
			'options'      => array(
				'card'       => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'blik'       => _x( 'BLIK', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'sepa'       => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'bancontact' => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'eps'        => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'giropay'    => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'ideal'      => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'mercadopago'=> _x( 'Mercado Pago', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'mybank'     => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'p24'        => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'sofort'     => _x( 'Sofort', 'Name of payment method', 'woocommerce-paypal-payments' ),
				'venmo'      => _x( 'Venmo', 'Name of payment method', 'woocommerce-paypal-payments' ),
			)
		);

		return parent::add_authorization_charge_form_fields( $form_fields );
	}


	/**
	 * Generates HTML for the PayPal button preview.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	protected function generate_button_preview_html() {

		wp_enqueue_script( 'braintree-js-paypal-sdk', $this->get_paypal_sdk_url(), array(), null, true );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'Preview', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
			</th>
			<td class="forminp">
				<div id="wc_braintree_paypal_button_preview_container" style="max-width: 400px; pointer-events: none;">
					<?php if ( $this->is_paypal_pay_later_supported() ) : ?>
						<div id="wc_braintree_paypal_pay_later_message_preview" data-pp-layout="text" <?php echo wp_kses( $this->get_pay_later_messaging_style_attributes(), '' ); ?>></div>
					<?php endif; ?>
					<div id="wc_braintree_paypal_button_preview" style="max-width:400px; pointer-events:none;"></div>
					<div id="wc_braintree_paypal_button_preview_paylater" style="max-width:400px; pointer-events:none;"></div>
				</div>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}


	/**
	 * Gets the URL used to load the PayPal SDK for the button preview.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	protected function get_paypal_sdk_url() {

		// Gets the store country.
		$buyer_country = WC()->countries->get_base_country();

		$args = [
			'client-id'     => $this->get_sandbox_sdk_client_id( $buyer_country ),
			'components'    => 'buttons,messages,funding-eligibility',
			'buyer-country' => $buyer_country,
			'currency'      => get_woocommerce_currency(),
		];

		if ( $this->should_force_buyer_country_on_loading_sdk() && ( $buyer_country = get_user_meta( wp_get_current_user()->ID, 'billing_country', true ) ) ) {
			$args['buyer-country'] = $buyer_country;
		}

		return esc_url( add_query_arg( rawurlencode_deep( $args ), 'https://www.paypal.com/sdk/js' ) );
	}


	/**
	 * Add PayPal method specific form fields, currently:
	 *
	 * + remove phone/URL dynamic descriptor (does not apply to PayPal)
	 *
	 * @since 3.0.0
	 * @see WC_Gateway_Braintree::get_method_form_fields()
	 * @return array
	 */
	protected function get_method_form_fields() {

		$fields = parent::get_method_form_fields();

		unset( $fields['phone_dynamic_descriptor'] );
		unset( $fields['url_dynamic_descriptor'] );

		return $fields;
	}


	/**
	 * Verify that a payment method nonce is present before processing the
	 * transaction
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	protected function validate_paypal_fields( $is_valid ) {

		return $this->validate_payment_nonce( $is_valid );
	}


	/**
	 * Gets the PayPal checkout locale based on the WordPress locale
	 *
	 * @link http://wpcentral.io/internationalization/
	 * @link https://developers.braintreepayments.com/guides/paypal/vault/javascript/v2#country-and-language-support
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_safe_locale() {

		$locale = strtolower( get_locale() );

		$safe_locales = array(
				'en_au',
				'de_at',
				'en_be',
				'en_ca',
				'da_dk',
				'en_us',
				'fr_fr',
				'de_de',
				'en_gb',
				'zh_hk',
				'it_it',
				'nl_nl',
				'no_no',
				'pl_pl',
				'es_es',
				'sv_se',
				'en_ch',
				'tr_tr',
				'es_xc',
				'fr_ca',
				'ru_ru',
				'en_nz',
				'pt_pt',
		);

		if ( ! in_array( $locale, $safe_locales ) ) {
			$locale = 'en_us';
		}

		/**
		 * Braintree PayPal Locale Filter.
		 *
		 * Allow actors to filter the locale used for the Braintree SDK
		 *
		 * @since 3.0.0
		 * @param string $lang The button locale.
		 * @return string
		 */
		return apply_filters( 'wc_braintree_paypal_locale', $locale );
	}


	/**
	 * Performs a payment transaction for the given order and returns the
	 * result
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::do_transaction()
	 * @param \WC_Order $order the order object
	 * @return \SV_WC_Payment_Gateway_API_Response the response
	 */
	protected function do_paypal_transaction( WC_Order $order ) {

		if ( $this->perform_credit_card_charge( $order ) ) {
			$response = $this->get_api()->credit_card_charge( $order );
		} else {
			$response = $this->get_api()->credit_card_authorization( $order );
		}

		// success! update order record
		if ( $response->transaction_approved() ) {

			// order note, e.g. Braintree (PayPal) Sandbox Payment Approved (Transaction ID ABC)
			/* translators: Placeholders: %1$s - payment method title (e.g. PayPal), %2$s - transaction environment (either Sandbox or blank string), %3$s - type of transaction (either Authorization or Payment) */
			$message = sprintf(
				esc_html__( '%1$s %2$s %3$s Approved', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				$this->get_method_title(),
				$this->is_test_environment() ? esc_html__( 'Sandbox', 'woocommerce-gateway-paypal-powered-by-braintree' ) : '',
				$this->perform_credit_card_authorization( $order ) ? esc_html__( 'Authorization', 'woocommerce-gateway-paypal-powered-by-braintree' ) : esc_html__( 'Payment', 'woocommerce-gateway-paypal-powered-by-braintree' )
			);

			// adds the transaction id (if any) to the order note
			if ( $response->get_transaction_id() ) {
				/* translators: Placeholders: %s - transaction ID */
				$message .= ' ' . sprintf( esc_html__( '(Transaction ID %s)', 'woocommerce-gateway-paypal-powered-by-braintree' ), $response->get_transaction_id() );
			}

			$order->add_order_note( $message );
		}

		return $response;
	}


	/**
	 * Get the order note message when a customer saves their PayPal account
	 * to their WC account
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::get_saved_payment_method_token_order_note()
	 * @param \WC_Braintree_Payment_Method $token the payment token being saved
	 * @return string
	 */
	protected function get_saved_payment_token_order_note( $token ) {

		return sprintf( esc_html__( 'PayPal Account Saved: %s', 'woocommerce-gateway-paypal-powered-by-braintree' ), $token->get_payer_email() );
	}


	/**
	 * Adds any gateway-specific transaction data to the order
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway_Direct::add_transaction_data()
	 * @param \WC_Order $order the order object
	 * @param \WC_Braintree_API_PayPal_Transaction_Response $response the transaction response
	 */
	public function add_payment_gateway_transaction_data( $order, $response ) {

		// authorization code, called "Authorization Unique Transaction ID" by PayPal
		if ( $response->get_authorization_code() ) {
			$this->update_order_meta( $order, 'authorization_code', $response->get_authorization_code() );
		}

		// charge captured
		if ( $order->payment_total > 0 ) {
			// mark as captured
			if ( $this->perform_credit_card_charge( $order ) ) {
				$captured = 'yes';
			} else {
				$captured = 'no';
			}
			$this->update_order_meta( $order, 'charge_captured', $captured );
		}

		// payer email
		if ( $response->get_payer_email() ) {
			$this->update_order_meta( $order, 'payer_email', $response->get_payer_email() );
		}

		// payment ID
		if ( $response->get_payment_id() ) {
			$this->update_order_meta( $order, 'payment_id', $response->get_payment_id() );
		}

		// debug ID, if logging is enabled
		if ( $this->debug_log() && $response->get_debug_id() ) {
			$this->update_order_meta( $order, 'debug_id', $response->get_debug_id() );
		}
	}


	/**
	 * Builds the Pre-Orders integration class instance.
	 *
	 * @since 2.4.0
	 *
	 * @return \WC_Braintree\Integrations\Pre_Orders
	 */
	protected function build_pre_orders_integration() {

		require_once( $this->get_plugin()->get_plugin_path() . '/includes/integrations/Pre_Orders.php' );

		return new WC_Braintree\Integrations\Pre_Orders( $this );
	}


	/** Refund feature ********************************************************/


	/**
	 * Adds PayPal-specific data to the order after a refund is performed
	 *
	 * @since 3.0.0
	 * @param \WC_Order $order the order object
	 * @param \WC_Braintree_API_PayPal_Transaction_Response $response the transaction response
	 */
	protected function add_payment_gateway_refund_data( WC_Order $order, $response ) {

		if ( $response->get_refund_id() ) {
			// add_order_meta() to account for multiple refunds on a single order
			$this->add_order_meta( $order, 'refund_id', $response->get_refund_id() );
		}
	}


	/** Getters ***************************************************************/


	/**
	 * Gets the array of instantiated button handlers.
	 *
	 * @since 2.3.0
	 *
	 * @return Buttons\Abstract_Button[]
	 */
	public function get_button_handlers() {

		return $this->button_handlers;
	}


	/**
	 * Get the default payment method title, which is configurable within the
	 * admin and displayed on checkout
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway::get_default_title()
	 * @return string payment method title to show on checkout
	 */
	protected function get_default_title() {

		return esc_html__( 'PayPal', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Get the default payment method description, which is configurable
	 * within the admin and displayed on checkout
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway::get_default_description()
	 * @return string payment method description to show on checkout
	 */
	protected function get_default_description() {

		return esc_html__( 'Click the PayPal icon below to sign into your PayPal account and pay securely.', 'woocommerce-gateway-paypal-powered-by-braintree' );
	}


	/**
	 * Override the default icon to set a PayPal-specific one
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_icon() {

		// from https://www.paypal.com/webapps/mpp/logos-buttons
		$icon_html = '<img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_100x26.png" alt="PayPal" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->get_id() );
	}


	/**
	 * Return the PayPal payment method image URL
	 *
	 * @since 3.0.0
	 * @see SV_WC_Payment_Gateway::get_payment_method_image_url()
	 * @param string $type unused
	 * @return string the image URL
	 */
	public function get_payment_method_image_url( $type ) {

		return parent::get_payment_method_image_url( 'paypal' );
	}


	/**
	 * Braintree PayPal acts like a direct gateway
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports authorization
	 */
	public function supports_credit_card_authorization() {
		return $this->supports( self::FEATURE_CREDIT_CARD_AUTHORIZATION );
	}


	/**
	 * Braintree PayPal acts like a direct gateway
	 *
	 * @since 3.0.0
	 * @return boolean true if the gateway supports charges
	 */
	public function supports_credit_card_charge() {
		return $this->supports( self::FEATURE_CREDIT_CARD_CHARGE );
	}


	/**
	 * Determines if cart checkout is enabled.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public function cart_checkout_enabled() {

		/**
		 * Filters whether cart checkout is enabled.
		 *
		 * @since 2.1.0
		 *
		 * @param bool $enabled whether cart checkout is enabled in the settings
		 * @param \WC_Gateway_Braintree_PayPal $gateway gateway object
		 */
		return (bool) apply_filters( 'wc_braintree_paypal_cart_checkout_enabled', 'no' !== $this->enable_cart_checkout, $this );
	}


	/**
	 * Determines if buy now buttons should be added to the product pages.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function product_page_buy_now_enabled() {

		/**
		 * Filters whether product page buy now buttons are enabled.
		 *
		 * @since 2.3.0
		 *
		 * @param bool $enabled whether product buy now buttons are enabled in the settings
		 * @param \WC_Gateway_Braintree_PayPal $gateway gateway object
		 */
		return (bool) apply_filters( 'wc_braintree_paypal_product_buy_now_enabled', 'no' !== $this->enable_product_buy_now, $this );
	}


	/**
	 * Determines whether the PayPal Pay Later button is enabled.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public function is_paypal_pay_later_enabled() {

		return 'no' !== $this->enable_paypal_pay_later && $this->is_paypal_pay_later_supported();
	}


	/**
	 * Determines whether PayPal Pay Later is supported.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public function is_paypal_pay_later_supported() {

		// pay later buttons can only be enabled by merchants with the following store base locations and currencies
		$supported_settings = [
			'US' => 'USD',
			'GB' => 'GBP',
			'FR' => 'EUR',
			'DE' => 'EUR',
			'AU' => 'AUD',
			'IT' => 'EUR',
			'ES' => 'EUR',
		];

		// gets the store base country
		$base_country = strtoupper( isset( WC()->countries ) ? WC()->countries->get_base_country() : null );

		// gets the store currency
		$currency = strtoupper( get_woocommerce_currency() );

		return isset( $supported_settings[ $base_country ] ) && $currency === $supported_settings[ $base_country ];
	}


	/**
	 * Gets the configured logo type for the Pay Later messaging component.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public function get_pay_later_messaging_logo_type() {

		return $this->get_option( 'pay_later_messaging_logo_type' );
	}


	/**
	 * Gets the configured logo position for the Pay Later messaging component.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public function get_pay_later_messaging_logo_postion() {

		return $this->get_option( 'pay_later_messaging_logo_position' );
	}


	/**
	 * Gets the configured text color for the Pay Later messaging component.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public function get_pay_later_messaging_text_color() {

		return $this->get_option( 'pay_later_messaging_text_color' );
	}


	/**
	 * Gets disabled funding sources that should not be offer to customers
	 *
	 * @since 2.6.1
	 *
	 * @return array
	 */
	public function get_disabled_funding_sources() {
		$disabled_funding = $this->get_option( 'disable_funding' );

		if ( !$disabled_funding ) {
			return array();
		}
		return $disabled_funding;
	}


	/**
	 * Determines whether PayPal Debit/Credit Card option should be offered to customers.
	 *
	 * The Debit/Credit Card should only be shown if the merchant has not enabled Credit Card via Braintree.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public function is_paypal_card_enabled() {

		if ( $credit_card = $this->get_plugin()->get_gateway( WC_Braintree::CREDIT_CARD_GATEWAY_ID ) ) {
			return ! $credit_card->is_enabled();
		}

		return true;
	}


	/**
	 * Determines whether the buyer country must be programmatically set or not.
	 *
	 * When this method returns true, the PayPal SDK will force the buyer country param, which
	 * is retrieved automatically by PayPal by default.
	 *
	 * @since 2.5.0
	 *
	 * @return bool
	 */
	public function should_force_buyer_country_on_loading_sdk() {

		$force_buyer_country =
			defined( 'WP_DEBUG' ) &&
			WP_DEBUG &&
			$this->is_test_environment();

		/**
		 * Allows overriding the buyer's country parameter when loading the PayPal SDK.
		 *
		 * This filter must be used for testing purposes only! If it returns true, the PayPal SDK will
		 * load with the buyer country param explicitly declared and picking the logged user's billing country.
		 *
		 * @since 2.5.0
		 *
		 * @param bool $force_buyer_country whether the buyer country will be set programmatically or not
		 * @param WC_Gateway_Braintree_PayPal $this instance
		 */
		return apply_filters( 'wc_braintree_should_force_buyer_country_on_loading_sdk', $force_buyer_country, $this );
	}


	/**
	 * Gets the configured button color.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_button_color() {

		return $this->get_option( 'button_color' );
	}


	/**
	 * Gets the configured button size.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_button_size() {

		return $this->get_option( 'button_size' );
	}


	/**
	 * Gets the configured button shape.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	public function get_button_shape() {

		return $this->get_option( 'button_shape' );
	}


	/**
	 * Add Braintree-specific data to the order prior to processing, currently:
	 *
	 * @since 2.5.0

	 * @see SV_WC_Payment_Gateway_Direct::get_order()

	 * @param int $order order ID being processed
	 * @return \WC_Order object with payment and transaction information attached
	 */
	public function get_order( $order ) {

		$order = parent::get_order( $order );

		return $order;
	}


	/**
	 * Gets the standard button sizes provided by the original Checkout.js customization options so that we can set the
	 * height param and container width as expected by the new SDK.
	 *
	 * @see https://developer.paypal.com/docs/archive/checkout/how-to/customize-button/#size
	 * @see https://developer.paypal.com/docs/checkout/integration-features/customize-button
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_button_sizes() {

		return [
			'small'  => [
				'width'  => 150,
				'height' => 25,
			],
			'medium' => [
				'width'  => 250,
				'height' => 35,
			],
			'large'  => [
				'width'  => 350,
				'height' => 40,
			],
		];
	}


	/**
	 * Gets the button height based on the configured button size.
	 *
	 * @since 2.5.0
	 * @param string $size
	 * @return int|null
	 */
	public function get_button_height( $size ) {

		$button_sizes = $this->get_button_sizes();

		return ! empty( $button_sizes[ $size ] ) ? $button_sizes[ $size ]['height'] : null;
	}


	/**
	 * Gets the button width based on the configured button size.
	 *
	 * @since 2.5.0
	 * @param string $size
	 * @return int|null
	 */
	public function get_button_width( $size ) {

		$button_sizes = $this->get_button_sizes();

		return ! empty( $button_sizes[ $size ] ) ? $button_sizes[ $size ]['width'] : null;
	}


	/**
	 * Gets the style tag for the button container HTML.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_button_container_style() {

		$container_style = '';
		$container_width = $this->get_button_width( $this->get_button_size() );
		if ( ! empty( $container_width ) ) {
			$container_style = 'style="width:' . $container_width . 'px;"';
		}

		return $container_style;
	}


	/**
	 * Gets the style attributes for the Pay Later Messaging container element.
	 *
	 * @since 2.5.0
	 *
	 * @return string
	 */
	public function get_pay_later_messaging_style_attributes() {

		$attributes = [
			'data-pp-style-logo-type'     => $this->get_pay_later_messaging_logo_type(),
			'data-pp-style-logo-position' => $this->get_pay_later_messaging_logo_postion(),
			'data-pp-style-text-color'    => $this->get_pay_later_messaging_text_color(),
		];

		return implode( ' ', array_map( function( $name, $value ) {
			return sprintf( '%s="%s"', $name, esc_attr( $value ) );
		}, array_keys( $attributes ), array_values( $attributes ) ) );
	}


	/**
	 * Gets admin params.
	 *
	 * @internal
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_admin_params() {

		return [
			'button_sizes' => $this->get_button_sizes(),
		];
	}


	/**
	 * Gets the specific settings help text for a given country.
	 *
	 * @since 2.5.0
	 *
	 * @return string the best help text for the store's country
	 */
	protected function get_settings_description_text() {

		// gets the store base country
		$country = WC()->countries->get_base_country();

		if ( 'FR' === $country ) {
			return esc_html__( 'Displays Pay Later messaging for available offers. Vaulted payment/billing agreement integrations and some merchant categories are not eligible to promote Pay in 4X. You may not add additional content, wording, marketing or other material to encourage use. PayPal reserves the right to take action in accordance with the User agreement.', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		// replaces the United Kingdom country code to UK as accepted by the PayPal learn more link
		$country = 'GB' === $country ? 'UK' : $country;

		if ( in_array( $country, [ 'US', 'UK', 'DE', 'AU', 'IT', 'ES' ] ) ) {

			return sprintf(
				/** translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
				__( 'Displays Pay Later messaging for available offers. Restrictions apply. %1$sClick here to learn more.%2$s', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'<a href="https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/' . strtolower( $country ) . '/" target="_blank">',
				'</a>'
			);
		}

		return '';
	}


	/**
	 * Gets the specific client ID for a given country.
	 *
	 * @since 2.5.0
	 *
	 * @param string $country the country code to determine which client ID to be returned
	 * @return string the best client ID for the given country
	 */
	protected function get_sandbox_sdk_client_id( $country ) {

		$supported_countries = [
			'US' => 'AZ9MoAnROxNajqLdyqq6HOv1_DTLj1UL8Yr0Eav875FQvchaz4Xvqo53UwiQBWuSkfBcQj8PM3JsDU7c',
			'GB' => 'AYWNWViebHPcyXNmWe_W2zgXyicRmBpBU3QTs-wFa7I9tfeqYeYz5dShmRnDgE3UTjpzZy-_wEc4lzZE',
			'DE' => 'AdowVAtuU8-qDkQ36TGwtopX2Y2w6D3n42gc_w9bBWm2HieWyByc8-ugVHKY-xhESgAKdkLl-7XyHRJu',
			'FR' => 'AQX3zTYQapL1f3gomgwDM-5lzHJsGTEQ6-UnAo8cc9QsyweMQOtjE30f5tRuBxbDS9loctREppyZRXXH',
			'AU' => 'ATNkCFUvcxbsRysc_yrXH-mbAYlzvXsvayghKp3LPnX4uhgCHkX1MFv1Mj4Z3dYMvVgqeMwlAarYfXjB',
			'IT' => 'ATaaaGAlWJL3mNG4iWJN_YVu0qseY88v3L4_RgcHBfXhFmdc1BxkPhIetF9AL0whVeLF6xcB8PKiRn10',
			'ES' => 'AdFciOp79QaJZQiuj2zPzuOcQTq3wy5AT7Iz5b15kN1IlUWUglFUHV4D2Grns2qY8U0PszMccZdM30Dn',
		];

		// returns the client ID if defined for the country or a default one that will work as a generic client
		return isset( $supported_countries[ $country ] ) ? $supported_countries[ $country ] : 'sb';
	}
}
