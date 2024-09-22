/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID, PAYMENT_METHOD_NAME } from './constants';
import { getBraintreePayPalServerData, logData } from './utils';
import { getClientToken, setPaymentNonceSession } from '../braintree-utils';

const {
	ajaxUrl,
	clientTokenNonce,
	isTestEnvironment,
	buttonStyles,
	payPalIntent,
	isPayPalPayLaterEnabled,
	isPayPalCardEnabled,
	payPalDisabledFundingOptions,
	tokenizationForced,
	paypalLocale,
	forceBuyerCountry,
	cartPaymentNonce,
	cartHandlerUrl,
	setPaymentMethodNonce,
	paymentErrorMessage,
} = getBraintreePayPalServerData();

/**
 * Payment Form Handler
 *
 * @param {Object}   props                   Incoming props for the handler.
 * @param {Object}   props.billing           Billing data.
 * @param {Object}   props.billing.cartTotal Cart total.
 * @param {Object}   props.billing.currency  Cart currency.
 * @param {Function} props.onSubmit          Function to submit payment form.
 * @param {boolean}  props.shouldSavePayment Whether or not the payment method should be saved.
 * @param {string}   props.token             Saved payment token.
 * @param {boolean}  props.isExpress         Whether or not we're in express checkout.
 * @param {boolean}  props.needsShipping     Whether or not the order needs shipping.
 *
 * @return {Object} An object with properties that interact with the Payment Form.
 */
export const usePaymentForm = ({
	billing: { cartTotal, currency },
	onSubmit,
	shouldSavePayment,
	token = null,
	isExpress = false,
	needsShipping = false,
}) => {
	const [paymentNonce, setPaymentNonce] = useState(cartPaymentNonce || '');
	const [deviceData, setDeviceData] = useState('');
	const [testAmount, setTestAmount] = useState('');

	const currencyCode = currency.code;
	const amount = (cartTotal.value / 10 ** currency.minorUnit).toFixed(2);

	const isSingleUse = useMemo(() => {
		return !shouldSavePayment && !tokenizationForced;
	}, [shouldSavePayment]);

	// If we're in express checkout, use the 'checkout' label.
	if (isExpress) {
		buttonStyles.label = 'checkout';
	}

	const renderPayPalButtons = useCallback(
		(paypalCheckoutInstance, containerId) => {
			const options = {
				env: isTestEnvironment ? 'sandbox' : 'production',
				commit: isExpress ? false : true,
				style: buttonStyles,
				// eslint-disable-next-line no-unused-vars
				onApprove(data, actions) {
					return paypalCheckoutInstance
						.tokenizePayment(data)
						.then(function (payload) {
							logData('Payment tokenized.', payload);
							// If we're in express checkout, send the nonce to the server.
							if (isExpress) {
								// Send the nonce to the server.
								if (payload) {
									payload.wp_nonce = setPaymentMethodNonce;
									return setPaymentNonceSession(
										cartHandlerUrl,
										payload
									)
										.then((res) => {
											if (res && res.redirect_url) {
												window.location =
													res.redirect_url;
												return;
											}
											throw new Error(
												paymentErrorMessage
											);
										})
										.catch((error) => {
											setPaymentNonce('');
											logData(
												`Payment Error: ${error.message}`,
												error
											);
										});
								}
							}
							setPaymentNonce(payload.nonce);
							// Place an Order.
							onSubmit();
						});
				},
				onError(error) {
					setPaymentNonce('');
					logData(`Payment Error: ${error.message}`, error);
				},
			};

			const createOrder = () => {
				return paypalCheckoutInstance.createPayment({
					flow: isSingleUse ? 'checkout' : 'vault',
					intent: isSingleUse ? payPalIntent : 'tokenize',
					amount,
					currency: currencyCode,
					locale: paypalLocale,
					enableShippingAddress: needsShipping,
				});
			};

			if (isSingleUse) {
				options.createOrder = createOrder;
			} else {
				options.createBillingAgreement = createOrder;
			}

			// Restore the button container to its initial state.
			const container = document.getElementById(containerId);
			if (container) {
				container.innerHTML = '';
			}

			// Render the PayPal button.
			if (document.getElementById(containerId)) {
				return paypal.Buttons(options).render(`#${containerId}`);
			}
		},
		[amount, currencyCode, isExpress, isSingleUse, needsShipping, onSubmit]
	);

	/**
	 * PayPal SDK options.
	 */
	const sdkOptions = useMemo(() => {
		const [enabledFunding, disabledFunding] = [
			[],
			payPalDisabledFundingOptions,
		];
		if (!isPayPalCardEnabled) {
			disabledFunding.push('card');
		}

		if (isPayPalPayLaterEnabled) {
			enabledFunding.push('paylater');
		} else {
			disabledFunding.push('paylater');
		}

		const options = {
			components: isPayPalPayLaterEnabled
				? 'buttons,messages'
				: 'buttons',
			currency: currencyCode,
			intent: isSingleUse ? payPalIntent : 'tokenize',
			vault: isSingleUse ? false : true,
			commit: isExpress ? false : true,
		};

		if (enabledFunding.length) {
			options['enable-funding'] = enabledFunding.join(',');
		}

		if (disabledFunding.length) {
			options['disable-funding'] = disabledFunding.join(',');
		}

		if (forceBuyerCountry) {
			options['buyer-country'] = forceBuyerCountry;
		}
		return options;
	}, [currencyCode, isSingleUse, isExpress]);

	const loadPayPalSDK = useCallback(
		async (containerId = '', mounted = {}) => {
			const { braintree } = window;
			const responseObj = {};
			// Get client token.
			const clientToken = await getClientToken(
				ajaxUrl,
				PAYMENT_METHOD_ID,
				clientTokenNonce
			);

			logData('Creating client');
			// Setup Braintree client.
			const clientInstance = await braintree.client.create({
				authorization: clientToken,
			});
			logData('Client ready');

			// Setup Braintree data collector.
			try {
				const dataCollectorInstance =
					await braintree.dataCollector.create({
						client: clientInstance,
					});
				if (dataCollectorInstance && dataCollectorInstance.deviceData) {
					if (mounted.current) {
						setDeviceData(dataCollectorInstance.deviceData);
					}
					responseObj.dataCollectorInstance = dataCollectorInstance;
				}
			} catch (error) {
				logData(error);
			}

			// Load PayPal SDK and Render the PayPal button.
			if (containerId) {
				logData('Creating integration');
				// Setup Braintree PayPal Checkout.
				const paypalCheckoutInstance =
					await braintree.paypalCheckout.create({
						client: clientInstance,
					});

				// Render the PayPal button.
				await paypalCheckoutInstance.loadPayPalSDK(sdkOptions);
				await renderPayPalButtons(paypalCheckoutInstance, containerId);
				responseObj.paypalCheckoutInstance = paypalCheckoutInstance;
				logData('Integration ready');
			}

			return responseObj;
		},
		[renderPayPalButtons, sdkOptions]
	);

	const getPaymentMethodData = useCallback(() => {
		const paymentMethodData = {
			wc_braintree_paypal_payment_nonce: paymentNonce,
			wc_braintree_device_data: deviceData,
		};
		if (!isSingleUse) {
			paymentMethodData[
				`wc-${PAYMENT_METHOD_NAME}-tokenize-payment-method`
			] = true;
		}

		if (token) {
			paymentMethodData[`wc-${PAYMENT_METHOD_NAME}-payment-token`] =
				token;
			paymentMethodData.token = token;
			paymentMethodData.isSavedToken = true;
		}

		if (testAmount) {
			paymentMethodData[`wc-${PAYMENT_METHOD_NAME}-test-amount`] =
				testAmount;
		}

		return paymentMethodData;
	}, [deviceData, isSingleUse, paymentNonce, testAmount, token]);

	return {
		amount,
		testAmount,
		setTestAmount,
		loadPayPalSDK,
		getPaymentMethodData,
	};
};
