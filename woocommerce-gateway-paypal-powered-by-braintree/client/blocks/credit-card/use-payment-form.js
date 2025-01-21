/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID, PAYMENT_METHOD_NAME } from './constants';
import {
	getBraintreeCreditCardServerData,
	attachEventsOnHostedFields,
	getHostedFieldsOptions,
	logData,
} from './utils';
import { getClientToken } from '../braintree-utils';

const {
	ajaxUrl,
	clientTokenNonce,
	tokenizationForced,
	threeds,
	isAdvancedFraudTool,
	cartContainsSubscription,
	orderTotal3DSecure,
	cscRequired,
} = getBraintreeCreditCardServerData();

/**
 * Payment Form Handler
 *
 * @param {Object}  props                              Incoming props for the handler.
 * @param {Object}  props.billing                      Billing data.
 * @param {Object}  props.shippingData                 Shipping data.
 * @param {Object}  props.shippingData.shippingAddress Shipping address.
 * @param {boolean} props.shouldSavePayment            Whether or not the payment method should be saved.
 * @param {string}  props.token                        Saved payment token.
 *
 * @return {Object} An object with properties that interact with the Payment Form.
 */
export const usePaymentForm = ({
	billing,
	shippingData: { shippingAddress },
	shouldSavePayment,
	token = null,
}) => {
	const { cartTotal, currency } = billing;
	const amount = (cartTotal.value / 10 ** currency.minorUnit).toFixed(2);

	const [testAmount, setTestAmount] = useState('');
	const [deviceData, setDeviceData] = useState('');
	const [hostedFieldsInstance, setHostedFieldsInstance] = useState(null);
	const [threeDSecureInstance, setThreeDSecureInstance] = useState(null);

	const isSingleUse = useMemo(() => {
		return !shouldSavePayment && !tokenizationForced;
	}, [shouldSavePayment]);
	const usingToken = !!token;

	/**
	 * Setup Braintree integration.
	 */
	const setupIntegration = useCallback(async () => {
		const { braintree } = window;
		let dataCollectorInstance, threedsInstance, braintreeHostedFields;
		const is3DSecureEnabled =
			threeds && threeds.enabled && braintree && braintree.threeDSecure;

		const clientToken = await getClientToken(
			ajaxUrl,
			PAYMENT_METHOD_ID,
			clientTokenNonce
		);

		logData('Creating client');
		// Setup Braintree client.
		const client = await braintree.client.create({
			authorization: clientToken,
		});
		logData('Client Ready');

		// Setup Braintree hosted fields.
		if (!(usingToken && !cscRequired)) {
			logData('Creating integration');
			braintreeHostedFields = await braintree.hostedFields.create({
				...getHostedFieldsOptions(usingToken),
				client,
			});
			setHostedFieldsInstance(braintreeHostedFields);
			attachEventsOnHostedFields(braintreeHostedFields);
			logData('Integration ready');
		}

		// Setup Device Data
		if (isAdvancedFraudTool && braintree && braintree.dataCollector) {
			dataCollectorInstance = await braintree.dataCollector.create({
				client,
			});
			if (dataCollectorInstance && dataCollectorInstance.deviceData) {
				setDeviceData(dataCollectorInstance.deviceData);
			}
		}

		// Setup Braintree 3DS.
		if (is3DSecureEnabled) {
			// Determine this on an account level only if it's not specifically disabled
			const isEnabled =
				client.getConfiguration().gatewayConfiguration
					.threeDSecureEnabled;

			if (isEnabled) {
				threedsInstance = await braintree.threeDSecure.create({
					version: 2,
					client,
				});
				setThreeDSecureInstance(threedsInstance);
			}
		}

		return {
			hostedFields: braintreeHostedFields,
			dataCollector: dataCollectorInstance,
			threeDSecure: threedsInstance,
		};
	}, [usingToken]);

	const verificationDetails = useMemo(() => {
		const verificationData = {
			amount: amount.toString(),
			email: billing.billingData.email || '',
			challengeRequested: true, // Always request 3DS challenge, if possible. see https://github.com/woocommerce/woocommerce-gateway-paypal-powered-by-braintree/issues/637
			billingAddress: {
				givenName: billing.billingData.first_name || '',
				surname: billing.billingData.last_name || '',
				phoneNumber: billing.billingData.phone || '',
				streetAddress: billing.billingData.address_1 || '',
				extendedAddress: billing.billingData.address_2 || '',
				locality: billing.billingData.city || '',
				region:
					billing.billingData.state.length <= 2
						? billing.billingData.state
						: '',
				postalCode: billing.billingData.postcode || '',
				countryCodeAlpha2:
					billing.billingData.country.length <= 2
						? billing.billingData.country
						: '',
			},
			additionalInformation: {
				shippingGivenName: shippingAddress.first_name || '',
				shippingSurname: shippingAddress.last_name || '',
				shippingPhone: shippingAddress.phone || '',
				shippingAddress: {
					streetAddress: shippingAddress.address_1 || '',
					extendedAddress: shippingAddress.address_2 || '',
					locality: shippingAddress.city || '',
					region:
						shippingAddress.state.length <= 2
							? shippingAddress.state
							: '',
					postalCode: shippingAddress.postcode || '',
					countryCodeAlpha2:
						shippingAddress.country.length <= 2
							? shippingAddress.country
							: '',
				},
			},
		};

		// If the order total is 0 (eg: trial), we need to set the recurring amount to the order total.
		// TODO: Check further on this and make sure braintree not support 0 amount 3DS verification.
		if (cartContainsSubscription && amount === '0.00' && orderTotal3DSecure) {
			verificationData.amount = orderTotal3DSecure.toFixed(2);
		}

		return verificationData;
	}, [amount, billing.billingData, shippingAddress]);

	/**
	 * Verifies 3D Secure.
	 *
	 * @param {string} nonce The nonce to verify.
	 * @param {string} bin   The card bin.
	 *
	 * @return {Promise} Promise that resolves with the verification result.
	 */
	const verify3DSecure = useCallback(
		(nonce, bin) => {
			logData('Verifying 3D Secure.', verificationDetails);
			return threeDSecureInstance.verifyCard({
				...verificationDetails,
				nonce,
				bin,
				onLookupComplete: (data, next) => {
					logData('3DS lookup complete', data);
					next();
				},
			});
		},
		[threeDSecureInstance, verificationDetails]
	);

	const getPaymentMethodData = useCallback(() => {
		const paymentMethodData = {
			wc_braintree_device_data: deviceData,
		};
		if (!isSingleUse) {
			paymentMethodData[
				`wc-${PAYMENT_METHOD_NAME}-tokenize-payment-method`
			] = true;
		}

		if (testAmount) {
			paymentMethodData[`wc-${PAYMENT_METHOD_NAME}-test-amount`] =
				testAmount;
		}

		return paymentMethodData;
	}, [deviceData, isSingleUse, testAmount]);

	return {
		testAmount,
		setTestAmount,
		setupIntegration,
		getPaymentMethodData,
		verify3DSecure,
		hostedFieldsInstance,
		threeDSecureInstance,
	};
};
