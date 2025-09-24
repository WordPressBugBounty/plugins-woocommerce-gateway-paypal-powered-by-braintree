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
 * @package   WC-Braintree/Gateway/API/Requests/Transaction
 * @author    WooCommerce
 * @copyright Copyright: (c) 2016-2020, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace WC_Braintree\API\Requests;

use SkyVerge\WooCommerce\PluginFramework\v5_15_10 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * Braintree API Transaction Request Class
 *
 * Handles transaction requests (charges, auths, captures, refunds, voids)
 *
 * @since 3.0.0
 */
class WC_Braintree_API_Transaction_Request extends WC_Braintree_API_Request {


	/**
	 * Auth and capture transaction type.
	 *
	 * @var bool
	 */
	const AUTHORIZE_AND_CAPTURE = true;

	/**
	 * Authorize-only transaction type.
	 *
	 * @var bool
	 */
	const AUTHORIZE_ONLY = false;

	/**
	 * Braintree partner ID.
	 *
	 * @var string
	 */
	protected $channel;


	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 * @param \WC_Order|null $order order if available.
	 * @param string         $channel Braintree Partner ID/channel.
	 */
	public function __construct( $order = null, $channel = '' ) {

		parent::__construct( $order );

		$this->channel = $channel;
	}


	/**
	 * Find a transaction by ID
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/find/php
	 *
	 * @since 3.4.0
	 * @param string $transaction_id Transaction ID.
	 */
	public function find_transaction( $transaction_id ) {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'find' );

		$this->request_data = $transaction_id;
	}


	/**
	 * Creates a credit card charge request for the payment method / customer
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php
	 *
	 * @since 3.0.0
	 */
	public function create_credit_card_charge() {

		$this->create_transaction( self::AUTHORIZE_AND_CAPTURE );
	}


	/**
	 * Creates a credit card auth request for the payment method / customer
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php
	 *
	 * @since 3.0.0
	 */
	public function create_credit_card_auth() {

		$this->create_transaction( self::AUTHORIZE_ONLY );
	}


	/**
	 * Capture funds for a previous credit card authorization
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/submit-for-settlement/php
	 *
	 * @since 3.0.0
	 */
	public function create_credit_card_capture() {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'submitForSettlement' );

		$this->request_data = array( $this->get_order()->capture->trans_id, $this->get_order()->capture->amount );
	}


	/**
	 * Refund funds from a previous transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/refund/php
	 *
	 * @since 3.0.0
	 */
	public function create_refund() {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'refund' );

		$this->request_data = array( $this->get_order()->refund->trans_id, $this->get_order()->refund->amount );
	}


	/**
	 * Void a previous transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/void/php
	 *
	 * @since 3.0.0
	 */
	public function create_void() {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'void' );

		$this->request_data = $this->get_order()->refund->trans_id;
	}


	/**
	 * Create a sale transaction with the given settlement type
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php
	 *
	 * @since 3.0.0
	 * @param bool $settlement_type true = auth/capture, false = auth-only.
	 */
	protected function create_transaction( $settlement_type ) {

		$this->set_resource( 'transaction' );
		$this->set_callback( 'sale' );

		$this->request_data = array(
			'amount'            => $this->get_order()->payment_total,
			'orderId'           => $this->get_order()->get_order_number(),
			'merchantAccountId' => empty( $this->get_order()->payment->merchant_account_id ) ? null : $this->get_order()->payment->merchant_account_id,
			'shipping'          => $this->get_shipping_address(),
			'options'           => $this->get_options( $settlement_type ),
			'channel'           => $this->get_channel(),
			'deviceData'        => empty( $this->get_order()->payment->device_data ) ? null : $this->get_order()->payment->device_data,
			'taxExempt'         => $this->get_order()->get_user_id() > 0 && is_callable( array( WC()->customer, 'is_vat_exempt' ) ) ? WC()->customer->is_vat_exempt() : false,
		);

		// Add Level 2 data
		// Note: purchaseOrderNumber is not available in WC core, can be added via `wc_braintree_transaction_data` filter.
		$this->request_data['taxAmount'] = Framework\SV_WC_Helper::number_format( $this->get_order()->get_total_tax() );

		// Add Level 3 data.
		$this->request_data['shippingAmount']      = Framework\SV_WC_Helper::number_format( $this->get_order()->get_shipping_total() );
		$this->request_data['shippingTaxAmount']   = Framework\SV_WC_Helper::number_format( $this->get_order()->get_shipping_tax() );
		$this->request_data['discountAmount']      = Framework\SV_WC_Helper::number_format( $this->get_order()->get_discount_total() );
		$this->request_data['shipsFromPostalCode'] = WC()->countries->get_base_postcode();

		// Add line items for Level 3 data.
		$this->set_line_items();

		// set customer data.
		$this->set_customer();

		// set billing data.
		$this->set_billing();

		// set payment method, either existing token or nonce.
		$this->set_payment_method();

		// add dynamic descriptors.
		$this->set_dynamic_descriptors();

		/**
		 * Filters the request data for new transactions.
		 *
		 * @since 2.0.0
		 * @param array $data The transaction/sale data.
		 * @param \WC_Braintree_API_Transaction_Request $request the request object.
		 */
		$this->request_data = apply_filters( 'wc_braintree_transaction_data', $this->request_data, $this );
	}


	/**
	 * Set the customer data for the transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#customer
	 *
	 * @since 3.0.0
	 */
	protected function set_customer() {

		if ( $this->get_order()->customer_id ) {

			// use existing customer ID.
			$this->request_data['customerId'] = $this->get_order()->customer_id;

		} else {

			// set customer info
			// a customer will only be created if tokenization is required and
			// storeInVaultOnSuccess is set to true, see get_options() below.
			$this->request_data['customer'] = array(
				'firstName' => $this->get_order_prop( 'billing_first_name' ),
				'lastName'  => $this->get_order_prop( 'billing_last_name' ),
				'company'   => $this->get_order_prop( 'billing_company' ),
				'phone'     => Framework\SV_WC_Helper::str_truncate( preg_replace( '/[^\d\-().]/', '', $this->get_order_prop( 'billing_phone' ) ), 14, '' ),
				'email'     => $this->get_order_prop( 'billing_email' ),
			);
		}
	}


	/**
	 * Get the billing address for the transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#billing
	 *
	 * @since 3.0.0
	 */
	protected function set_billing() {

		if ( ! empty( $this->get_order()->payment->billing_address_id ) ) {

			// use the existing billing address when using a saved payment method.
			$this->request_data['billingAddressId'] = $this->get_order()->payment->billing_address_id;

		} else {

			// otherwise just set the billing address directly.
			$this->request_data['billing'] = array(
				'firstName'         => $this->get_order_prop( 'billing_first_name' ),
				'lastName'          => $this->get_order_prop( 'billing_last_name' ),
				'company'           => $this->get_order_prop( 'billing_company' ),
				'streetAddress'     => $this->get_order_prop( 'billing_address_1' ),
				'extendedAddress'   => $this->get_order_prop( 'billing_address_2' ),
				'locality'          => $this->get_order_prop( 'billing_city' ),
				'region'            => $this->get_order_prop( 'billing_state' ),
				'postalCode'        => $this->get_order_prop( 'billing_postcode' ),
				'countryCodeAlpha2' => $this->get_order_prop( 'billing_country' ),
			);
		}
	}


	/**
	 * Get the shipping address for the transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#shipping
	 *
	 * @since 3.0.0
	 * @return array
	 */
	protected function get_shipping_address() {

		$shipping_address = array(
			'firstName'         => $this->get_order_prop( 'shipping_first_name' ),
			'lastName'          => $this->get_order_prop( 'shipping_last_name' ),
			'company'           => $this->get_order_prop( 'shipping_company' ),
			'streetAddress'     => $this->get_order_prop( 'shipping_address_1' ),
			'extendedAddress'   => $this->get_order_prop( 'shipping_address_2' ),
			'locality'          => $this->get_order_prop( 'shipping_city' ),
			'region'            => $this->get_order_prop( 'shipping_state' ),
			'postalCode'        => $this->get_order_prop( 'shipping_postcode' ),
			'countryCodeAlpha2' => $this->get_order_prop( 'shipping_country' ),
		);

		// Add countryCodeAlpha3 for Level 3 data.
		$alpha2_code = $this->get_order_prop( 'shipping_country' );
		if ( $alpha2_code ) {
			$alpha3_code = $this->convert_country_code_to_alpha3( $alpha2_code );
			if ( $alpha3_code ) {
				$shipping_address['countryCodeAlpha3'] = $alpha3_code;
			}
		}

		return $shipping_address;
	}


	/**
	 * Set the payment method for the transaction, either a previously saved payment
	 * method (token) or a new payment method (nonce)
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#payment_method_nonce
	 *
	 * @since 3.0.0
	 */
	protected function set_payment_method() {

		if ( ! empty( $this->get_order()->payment->token ) && empty( $this->get_order()->payment->use_3ds_nonce ) ) {

			// use saved payment method (token).
			$this->request_data['paymentMethodToken'] = $this->get_order()->payment->token;

		} else {

			// use new payment method (nonce).
			$this->request_data['paymentMethodNonce'] = $this->get_order()->payment->nonce;

			// set cardholder name when adding a credit card, note this isn't possible
			// when using a 3DS nonce.
			if ( 'credit_card' === $this->get_order()->payment->type && empty( $this->get_order()->payment->use_3ds_nonce ) ) {
				$this->request_data['creditCard'] = array( 'cardholderName' => $this->get_order()->get_formatted_billing_full_name() );
			}
		}

		// add recurring flag to transactions that are subscription renewals.
		if ( ! empty( $this->get_order()->payment->subscription ) ) {
			$this->request_data['transactionSource'] = $this->get_order()->payment->subscription->is_renewal ? 'recurring' : 'recurring_first';
		}
	}


	/**
	 * Set the dynamic descriptors for the transaction, these are set by the
	 * admin in the gateway settings
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#descriptor
	 *
	 * @since 3.0.0
	 */
	protected function set_dynamic_descriptors() {

		// dynamic descriptors.
		if ( ! empty( $this->get_order()->payment->dynamic_descriptors ) ) {

			$this->request_data['descriptor'] = array();

			foreach ( array( 'name', 'phone', 'url' ) as $key ) {

				if ( ! empty( $this->get_order()->payment->dynamic_descriptors->$key ) ) {
					$this->request_data['descriptor'][ $key ] = $this->get_order()->payment->dynamic_descriptors->$key;
				}
			}
		}
	}


	/**
	 * Get the options for the transaction
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#options
	 *
	 * @since 3.0.0
	 * @param bool $settlement_type authorize or auth/capture.
	 * @return array
	 */
	protected function get_options( $settlement_type ) {

		$options = array(
			'submitForSettlement'   => $settlement_type,
			'storeInVaultOnSuccess' => $this->get_order()->payment->tokenize,
		);

		if ( $this->get_order()->payment->tokenize ) {
			$options['addBillingAddressToPaymentMethod'] = true;
		}

		if ( ! empty( $this->get_order()->payment->is_3ds_required ) ) {
			$options['three_d_secure'] = array( 'required' => true );
		}

		return $options;
	}


	/**
	 * Gets the channel ID for the transaction.
	 *
	 * @link https://developers.braintreepayments.com/reference/request/transaction/sale/php#channel
	 *
	 * @since 3.0.0
	 */
	protected function get_channel() {

		return $this->channel;
	}


	/**
	 * Set line items for Level 3 transaction data
	 */
	protected function set_line_items() {

		$line_items = array();

		// Get order items.
		foreach ( $this->get_order()->get_items() as $item ) {
			// Skip this item from the L3 request data if the item total amount is 0.
			// If the item total amount is 0 (even if the order total is non-zero) it results in error: "95816: Total amount must be greater than zero. Zero is allowed for PayPal transactions.".
			$item_total_amount = $this->get_order()->get_line_total( $item, false );
			if ( $item_total_amount <= 0 || $item_total_amount != (float) $item_total_amount ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				continue;
			}

			// Note: L3 fields, commodityCode and unitOfMeasure are not available in WC core, can be added via `wc_braintree_transaction_data` filter.
			$line_item = array(
				'name'           => Framework\SV_WC_Helper::str_truncate( $item->get_name(), 35, '' ),
				'kind'           => 'debit',
				'quantity'       => (string) $item->get_quantity(),
				'unitAmount'     => Framework\SV_WC_Helper::number_format( $this->get_order()->get_item_subtotal( $item, false ) ),
				'unitTaxAmount'  => Framework\SV_WC_Helper::number_format( $this->get_order()->get_item_tax( $item ) / $item->get_quantity() ),
				'totalAmount'    => Framework\SV_WC_Helper::number_format( $item_total_amount ),
				'taxAmount'      => Framework\SV_WC_Helper::number_format( $this->get_order()->get_line_tax( $item ) ),
				'discountAmount' => Framework\SV_WC_Helper::number_format( $item->get_subtotal() - $item->get_total() ),
				'productCode'    => Framework\SV_WC_Helper::str_truncate( $item->get_product()->get_sku(), 12, '' ),
			);

			$line_items[] = $line_item;
		}

		if ( ! empty( $line_items ) ) {
			$this->request_data['lineItems'] = $line_items;
		}
	}


	/**
	 * Convert ISO 3166-1 alpha-2 country code to alpha-3
	 * https://www.iban.com/country-codes
	 *
	 * @param string $alpha2_code The 2-letter country code.
	 * @return string|null The 3-letter country code or null if not found
	 */
	private function convert_country_code_to_alpha3( $alpha2_code ) {

		// ISO 3166-1 alpha-2 to alpha-3 mapping.
		$country_codes = array(
			'AD' => 'AND',
			'AE' => 'ARE',
			'AF' => 'AFG',
			'AG' => 'ATG',
			'AI' => 'AIA',
			'AL' => 'ALB',
			'AM' => 'ARM',
			'AO' => 'AGO',
			'AQ' => 'ATA',
			'AR' => 'ARG',
			'AS' => 'ASM',
			'AT' => 'AUT',
			'AU' => 'AUS',
			'AW' => 'ABW',
			'AX' => 'ALA',
			'AZ' => 'AZE',
			'BA' => 'BIH',
			'BB' => 'BRB',
			'BD' => 'BGD',
			'BE' => 'BEL',
			'BF' => 'BFA',
			'BG' => 'BGR',
			'BH' => 'BHR',
			'BI' => 'BDI',
			'BJ' => 'BEN',
			'BL' => 'BLM',
			'BM' => 'BMU',
			'BN' => 'BRN',
			'BO' => 'BOL',
			'BQ' => 'BES',
			'BR' => 'BRA',
			'BS' => 'BHS',
			'BT' => 'BTN',
			'BV' => 'BVT',
			'BW' => 'BWA',
			'BY' => 'BLR',
			'BZ' => 'BLZ',
			'CA' => 'CAN',
			'CC' => 'CCK',
			'CD' => 'COD',
			'CF' => 'CAF',
			'CG' => 'COG',
			'CH' => 'CHE',
			'CI' => 'CIV',
			'CK' => 'COK',
			'CL' => 'CHL',
			'CM' => 'CMR',
			'CN' => 'CHN',
			'CO' => 'COL',
			'CR' => 'CRI',
			'CU' => 'CUB',
			'CV' => 'CPV',
			'CW' => 'CUW',
			'CX' => 'CXR',
			'CY' => 'CYP',
			'CZ' => 'CZE',
			'DE' => 'DEU',
			'DJ' => 'DJI',
			'DK' => 'DNK',
			'DM' => 'DMA',
			'DO' => 'DOM',
			'DZ' => 'DZA',
			'EC' => 'ECU',
			'EE' => 'EST',
			'EG' => 'EGY',
			'EH' => 'ESH',
			'ER' => 'ERI',
			'ES' => 'ESP',
			'ET' => 'ETH',
			'FI' => 'FIN',
			'FJ' => 'FJI',
			'FK' => 'FLK',
			'FM' => 'FSM',
			'FO' => 'FRO',
			'FR' => 'FRA',
			'GA' => 'GAB',
			'GB' => 'GBR',
			'GD' => 'GRD',
			'GE' => 'GEO',
			'GF' => 'GUF',
			'GG' => 'GGY',
			'GH' => 'GHA',
			'GI' => 'GIB',
			'GL' => 'GRL',
			'GM' => 'GMB',
			'GN' => 'GIN',
			'GP' => 'GLP',
			'GQ' => 'GNQ',
			'GR' => 'GRC',
			'GS' => 'SGS',
			'GT' => 'GTM',
			'GU' => 'GUM',
			'GW' => 'GNB',
			'GY' => 'GUY',
			'HK' => 'HKG',
			'HM' => 'HMD',
			'HN' => 'HND',
			'HR' => 'HRV',
			'HT' => 'HTI',
			'HU' => 'HUN',
			'ID' => 'IDN',
			'IE' => 'IRL',
			'IL' => 'ISR',
			'IM' => 'IMN',
			'IN' => 'IND',
			'IO' => 'IOT',
			'IQ' => 'IRQ',
			'IR' => 'IRN',
			'IS' => 'ISL',
			'IT' => 'ITA',
			'JE' => 'JEY',
			'JM' => 'JAM',
			'JO' => 'JOR',
			'JP' => 'JPN',
			'KE' => 'KEN',
			'KG' => 'KGZ',
			'KH' => 'KHM',
			'KI' => 'KIR',
			'KM' => 'COM',
			'KN' => 'KNA',
			'KP' => 'PRK',
			'KR' => 'KOR',
			'KW' => 'KWT',
			'KY' => 'CYM',
			'KZ' => 'KAZ',
			'LA' => 'LAO',
			'LB' => 'LBN',
			'LC' => 'LCA',
			'LI' => 'LIE',
			'LK' => 'LKA',
			'LR' => 'LBR',
			'LS' => 'LSO',
			'LT' => 'LTU',
			'LU' => 'LUX',
			'LV' => 'LVA',
			'LY' => 'LBY',
			'MA' => 'MAR',
			'MC' => 'MCO',
			'MD' => 'MDA',
			'ME' => 'MNE',
			'MF' => 'MAF',
			'MG' => 'MDG',
			'MH' => 'MHL',
			'MK' => 'MKD',
			'ML' => 'MLI',
			'MM' => 'MMR',
			'MN' => 'MNG',
			'MO' => 'MAC',
			'MP' => 'MNP',
			'MQ' => 'MTQ',
			'MR' => 'MRT',
			'MS' => 'MSR',
			'MT' => 'MLT',
			'MU' => 'MUS',
			'MV' => 'MDV',
			'MW' => 'MWI',
			'MX' => 'MEX',
			'MY' => 'MYS',
			'MZ' => 'MOZ',
			'NA' => 'NAM',
			'NC' => 'NCL',
			'NE' => 'NER',
			'NF' => 'NFK',
			'NG' => 'NGA',
			'NI' => 'NIC',
			'NL' => 'NLD',
			'NO' => 'NOR',
			'NP' => 'NPL',
			'NR' => 'NRU',
			'NU' => 'NIU',
			'NZ' => 'NZL',
			'OM' => 'OMN',
			'PA' => 'PAN',
			'PE' => 'PER',
			'PF' => 'PYF',
			'PG' => 'PNG',
			'PH' => 'PHL',
			'PK' => 'PAK',
			'PL' => 'POL',
			'PM' => 'SPM',
			'PN' => 'PCN',
			'PR' => 'PRI',
			'PS' => 'PSE',
			'PT' => 'PRT',
			'PW' => 'PLW',
			'PY' => 'PRY',
			'QA' => 'QAT',
			'RE' => 'REU',
			'RO' => 'ROU',
			'RS' => 'SRB',
			'RU' => 'RUS',
			'RW' => 'RWA',
			'SA' => 'SAU',
			'SB' => 'SLB',
			'SC' => 'SYC',
			'SD' => 'SDN',
			'SE' => 'SWE',
			'SG' => 'SGP',
			'SH' => 'SHN',
			'SI' => 'SVN',
			'SJ' => 'SJM',
			'SK' => 'SVK',
			'SL' => 'SLE',
			'SM' => 'SMR',
			'SN' => 'SEN',
			'SO' => 'SOM',
			'SR' => 'SUR',
			'SS' => 'SSD',
			'ST' => 'STP',
			'SV' => 'SLV',
			'SX' => 'SXM',
			'SY' => 'SYR',
			'SZ' => 'SWZ',
			'TC' => 'TCA',
			'TD' => 'TCD',
			'TF' => 'ATF',
			'TG' => 'TGO',
			'TH' => 'THA',
			'TJ' => 'TJK',
			'TK' => 'TKL',
			'TL' => 'TLS',
			'TM' => 'TKM',
			'TN' => 'TUN',
			'TO' => 'TON',
			'TR' => 'TUR',
			'TT' => 'TTO',
			'TV' => 'TUV',
			'TW' => 'TWN',
			'TZ' => 'TZA',
			'UA' => 'UKR',
			'UG' => 'UGA',
			'UM' => 'UMI',
			'US' => 'USA',
			'UY' => 'URY',
			'UZ' => 'UZB',
			'VA' => 'VAT',
			'VC' => 'VCT',
			'VE' => 'VEN',
			'VG' => 'VGB',
			'VI' => 'VIR',
			'VN' => 'VNM',
			'VU' => 'VUT',
			'WF' => 'WLF',
			'WS' => 'WSM',
			'YE' => 'YEM',
			'YT' => 'MYT',
			'ZA' => 'ZAF',
			'ZM' => 'ZMB',
			'ZW' => 'ZWE',
		);

		return $country_codes[ $alpha2_code ] ?? null;
	}
}
