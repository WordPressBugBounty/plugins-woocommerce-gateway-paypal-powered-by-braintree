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
 * @copyright Copyright: (c) 2016-2026, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Wrapper for the lower-level Braintree Merchant Account object to add convenience methods.
 */
class WC_Braintree_Merchant_Account {
	protected const ACH_PAYMENT_METHOD    = 'US_BANK_ACCOUNT';
	protected const LOCAL_PAYMENTS_METHOD = 'LOCAL_PAYMENT';
	protected const PAYPAL_PAYMENT_METHOD = 'PAYPAL_ACCOUNT';
	protected const SEPA_PAYMENT_METHOD   = 'SEPA_DEBIT_ACCOUNT';
	protected const VENMO_PAYMENT_METHOD  = 'VENMO_ACCOUNT';

	protected const APPLE_PAY_PREFIX  = 'APPLE_PAY_';
	protected const GOOGLE_PAY_PREFIX = 'GOOGLE_PAY_';

	/**
	 * The merchant account object.
	 *
	 * @var \Braintree\MerchantAccount
	 */
	protected \Braintree\MerchantAccount $merchant_account;

	/**
	 * Whether the Fastlane feature is enabled for this merchant account.
	 * This has a separate property because it's not available in the merchant account object,
	 * and we need to fetch it separately.
	 *
	 * @var bool|null
	 */
	protected ?bool $is_fastlane_enabled = null;

	/**
	 * Constructor.
	 *
	 * @param \Braintree\MerchantAccount $merchant_account The merchant account object.
	 * @return void
	 */
	public function __construct( \Braintree\MerchantAccount $merchant_account ) {
		$this->merchant_account = $merchant_account;
	}

	/**
	 * Get the merchant account ID.
	 *
	 * @return string The merchant account ID.
	 */
	public function get_id(): string {
		if ( isset( $this->merchant_account->id ) && is_string( $this->merchant_account->id ) ) {
			return $this->merchant_account->id;
		}
		return '';
	}

	/**
	 * Get the currency of the merchant account.
	 *
	 * @return string The currency of the merchant account.
	 */
	public function get_currency(): string {
		if ( isset( $this->merchant_account->currencyIsoCode ) && is_string( $this->merchant_account->currencyIsoCode ) ) {
			return $this->merchant_account->currencyIsoCode;
		}
		return '';
	}

	/**
	 * Get the status of the merchant account.
	 *
	 * @return string The status of the merchant account.
	 */
	public function get_status(): string {
		if ( isset( $this->merchant_account->status ) && is_string( $this->merchant_account->status ) ) {
			return $this->merchant_account->status;
		}
		return '';
	}

	/**
	 * Check if the merchant account is active.
	 *
	 * @return bool Whether the merchant account is active.
	 */
	public function is_active(): bool {
		return \Braintree\MerchantAccount::STATUS_ACTIVE === $this->get_status();
	}

	/**
	 * Check if this merchant account is the default merchant account for the gateway.
	 *
	 * @return bool Whether this merchant account is the default merchant account for the gateway.
	 */
	public function is_default_merchant_account(): bool {
		return (bool) $this->merchant_account->default ?? false;
	}

	/**
	 * Get the accepted payment methods for the merchant account.
	 *
	 * @return string[]
	 */
	public function get_accepted_payment_methods(): array {
		if ( isset( $this->merchant_account->acceptedPaymentMethods ) && is_array( $this->merchant_account->acceptedPaymentMethods ) ) {
			return $this->merchant_account->acceptedPaymentMethods;
		}
		return [];
	}

	/**
	 * Check if a specific payment gateway is supported by this merchant account.
	 *
	 * @param string $payment_gateway_id The ID of the payment gateway to check.
	 * @return bool Whether the payment gateway is supported by this merchant account.
	 */
	public function is_payment_gateway_supported( string $payment_gateway_id ): bool {
		switch ( $payment_gateway_id ) {
			case \WC_Braintree\WC_Braintree::ACH_GATEWAY_ID:
				return $this->is_ach_enabled();
			case \WC_Braintree\WC_Braintree::CREDIT_CARD_GATEWAY_ID:
				return true;
			case \WC_Braintree\WC_Braintree::LOCAL_PAYMENTS_GATEWAY_ID:
				return $this->are_local_payments_enabled();
			case \WC_Braintree\WC_Braintree::PAYPAL_GATEWAY_ID:
				return $this->is_paypal_enabled();
			case \WC_Braintree\WC_Braintree::SEPA_GATEWAY_ID:
				return $this->is_sepa_enabled();
			case \WC_Braintree\WC_Braintree::VENMO_GATEWAY_ID:
				return $this->is_venmo_enabled();
			default:
				return false;
		}
	}

	/**
	 * Check if ACH is enabled for this merchant account.
	 *
	 * @return bool Whether ACH is enabled for this merchant account.
	 */
	public function is_ach_enabled(): bool {
		return $this->is_payment_method_enabled( self::ACH_PAYMENT_METHOD );
	}

	/**
	 * Check if Apple Pay is enabled for this merchant account.
	 *
	 * @return bool Whether Apple Pay is enabled for this merchant account.
	 */
	public function is_apple_pay_enabled(): bool {
		return $this->is_payment_method_enabled_by_prefix( self::APPLE_PAY_PREFIX );
	}

	/**
	 * Check if Google Pay is enabled for this merchant account.
	 *
	 * @return bool Whether Google Pay is enabled for this merchant account.
	 */
	public function is_google_pay_enabled(): bool {
		return $this->is_payment_method_enabled_by_prefix( self::GOOGLE_PAY_PREFIX );
	}

	/**
	 * Check if PayPal is enabled for this merchant account.
	 *
	 * @return bool Whether PayPal is enabled for this merchant account.
	 */
	public function is_paypal_enabled(): bool {
		return $this->is_payment_method_enabled( self::PAYPAL_PAYMENT_METHOD );
	}

	/**
	 * Check if SEPA is enabled for this merchant account.
	 *
	 * @return bool Whether SEPA is enabled for this merchant account.
	 */
	public function is_sepa_enabled(): bool {
		return $this->is_payment_method_enabled( self::SEPA_PAYMENT_METHOD );
	}

	/**
	 * Check if Venmo is enabled for this merchant account.
	 *
	 * @return bool Whether Venmo is enabled for this merchant account.
	 */
	public function is_venmo_enabled(): bool {
		return $this->is_payment_method_enabled( self::VENMO_PAYMENT_METHOD );
	}

	/**
	 * Check if Braintree's Local Payments feature is enabled for this merchant account.
	 *
	 * @return bool Whether the Local Payments feature is enabled for this merchant account.
	 */
	public function are_local_payments_enabled(): bool {
		return $this->is_payment_method_enabled( self::LOCAL_PAYMENTS_METHOD );
	}

	/**
	 * Get the Fastlane enabled flag for this merchant account.
	 *
	 * @return bool|null The Fastlane enabled flag, where null represents unknown status.
	 */
	public function is_fastlane_enabled(): ?bool {
		return $this->is_fastlane_enabled;
	}

	/**
	 * Set the Fastlane enabled flag for this merchant account.
	 *
	 * @param bool|null $is_fastlane_enabled The new value for the flag, where null represents unknown status.
	 * @return void
	 */
	public function set_is_fastlane_enabled( ?bool $is_fastlane_enabled ): void {
		$this->is_fastlane_enabled = $is_fastlane_enabled;
	}

	/**
	 * Helper method to check if a specific payment method is enabled.
	 *
	 * @param string $payment_method The payment method to look for.
	 * @return bool Whether the payment method is enabled.
	 */
	protected function is_payment_method_enabled( string $payment_method ): bool {
		$accepted_payment_methods = $this->get_accepted_payment_methods();

		return in_array( $payment_method, $accepted_payment_methods, true );
	}

	/**
	 * Helper method to check if any payment methods should be shown as enabled
	 * when the accepted payment methods include entries with a specific prefix.
	 *
	 * @param string $prefix The prefix to check for in the accepted payment methods.
	 * @return bool Whether any accepted payment methods have the specified prefix.
	 */
	protected function is_payment_method_enabled_by_prefix( string $prefix ): bool {
		$accepted_payment_methods = $this->get_accepted_payment_methods();

		foreach ( $accepted_payment_methods as $payment_method ) {
			if ( str_starts_with( $payment_method, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
