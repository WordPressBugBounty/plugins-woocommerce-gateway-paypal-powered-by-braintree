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
 * @param {string} props.token               Token for the saved payment method.
 */
export const CheckoutHandler = ({
	checkoutFormHandler,
	eventRegistration,
	emitResponse,
	token = null,
}) => {
	const {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
	} = eventRegistration;
	const { getPaymentMethodData, hostedFieldsInstance, verify3DSecure } =
		checkoutFormHandler;

	usePaymentProcessing(
		onPaymentProcessing,
		emitResponse,
		getPaymentMethodData,
		verify3DSecure,
		hostedFieldsInstance,
		token
	);

	useAfterProcessingCheckout(
		onCheckoutAfterProcessingWithError,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse
	);

	return null;
};
