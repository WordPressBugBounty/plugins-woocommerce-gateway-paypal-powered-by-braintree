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

namespace WC_Braintree\Apple_Pay;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;
use WC_Braintree\WC_Braintree_Express_Checkout_Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * The Braintree Apple Pay frontend handler.
 *
 * @since 2.2.0
 */
class Frontend extends Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend {

	use WC_Braintree_Express_Checkout_Frontend;

	/**
	 * Gets the JS handler class name.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_js_handler_class_name() {

		return 'WC_Braintree_Apple_Pay_Handler';
	}


	/**
	 * Enqueues the scripts.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend::enqueue_scripts()
	 *
	 * @since 2.2.0
	 */
	public function enqueue_scripts() {

		parent::enqueue_scripts();

		// Register legacy Apple Pay script.
		wp_register_script( 'wc-braintree-apple-pay-js', $this->get_plugin()->get_plugin_url() . '/assets/js/frontend/wc-braintree-apple-pay.min.js', array( 'jquery', 'braintree-js-apple-pay' ), $this->get_plugin()->get_version(), true );

		// Register blocks Apple Pay script.
		$asset_path   = $this->get_plugin()->get_plugin_path() . '/assets/js/blocks/apple-pay.asset.php';
		$version      = $this->get_plugin()->get_version();
		$dependencies = array( 'braintree-js-apple-pay' );

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = array_merge( $dependencies, isset( $asset['dependencies'] ) ? $asset['dependencies'] : array() );
		}

		wp_register_script(
			'wc-braintree-blocks-apple-pay',
			$this->get_plugin()->get_plugin_url() . '/assets/js/blocks/apple-pay.min.js',
			$dependencies,
			$version,
			true
		);

		// braintree.js library.
		wp_enqueue_script( 'braintree-js-client', 'https://js.braintreegateway.com/web/' . \WC_Braintree\WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/client.min.js', array(), \WC_Braintree\WC_Braintree::VERSION, true );

		wp_enqueue_script( 'braintree-js-apple-pay', 'https://js.braintreegateway.com/web/' . \WC_Braintree\WC_Braintree::BRAINTREE_JS_SDK_VERSION . '/js/apple-pay.min.js', array( 'braintree-js-client' ), \WC_Braintree\WC_Braintree::VERSION, true );

		// Enqueue SkyVerge framework Apple Pay CSS.
		$framework_version = $this->get_plugin()->get_assets_version( 'braintree_credit_card' );
		wp_enqueue_style(
			'sv-wc-apple-pay-v5_15_10',
			$this->get_plugin()->get_payment_gateway_framework_assets_url() . '/css/frontend/sv-wc-payment-gateway-apple-pay.css',
			array(),
			$framework_version
		);

		// Enqueue plugin-specific Apple Pay CSS.
		$css_path = $this->get_plugin()->get_plugin_path() . '/assets/css/frontend/wc-apply-pay.min.css';

		if ( is_readable( $css_path ) ) {
			$css_url = $this->get_plugin()->get_plugin_url() . '/assets/css/frontend/wc-apply-pay.min.css';
			wp_enqueue_style( 'wc-braintree-apply-pay', $css_url, array(), $this->get_plugin()->get_version() );
		}

		// Enqueue the appropriate Apple Pay script based on the page type.
		if ( \WC_Braintree\WC_Braintree::is_blocks_page() ) {
			wp_enqueue_script( 'wc-braintree-blocks-apple-pay' );
		} elseif ( parent::should_enqueue_scripts() ) {
			// Only enqueue legacy script if framework says we should (classic pages).
			wp_enqueue_script( 'wc-braintree-apple-pay-js' );
		}
	}


	/**
	 * Gets the parameters to be passed to the JS handler.
	 *
	 * @see Framework\SV_WC_Payment_Gateway_Apple_Pay_Frontend::get_js_handler_args()
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	protected function get_js_handler_args() {

		$params = parent::get_js_handler_args();

		$params['store_name']         = mb_substr( \WC_Braintree\WC_Braintree::get_braintree_store_name(), 0, 64 );
		$params['client_token_nonce'] = wp_create_nonce( 'wc_' . $this->get_gateway()->get_id() . '_get_client_token' );
		$params['force_tokenization'] = $this->is_tokenization_forced();

		return $params;
	}


	/**
	 * Renders an Apple Pay button.
	 *
	 * @since 3.2.2
	 */
	public function render_button() {

		$button_text = '';
		$is_disabled = $this->is_tokenization_forced() ? 'disabled' : '';
		$classes     = array(
			'sv-wc-apple-pay-button',
		);

		switch ( $this->get_handler()->get_button_style() ) {

			case 'black':
				$classes[] = 'apple-pay-button-black';
				break;

			case 'white':
				$classes[] = 'apple-pay-button-white';
				break;

			case 'white-with-line':
				$classes[] = 'apple-pay-button-white-with-line';
				break;
		}

		if ( $this->is_tokenization_forced() ) {
			$classes[] = 'apple-pay-button-subscription';
		}

		// if on the single product page, add some text.
		if ( is_product() ) {
			$classes[]   = 'apple-pay-button-buy-now';
			$button_text = _x( 'Buy with', 'Apple Pay', 'woocommerce-gateway-paypal-powered-by-braintree' );
		}

		if ( $button_text ) {
			$classes[] = 'apple-pay-button-with-text';
		}

		if ( is_checkout() ) {
			printf( '<span class="wc-braintree-express-payment-title">%s</span>', esc_html__( 'Express checkout', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
		}

		echo '<button ' . esc_attr( $is_disabled ) . ' class="' . implode( ' ', array_map( 'sanitize_html_class', $classes ) ) . '" lang="' . esc_attr( substr( get_locale(), 0, 2 ) ) . '">';

		if ( $button_text ) {
			echo '<span class="text">' . esc_html( $button_text ) . '</span><span class="logo"></span>';
		}

		echo '</button>';

		if ( $this->is_tokenization_forced() ) {
			printf(
				'<div class="wc-braintree-apple-pay-vaulting-consent">
					<input type="checkbox" id="wc-braintree-apple-pay-vaulting-consent" />
					<label for="wc-braintree-apple-pay-vaulting-consent">
						%s
					</label>
				</div>',
				esc_html__( 'I consent to use this Apple Pay card for future subscriptions transactions.', 'woocommerce-gateway-paypal-powered-by-braintree' )
			);
		}
	}
}
