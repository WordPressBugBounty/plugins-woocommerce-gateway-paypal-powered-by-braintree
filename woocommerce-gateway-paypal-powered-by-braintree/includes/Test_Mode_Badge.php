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
		/* translators: screen-reader-only suffix to the "Test" badge — full reading is "Test mode" */
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
