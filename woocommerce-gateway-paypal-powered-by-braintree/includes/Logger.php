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
 * @copyright Copyright: (c) 2016-2025, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree;

defined( 'ABSPATH' ) or exit;

/**
 * Log all things!
 *
 * @since 3.5.0
 */
class Logger {

	const WC_LOG_FILENAME = 'woocommerce-gateway-paypal-powered-by-braintree';

	const LOG_CONTEXT = [
		'source'         => self::WC_LOG_FILENAME,
		'plugin_version' => WC_Braintree::VERSION,
	];

	/**
	 * Log handler instance.
	 *
	 * @see https://woocommerce.github.io/code-reference/classes/WC-Logger.html
	 * @see https://developer.woocommerce.com/docs/best-practices/data-management/logging/#log-handlers
	 *
	 * @var WC_Logger
	 */
	public static $logger;

	/**
	 * Get the WooCommerce logger instance.
	 *
	 * @since 3.5.0
	 *
	 * @return WC_Logger
	 */
	private static function get_logger() {
		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}
		return self::$logger;
	}

	// Logs have eight different severity levels:
	// - emergency.
	// - alert.
	// - critical.
	// - error.
	// - warning.
	// - notice.
	// - info.
	// - debug.

	/**
	 * Creates a log entry of type emergency.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function emergency( $message, $context = [] ) {
		self::get_logger()->emergency( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type alert.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function alert( $message, $context = [] ) {
		self::get_logger()->alert( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type critical.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function critical( $message, $context = [] ) {
		self::get_logger()->critical( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type error.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function error( $message, $context = [] ) {
		self::get_logger()->error( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type warning.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function warning( $message, $context = [] ) {
		self::get_logger()->warning( $message, array_merge( self::LOG_CONTEXT, $context, [ 'backtrace' => true ] ) );
	}

	/**
	 * Creates a log entry of type notice.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function notice( $message, $context = [] ) {
		self::get_logger()->notice( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}

	/**
	 * Creates a log entry of type info.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function info( $message, $context = [] ) {
		self::get_logger()->info( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}

	/**
	 * Creates a log entry of type debug.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Message to send to the log file.
	 * @param array  $context Additional context to add to the log.
	 *
	 * @return void
	 */
	public static function debug( $message, $context = [] ) {
		self::get_logger()->debug( $message, array_merge( self::LOG_CONTEXT, $context ) );
	}
}
