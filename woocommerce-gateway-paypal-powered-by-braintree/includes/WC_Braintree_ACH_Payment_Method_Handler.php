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
 * @package   WC-Braintree/Gateway/ACH-Payment-Method-Handler
 * @author    WooCommerce
 * @copyright Copyright: (c) 2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Custom payment method handler for ACH payments.
 *
 * @since 3.8.0
 */
class WC_Braintree_ACH_Payment_Method_Handler extends WC_Braintree_Payment_Method_Handler {

	/**
	 * Known Braintree token values for digit-only token IDs.
	 *
	 * @since 3.8.0
	 * @var array<string, array<int, array<string, string|null>>>
	 */
	protected array $known_int_tokens = [];

	/**
	 * Overridden to support block checkout, where the token ID may be supplied instead of the token value.
	 *
	 * @since 3.8.0
	 * @param int                                                  $user_id        WordPress user identifier, or 0 for guest.
	 * @param string|Framework\SV_WC_Payment_Gateway_Payment_Token $token          The token, the token value, or the token ID.
	 * @param string|null                                          $environment_id optional environment id, defaults to plugin current environment.
	 * @return bool
	 */
	public function user_has_token( $user_id, $token, $environment_id = null ) {
		$braintree_token = $this->get_braintree_token_from_int_token_for_user_id( $user_id, $token, $environment_id );
		if ( null !== $braintree_token ) {
			return parent::user_has_token( $user_id, $braintree_token, $environment_id );
		}

		return parent::user_has_token( $user_id, $token, $environment_id );
	}

	/**
	 * Override to support block checkout, where the token ID may be supplied instead of the token value.
	 *
	 * @since 3.8.0
	 * @param int         $user_id        WordPress user ID.
	 * @param string      $token          Token ID or value.
	 * @param string|null $environment_id Optional environment ID.
	 * @return Framework\SV_WC_Payment_Gateway_Payment_Token|null Token instance, or null if not found.
	 */
	public function get_token( $user_id, $token, $environment_id = null ) {
		$braintree_token = $this->get_braintree_token_from_int_token_for_user_id( $user_id, $token, $environment_id );
		if ( null !== $braintree_token ) {
			return parent::get_token( $user_id, $braintree_token, $environment_id );
		}

		return parent::get_token( $user_id, $token, $environment_id );
	}

	/**
	 * Helper method to get the Braintree token value for an incoming token supplied as a digit-only ID.
	 * The code also remembers what happens for lookups in {@see known_int_tokens}.
	 *
	 * @param int                                                  $user_id        WordPress user ID.
	 * @param string|Framework\SV_WC_Payment_Gateway_Payment_Token $token          Token ID or value.
	 * @param string|null                                          $environment_id Optional environment ID.
	 * @return string|null Braintree token, or null if not found.
	 */
	protected function get_braintree_token_from_int_token_for_user_id( $user_id, $token, $environment_id = null ): ?string {
		if ( empty( $user_id ) || ! is_string( $token ) || ! ctype_digit( $token ) ) {
			return null;
		}

		if ( null === $environment_id ) {
			$environment_id = $this->get_environment_id();
		}

		if ( ! isset( $this->known_int_tokens[ $environment_id ] ) ) {
			$this->known_int_tokens[ $environment_id ] = [];
		}

		if ( ! isset( $this->known_int_tokens[ $environment_id ][ $user_id ] ) ) {
			$this->known_int_tokens[ $environment_id ][ $user_id ] = [];
		}

		if ( isset( $this->known_int_tokens[ $environment_id ][ $user_id ][ $token ] ) ) {
			return $this->known_int_tokens[ $environment_id ][ $user_id ][ $token ];
		}

		$maybe_ach_token = \WC_Payment_Tokens::get( $token );
		if ( ! $maybe_ach_token instanceof WC_Payment_Token_Braintree_ACH || empty( $maybe_ach_token->get_token() ) || $user_id !== $maybe_ach_token->get_user_id() ) {
			$this->known_int_tokens[ $environment_id ][ $user_id ][ $token ] = null;
			return null;
		}

		$this->known_int_tokens[ $environment_id ][ $user_id ][ $token ] = $maybe_ach_token->get_token();
		return $this->known_int_tokens[ $environment_id ][ $user_id ][ $token ];
	}
}
