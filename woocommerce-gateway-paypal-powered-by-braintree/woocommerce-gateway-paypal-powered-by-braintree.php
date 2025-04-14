<?php
/**
 * Plugin Name: Braintree for WooCommerce Payment Gateway
 * Requires Plugins: woocommerce
 * Plugin URI: https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/
 * Documentation URI: https://docs.woocommerce.com/document/woocommerce-gateway-paypal-powered-by-braintree/
 * Description: Receive credit card or PayPal payments using Braintree for WooCommerce.  A server with cURL, SSL support, and a valid SSL certificate is required (for security reasons) for this gateway to function. Requires PHP 7.4+
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Version: 3.2.7
 * Text Domain: woocommerce-gateway-paypal-powered-by-braintree
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 6.6
 * Tested up to: 6.8
 * WC requires at least: 9.6
 * WC tested up to: 9.8
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * Copyright (c) 2016-2020, Automattic, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

defined( 'ABSPATH' ) or exit;

/**
 * Required minimums
 */
define( 'WC_PAYPAL_BRAINTREE_MIN_PHP_VER', '7.4.0' );

/**
 * Base plugin file
 */
define( 'WC_PAYPAL_BRAINTREE_FILE', __FILE__ );

/**
 * The plugin loader class.
 *
 * @since 2.3.0
 */
class WC_PayPal_Braintree_Loader {


	/** minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '7.4';

	/** minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '6.6';

	/** minimum WooCommerce version required by this plugin */
	const MINIMUM_WC_VERSION = '9.6';

	/** SkyVerge plugin framework version used by this plugin */
	const FRAMEWORK_VERSION = '5.12.7';

	/** the plugin name, for displaying notices */
	const PLUGIN_NAME = 'Braintree for WooCommerce';


	/** @var \WC_PayPal_Braintree_Loader the singleton instance of the class */
	private static $instance;

	/** @var array the admin notices to add */
	public $notices = array();


	/**
	 * Constructs the loader.
	 *
	 * @since 2.3.0
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		add_filter( 'extra_plugin_headers', array( $this, 'add_documentation_header' ) );

		// if the environment check fails, initialize the plugin
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		}

		// Show delete user warning.
		add_action( 'admin_notices', array( $this, 'delete_user_warning' ) );

		// Declare compatibility with Woocommerce features.
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_feature_compatibility' ) );

		// Add support for WooCommerce Blocks.
		add_action( 'woocommerce_blocks_loaded', array( $this, 'woocommerce_block_support' ) );

		// Filter credit card settings to ensure validity of card_types.
		add_filter( 'option_woocommerce_braintree_credit_card_settings', array( $this, 'ensure_card_types_is_an_array' ) );
	}

	/**
	 * Filter for the woocommerce_braintree_credit_card_settings option.
	 *
	 * Checks the card_types value of the option to ensure it is an array.
	 * If the value isn't set, it's converted to an empty array. If a non-array
	 * type is returned, it's converted to an array with the value as the only
	 * element.
	 *
	 * The card_types value is passed through a number of array_*() functions which
	 * throw warnings if the value isn't an array.
	 *
	 * @since 3.0.6
	 *
	 * @param mixed $option_value The option value as stored in WordPress.
	 * @return mixed The modified option value.
	 */
	public function ensure_card_types_is_an_array( $option_value ) {
		if ( empty( $option_value['card_types'] ) ) {
			$option_value['card_types'] = array();
		} elseif ( ! is_array( $option_value['card_types'] ) ) {
			$option_value['card_types'] = array( $option_value['card_types'] );
		}
		return $option_value;
	}

	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 2.3.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', esc_html( get_class( $this ) ) ), '1.0.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 2.3.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', esc_html( get_class( $this ) ) ), '1.0.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 2.0.0
	 */
	public function init_plugin() {

		// if the legacy plugin is active, let the admin know
		if ( function_exists( 'wc_braintree' ) ) {
			$this->add_admin_notice( 'bad_environment', 'error', __( 'Braintree for WooCommerce is inactive. Please deactivate the retired WooCommerce Braintree plugin.', 'woocommerce-gateway-paypal-powered-by-braintree' ) );
			return;
		}

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		$this->load_framework();

		// autoload plugin and vendor files
		require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

		// load main plugin file
		require_once( plugin_dir_path( __FILE__ ) . 'class-wc-braintree.php' );

		// if WooCommerce is inactive, render a notice and bail
		if ( ! WC_Braintree::is_woocommerce_active() ) {

			add_action( 'admin_notices', static function() {

				echo '<div class="error"><p>';
				esc_html_e( 'Braintree for WooCommerce is inactive because WooCommerce is not installed.', 'woocommerce-gateway-paypal-powered-by-braintree' );
				echo '</p></div>';

			} );

			return;
		}

		// fire it up!
		add_action( 'woocommerce_init', 'wc_braintree' );
	}


	/**
	 * Loads the base framework classes.
	 *
	 * @since 2.3.0
	 */
	protected function load_framework() {

		if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Plugin' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/class-sv-wc-plugin.php' );
		}

		if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\' . $this->get_framework_version_namespace() . '\\SV_WC_Payment_Gateway_Plugin' ) ) {
			require_once( plugin_dir_path( __FILE__ ) . 'vendor/skyverge/wc-plugin-framework/woocommerce/payment-gateway/class-sv-wc-payment-gateway-plugin.php' );
		}
	}


	/**
	 * Gets the framework version in namespace form.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	protected function get_framework_version_namespace() {

		return 'v' . str_replace( '.', '_', $this->get_framework_version() );
	}


	/**
	 * Gets the framework version used by this plugin.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	protected function get_framework_version() {

		return self::FRAMEWORK_VERSION;
	}


	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * Based on {@link http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments}
	 *
	 * @since 2.3.0
	 */
	public function activation_check() {

		// deactivate the retired plugin if active.
		if ( is_plugin_active( 'woocommerce-gateway-braintree/woocommerce-gateway-braintree.php' ) ) {
			deactivate_plugins( 'woocommerce-gateway-braintree/woocommerce-gateway-braintree.php' );
		}

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( esc_html( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() ) );
		}

		// enable the PayPal gateway on activation.
		$paypal_settings            = get_option( 'woocommerce_braintree_paypal_settings', array() );
		$paypal_settings['enabled'] = 'yes';

		update_option( 'woocommerce_braintree_paypal_settings', $paypal_settings );
	}

	/**
	 * Checks the environment when loading WordPress, just in case the environment changes after activation.
	 *
	 * @internal
	 *
	 * @since 2.3.0
	 */
	public function check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Checks the environment for compatibility problems.
	 *
	 * @since 2.3.0
	 *
	 * @param bool $during_activation whether this check is during plugin activation
	 * @return string|bool the error message if one exists, or false if everything's okay
	 */
	public static function get_environment_warning( $during_activation = false ) {

		$message = false;

		// check the PHP version
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			/* translators: Placeholders: %1$s - braintree version, %2$s - phpversion() */
			$message = sprintf( __( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paypal-powered-by-braintree' ), WC_PAYPAL_BRAINTREE_MIN_PHP_VER, phpversion() );

			$prefix = ( $during_activation ) ? 'The plugin could not be activated. ' : 'Braintree for WooCommerce has been deactivated. ';

			$message = $prefix . $message;
		}

		return $message;
	}


	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @internal
	 *
	 * @since 2.3.0
	 */
	public function add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {

			$this->add_admin_notice( 'update_wordpress', 'error', sprintf(
				'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_WP_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
			) );
		}

		if ( ! $this->is_wc_compatible() ) {

			$this->add_admin_notice( 'update_woocommerce', 'error', sprintf(
				'%s requires WooCommerce version %s or higher. Please %supdate WooCommerce &raquo;%s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_WC_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
			) );
		}

		if ( ! extension_loaded( 'curl' ) ) {

			$this->add_admin_notice( 'install_curl', 'error', sprintf(
				'%1$s requires the cURL PHP extension to function. Contact your host or server administrator to install and configure cURL.',
				'<strong>' . self::PLUGIN_NAME . '</strong>'
			) );
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function plugins_compatible() {

		return $this->is_wp_compatible() && $this->is_wc_compatible() && extension_loaded( 'curl' );
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function is_wp_compatible() {

		return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
	}


	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function is_wc_compatible() {

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 2.3.0
	 */
	protected function deactivate_plugin() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		// phpcs:enable
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function is_environment_compatible() {

		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 2.3.0
	 *
	 * @return string
	 */
	protected function get_environment_message() {

		return sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );;
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 2.3.0
	 *
	 * @param string $slug notice slug/ID
	 * @param string $class notice HTML class
	 * @param string $message notice message
	 */
	public function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class' => $class,
			'message' => $message
		);
	}


	/**
	 * Displays any admin notices added by the plugin loader
	 *
	 * @internal
	 *
	 * @since 2.3.0
	 */
	public function admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) :

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p><?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?></p>
			</div>
			<?php

		endforeach;
	}


	/**
	 * Adds the Documentation URI header.
	 *
	 * @internal
	 *
	 * @since 2.5.0
	 *
	 * @param array $headers original plugin headers
	 * @return array
	 */
	public function add_documentation_header( $headers ) {

		$headers[] = 'Documentation URI';

		return $headers;
	}


	/**
	 * Gets the main loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @since 2.3.0
	 *
	 * @return \WC_PayPal_Braintree_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Display Warning about Braintree payment method at User delete confirmation screen.
	 *
	 * @return void
	 */
	public function delete_user_warning() {
		global $pagenow;
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Check if we are on user delete confirmation page and site in not staging env.
		if (
			'users.php' === $pagenow &&
			(
				( isset( $_GET['action'] ) && 'delete' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) ||
				( isset( $_GET['action2'] ) && 'delete' === sanitize_text_field( wp_unslash( $_GET['action2'] ) ) )
			) &&
			! WC_Braintree::is_staging_site()
		) {
			$has_token = false;
			$users     = array();

			if ( isset( $_GET['user'] ) && ! empty( $_GET['user'] ) ) {
				$users = array( absint( $_GET['user'] ) );
			} elseif ( isset( $_GET['users'] ) && ! empty( $_GET['users'] ) ) {
				$users = array_map( 'absint', $_GET['users'] );
			}

			foreach ( $users as $user_id ) {
				$tokens = wc_braintree()->get_gateway()->get_payment_tokens_handler()->get_tokens( $user_id );
				if ( ! empty( $tokens ) ) {
					$has_token = true;
					break;
				}
			}

			if ( $has_token ) {
				?>
				<div class='notice notice-error'>
					<p>
						<?php
						echo printf( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag */
							esc_html__( '%1$sWarning:%2$s Deleting a user will also delete the user\'s payment method saved on Braintree account.', 'woocommerce-gateway-paypal-powered-by-braintree' ),
							'<strong>',
							'</strong>'
						);
						?>
					</p>
				</div>
				<?php
			}
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Declare compatibility with Woocommerce features.
	 *
	 * List of feature -
	 *   1. High-Performance Order Storage
	 *   2. Product block editor
	 *
	 * @since 3.1.1 Rename function.
	 * @since 3.0.0
	 */
	public function declare_woocommerce_feature_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__
			);

			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'product_block_editor',
				__FILE__
			);
		}
	}

	/**
	 * Add Braintree PayPal & Credit Card payment method to WooCommerce Blocks.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-gateway-braintree-blocks-support.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-gateway-braintree-paypal-blocks-support.php';
			require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-gateway-braintree-credit-card-blocks-support.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Braintree_PayPal_Blocks_Support() );
					$payment_method_registry->register( new WC_Gateway_Braintree_Credit_Card_Blocks_Support() );
				}
			);

			// Register payment requirements for Braintree PayPal Checkout Confirmation.
			if ( function_exists( 'woocommerce_store_api_register_payment_requirements' ) ) {
				woocommerce_store_api_register_payment_requirements(
					array(
						'data_callback' => function () {
							$payment_gateways = WC()->payment_gateways->payment_gateways();
							$gateway          = $payment_gateways['braintree_paypal'];
							if ( ! $gateway ) {
								return array();
							}

							$payment_form = $gateway->get_payment_form_instance();
							if ( $gateway->is_available() && $payment_form && $payment_form->get_cart_nonce() ) {
								return array( 'braintree_paypal_checkout_confirmation' );
							}

							return array();
						},
					)
				);
			}
		}
	}
}

WC_PayPal_Braintree_Loader::instance();
