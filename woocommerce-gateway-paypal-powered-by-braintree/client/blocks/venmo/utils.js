/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';

let cachedVenmoServerData = null;

/**
 * Braintree Venmo data comes from the server passed on a global object.
 */
export const getBraintreeVenmoServerData = () => {
	if ( cachedVenmoServerData !== null ) {
		return cachedVenmoServerData;
	}

	const braintreeVenmoData = getSetting(
		`${ PAYMENT_METHOD_ID }_data`,
		null
	);

	if ( ! braintreeVenmoData ) {
		throw new Error(
			'Braintree Venmo initialization data is not available'
		);
	}

	const isCheckoutConfirmation =
		braintreeVenmoData.is_checkout_confirmation || false;

	cachedVenmoServerData = {
		ajaxUrl: braintreeVenmoData.ajax_url || '',
		clientTokenNonce: braintreeVenmoData.client_token_nonce || '',
		debug: braintreeVenmoData.debug || false,
		description: braintreeVenmoData.description || '',
		paymentUsage: braintreeVenmoData.payment_usage || 'single_use',
		cartPaymentNonce: braintreeVenmoData.cart_payment_nonce || '',
		showSavedCards:
			( braintreeVenmoData.show_saved_cards &&
				! isCheckoutConfirmation ) ||
			false,
		showSaveOption:
			( braintreeVenmoData.show_save_option &&
				! isCheckoutConfirmation ) ||
			false,
		supports: braintreeVenmoData.supports || {},
		title: braintreeVenmoData.title || '',
		tokenizationForced: braintreeVenmoData.tokenization_forced || false,
		isCheckoutConfirmation,
		checkoutConfirmationDescription:
			braintreeVenmoData.checkout_confirmation_description || '',
		pluginUrl: braintreeVenmoData.plugin_url || '',
		cartCheckoutEnabled: braintreeVenmoData.cart_checkout_enabled || false,
		cartHandlerUrl: braintreeVenmoData.cart_handler_url || '',
		cartHandlerNonce: braintreeVenmoData.set_payment_method_nonce || '',
	};

	return cachedVenmoServerData;
};

/**
 * Log data to console if debug is enabled.
 *
 * @param {string} message Message to log
 * @param {Object} data    Data object to log
 * @return {void}
 */
export const logData = ( message, data = null ) => {
	if ( getBraintreeVenmoServerData().debug ) {
		/* eslint-disable no-console */
		console.log( `Braintree (Venmo): ${ message }` );
		if ( data ) {
			console.log( data );
		}
		/* eslint-enable no-console */
	}
};
