/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { usePaymentForm } from '../use-payment-form';
import { BraintreeCreditCardFields } from './credit-card-fields';
import { CheckoutHandler } from '../checkout-handler';
import { getBraintreeCreditCardServerData, logData } from '../utils';

const isBlockTheme = getSetting( 'isBlockTheme' );
const errorNoticeClass = isBlockTheme ? 'wc-block-components-notice-banner is-error' : 'woocommerce-error';
const { cscRequired, integrationErrorMessage } =
	getBraintreeCreditCardServerData();

/**
 * BrainTree Saved Credit Card component.
 *
 * @param {Object} props Incoming props
 */
export const BraintreeCreditCardSavedToken = (props) => {
	const { emitResponse, eventRegistration, token } = props;

	const paymentForm = usePaymentForm(props);
	const { setupIntegration, hostedFieldsInstance } = paymentForm;
	const [errorMessage, setErrorMessage] = useState('');
	const [isLoaded, setIsLoaded] = useState(false);

	useEffect(() => {
		let hostedFieldsObj, dataCollectorObj, threeDSecureObj;
		async function integrate() {
			try {
				const { hostedFields, dataCollector, threeDSecure } =
					await setupIntegration();
				[hostedFieldsObj, dataCollectorObj, threeDSecureObj] = [
					hostedFields,
					dataCollector,
					threeDSecure,
				];
				setIsLoaded(true);
			} catch (e) {
				logData(`Integration Error: ${e.message}`, e);
				setErrorMessage(integrationErrorMessage);
			}
		}
		integrate();
		return () => {
			setIsLoaded(false);
			if (hostedFieldsObj) {
				hostedFieldsObj.teardown();
			}
			if (dataCollectorObj) {
				dataCollectorObj.teardown();
			}
			if (threeDSecureObj) {
				threeDSecureObj.teardown();
			}
		};
	}, [setupIntegration]);

	if (errorMessage) {
		return <div className={ errorNoticeClass }>{errorMessage}</div>;
	}

	return (
		<div className="wc-braintree-hosted-fields-saved-token is-small">
			{cscRequired && (
				<p className="wc-block-components-checkout-step__description">
					{__(
						'Card Security Code is required to make payments using saved cards',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)}
				</p>
			)}
			<BraintreeCreditCardFields
				{...props}
				token={token}
				isLoaded={isLoaded}
				hostedFieldsInstance={hostedFieldsInstance}
			/>
			<CheckoutHandler
				checkoutFormHandler={paymentForm}
				eventRegistration={eventRegistration}
				emitResponse={emitResponse}
				token={token}
			/>
		</div>
	);
};
