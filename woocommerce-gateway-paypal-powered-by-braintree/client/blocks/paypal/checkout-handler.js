/**
 * Internal dependencies
 */
import { usePaymentProcessing } from './use-payment-processing';
import { useAfterProcessingCheckout } from './use-after-processing-checkout';

/**
 * Handles checkout processing
 *
 * @param {Object} props                     Incoming props
 * @param {Object} props.checkoutFormHandler Checkout form handler
 * @param {Object} props.eventRegistration   Event registration functions.
 * @param {Object} props.emitResponse        Helpers for observer response objects.
 */
export const CheckoutHandler = ({
	checkoutFormHandler,
	eventRegistration,
	emitResponse,
}) => {
	const {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
	} = eventRegistration;
	const { getPaymentMethodData } = checkoutFormHandler;

	usePaymentProcessing(
		onPaymentProcessing,
		emitResponse,
		getPaymentMethodData
	);

	useAfterProcessingCheckout(
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse
	);

	return null;
};
