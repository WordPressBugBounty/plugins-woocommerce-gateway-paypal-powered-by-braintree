/**
 * Internal dependencies
 */
import { usePaymentProcessing } from './use-payment-processing';
import { useAfterProcessingCheckout } from './use-after-processing-checkout';

/**
 * Checkout event handler for Venmo payment.
 *
 * @param {Object} props                     Incoming props
 * @param {Object} props.checkoutFormHandler Payment form handler
 * @param {Object} props.eventRegistration   Event registration object
 * @param {Object} props.emitResponse        Emit response helpers
 *
 * @return {null} This component doesn't render anything.
 */
export const CheckoutHandler = ( {
	checkoutFormHandler,
	eventRegistration,
	emitResponse,
} ) => {
	const {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
	} = eventRegistration;

	usePaymentProcessing(
		checkoutFormHandler,
		eventRegistration,
		emitResponse
	);
	useAfterProcessingCheckout(
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse
	);

	return null;
};
