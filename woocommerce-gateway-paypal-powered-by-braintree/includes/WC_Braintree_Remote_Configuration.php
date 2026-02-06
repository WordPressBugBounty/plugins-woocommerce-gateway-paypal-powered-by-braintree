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
 * The remote configuration for a Braintree payment gateway.
 */
class WC_Braintree_Remote_Configuration {
	/**
	 * The supported Braintree payment gateway IDs.
	 *
	 * @var string[]
	 */
	public const SUPPORTED_GATEWAYS = [
		\WC_Braintree\WC_Braintree::PAYPAL_GATEWAY_ID,
		\WC_Braintree\WC_Braintree::CREDIT_CARD_GATEWAY_ID,
	];

	/**
	 * The version of the Braintree API to specify in API requests.
	 *
	 * @var string
	 */
	protected const BRAINTREE_API_VERSION = '2019-01-01';

	/**
	 * Cached remote configurations. Keys are merchant account IDs.
	 *
	 * @var array<string, WC_Braintree_Remote_Configuration>
	 */
	protected static array $configurations = [];

	/**
	 * The mapping of gateway IDs to merchant account IDs. Keys are the gateway IDs and values are the merchant account IDs.
	 *
	 * @var array<string, string>
	 */
	protected static array $gateway_map = [];

	/**
	 * The merchant ID for the current gateway and remote configuration.
	 *
	 * @var string|null
	 */
	public ?string $merchant_id = null;

	/**
	 * The merchant accounts for the current gateway.
	 *
	 * @var \WC_Braintree\WC_Braintree_Merchant_Account[]
	 */
	protected array $merchant_accounts = [];

	/**
	 * The error message from the last attempt to fetch merchant accounts.
	 * Null if there was no error, or the error message if there was an error.
	 *
	 * @var string|null
	 */
	protected ?string $merchant_accounts_fetch_error = null;

	/**
	 * The Braintree Gateway instance. It may be null if we don't have credentials.
	 *
	 * @var \Braintree\Gateway|null
	 */
	protected ?\Braintree\Gateway $braintree_gateway = null;

	/**
	 * Constructor.
	 *
	 * @param string $gateway_id The ID of the Braintree payment gateway to get the configuration for.
	 * @return void
	 * @throws \Exception If the gateway ID is invalid.
	 */
	public function __construct( string $gateway_id ) {
		self::validate_gateway_id( $gateway_id );

		$this->fetch_configuration( $gateway_id );
	}

	/**
	 * Get the merchant accounts for the current gateway.
	 *
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account[]
	 */
	public function get_merchant_accounts(): array {
		return $this->merchant_accounts;
	}

	/**
	 * Get the error message from the last attempt to fetch merchant accounts.
	 *
	 * @return string|null The error message if there was an error, null otherwise.
	 */
	public function get_merchant_accounts_fetch_error(): ?string {
		return $this->merchant_accounts_fetch_error;
	}

	/**
	 * Get the merchant accounts that support a specified currency.
	 *
	 * @param string $currency The currency that must be supported.
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account[] The merchant accounts that support the specified currency.
	 */
	public function get_merchant_accounts_by_currency( string $currency ): array {
		$currency_upper = strtoupper( $currency );

		return array_filter(
			$this->merchant_accounts,
			function ( \WC_Braintree\WC_Braintree_Merchant_Account $merchant_account ) use ( $currency_upper ) {
				return $currency_upper === $merchant_account->get_currency();
			}
		);
	}

	/**
	 * Get the merchant accounts that support a specified payment gateway.
	 *
	 * @param string $payment_gateway_id The ID of the payment gateway to check.
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account[] The merchant accounts that support the specified payment gateway.
	 */
	public function get_merchant_accounts_by_payment_gateway( string $payment_gateway_id ): array {
		if ( [] === $this->merchant_accounts ) {
			return [];
		}

		return array_filter(
			$this->merchant_accounts,
			function ( \WC_Braintree\WC_Braintree_Merchant_Account $merchant_account ) use ( $payment_gateway_id ) {
				return $merchant_account->is_payment_gateway_supported( $payment_gateway_id );
			}
		);
	}

	/**
	 * Find the merchant accounts that support a specified currency and payment gateway.
	 *
	 * @param string $currency The currency that must be supported.
	 * @param string $payment_gateway_id The ID of the payment gateway to check.
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account[] The merchant accounts that support the specified currency and payment gateway.
	 */
	public function find_eligible_merchant_accounts_by_currency_and_payment_gateway( string $currency, string $payment_gateway_id ): array {
		$merchant_accounts = $this->get_merchant_accounts_by_currency( $currency );

		if ( [] === $merchant_accounts ) {
			return [];
		}

		return array_filter(
			$merchant_accounts,
			function ( \WC_Braintree\WC_Braintree_Merchant_Account $merchant_account ) use ( $payment_gateway_id ) {
				return $merchant_account->is_payment_gateway_supported( $payment_gateway_id );
			}
		);
	}

	/**
	 * Get the merchant account for the given merchant account ID.
	 * If no merchant account ID is provided, the default merchant account will be used.
	 *
	 * @param string|null $merchant_account_id The ID of the merchant account to get. If not provided, the default merchant account will be used.
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account|null The merchant account if found, null otherwise.
	 */
	public function get_merchant_account( ?string $merchant_account_id = null ): ?\WC_Braintree\WC_Braintree_Merchant_Account {
		if ( [] === $this->merchant_accounts ) {
			return null;
		}

		foreach ( $this->merchant_accounts as $merchant_account ) {
			if ( null === $merchant_account_id ) {
				if ( $merchant_account->is_default_merchant_account() ) {
					return $merchant_account;
				}
			} elseif ( $merchant_account->get_id() === $merchant_account_id ) {
				return $merchant_account;
			}
		}

		return null;
	}

	/**
	 * Get the remote configuration for the given Braintree payment gateway.
	 *
	 * @param string $gateway_id    The ID of the gateway to get the configuration for.
	 * @param bool   $force_refresh Whether to force a refresh of the configuration.
	 * @return \WC_Braintree\WC_Braintree_Remote_Configuration The remote configuration.
	 * @throws \Exception If the gateway ID is invalid.
	 */
	public static function get_remote_configuration( string $gateway_id, bool $force_refresh = false ): self {
		self::validate_gateway_id( $gateway_id );

		if ( ! $force_refresh && isset( self::$gateway_map[ $gateway_id ] ) ) {
			$account_id = self::$gateway_map[ $gateway_id ];
			if ( isset( self::$configurations[ $account_id ] ) ) {
				return self::$configurations[ $account_id ];
			}
		}

		return new self( $gateway_id );
	}

	/**
	 * Fetch the configuration for the given Braintree payment gateway.
	 *
	 * @param string $gateway_id The ID of the gateway to fetch the configuration for.
	 * @return void
	 */
	protected function fetch_configuration( string $gateway_id ): void {
		$payment_gateways = WC()->payment_gateways()->payment_gateways();

		$payment_gateway = $payment_gateways[ $gateway_id ] ?? null;
		if ( null === $payment_gateway || ! $payment_gateway instanceof WC_Gateway_Braintree ) {
			\WC_Braintree\Logger::warning( 'Failed to get Braintree configuration for unavailable gateway', [ 'gateway_id' => $gateway_id ] );
			return;
		}

		$this->merchant_id = $payment_gateway->get_merchant_id();

		if ( ! empty( $this->merchant_id ) ) {
			self::$gateway_map[ $gateway_id ]           = $this->merchant_id;
			self::$configurations[ $this->merchant_id ] = $this;
		}

		if ( $payment_gateway->is_connected() && ! $payment_gateway->is_connected_manually() ) {
			$configuration = [
				'accessToken' => $payment_gateway->get_auth_access_token(),
			];
		} else {
			$configuration = [
				'environment' => $payment_gateway->get_environment(),
				'publicKey'   => $payment_gateway->get_public_key(),
				'privateKey'  => $payment_gateway->get_private_key(),
				'merchantId'  => $this->merchant_id,
			];
		}

		foreach ( $configuration as $config_value ) {
			if ( empty( $config_value ) ) {
				// We don't have the credentials we need to make an API call.
				return;
			}
		}

		$this->braintree_gateway = new \Braintree\Gateway( $configuration );

		$this->merchant_accounts = $this->fetch_merchant_accounts( $this->braintree_gateway );
	}

	/**
	 * Fetch the merchant accounts using the supplied Braintree Gateway instance.
	 *
	 * @param \Braintree\Gateway $braintree_gateway The Braintree Gateway instance to use.
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account[] The merchant accounts.
	 */
	protected function fetch_merchant_accounts( \Braintree\Gateway $braintree_gateway ): array {
		$this->merchant_accounts_fetch_error = null;

		// Note the try block needs to surround the initial call _and_ the loop over the collection,
		// as the PaginatedCollection object may only issue them during the iteration.
		try {
			$merchant_account_collection = $braintree_gateway->merchantAccount()->all();

			$merchant_accounts = [];
			foreach ( $merchant_account_collection as $merchant_account ) {
				if ( $merchant_account instanceof \Braintree\MerchantAccount ) {
					$merchant_accounts[] = new WC_Braintree_Merchant_Account( $merchant_account );
				}
			}
		} catch ( \Exception $e ) {
			\WC_Braintree\Logger::error( 'Failed to fetch merchant accounts', [ 'error' => $e->getMessage() ] );
			$this->merchant_accounts_fetch_error = __( 'Failed to fetch merchant accounts', 'woocommerce-gateway-paypal-powered-by-braintree' );

			return [];
		}

		return $merchant_accounts;
	}

	/**
	 * Validate the gateway ID to ensure it is supported.
	 *
	 * @param string $gateway_id The gateway ID to validate.
	 * @return void
	 * @throws \Exception If the gateway ID is invalid.
	 */
	protected static function validate_gateway_id( string $gateway_id ): void {
		if ( ! in_array( $gateway_id, self::SUPPORTED_GATEWAYS, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( 'Invalid gateway ID; Must be one of: ' . implode( ', ', self::SUPPORTED_GATEWAYS ) );
		}
	}

	/**
	 * Fetch the Fastlane status for a merchant account.
	 * Updates the is_fastlane_enabled property of the merchant account if the fetch is successful.
	 *
	 * @param \WC_Braintree\WC_Braintree_Merchant_Account $merchant_account The merchant account to fetch the Fastlane status for.
	 * @return bool|null The Fastlane status if the fetch is successful, null otherwise.
	 */
	public function fetch_fastlane_enabled_for_merchant_account( \WC_Braintree\WC_Braintree_Merchant_Account $merchant_account ): ?bool {
		// Reset the Fastlane status to null to indicate that we haven't fetched it yet.
		$merchant_account->set_is_fastlane_enabled( null );

		// If we couldn't create a Braintree Gateway instance during initialization,
		// we can't fetch the Fastlane status.
		if ( null === $this->braintree_gateway ) {
			return null;
		}

		// The Fastlane status is only available via the clientConfiguration GraphQL query,
		// but the server tokens don't have permissions on that resource.
		// As such, we need to generate a client token and make a direct API call
		// to get the Fastlane status.
		$token_arguments = [];
		if ( ! $merchant_account->is_default_merchant_account() ) {
			$token_arguments['merchantAccountId'] = $merchant_account->get_id();
		}
		$client_token = $this->braintree_gateway->clientToken()->generate( $token_arguments );

		$client_token_json = base64_decode( $client_token ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $client_token_json ) {
			return null;
		}

		$client_token_array = json_decode( $client_token_json, true );
		if ( ! is_array( $client_token_array ) ) {
			return null;
		}

		$authorization_fingerprint = $client_token_array['authorizationFingerprint'] ?? null;
		if ( empty( $authorization_fingerprint ) ) {
			return null;
		}

		$fastlane_query = 'query { clientConfiguration { fastlane { enabled } } }';

		$graphql_url = $this->braintree_gateway->config->graphQLBaseUrl();

		$fastlane_response = wp_remote_post(
			$graphql_url,
			[
				'headers' => [
					'Authorization'     => 'Bearer ' . $authorization_fingerprint,
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'Braintree-Version' => self::BRAINTREE_API_VERSION,
					// Note that we need a custom User-Agent, as the default WordPress User-Agent seems to cause issues for the API.
					'User-Agent'        => 'WooCommerce Braintree Gateway/' . WC_Braintree::VERSION,
				],
				'body'    => wp_json_encode( [ 'query' => $fastlane_query ] ),
			]
		);

		if ( is_wp_error( $fastlane_response ) ) {
			\WC_Braintree\Logger::error(
				'Error fetching Fastlane status',
				[
					'error_code'    => $fastlane_response->get_error_code(),
					'error_message' => $fastlane_response->get_error_message(),
				]
			);
			return null;
		}

		$response_body = $fastlane_response['body'] ?? '';
		$response_code = wp_remote_retrieve_response_code( $fastlane_response );

		if ( 200 !== $response_code ) {
			\WC_Braintree\Logger::error(
				'Unexpected HTTP response code fetching Fastlane status',
				[
					'response_code' => $response_code,
					'response_body' => $response_body,
				]
			);
			return null;
		}

		$fastlane_response_body = json_decode( $response_body, true );
		if ( ! is_array( $fastlane_response_body ) ) {
			\WC_Braintree\Logger::error( 'Unexpected Fastlane response body format', [ 'response_body' => $response_body ] );
			return null;
		}

		if ( ! isset( $fastlane_response_body['data']['clientConfiguration']['fastlane']['enabled'] ) ) {
			\WC_Braintree\Logger::error( 'Unexpected Fastlane response body format; missing Fastlane data', [ 'response_body' => $response_body ] );
			return null;
		}

		$is_fastlane_enabled = (bool) ( $fastlane_response_body['data']['clientConfiguration']['fastlane']['enabled'] ?? false );

		$merchant_account->set_is_fastlane_enabled( $is_fastlane_enabled );

		return $is_fastlane_enabled;
	}

	/**
	 * Get the default merchant account for the current gateway.
	 *
	 * @return \WC_Braintree\WC_Braintree_Merchant_Account|null The default merchant account if found, null otherwise.
	 */
	public function get_default_merchant_account(): ?\WC_Braintree\WC_Braintree_Merchant_Account {
		// 'get_merchant_account' returns the default merchant account if null is provided as the merchant account ID.
		return $this->get_merchant_account( null );
	}
}
