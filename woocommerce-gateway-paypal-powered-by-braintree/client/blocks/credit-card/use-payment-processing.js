/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import {
	getBraintreeCreditCardServerData,
	getErrorMessage,
	getTokenData,
	logData,
} from './utils';
import { PAYMENT_METHOD_NAME } from './constants';

const { threeds, integrationErrorMessage, paymentErrorMessage, cscRequired } =
	getBraintreeCreditCardServerData();
const { braintree } = window;

/**
 * Sets up payment details and POST data to be processed on server-side on checkout submission.
 *
 * @param {Function} onPaymentProcessing  Callback for registering observers on the payment processing event
 * @param {Object}   emitResponse         Helpers for observer response objects
 * @param {Function} getPaymentMethodData getPaymentMethodData function
 * @param {Function} verify3DSecure       verify3DSecure       function
 * @param {Object}   hostedFieldsInstance hostedFieldsInstance
 * @param {string}   token                token
 */
export const usePaymentProcessing = (
	onPaymentProcessing,
	emitResponse,
	getPaymentMethodData,
	verify3DSecure,
	hostedFieldsInstance,
	token = null
) => {
	useEffect(() => {
		const errorResponse = {
			type: emitResponse.responseTypes.ERROR,
			messageContext: emitResponse.noticeContexts.PAYMENTS,
		};
		const is3DSecureEnabled =
			threeds && threeds.enabled && braintree && braintree.threeDSecure;
		const shouldTokenize = !(token && !cscRequired);
		let shouldVerify3DSecure = false;

		const unsubscribe = onPaymentProcessing(async () => {
			if (shouldTokenize && !hostedFieldsInstance) {
				return {
					...errorResponse,
					message: integrationErrorMessage,
				};
			}

			try {
				const paymentData = getPaymentMethodData();
				let nonce, bin, cardType, type;
				if (shouldTokenize) {
					// Tokenize hosted fields.
					const res = await hostedFieldsInstance.tokenize();
					[nonce, bin, cardType, type] = [
						res.nonce,
						res.details.bin,
						res.details.cardType,
						res.type,
					];

					// Return error if nonce is not present.
					if (!nonce) {
						return {
							...errorResponse,
							message: res.message,
						};
					}
					paymentData.wc_braintree_credit_card_payment_nonce = nonce;
				}

				// Get Token data for saved card.
				if (token) {
					try {
						const tokenData = await getTokenData(token);
						if (tokenData && tokenData.nonce) {
							nonce = tokenData.nonce;
							bin = tokenData.bin;
							shouldVerify3DSecure = true;
						}
						paymentData.token = tokenData.token;
						paymentData[`wc-${PAYMENT_METHOD_NAME}-payment-token`] =
							tokenData.token;
					} catch (error) {
						return {
							...errorResponse,
							message: error.message || paymentErrorMessage,
						};
					}
				}

				/**
				 * Verify 3DS, IF 3DS is enabled AMD
				 *  - Card type is supported
				 *      OR
				 *  - Token is present and it should be verified
				 */
				if (
					is3DSecureEnabled &&
					((token && shouldVerify3DSecure) ||
						(type === 'CreditCard' &&
							threeds.card_types.includes(cardType)))
				) {
					const response = await verify3DSecure(nonce, bin);
					logData('3D Secure Response received', response);

					// Decline if a liability shift is required for all eligible transactions and liability was _not_ shifted
					if (
						threeds.liability_shift_always_required &&
						!response.liabilityShifted
					) {
						return {
							...errorResponse,
							message: threeds.liability_shift_message,
						};
					}

					// Load 3DS related payment data.
					paymentData[`wc-${PAYMENT_METHOD_NAME}-card-type`] = token
						? ''
						: cardType.replace(' ', '').toLowerCase();
					paymentData[`wc-${PAYMENT_METHOD_NAME}-3d-secure-enabled`] =
						'1';
					paymentData[
						`wc-${PAYMENT_METHOD_NAME}-3d-secure-verified`
					] = '1';
					paymentData.wc_braintree_credit_card_payment_nonce =
						response.nonce;
				}

				// All good to send payment data to server.
				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: paymentData,
					},
				};
			} catch (error) {
				logData(`Payment Error: ${error.message}`, error);
				const message = getErrorMessage(error, token) || error.message;
				return {
					...errorResponse,
					message: decodeEntities(message),
				};
			}
		});
		return unsubscribe;
	}, [
		emitResponse.responseTypes.SUCCESS,
		emitResponse.responseTypes.ERROR,
		emitResponse.noticeContexts.PAYMENTS,
		onPaymentProcessing,
		getPaymentMethodData,
		hostedFieldsInstance,
		verify3DSecure,
		token,
	]);
};
