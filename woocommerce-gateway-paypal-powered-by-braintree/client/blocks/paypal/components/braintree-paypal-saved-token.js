/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { usePaymentForm } from '../use-payment-form';
import { CheckoutHandler } from '../checkout-handler';

/**
 * Renders the Braintree PayPal saved token component.
 * This component is used when a customer has a saved PayPal account.
 *
 * @param {Object} props Incoming props
 *
 * @return {JSX.Element} The Braintree PayPal saved token component.
 */
export const BraintreePayPalSavedToken = (props) => {
	const { eventRegistration, emitResponse } = props;
	const paymentForm = usePaymentForm(props);
	const { loadPayPalSDK } = paymentForm;
	const mounted = useRef(false);

	useEffect(() => {
		mounted.current = true;
		let collectorInstance;
		async function loadDeviceData() {
			const { dataCollectorInstance } = await loadPayPalSDK('', mounted);
			collectorInstance = dataCollectorInstance;
		}
		loadDeviceData();

		return () => {
			mounted.current = false;
			if (collectorInstance && collectorInstance.teardown) {
				collectorInstance.teardown();
			}
		};
	}, [loadPayPalSDK]);

	return (
		<CheckoutHandler
			checkoutFormHandler={paymentForm}
			eventRegistration={eventRegistration}
			emitResponse={emitResponse}
		/>
	);
};
