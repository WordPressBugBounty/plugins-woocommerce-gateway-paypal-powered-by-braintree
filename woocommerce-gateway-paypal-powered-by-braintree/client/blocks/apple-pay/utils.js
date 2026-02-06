/**
 * Apple Pay utility functions
 */
import { getSetting } from '@woocommerce/settings';
import { PAYMENT_METHOD_ID } from './constants';

/**
 * Get Braintree Apple Pay server data from localized settings
 * @returns {Object} Server data for Apple Pay
 */
export const getBraintreeApplePayServerData = () => {
	const creditCardData = getSetting( `${ PAYMENT_METHOD_ID }_data`, {} );

	return {
		applePayEnabled: creditCardData.apple_pay_enabled,
		buttonStyle: creditCardData.apple_pay_button_style,
		displayLocations: creditCardData.apple_pay_display_locations,
		merchantName: creditCardData.store_name,
		clientTokenNonce: creditCardData.client_token_nonce,
		isTestEnvironment: creditCardData.is_test_environment,
		supports: creditCardData.supports,
		tokenizationForced: creditCardData.tokenization_forced,
		cartContainsSubscription: creditCardData.cart_contains_subscription,
		ajaxUrl: creditCardData.ajax_url,
		recalculateTotalsNonce:
			creditCardData.apple_pay_recalculate_totals_nonce,
	};
};

/**
 * Check if Apple Pay is available in the browser
 * @returns {boolean} True if Apple Pay is available
 */
export const isApplePayAvailable = () => {
	return window.ApplePaySession && ApplePaySession.canMakePayments();
};

/**
 * Convert Apple Pay contact to WooCommerce address format
 * @param {Object} contact - Apple Pay contact object
 * @returns {Object} Address object
 */
export const convertApplePayContactToAddress = ( contact ) => {
	if ( ! contact ) {
		return {};
	}

	return {
		first_name: contact.givenName || '',
		last_name: contact.familyName || '',
		company: '',
		address_1: contact.addressLines?.[ 0 ] || '',
		address_2: contact.addressLines?.[ 1 ] || '',
		city: contact.locality || '',
		state: contact.administrativeArea || '',
		postcode: contact.postalCode || '',
		country: contact.countryCode || '',
		email: contact.emailAddress || '',
		phone: contact.phoneNumber || '',
	};
};

/**
 * Format currency amounts from cents to dollars
 * @param {number} valueInCents - The value in cents
 * @returns {string} Formatted currency string
 */
export const formatCurrencyAmount = ( valueInCents ) => {
	const value = valueInCents || 0;
	return ( value / 100 ).toFixed( 2 );
};
