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
 * needs please refer to https://woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/
 *
 * @package   WC-Braintree/Gateway
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce Gateway Braintree Feature Flags Class.
 *
 * @since 3.3.0
 */
class WC_Braintree_Feature_Flags {

	/**
	 * The name of the option that stores the early access feature settings.
	 *
	 * @var string
	 */
	public const EARLY_ACCESS_OPTION_NAME = 'wc_braintree_early_access_enabled';

	/**
	 * The name of the option that stores the feature flags.
	 *
	 * @var string
	 */
	public const FEATURE_FLAGS_OPTION_NAME = 'wc_braintree_feature_flags';

	/**
	 * The name of the feature flag for ACH.
	 *
	 * @var string
	 */
	private const FEATURE_ACH = 'ach';

	/**
	 * The name of the feature flag for SEPA.
	 *
	 * @var string
	 */
	private const FEATURE_SEPA = 'sepa';

	/**
	 * The name of the feature flag for Local Payments.
	 *
	 * @var string
	 */
	private const FEATURE_LOCAL_PAYMENTS = 'local_payments';

	/**
	 * Default values for feature flags.
	 *
	 * @var array<string, string> Feature flags
	 */
	private array $feature_flags = [
		self::FEATURE_SEPA           => 'no',
		self::FEATURE_ACH            => 'no',
		self::FEATURE_LOCAL_PAYMENTS => 'no',
	];

	/**
	 * Single instance of the Feature Flags Handler.
	 *
	 * @var WC_Braintree_Feature_Flags|null single instance of the Feature Flags Handler.
	 */
	private static ?WC_Braintree_Feature_Flags $instance = null;

	/**
	 * Feature Flags Handler Instance, ensures only one instance is/can be loaded.
	 *
	 * @since 3.3.0
	 *
	 * @return WC_Braintree_Feature_Flags
	 */
	public static function instance(): WC_Braintree_Feature_Flags {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$db_flags = get_option( self::FEATURE_FLAGS_OPTION_NAME, [] );
		if ( ! is_array( $db_flags ) ) {
			WC_Braintree::instance()->log( 'Invalid feature flags option value (' . self::FEATURE_FLAGS_OPTION_NAME . ')' );
			$db_flags = [];
		}

		$this->feature_flags = array_merge( $this->feature_flags, $db_flags );
	}

	/**
	 * Check if early access features are enabled
	 *
	 * @return bool True if the early access feature option is enabled, false otherwise.
	 */
	private function is_early_access_enabled(): bool {
		return 'yes' === get_option( self::EARLY_ACCESS_OPTION_NAME, 'no' );
	}

	/**
	 * Check if a feature flag is enabled.
	 *
	 * @param string $feature_flag_name The feature flag to check.
	 * @return bool True if the feature flag is enabled, false otherwise.
	 */
	private function is_feature_flag_enabled( string $feature_flag_name ): bool {
		if ( ! isset( $this->feature_flags, $this->feature_flags[ $feature_flag_name ] ) ) {
			return false;
		}

		return 'yes' === $this->feature_flags[ $feature_flag_name ];
	}

	/**
	 * Set a feature flag status to enabled (yes) or disabled (no).
	 *
	 * @param string $feature_flag_name The feature flag to set.
	 * @param bool   $enabled True if the feature flag should be enabled, false otherwise.
	 */
	private function toggle_feature_flag_enabled( string $feature_flag_name, bool $enabled ): void {
		if ( empty( $feature_flag_name ) ) {
			return;
		}

		$this->feature_flags[ $feature_flag_name ] = $enabled ? 'yes' : 'no';

		update_option( self::FEATURE_FLAGS_OPTION_NAME, $this->feature_flags );
	}


	/** Specific Feature Flags Methods ***************************************************************************************/

	/**
	 * Check if Venmo feature is enabled.
	 *
	 * @since 3.5.0
	 * @deprecated 3.7.0 Venmo is now generally available.
	 *
	 * @return bool Always returns true since Venmo is generally available.
	 */
	public static function is_venmo_enabled(): bool {
		wc_deprecated_function( __METHOD__, '3.7.0', 'Venmo is now generally available and this method always returns true.' );
		return true;
	}

	/**
	 * Check if ACH feature is enabled.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True if ACH is enabled, false otherwise.
	 */
	public static function is_ach_enabled(): bool {
		return self::instance()->is_early_access_enabled() && self::instance()->is_feature_flag_enabled( self::FEATURE_ACH );
	}

	/**
	 * Check if SEPA feature is enabled.
	 *
	 * @since 3.5.0
	 *
	 * @return bool True if SEPA is enabled, false otherwise.
	 */
	public static function is_sepa_enabled(): bool {
		return self::instance()->is_early_access_enabled() && self::instance()->is_feature_flag_enabled( self::FEATURE_SEPA );
	}

	/**
	 * Check if Fastlane feature is enabled.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True if Fastlane is enabled, false otherwise.
	 */
	public static function is_fastlane_enabled(): bool {
		return self::instance()->is_early_access_enabled();
	}

	/**
	 * Check if Local Payments are enabled.
	 *
	 * @since 3.7.0
	 *
	 * @return bool True if Local Payments are enabled, false otherwise.
	 */
	public static function are_local_payments_enabled(): bool {
		return self::instance()->is_early_access_enabled() && self::instance()->is_feature_flag_enabled( self::FEATURE_LOCAL_PAYMENTS );
	}
}
