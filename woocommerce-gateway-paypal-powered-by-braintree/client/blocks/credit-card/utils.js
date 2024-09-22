/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';

let cachedCreditCardServerData = null;

/**
 * Braintree Credit Card data comes from the server passed on a global object.
 */
export const getBraintreeCreditCardServerData = () => {
	if (cachedCreditCardServerData !== null) {
		return cachedCreditCardServerData;
	}

	const creditCardData = getSetting(`${PAYMENT_METHOD_ID}_data`, null);

	if (!creditCardData) {
		throw new Error(
			'Braintree Credit Card initialization data is not available'
		);
	}

	cachedCreditCardServerData = {
		ajaxUrl: creditCardData.ajax_url || '',
		cartContainsSubscription:
			creditCardData.cart_contains_subscription || false,
		clientTokenNonce: creditCardData.client_token_nonce || '',
		cscRequired: creditCardData.csc_required === false ? false : true,
		debug: creditCardData.debug || false,
		description: creditCardData.description || '',
		enabledCardTypes: creditCardData.enabled_card_types || [],
		fieldsErrorMessages: creditCardData.fields_error_messages || {},
		hostedFieldsStyles: creditCardData.hosted_fields_styles || {},
		icons: creditCardData.icons || {},
		integrationErrorMessage:
			creditCardData.integration_error_message || 'Unknown error',
		isTestEnvironment: creditCardData.is_test_environment || false,
		isAdvancedFraudTool: creditCardData.is_advanced_fraud_tool || false,
		orderTotal3DSecure: creditCardData.order_total_for_3ds || 0,
		paymentErrorMessage:
			creditCardData.payment_error_message || 'Unknown error',
		showSavedCards: creditCardData.show_saved_cards || false,
		showSaveOption: creditCardData.show_save_option || false,
		supports: creditCardData.supports || {},
		title: creditCardData.title || '',
		threeds: creditCardData.threeds || {},
		tokenDataNonce: creditCardData.token_data_nonce || '',
		tokenizationForced: creditCardData.tokenization_forced || false,
	};

	return cachedCreditCardServerData;
};

/**
 * Attach EventListners to various hosted fields events.
 *
 * @param {Object} hostedFieldsInstance
 */
export const attachEventsOnHostedFields = (hostedFieldsInstance) => {
	function addClass(eventObj, className) {
		const field = eventObj.fields[eventObj.emittedBy];
		if (field) {
			field.container.classList.add(className);
		}
	}

	function removeClass(eventObj, className) {
		const field = eventObj.fields[eventObj.emittedBy];
		if (field) {
			field.container.classList.remove(className);
		}
	}

	/**
	 * Add/remove class of field on various events (focus, blur, etc...) to apply blocks CSS styles.
	 */
	hostedFieldsInstance.on('focus', (event) => {
		addClass(event, 'focused');
	});

	hostedFieldsInstance.on('blur', (event) => {
		const field = event.fields[event.emittedBy];
		removeClass(event, 'focused');
		if (field.isEmpty) {
			addClass(event, 'empty');
		}
	});
	hostedFieldsInstance.on('empty', (event) => {
		addClass(event, 'empty');
	});

	hostedFieldsInstance.on('notEmpty', function (event) {
		removeClass(event, 'empty');
	});

	/**
	 * Fires when the Hosted Fields integration detects a card type change.
	 *
	 * This is used to update the card type icon if a specific type is found
	 * or indicate an invalid type when necessary.
	 */
	hostedFieldsInstance.on('cardTypeChange', function (event) {
		if (!event.cards) {
			return;
		}

		const cardField = document.getElementById(
			'wc-braintree-credit-card-account-number-hosted'
		);

		// Clear any existing card type class
		cardField.classList.forEach((className) => {
			if (className.startsWith('card-type-')) {
				cardField.classList.remove(className);
			}
		});

		if (!event.cards.length) {
			return cardField.classList.add('card-type-invalid');
		}

		if (event.cards.length === 1) {
			const card = event.cards[0];
			const { enabledCardTypes } = getBraintreeCreditCardServerData();

			if (card.type && enabledCardTypes.includes(card.type)) {
				cardField.classList.add(`card-type-${card.type}`);
			} else {
				cardField.classList.add('card-type-invalid');
			}
		}
	});
};

/**
 * Prepare and Return hosted fields options for styles and fields.
 *
 * @param {boolean} usingToken - Whether or not the customer is using a saved payment method.
 */
export const getHostedFieldsOptions = (usingToken = false) => {
	const { cscRequired, hostedFieldsStyles } =
		getBraintreeCreditCardServerData();
	const hostedFieldsOptions = {
		styles: {
			...hostedFieldsStyles,
			input: {
				'font-size': '16px',
				'line-height': '1.375',
			},
			'::placeholder': {
				color: 'transparent',
			},
			':focus::placeholder': {
				color: '#757575',
			},
			'.invalid': {
				color: '#cc1818',
			},
		},
		fields: {},
	};

	if (!usingToken) {
		hostedFieldsOptions.fields = {
			number: {
				selector: '#wc-braintree-credit-card-account-number-hosted',
				placeholder: '•••• •••• •••• ••••',
			},
			expirationDate: {
				selector: '#wc-braintree-credit-card-expiry-hosted',
				placeholder: __(
					'MM / YY',
					'woocommerce-gateway-paypal-powered-by-braintree'
				),
			},
		};
	}

	if (cscRequired) {
		const selector = usingToken
			? '#wc-braintree-credit-card-csc-hosted-token'
			: '#wc-braintree-credit-card-csc-hosted';
		hostedFieldsOptions.fields.cvv = {
			selector,
			placeholder: __(
				'CSC',
				'woocommerce-gateway-paypal-powered-by-braintree'
			),
		};
	}

	return hostedFieldsOptions;
};

/**
 * Get the error message from the error object.
 *
 * @param {Object} error - The error object.
 * @param {boolean} usingToken - Whether or not the customer is using a saved payment method.
 * @return {string} The error message.
 */
export const getErrorMessage = (error, usingToken = false) => {
	const messages = [];
	const { fieldsErrorMessages, cscRequired } =
		getBraintreeCreditCardServerData();

	if (!error || !error.type) {
		return error.message || '';
	}

	if (error.type === 'CUSTOMER') {
		switch (error.code) {
			case 'HOSTED_FIELDS_FIELDS_EMPTY':
				if (!usingToken) {
					messages.push(fieldsErrorMessages.card_number_required);
					messages.push(
						fieldsErrorMessages.card_expirationDate_required
					);
				}
				if (cscRequired) {
					messages.push(fieldsErrorMessages.card_cvv_required);
				}
				break;

			case 'HOSTED_FIELDS_FIELDS_INVALID':
				if (error.details && error.details.invalidFieldKeys) {
					for (const field of error.details.invalidFieldKeys) {
						messages.push(
							fieldsErrorMessages[`card_${field}_invalid`] || ''
						);
					}
				}
				break;

			default:
				messages.push(error.message || '');
				messages.push((error.error && error.error.message) || '');

				if (error.details && error.details.originalError) {
					// Recursively add error messages from nested originalError objects.
					const errors = getErrorMessage(error.details.originalError);
					if (errors) {
						messages.push(errors);
					}
				}
				break;
		}
	} else if (error.type === 'NETWORK') {
		if (
			error.details &&
			error.details.originalError &&
			error.details.originalError.error
		) {
			messages.push(error.details.originalError.error.message || '');
		}
	}

	if (messages.length) {
		return messages.filter((ele) => ele).join('. ');
	}
	return '';
};

/**
 * Get token and token nonce data.
 *
 * @param {string} token Saved Token ID.
 * @return {Object} return token and token 3ds nonce.
 */
export const getTokenData = async (token) => {
	const { ajaxUrl, tokenDataNonce, paymentErrorMessage } =
		getBraintreeCreditCardServerData();
	const formData = new FormData();
	formData.append('action', `wc_${PAYMENT_METHOD_ID}_get_token_data`);
	formData.append('token_id', token);
	formData.append('nonce', tokenDataNonce);

	const res = await fetch(ajaxUrl, {
		method: 'POST',
		body: formData,
	});
	const response = await res.json();
	if (response && !response.success) {
		const message =
			(response.data && response.data.message) || paymentErrorMessage;
		throw new Error(message);
	}

	if (response && response.success && response.data) {
		return response.data;
	}

	throw new Error(paymentErrorMessage);
};

/**
 * Log data to console if debug is enabled.
 *
 * @param {string} message Message to log
 * @param {Object} data    Data object to log
 * @return {void}
 */
export const logData = (message, data = null) => {
	if (getBraintreeCreditCardServerData().debug) {
		/* eslint-disable no-console */
		console.log(`Braintree (Credit Card): ${message}`);
		if (data) {
			console.log(data);
		}
		/* eslint-enable no-console */
	}
};

/**
 * Get BrainTree credit card Icons.
 *
 * @return {Object} The credit card icons.
 */
export const getCardIcons = () => {
	const { icons = {} } = getBraintreeCreditCardServerData();
	return Object.entries(icons).map(([id, { src, alt }]) => {
		return {
			id,
			src,
			alt,
		};
	});
};
