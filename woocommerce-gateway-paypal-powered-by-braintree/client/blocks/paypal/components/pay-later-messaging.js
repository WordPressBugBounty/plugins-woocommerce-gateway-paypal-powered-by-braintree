/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getBraintreePayPalServerData, logData } from '../utils';

const {
	buttonWidth,
	messageLogoType,
	messageLogoPosition,
	messageTextColor,
	isPayPalPayLaterEnabled,
} = getBraintreePayPalServerData();

/**
 * Renders the PayPal Pay Later messaging.
 *
 * @param {Object} props Incoming props
 * @param {string} props.amount The amount to display in the messaging
 *
 * @return {JSX.Element} The PayPal Pay Later messaging
 */
export const PayPalPayLaterMessaging = ({ amount }) => {
	const containerId = 'wc-braintree-paypal-pay-later-messaging-container';

	useEffect(() => {
		if (!isPayPalPayLaterEnabled) {
			return;
		}
		const container = document.getElementById(containerId);

		if (!container) {
			return;
		}

		if (!paypal.Messages) {
			return (container.style.display = 'none');
		}

		paypal
			.Messages({
				amount,
				style: {
					layout: 'text',
					logo: {
						type: messageLogoType,
						position: messageLogoPosition,
					},
					text: {
						color: messageTextColor,
					},
				},
			})
			.render(`#${containerId}`)
			.catch((error) => {
				logData(
					`Could not render the PayPal Pay Later messeging: ${error.message}`,
					error
				);
			});
	}, [amount]);

	const style = buttonWidth ? { width: `${buttonWidth}px` } : {};
	return <div id={containerId} style={style} />;
};
