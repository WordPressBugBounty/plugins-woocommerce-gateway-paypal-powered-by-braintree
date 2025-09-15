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
 * @package   WC-Braintree/Admin
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\Admin;

use Braintree\PaymentInstrumentType;
use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Admin Order handler.
 *
 * @since 3.4.0
 */
class Order {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_transaction_data' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_braintree_get_transaction_data', [ $this, 'ajax_get_transaction_data' ] );
	}

	/**
	 * Display transaction data button in the Order Details section.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function display_transaction_data( $order ) {
		// Check if this is a Braintree order.
		$payment_method = $order->get_payment_method();
		$plugin         = \WC_Braintree\WC_Braintree::instance();

		// Use the plugin's gateway IDs.
		if ( ! in_array( $payment_method, [ $plugin::CREDIT_CARD_GATEWAY_ID, $plugin::PAYPAL_GATEWAY_ID ], true ) ) {
			return;
		}

		// Check if transaction ID exists.
		$gateway        = $plugin->get_gateway( $payment_method );
		$transaction_id = $gateway->get_order_meta( $order, 'trans_id' );

		// Only show the button if we have a transaction ID.
		if ( ! $transaction_id ) {
			return;
		}

		// Output the button.
		?>
		<div class="braintree-transaction-data">
			<h3>
				<?php esc_html_e( 'View Transaction Details', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>
				<a href="#" id="braintree-open-modal" class="braintree-info-icon" title="<?php esc_attr_e( 'View transaction details', 'woocommerce-gateway-paypal-powered-by-braintree' ); ?>">
					<span class="dashicons dashicons-visibility"></span>
				</a>
			</h3>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for the order edit page.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id || ! isset( $_GET['action'] ) || 'edit' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		// Enqueue admin styles.
		wp_enqueue_style(
			'braintree-admin-order',
			\WC_Braintree\WC_Braintree::instance()->get_plugin_url() . '/assets/css/admin/order.min.css',
			[],
			\WC_Braintree\WC_Braintree::VERSION
		);

		// Enqueue admin script.
		wp_enqueue_script(
			'braintree-admin-order',
			\WC_Braintree\WC_Braintree::instance()->get_plugin_url() . '/assets/js/admin/order.min.js',
			[ 'jquery', 'wp-i18n' ],
			\WC_Braintree\WC_Braintree::VERSION,
			true
		);

		wp_set_script_translations(
			'braintree-admin-order',
			'woocommerce-gateway-paypal-powered-by-braintree'
		);

		$order_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		// Localize script.
		wp_localize_script(
			'braintree-admin-order',
			'braintree_admin_order',
			[
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'order_id'               => $order_id,
				'transaction_data_nonce' => wp_create_nonce( 'braintree_get_transaction_data' ),
			]
		);
	}


	/**
	 * AJAX handler to get transaction data from Braintree
	 */
	public function ajax_get_transaction_data() {
		// Verify nonce.
		$nonce = Framework\SV_WC_Helper::get_requested_value( 'transaction_data_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'braintree_get_transaction_data' ) ) {
			wp_die( esc_html__( 'Invalid nonce', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		// Verify capabilities.
		if ( ! current_user_can( 'edit_shop_orders' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'Insufficient permissions', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		$order_id = intval( Framework\SV_WC_Helper::get_requested_value( 'order_id' ) );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found', 'woocommerce-gateway-paypal-powered-by-braintree' ) ] );
		}

		// Check if this is a Braintree order.
		$payment_method = $order->get_payment_method();
		$plugin         = \WC_Braintree\WC_Braintree::instance();

		if ( ! in_array( $payment_method, [ $plugin::CREDIT_CARD_GATEWAY_ID, $plugin::PAYPAL_GATEWAY_ID ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Not a Braintree order', 'woocommerce-gateway-paypal-powered-by-braintree' ) ] );
		}

		// Get transaction ID.
		$gateway        = $plugin->get_gateway( $payment_method );
		$transaction_id = $gateway->get_order_meta( $order, 'trans_id' );

		if ( ! $transaction_id ) {
			wp_send_json_error( [ 'message' => __( 'Transaction ID not found', 'woocommerce-gateway-paypal-powered-by-braintree' ) ] );
		}

		try {
			// Fetch transaction data from Braintree.
			$api          = $gateway->get_api();
			$payment_type = \WC_Braintree\WC_Braintree::PAYPAL_GATEWAY_ID === $payment_method ? 'paypal' : 'credit_card';
			$response     = $api->get_transaction( $transaction_id, $payment_type );

			// Format the data for display.
			$transaction_data = $this->format_transaction_data( $response, $order );

			wp_send_json_success( $transaction_data );

		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}


	/**
	 * Format transaction data for display
	 *
	 * @param mixed     $response Braintree API response.
	 * @param \WC_Order $order Order object.
	 * @return array Formatted transaction data
	 */
	private function format_transaction_data( $response, $order ) {
		$data = [];

		// General Info.
		$data['general'] = [
			'status'              => $response->get_status(),
			'type'                => $response->get_transaction_type(),
			'merchant_account_id' => $response->get_merchant_account_id() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		];

		// Response Data.
		$data['response'] = [
			'authorization_code' => $response->get_authorization_code() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'avs_result'         => $response->get_avs_result() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			'cvv_result'         => $response->get_csc_result() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		];

		// Fraud checks.
		if ( method_exists( $response, 'get_risk_decision' ) ) {
			$data['response']['risk_decision'] = $response->get_risk_decision() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		// 3DS data.
		$data['three_ds'] = [];
		if ( method_exists( $response, 'get_three_d_secure_info' ) ) {
			$three_ds_info = $response->get_three_d_secure_info();
			if ( $three_ds_info ) {
				$data['three_ds'] = [
					'status'                   => $three_ds_info['status'] ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'liability_shifted'        => isset( $three_ds_info['liability_shifted'] ) ? ( $three_ds_info['liability_shifted'] ? __( 'Yes', 'woocommerce-gateway-paypal-powered-by-braintree' ) : __( 'No', 'woocommerce-gateway-paypal-powered-by-braintree' ) ) : __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
					'liability_shift_possible' => isset( $three_ds_info['liability_shift_possible'] ) ? ( $three_ds_info['liability_shift_possible'] ? __( 'Yes', 'woocommerce-gateway-paypal-powered-by-braintree' ) : __( 'No', 'woocommerce-gateway-paypal-powered-by-braintree' ) ) : __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				];
			}
		}

		// Payment Data.
		$payment_method = $order->get_payment_method();
		if ( \WC_Braintree\WC_Braintree::CREDIT_CARD_GATEWAY_ID === $payment_method ) {
			$data['payment'] = [
				'card_type'       => $response->get_card_type() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'last_four'       => $response->get_last_four() ?? __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'payment_type'    => $this->format_payment_type( $response ),
				'transaction_fee' => $this->format_transaction_fee( $response ),
			];
		} elseif ( \WC_Braintree\WC_Braintree::PAYPAL_GATEWAY_ID === $payment_method ) {
			$data['payment'] = [
				'payer_email'     => method_exists( $response, 'get_payer_email' ) ? $response->get_payer_email() : __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' ),
				'payment_type'    => $this->format_payment_type( $response ),
				'transaction_fee' => $this->format_transaction_fee( $response ),
			];
		}

		return $data;
	}

	/**
	 * Format payment type for display
	 *
	 * @param mixed $response API response object.
	 * @return string Formatted payment type
	 */
	private function format_payment_type( $response ) {
		if ( ! method_exists( $response, 'get_payment_instrument_type' ) ) {
			return __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		$payment_type = $response->get_payment_instrument_type();

		if ( ! $payment_type ) {
			return __( 'N/A', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		// Format common payment types for better readability.
		$formatted_types = [
			PaymentInstrumentType::CREDIT_CARD    => __( 'Credit Card', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			PaymentInstrumentType::PAYPAL_ACCOUNT => __( 'PayPal Account', 'woocommerce-gateway-paypal-powered-by-braintree' ),
			PaymentInstrumentType::APPLE_PAY_CARD => __( 'Apple Pay', 'woocommerce-gateway-paypal-powered-by-braintree' ),
		];

		return $formatted_types[ $payment_type ] ?? ucwords( str_replace( '_', ' ', $payment_type ) );
	}

	/**
	 * Format transaction fee for display
	 *
	 * @param mixed $response API response object.
	 * @return string Formatted transaction fee
	 */
	private function format_transaction_fee( $response ) {
		if ( ! method_exists( $response, 'get_transaction_fee_amount' ) ) {
			return '';
		}

		$fee_amount = $response->get_transaction_fee_amount();

		if ( ! $fee_amount ) {
			return '';
		}

		// Get currency from the transaction if available.
		$currency = get_woocommerce_currency();

		// Format the fee amount with currency.
		return wc_price( $fee_amount, [ 'currency' => $currency ] );
	}
}
