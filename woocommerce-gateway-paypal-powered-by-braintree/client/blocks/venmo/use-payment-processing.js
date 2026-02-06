/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Payment processing hook
 *
 * @param {Object} checkoutFormHandler Payment form handler
 * @param {Object} eventRegistration   Event registration object
 * @param {Object} emitResponse        Emit response helpers
 */
export const usePaymentProcessing = (
	checkoutFormHandler,
	eventRegistration,
	emitResponse
) => {
	const { onPaymentSetup } = eventRegistration;
	const { ERROR, SUCCESS } = emitResponse.responseTypes;
	const checkoutFormHandlerRef = useRef( checkoutFormHandler );

	useEffect( () => {
		checkoutFormHandlerRef.current = checkoutFormHandler;
	}, [ checkoutFormHandler ] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			const paymentMethodData =
				checkoutFormHandlerRef.current.getPaymentMethodData();

			// Validate that we have a payment nonce
			if (
				! paymentMethodData ||
				( ! paymentMethodData.isSavedToken &&
					! paymentMethodData.wc_braintree_venmo_payment_nonce )
			) {
				return {
					type: ERROR,
					message: __(
						'Please complete Venmo authorization before placing your order.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					),
				};
			}

			return {
				type: SUCCESS,
				meta: {
					paymentMethodData,
				},
			};
		} );

		return unsubscribe;
	}, [ onPaymentSetup, ERROR, SUCCESS ] );
};
