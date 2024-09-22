/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';

let cachedPayPalServerData = null;

/**
 * Braintree Paypal data comes from the server passed on a global object.
 */
export const getBraintreePayPalServerData = () => {
	if (cachedPayPalServerData !== null) {
		return cachedPayPalServerData;
	}

	const braintreePayPalData = getSetting(`${PAYMENT_METHOD_ID}_data`, null);

	if (!braintreePayPalData) {
		throw new Error(
			'Braintree Paypal initialization data is not available'
		);
	}

	const isCheckoutConfirmation =
		braintreePayPalData.is_checkout_confirmation || false;

	cachedPayPalServerData = {
		ajaxUrl: braintreePayPalData.ajax_url || '',
		buttonStyles: braintreePayPalData.button_styles || {},
		buttonWidth: braintreePayPalData.button_width || null,
		clientTokenNonce: braintreePayPalData.client_token_nonce || '',
		setPaymentMethodNonce:
			braintreePayPalData.set_payment_method_nonce || '',
		debug: braintreePayPalData.debug || false,
		description: braintreePayPalData.description || '',
		forceBuyerCountry: braintreePayPalData.force_buyer_country || '',
		integrationErrorMessage:
			braintreePayPalData.integration_error_message || 'Unknown error',
		isTestEnvironment: braintreePayPalData.is_test_environment || false,
		isPayPalPayLaterEnabled:
			braintreePayPalData.is_paypal_pay_later_enabled || false,
		isPayPalCardEnabled:
			braintreePayPalData.is_paypal_card_enabled || false,
		logoUrl: braintreePayPalData.logo_url || '',
		messageLogoType: braintreePayPalData.messaging_logo_type || 'inline',
		messageLogoPosition:
			braintreePayPalData.messaging_logo_position || 'left',
		messageTextColor: braintreePayPalData.messaging_text_color || 'black',
		paymentErrorMessage:
			braintreePayPalData.payment_error_message || 'Unknown error',
		payPalDisabledFundingOptions:
			braintreePayPalData.paypal_disabled_funding_options || [],
		payPalIntent: braintreePayPalData.paypal_intent || '',
		paypalLocale: braintreePayPalData.paypal_locale || 'en_us',
		showSavedCards:
			(braintreePayPalData.show_saved_cards && !isCheckoutConfirmation) ||
			false,
		showSaveOption:
			(braintreePayPalData.show_save_option && !isCheckoutConfirmation) ||
			false,
		supports: braintreePayPalData.supports || {},
		title: braintreePayPalData.title || '',
		tokenizationForced: braintreePayPalData.tokenization_forced || false,
		isCheckoutConfirmation:
			braintreePayPalData.is_checkout_confirmation || false,
		payPalCustomerDetails:
			braintreePayPalData.paypal_customer_details || {},
		cartPaymentNonce: braintreePayPalData.cart_payment_nonce || '',
		cartHandlerUrl: braintreePayPalData.cart_handler_url || '',
		cartCheckoutEnabled: braintreePayPalData.cart_checkout_enabled || false,
	};

	return cachedPayPalServerData;
};

/**
 * Log data to console if debug is enabled.
 *
 * @param {string} message Message to log
 * @param {Object} data    Data object to log
 * @return {void}
 */
export const logData = (message, data = null) => {
	if (getBraintreePayPalServerData().debug) {
		/* eslint-disable no-console */
		console.log(`Braintree (PayPal): ${message}`);
		if (data) {
			console.log(data);
		}
		/* eslint-enable no-console */
	}
};
