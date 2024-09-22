/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getBraintreePayPalServerData } from './utils';

/**
 * Sets up payment details and POST data to be processed on server-side on checkout submission.
 *
 * @param {Function} onPaymentProcessing  Callback for registering observers on the payment processing event
 * @param {Object}   emitResponse         Helpers for observer response objects
 * @param {Function} getPaymentMethodData getPaymentMethodData function
 */
export const usePaymentProcessing = (
	onPaymentProcessing,
	emitResponse,
	getPaymentMethodData
) => {
	useEffect(() => {
		const unsubscribe = onPaymentProcessing(() => {
			const paymentMethodData = getPaymentMethodData();
			if (
				!paymentMethodData ||
				(!paymentMethodData.token &&
					!paymentMethodData.wc_braintree_paypal_payment_nonce)
			) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: getBraintreePayPalServerData().paymentErrorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			}
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData,
				},
			};
		});
		return unsubscribe;
	}, [
		emitResponse.responseTypes.SUCCESS,
		onPaymentProcessing,
		getPaymentMethodData,
		emitResponse.responseTypes.ERROR,
		emitResponse.noticeContexts.PAYMENTS,
	]);
};
