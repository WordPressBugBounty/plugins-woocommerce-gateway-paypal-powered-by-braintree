/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * After processing checkout hook
 *
 * @param {Function} onCheckoutAfterProcessingWithError   Callback for registering observers for after the checkout has been processed and has an error
 * @param {Function} onCheckoutAfterProcessingWithSuccess Callback for registering observers for after the checkout has been processed and is successful
 * @param {Object}   emitResponse                         Helpers for observer response objects
 */
export const useAfterProcessingCheckout = (
	onCheckoutAfterProcessingWithError,
	onCheckoutAfterProcessingWithSuccess,
	emitResponse
) => {
	useEffect( () => {
		const onCheckoutComplete = ( checkoutResponse ) => {
			let response = { type: emitResponse.responseTypes.SUCCESS };
			const { paymentStatus, paymentDetails } =
				checkoutResponse.processingResponse;

			// Handle server-side payment errors
			if (
				paymentStatus === emitResponse.responseTypes.FAIL &&
				paymentDetails.result === emitResponse.responseTypes.FAIL &&
				paymentDetails.message
			) {
				response = {
					type: emitResponse.responseTypes.FAIL,
					message: paymentDetails.message,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
					retry: true,
				};
			}

			return response;
		};

		const unsubscribeCheckoutCompleteError =
			onCheckoutAfterProcessingWithError( onCheckoutComplete );
		const unsubscribeCheckoutCompleteSuccess =
			onCheckoutAfterProcessingWithSuccess( onCheckoutComplete );

		return () => {
			unsubscribeCheckoutCompleteError();
			unsubscribeCheckoutCompleteSuccess();
		};
	}, [
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.FAIL,
		emitResponse.responseTypes.SUCCESS,
	] );
};
