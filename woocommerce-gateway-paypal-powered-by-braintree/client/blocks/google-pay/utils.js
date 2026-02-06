/**
 * Google Pay utility functions.
 */
import { getSetting } from '@woocommerce/settings';
import { PAYMENT_METHOD_ID } from './constants';

/**
 * Get Braintree Google Pay server data from localized settings.
 *
 * @returns {Object} Server data for Google Pay.
 */
export const getBraintreeGooglePayServerData = () => {
	const data = getSetting( `${ PAYMENT_METHOD_ID }_data`, {} );

	return {
		googlePayEnabled: data.google_pay.flags.is_enabled,
		googlePayAvailable: data.google_pay.flags.is_available,
		googleMerchantId: data.google_pay.merchant_id,
		buttonStyle: data.google_pay.button_style,
		merchantName: data.store_name,
		clientTokenNonce: data.client_token_nonce,
		isTestEnvironment: data.is_test_environment,
		supports: data.supports,
		tokenizationForced: data.tokenization_forced,
		cartContainsSubscription: data.cart_contains_subscription,
		ajaxUrl: data.ajax_url,
		allowedCardNetworks: data.google_pay.card_types,
		allowedCountryCodes: data.google_pay.countries,
		recalculateTotalsNonce: data.google_pay.recalculate_totals_nonce,
		processPaymentNonce: data.google_pay.process_payment_nonce,
	};
};

/**
 * Get the Transaction Information from the server.
 *
 * @param {string} ajaxUrl         The AJAX URL.
 * @param {string} paymentMethodId The payment method ID.
 * @param {string} nonce           The ajax nonce to verify at server side.
 */
export const getTransactionInfo = ( ajaxUrl, paymentMethodId, nonce ) => {
	const formData = new FormData();
	formData.append( 'action', `wc_${ paymentMethodId }_get_transaction_info` );
	formData.append( 'nonce', nonce );

	return fetch( ajaxUrl, {
		method: 'POST',
		body: formData,
	} )
		.then( ( response ) => response.json() )
		.then( ( res ) => {
			if ( res && ! res.success ) {
				const message = ( res.data && res.data.message ) || '';
				throw new Error(
					`Could not retrieve the transaction info via AJAX: ${ message }`
				);
			}
			if ( res && res.success && res.data ) {
				return JSON.parse( res.data );
			}
		} );
};

/**
 * Get updated totals and shipping options via AJAX for use in the PaymentDataRequest
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentDataRequestUpdate|PaymentDataRequestUpdate}
 *
 * @param {string} ajaxUrl         The AJAX URL.
 * @param {string} paymentMethodId The payment method ID.
 * @param {string} nonce           The ajax nonce to verify at server side.
 * @param {object} shippingAddress The shipping address.
 * @param {object} shippingMethod  The chosen shipping method.
 * @returns {Promise<object>} Promise that resolves with PaymentDataRequestUpdate object
 */
export const getUpdatedTotals = (
	ajaxUrl,
	paymentMethodId,
	nonce,
	shippingAddress,
	shippingMethod
) => {
	const formData = new FormData();
	formData.append( 'action', `wc_${ paymentMethodId }_recalculate_totals` );
	formData.append( 'nonce', nonce );
	formData.append( 'shippingAddress', shippingAddress );
	formData.append( 'shippingMethod', shippingMethod );

	return fetch( ajaxUrl, {
		method: 'POST',
		body: formData,
	} )
		.then( ( response ) => response.json() )
		.then( ( res ) => {
			if ( res && ! res.success ) {
				const message = ( res.data && res.data.message ) || '';
				throw new Error(
					`Could not recalculate totals via AJAX: ${ message }`
				);
			}
			if ( res && res.success && res.data ) {
				return JSON.parse( res.data );
			}
		} );
};

/**
 * Process payment data returned by the Google Pay API
 *
 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentData|PaymentData object reference}
 *
 * @param {string} ajaxUrl         The AJAX URL.
 * @param {string} paymentMethodId The payment method ID.
 * @param {string} nonce           The ajax nonce to verify at server side.
 * @param {object} paymentData     The payment data.
 * @returns {Promise<object>} Promise that resolves with PaymentData object
 */
export const processPayment = (
	ajaxUrl,
	paymentMethodId,
	nonce,
	paymentData
) => {
	const formData = new FormData();
	formData.append( 'action', `wc_${ paymentMethodId }_process_payment` );
	formData.append( 'nonce', nonce );
	formData.append( 'paymentData', JSON.stringify( paymentData ) );

	return fetch( ajaxUrl, {
		method: 'POST',
		body: formData,
	} )
		.then( ( response ) => response.json() )
		.then( ( res ) => {
			if ( res && ! res.success ) {
				const message = ( res.data && res.data.message ) || '';
				throw new Error( message );
			}
			if ( res && res.success && res.data ) {
				return res.data;
			}
		} );
};
