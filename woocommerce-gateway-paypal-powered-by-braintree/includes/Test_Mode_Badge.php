<?php
/**
 * Appends a "Test" badge to Braintree gateway titles in sandbox mode.
 *
 * @package WC_Braintree
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Registers the `woocommerce_gateway_title` filter that decorates Braintree
 * gateway titles with a visible "Test" pill while running in sandbox mode.
 */
class Test_Mode_Badge {

	/**
	 * Registers the gateway-title filter callback.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_gateway_title', [ __CLASS__, 'maybe_append_badge' ], 10, 2 );
		add_filter( 'woocommerce_order_get_payment_method_title', [ __CLASS__, 'clean_admin_payment_method_title' ], 10, 2 );
	}

	/**
	 * Filters the payment method title for the admin order screen to remove any HTML tags.
	 *
	 * @since 3.12.0
	 *
	 * @param string   $payment_method_title The payment method title.
	 * @param WC_Order $order                The order object.
	 * @return string The cleaned payment method title.
	 */
	public static function clean_admin_payment_method_title( $payment_method_title, $order = null ) {
		if ( is_admin() ) {
			$screen = get_current_screen();
			if ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) {
				// Removes all HTML tags and leaves only plain text for the admin screen.
				$payment_method_title = wp_strip_all_tags( $payment_method_title );
			}
		}

		if ( $order instanceof \WC_Order ) {
			if ( did_action( 'woocommerce_order_details_before_order_table' ) > 0 || did_action( 'woocommerce_before_thankyou' ) > 0 ) {
				$payment_method_title = wp_strip_all_tags( $payment_method_title );
				$payment_method_id    = $order->get_payment_method();
				$payment_method_title = self::maybe_append_badge( $payment_method_title, $payment_method_id );
			}
		}
		return $payment_method_title;
	}

	/**
	 * Appends a "Test" badge to the gateway title when the gateway is a Braintree
	 * gateway in sandbox mode and the current request is a checkout-like surface.
	 *
	 * @param string $title      Original gateway title.
	 * @param string $gateway_id Gateway ID (e.g. `braintree_credit_card`).
	 * @return string Possibly-decorated title.
	 */
	public static function maybe_append_badge( string $title, string $gateway_id ): string {
		if ( ! self::is_allowed_context() ) {
			return $title;
		}

		$gateway = self::get_braintree_gateway( $gateway_id );
		if ( ! $gateway || ! $gateway->is_test_environment() ) {
			return $title;
		}

		/*
		 * Match the Blocks-side pattern: visible "Test" + visually-hidden " mode" suffix.
		 * Avoids the aria-label double-announce issue fixed during Task 2 review.
		 */
		$badge = '<span class="wc-braintree-test-badge">';
		/* translators: visible short-form badge label; paired with a screen-reader-only " mode" suffix that together read as "Test mode" */
		$badge .= esc_html__( 'Test', 'woocommerce-gateway-paypal-powered-by-braintree' );
		$badge .= '<span class="wc-braintree-sr-only">';
		/* translators: suffix to the "Test" badge label, announced only to screen readers */
		$badge .= esc_html__( ' mode', 'woocommerce-gateway-paypal-powered-by-braintree' );
		$badge .= '</span></span>';

		return $title . ' ' . $badge;
	}

	/**
	 * Determines whether the current request is one where the badge should render.
	 *
	 * Excludes REST responses, admin-non-AJAX requests, and active transactional
	 * email rendering — none of those surfaces should leak the badge HTML.
	 *
	 * @return bool
	 */
	private static function is_allowed_context(): bool {
		// REST / JSON API responses should not contain badge HTML.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Skip admin except for front-end AJAX actions (wc-ajax update_order_review, etc.).
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		// Skip if we're currently rendering a transactional email.
		if ( did_action( 'woocommerce_email_header' ) > did_action( 'woocommerce_email_footer' ) ) {
			return false;
		}

		// Skip if processing checkout.
		if (
			did_action( 'woocommerce_checkout_process' ) > 0 ||
			doing_action( 'wp_ajax_wc_braintree_credit_card_google_pay_process_payment' ) ||
			doing_action( 'wp_ajax_wc_braintree_credit_card_apple_pay_process_payment' )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Resolves a registered payment gateway by ID if it is a Braintree gateway.
	 *
	 * Uses the live gateway instance rather than reading the gateway's settings
	 * option directly so inherited credentials (via `inherit_settings_source`)
	 * are respected.
	 *
	 * @param string $gateway_id Gateway ID (e.g. `braintree_credit_card`).
	 * @return WC_Gateway_Braintree|null
	 */
	private static function get_braintree_gateway( string $gateway_id ): ?WC_Gateway_Braintree {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return null;
		}
		$gateways = WC()->payment_gateways->payment_gateways();
		$gateway  = $gateways[ $gateway_id ] ?? null;
		return $gateway instanceof WC_Gateway_Braintree ? $gateway : null;
	}

	/**
	 * Checks whether any registered Braintree gateway is in test mode.
	 *
	 * @return bool
	 */
	public static function any_gateway_in_sandbox(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return false;
		}
		foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
			if ( $gateway instanceof WC_Gateway_Braintree && $gateway->is_test_environment() ) {
				return true;
			}
		}
		return false;
	}
}
