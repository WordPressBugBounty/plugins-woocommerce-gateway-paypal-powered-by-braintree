/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { usePaymentForm } from '../use-payment-form';
import {
	getBraintreeCreditCardServerData,
	logData,
	getCardIcons,
} from '../utils';
import { BraintreeCreditCardFields } from './credit-card-fields';
import { CheckoutHandler } from '../checkout-handler';
import { BraintreeDescription } from './description';

const { integrationErrorMessage } = getBraintreeCreditCardServerData();
const cardIcons = getCardIcons();
const isBlockTheme = getSetting( 'isBlockTheme' );
const errorNoticeClass = isBlockTheme ? 'wc-block-components-notice-banner is-error' : 'woocommerce-error';

/**
 * BrainTree Credit Card component.
 *
 * @param {Object} props Incoming props
 */
export const BraintreeCreditCard = (props) => {
	const { emitResponse, eventRegistration } = props;
	const { PaymentMethodIcons } = props.components;
	const paymentForm = usePaymentForm(props);
	const {
		setupIntegration,
		hostedFieldsInstance,
		testAmount,
		setTestAmount,
	} = paymentForm;
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
		return <div className={ errorNoticeClass }>{errorMessage}</div>;;
	}

	return (
		<>
			<BraintreeDescription
				testAmount={testAmount}
				setTestAmount={setTestAmount}
			/>
			<BraintreeCreditCardFields
				{...props}
				isLoaded={isLoaded}
				hostedFieldsInstance={hostedFieldsInstance}
			/>
			{!!PaymentMethodIcons && !!cardIcons.length && (
				<PaymentMethodIcons icons={cardIcons} align="left" />
			)}
			<CheckoutHandler
				checkoutFormHandler={paymentForm}
				eventRegistration={eventRegistration}
				emitResponse={emitResponse}
			/>
		</>
	);
};
