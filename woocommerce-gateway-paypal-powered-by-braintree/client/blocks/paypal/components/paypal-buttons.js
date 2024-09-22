/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getBraintreePayPalServerData, logData } from '../utils';

const { buttonWidth, integrationErrorMessage } = getBraintreePayPalServerData();

/**
 * Renders the PayPal button.
 *
 * @param {Object}   props                        Incoming props
 * @param {Function} props.loadPayPalSDK          Loads the PayPal SDK
 * @param {Function} props.onError                Handles errors
 * @param {Function} props.buttonLoaded           Sets the button loaded state
 * @param {boolean}  props.isCheckoutConfirmation Whether or not we're on the checkout confirmation page
 *
 * @return {JSX.Element} The PayPal button
 */
export const PayPalButtons = ({
	loadPayPalSDK,
	onError,
	buttonLoaded,
	isCheckoutConfirmation = false,
}) => {
	const mounted = useRef(false);
	const containerId = 'wc-braintree-paypal-button-container';

	useEffect(() => {
		mounted.current = true;
		let checkoutInstance, collectorInstance;
		async function setupIntegration() {
			try {
				const { dataCollectorInstance, paypalCheckoutInstance } =
					await loadPayPalSDK(containerId, mounted);
				if (mounted.current) {
					buttonLoaded(true);
				}
				checkoutInstance = paypalCheckoutInstance;
				collectorInstance = dataCollectorInstance;
			} catch (error) {
				logData(`Integration Error: ${error.message}`, error);
				onError(integrationErrorMessage);
			}
		}
		setupIntegration();

		return () => {
			mounted.current = false;
			buttonLoaded(false);
			if (checkoutInstance) {
				checkoutInstance.teardown();
			}
			if (collectorInstance) {
				collectorInstance.teardown();
			}
		};
	}, [loadPayPalSDK, onError, buttonLoaded]);

	if (isCheckoutConfirmation) {
		return null;
	}

	const style = buttonWidth ? { width: `${buttonWidth}px` } : {};
	return <div id={containerId} style={style}></div>;
};
