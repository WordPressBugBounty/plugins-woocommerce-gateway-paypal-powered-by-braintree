/**
 * External dependencies
 */
import {
	useEffect,
	useState,
	useCallback,
	useRef,
	createElement,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	getBraintreeGooglePayServerData,
	getTransactionInfo,
	getUpdatedTotals,
	processPayment,
} from '../utils';
import { PAYMENT_METHOD_ID, EXPRESS_METHOD_NAME } from '../constants';
import { getClientToken } from '../../braintree-utils';

/**
 * Google Pay Express Payment Component
 */
export const BraintreeGooglePayExpress = ( {
	billing,
	shippingData,
	onClick,
	onClose,
	onSubmit,
	eventRegistration,
	components = {},
} ) => {
	const { LoadingMask } = components;
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ googlePayInstance, setGooglePayInstance ] = useState( null );
	const [ googlePaymentsClient, setGooglePaymentsClient ] = useState( null );
	const [ googlePayAvailable, setGooglePayAvailable ] = useState( false );
	const braintreeClientRef = useRef( null );

	const {
		googlePayEnabled,
		buttonStyle,
		merchantName,
		clientTokenNonce,
		tokenizationForced,
		cartContainsSubscription,
		isTestEnvironment,
		ajaxUrl,
		googleMerchantId,
		allowedCardNetworks,
		allowedCountryCodes,
		recalculateTotalsNonce,
		processPaymentNonce,
	} = getBraintreeGooglePayServerData();

	// Initialize Braintree when component mounts
	useEffect( () => {
		if ( ! googlePayEnabled ) {
			setIsLoading( false );
			return;
		}

		if (
			window.braintree &&
			window.braintree.client &&
			window.braintree.googlePayment
		) {
			initializeBraintree();
		} else {
			console.error( 'Braintree SDK not loaded' );
			setError(
				__(
					'Payment scripts not loaded. Please refresh the page.',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			setIsLoading( false );
		}
	}, [ googlePayEnabled ] );

	// Initialize Braintree client and Google Pay.
	const initializeBraintree = useCallback( async () => {
		try {
			// Get client token using shared utility
			const token = await getClientToken(
				ajaxUrl,
				PAYMENT_METHOD_ID,
				clientTokenNonce
			);

			// Create Braintree client
			const client = await window.braintree.client.create( {
				authorization: token,
			} );

			braintreeClientRef.current = client;

			// Create Google Pay instance
			const googlePayInst = await window.braintree.googlePayment.create( {
				client: client,
				googlePayVersion: 2,
				googleMerchantId: googleMerchantId,
			} );

			setGooglePayInstance( googlePayInst );

			let args = {
				environment: isTestEnvironment ? 'TEST' : 'PRODUCTION',
				merchantInfo: {
					merchantName: merchantName,
					merchantId: googleMerchantId,
				},
				paymentDataCallbacks: {
					onPaymentAuthorized: async ( paymentData ) =>
						await onPaymentAuthorized( paymentData ),
				},
			};
			if ( shippingData?.needsShipping ) {
				args.paymentDataCallbacks.onPaymentDataChanged = (
					paymentData
				) => onPaymentDataChanged( paymentData );
			}

			const paymentsClient = new google.payments.api.PaymentsClient(
				args
			);
			setGooglePaymentsClient( paymentsClient );

			const isReadyToPay = await paymentsClient.isReadyToPay( {
				// see https://developers.google.com/pay/api/web/reference/object#IsReadyToPayRequest for all options
				apiVersion: 2,
				apiVersionMinor: 0,
				allowedPaymentMethods:
					googlePayInst.createPaymentDataRequest()
						.allowedPaymentMethods,
				existingPaymentMethodRequired: true,
			} );

			setGooglePayAvailable( isReadyToPay && isReadyToPay.result );

			setIsLoading( false );
		} catch ( err ) {
			console.error( 'Failed to initialize Braintree:', err );
			setError(
				__(
					'Failed to initialize payment method',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			setIsLoading( false );
		}
	}, [] );

	/**
	 * Show Google Pay payment sheet when Google Pay payment button is clicked.
	 */
	const handleGooglePayClick = useCallback( async () => {
		if ( ! googlePayInstance ) {
			console.error( 'Google Pay instance not initialized' );
			return;
		}

		onClick();

		try {
			const paymentDataRequest = await getGooglePaymentDataRequest();
			await googlePaymentsClient.loadPaymentData( paymentDataRequest );
		} catch ( err ) {
			// Only set the error message if the payment was not cancelled (UI was not closed by the customer).
			if ( 'CANCELED' !== err.statusCode ) {
				console.error( 'Google Pay payment failed:', err );
				setError(
					__(
						'Payment failed. Please refresh the page and try again.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)
				);
			}
		} finally {
			onClose();
		}
	}, [
		googlePayInstance,
		onClick,
		onClose,
		onSubmit,
		merchantName,
		shippingData,
		billing,
	] );

	const getGooglePaymentDataRequest = async () => {
		try {
			const transactionInfo = await getTransactionInfo(
				ajaxUrl,
				EXPRESS_METHOD_NAME,
				clientTokenNonce
			);

			const paymentDataRequest = Object.assign(
				{},
				{
					apiVersion: 2,
					apiVersionMinor: 0,
				}
			);
			paymentDataRequest.transactionInfo = transactionInfo;
			paymentDataRequest.merchantInfo = {
				merchantId: googleMerchantId,
				merchantName: merchantName,
			};

			paymentDataRequest.emailRequired = true;
			paymentDataRequest.callbackIntents = [ 'PAYMENT_AUTHORIZATION' ];

			if ( shippingData?.needsShipping ) {
				paymentDataRequest.callbackIntents = [
					'SHIPPING_ADDRESS',
					'SHIPPING_OPTION',
					'PAYMENT_AUTHORIZATION',
				];
				paymentDataRequest.shippingAddressRequired = true;
				paymentDataRequest.shippingAddressParameters = {
					allowedCountryCodes: allowedCountryCodes,
				};
				paymentDataRequest.shippingOptionRequired = true;
			}

			paymentDataRequest.allowedPaymentMethods =
				googlePayInstance.createPaymentDataRequest().allowedPaymentMethods;
			paymentDataRequest.allowedPaymentMethods[ 0 ].parameters.billingAddressRequired = true;
			paymentDataRequest.allowedPaymentMethods[ 0 ].parameters.billingAddressParameters =
				{
					format: 'FULL',
				};

			return paymentDataRequest;
		} catch ( error ) {
			console.error( 'Google Pay payment failed:', error );
			throw error;
		}
	};

	/**
	 * Handles payment authorization callback intent.
	 *
	 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentData|PaymentData object reference}
	 *
	 * @param {object} paymentData response from Google Pay API after a payer approves payment.
	 * @returns Promise<{object}> Promise object to complete or fail the transaction.
	 */
	const onPaymentAuthorized = async ( paymentData ) => {
		try {
			paymentData.source = 'google_pay';
			paymentData.force_tokenization = tokenizationForced;

			const result = await processPayment(
				ajaxUrl,
				EXPRESS_METHOD_NAME,
				processPaymentNonce,
				paymentData
			);
			window.location = result.redirect;

			// By default, the Google Pay request UI remains open in the event of an error.
			// Since we display the error message on the main page (product/cart/checkout), we need to close it manually.
			// To do so, we always return success a success status.
			return { transactionState: 'SUCCESS' };
		} catch ( err ) {
			return {
				transactionState: 'ERROR',
				error: {
					intent: 'PAYMENT_AUTHORIZATION',
					message: err.message || 'Payment could not be processed',
					reason: 'PAYMENT_DATA_INVALID',
				},
			};
		}
	};

	/**
	 * Handles dynamic buy flow shipping address and shipping options callback intents.
	 *
	 * @param {object} intermediatePaymentData response from Google Pay API a shipping address or shipping option is selected in the payment sheet.
	 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#IntermediatePaymentData|IntermediatePaymentData object reference}
	 *
	 * @see {@link https://developers.google.com/pay/api/web/reference/response-objects#PaymentDataRequestUpdate|PaymentDataRequestUpdate}
	 * @returns Promise<{object}> Promise of PaymentDataRequestUpdate object to update the payment sheet.
	 */
	const onPaymentDataChanged = async ( intermediatePaymentData ) => {
		try {
			let shippingAddress = intermediatePaymentData.shippingAddress;
			let shippingOptionData = intermediatePaymentData.shippingOptionData;
			let chosenShippingMethod = '';

			if (
				intermediatePaymentData.callbackTrigger == 'SHIPPING_OPTION'
			) {
				chosenShippingMethod = shippingOptionData.id;
			}

			const paymentDataRequestUpdate = await getUpdatedTotals(
				ajaxUrl,
				EXPRESS_METHOD_NAME,
				recalculateTotalsNonce,
				shippingAddress,
				chosenShippingMethod
			);

			if (
				paymentDataRequestUpdate.newShippingOptionParameters
					.shippingOptions.length == 0
			) {
				return {
					reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
					message: __(
						'Cannot ship to the selected address.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					),
					intent: 'SHIPPING_ADDRESS',
				};
			}

			return paymentDataRequestUpdate;
		} catch ( err ) {
			console.error( 'Google Pay payment failed:', err );
			setError(
				__(
					'Payment failed. Please try again.',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			onClose();
		}
	};

	/**
	 * Renders the Google Pay button.
	 *
	 * The button is rendered using googlePaymentsClient.createButton() which return a RAW DOM element,
	 * so we need to convert it to a JSX element.
	 *
	 * @returns {JSX.Element} The Google Pay button.
	 */
	const ButtonContent = () => {
		if ( googlePayAvailable && googlePaymentsClient ) {
			// Create the Google Pay button
			const buttonElement = googlePaymentsClient.createButton( {
				onClick: ( event ) => handleGooglePayClick( event ),
				buttonColor: buttonStyle,
				buttonSizeMode: 'fill',
			} );

			// Convert DOM element to JSX by creating a React element
			// We'll create a div that contains the button element
			return createElement( 'div', {
				ref: ( el ) => {
					if ( el ) {
						el.innerHTML = '';
						el.appendChild( buttonElement );
					}
				},
			} );
		}
		return null;
	};

	// Don't render if Google Pay is not enabled or available.
	if ( ! googlePayEnabled || ! googlePayAvailable ) {
		return null;
	}

	if ( error ) {
		return (
			<div className="wc-block-components-express-payment__item">
				<div className="wc-block-components-express-payment-google-pay-error">
					{ error }
				</div>
			</div>
		);
	}

	return (
		<div className="wc-block-components-express-payment__item">
			<LoadingMask isLoading={ isLoading } showSpinner={ true }>
				<ButtonContent />
			</LoadingMask>
		</div>
	);
};
