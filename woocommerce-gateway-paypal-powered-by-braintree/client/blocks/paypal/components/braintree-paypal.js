/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { select, dispatch } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from '../constants';
import { PayPalButtons } from './paypal-buttons';
import { PayPalPayLaterMessaging } from './pay-later-messaging';
import { PayPalDescription } from './description';
import { usePaymentForm } from '../use-payment-form';
import { CheckoutHandler } from '../checkout-handler';
import { getBraintreePayPalServerData } from '../utils';

const { isCheckoutConfirmation, payPalCustomerDetails } =
	getBraintreePayPalServerData();

const isBlockTheme = getSetting( 'isBlockTheme' );
const errorNoticeClass = isBlockTheme ? 'wc-block-components-notice-banner is-error' : 'woocommerce-error';

const mergeAddress = (address, address2) => {
	if (!address2) {
		return address;
	}

	Object.keys(address2).forEach((key) => {
		if (address2[key] === '') {
			delete address2[key];
		}
	});

	return {
		...address,
		...address2,
	};
};

/**
 * Renders the Braintree PayPal Button and PayLater Messaging.
 *
 * @param {Object} props Incoming props
 *
 * @return {JSX.Element} The Braintree PayPal saved token component.
 */
export const BraintreePayPal = (props) => {
	const [errorMessage, setErrorMessage] = useState(null);
	const [isLoaded, setIsLoaded] = useState(false);

	const {
		eventRegistration,
		emitResponse,
		activePaymentMethod,
		components: { LoadingMask },
		billing,
		onSubmit,
		shouldSavePayment,
		token,
		shippingData,
	} = props;

	const paymentForm = usePaymentForm({
		billing,
		onSubmit,
		shouldSavePayment,
		token,
		isExpress: false,
		needsShipping: shippingData.needsShipping,
	});
	const { loadPayPalSDK, testAmount, setTestAmount, amount } = paymentForm;

	// Disable the place order button when PayPal is active. TODO: find a better way to do this.
	useEffect(() => {
		if (isCheckoutConfirmation) {
			return;
		}

		const button = document.querySelector(
			'button.wc-block-components-checkout-place-order-button'
		);
		if (button) {
			if (activePaymentMethod === PAYMENT_METHOD_ID) {
				button.setAttribute('disabled', 'disabled');
			}
			return () => {
				button.removeAttribute('disabled');
			};
		}
	}, [activePaymentMethod]);

	useEffect(() => {
		// Fill the form if in checkout confirmation
		if (
			!isCheckoutConfirmation ||
			!payPalCustomerDetails ||
			window.wcBraintreePayPalAddressFilled
		) {
			return;
		}

		try {
			const { billing: billingAddress, shipping: shippingAddress } =
				payPalCustomerDetails;
			const wcAddresses = select('wc/store/cart').getCustomerData();
			const addresses = {};
			addresses.billing = mergeAddress(
				wcAddresses.billingAddress,
				billingAddress
			);
			addresses.shipping = mergeAddress(
				wcAddresses.shippingAddress,
				shippingAddress
			);

			if (addresses.billing) {
				dispatch('wc/store/cart').setBillingAddress(addresses.billing);
			}
			if (shippingData.needsShipping && addresses.shipping) {
				dispatch('wc/store/cart').setShippingAddress(
					addresses.shipping
				);
			}
		} catch (err) {
			// Sometimes the PayPal address is missing, skip in this case.
			// eslint-disable-next-line no-console
			console.error(err);
		}
		// This useEffect should run only once, but adding this in case of some kind of full re-rendering
		window.wcBraintreePayPalAddressFilled = true;
	}, []);

	return (
		<>
			{!isCheckoutConfirmation && (
				<PayPalDescription
					testAmount={testAmount}
					setTestAmount={setTestAmount}
				/>
			)}
			{errorMessage && (
				<div className={ errorNoticeClass }>{ errorMessage }</div>
			)}
			{!errorMessage && (
				<LoadingMask isLoading={!isLoaded} showSpinner={true}>
					{isLoaded && !isCheckoutConfirmation && (
						<PayPalPayLaterMessaging amount={amount} />
					)}
					<PayPalButtons
						loadPayPalSDK={loadPayPalSDK}
						onError={setErrorMessage}
						buttonLoaded={setIsLoaded}
						isCheckoutConfirmation={isCheckoutConfirmation}
					/>
				</LoadingMask>
			)}
			<CheckoutHandler
				checkoutFormHandler={paymentForm}
				eventRegistration={eventRegistration}
				emitResponse={emitResponse}
			/>
		</>
	);
};
