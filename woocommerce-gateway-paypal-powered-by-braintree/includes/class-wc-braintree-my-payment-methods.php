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
 * @package   WC-Braintree/Gateway/Payment_Methods
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use SkyVerge\WooCommerce\PluginFramework\v5_12_7 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * My Payment Methods Class
 *
 * Renders the My Payment Methods table on the My Account page and handles
 * any associated actions (deleting a payment method, etc).
 * Overrides the default implementation in the SkyVerge framework.
 *
 * @since 2.6.2
 */
class WC_Braintree_My_Payment_Methods extends Framework\SV_WC_Payment_Gateway_My_Payment_Methods {

	/**
	 * Returns the JS handler class name. Overrides the one in the SV framework.
	 *
	 * @since 2.6.2
	 * @return string
	 */
	protected function get_js_handler_class_name() {
		return 'WC_Braintree_My_Payment_Methods_Handler';
	}

	/**
	 * Enqueue frontend CSS/JS.
	 *
	 * @since 2.6.2
	 */
	public function maybe_enqueue_styles_scripts() {
		parent::maybe_enqueue_styles_scripts();

		wp_enqueue_script( 'wc-braintree-my-payment-methods', $this->plugin->get_plugin_url() . '/assets/js/frontend/wc-braintree-my-payment-methods.min.js', array( 'jquery-tiptip', 'jquery' ), $this->plugin->get_version() );
	}

	/**
	 * Initializes the My Payment Methods table.
	 *
	 * @since 2.6.2
	 */
	public function init() {
		parent::init();

		if ( ! $this->is_payment_methods_page() ) {
			return;
		}

		add_action( 'woocommerce_account_payment_methods_column_expires', [ $this, 'add_payment_method_expires' ] );
	}

	/**
	 * Adds the Expires column content.
	 *
	 * @since 2.6.2
	 * @param array $method Payment method.
	 */
	public function add_payment_method_expires( $method ) {
		$token = ( ! empty( $method['token'] ) ) ? $this->plugin->get_gateway( $this->plugin::CREDIT_CARD_GATEWAY_ID )->get_payment_tokens_handler()->get_token( get_current_user_id(), $method['token'] ) : null;

		if ( ! $token ) {
			return;
		}

		// Can't escape the html being echoed here. The escaping is happening in the function itself.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_payment_method_expires_html( $token );
	}

	/**
	 * Returns a token's expiration date HTML.
	 * Escapes the HTML before returning it.
	 * @since 2.6.2
	 * @internal
	 * @param Framework\SV_WC_Payment_Gateway_Payment_Token $token Token object.
	 * @return string
	 */
	protected function get_payment_method_expires_html( $token ) {
		$html  = '';
		$html .= '<div class="view">';
		$html .= esc_html( $token->get_exp_date() );
		$html .= '</div>';
		$html .= '<div class="edit wc-braintree" style="display: none;">';
		$html .= '<input type="text" class="expires" name="expires" placeholder="MM/YY" value="' . esc_attr( $token->get_exp_date() ) . '" />';
		$html .= '</div>';

		// The html will not be escaped by whoever is calling this function. So make sure it is escaped before returning.
		return $html;
	}

	/**
	 * Saves a payment method via AJAX.
	 *
	 * @since 5.1.0
	 * @internal
	 */
	public function ajax_save_payment_method() {
		check_ajax_referer( 'wc_' . $this->get_plugin()->get_id() . '_save_payment_method', 'nonce' );

		try {

			$this->load_tokens();

			$token_id = Framework\SV_WC_Helper::get_posted_value( 'token_id' );

			if ( empty( $this->tokens[ $token_id ] ) || ! $this->tokens[ $token_id ] instanceof Framework\SV_WC_Payment_Gateway_Payment_Token ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( 'Invalid token ID' );
			}

			$user_id  = get_current_user_id();
			$token    = $this->tokens[ $token_id ];
			$gateway  = $this->get_plugin()->get_gateway_from_token( $user_id, $token );

			// bail if the gateway or token couldn't be found for this user
			if ( ! $gateway || ! $gateway->get_payment_tokens_handler()->user_has_token( $user_id, $token ) ) {
				throw new Framework\SV_WC_Payment_Gateway_Exception( 'Invalid token' );
			}

			$data = array();

			parse_str( Framework\SV_WC_Helper::get_posted_value( 'data' ), $data );

			// set the data
			$token = $this->save_token_data( $token, $data );

			// persist the data
			$gateway->get_payment_tokens_handler()->update_token( $user_id, $token );

			wp_send_json_success( [
				'title'   => $this->get_payment_method_title_html( $token ),
				'expires' => $this->get_payment_method_expires_html( $token ),
				'nonce'   => wp_create_nonce( 'wc_' . $this->get_plugin()->get_id() . '_save_payment_method' ),
			] );

		} catch ( Framework\SV_WC_Payment_Gateway_Exception $e ) {

			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Saves data to a token.
	 * Overrides the method in the parent class to add support for editing expiration dates.
	 *
	 * @since 2.6.2
	 * @param Framework\SV_WC_Payment_Gateway_Payment_Token $token Token object.
	 * @param array $data New data to store for the token.
	 * @return Framework\SV_WC_Payment_Gateway_Payment_Token
	 */
	protected function save_token_data( Framework\SV_WC_Payment_Gateway_Payment_Token $token, array $data ) {
		$token    = parent::save_token_data( $token, $data );
		$exp_date = $this->prepare_expiration_date( isset( $data['expires'] ) ? $data['expires'] : '' );

		if ( ! $exp_date ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( 'Invalid expiration date' );
		}

		if ( $token->get_exp_date() === $exp_date['month'] . '/' . $exp_date['year'] ) {
			return $token;
		}

		$token->set_exp_month( $exp_date['month'] );
		$token->set_exp_year( $exp_date['year'] );

		$api = $this->plugin->get_gateway( $this->plugin::CREDIT_CARD_GATEWAY_ID )->get_api();
		if ( ! $api instanceof WC_Braintree_API ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( 'Invalid token gateway' );
		}

		$response = $api->update_cc_token_expiration_date( $token->get_id(), $token->get_exp_date() );

		if ( ! $response->transaction_approved() ) {
			throw new Framework\SV_WC_Payment_Gateway_Exception( 'Could not update token' );
		}

		return $token;
	}

	/**
	 * Validates and splits an expiration date in format MM/YY into month and year parts.
	 *
	 * @param string $expiration_date The expiration date in MM/YY format.
	 * @return array|null NULL if expiration date is invalid. Otherwise, array with keys 'month' and 'year'.
	 */
	protected function prepare_expiration_date( $expiration_date ) {
		$expiration_date = trim( $expiration_date );

		if ( false === strstr( $expiration_date, '/' ) || 5 !== strlen( $expiration_date ) ) {
			return null;
		}

		list( $month, $year ) = array_map( 'strval', explode( '/', $expiration_date ) );

		if ( (int) $month < 1 || (int) $month > 12 ) {
			return null;
		}

		return compact( 'month', 'year' );
	}

}
