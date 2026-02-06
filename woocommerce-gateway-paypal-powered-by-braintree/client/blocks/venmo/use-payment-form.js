/**
 * External dependencies
 */
import {
	useCallback,
	useState,
	useRef,
	useEffect,
	useMemo,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID, PAYMENT_METHOD_NAME } from './constants';
import { getBraintreeVenmoServerData, logData } from './utils';
import { getClientToken } from '../braintree-utils';

const { ajaxUrl, clientTokenNonce, cartPaymentNonce, tokenizationForced } =
	getBraintreeVenmoServerData();

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
 *
 * @return {Object} An object with properties that interact with the Payment Form.
 */
export const usePaymentForm = ( {
	billing: { cartTotal, currency },
	onSubmit,
	shouldSavePayment,
	token = null,
} ) => {
	const [ shouldSubmit, setShouldSubmit ] = useState( false );
	const [ paymentNonce, setPaymentNonce ] = useState(
		cartPaymentNonce || ''
	);
	const paymentNonceRef = useRef( paymentNonce );

	const [ deviceData, setDeviceData ] = useState( '' );

	const amount = ( cartTotal.value / 10 ** currency.minorUnit ).toFixed( 2 );

	const isSingleUse = useMemo( () => {
		return ! shouldSavePayment && ! tokenizationForced;
	}, [ shouldSavePayment, tokenizationForced ] );

	// Update the ref when the payment nonce changes and trigger the onSubmit callback if the payment nonce is set.
	useEffect( () => {
		paymentNonceRef.current = paymentNonce;
		if ( shouldSubmit && paymentNonce ) {
			onSubmit();
		}
	}, [ paymentNonce, onSubmit, shouldSubmit ] );

	const loadVenmoSDK = useCallback(
		async ( containerId = '', mounted = {} ) => {
			const { braintree } = window;
			const responseObj = {};

			// Get client token.
			const clientToken = await getClientToken(
				ajaxUrl,
				PAYMENT_METHOD_ID,
				clientTokenNonce
			);

			logData( 'Creating client' );
			// Setup Braintree client.
			const clientInstance = await braintree.client.create( {
				authorization: clientToken,
			} );
			logData( 'Client ready' );

			// Setup Braintree data collector.
			try {
				const dataCollectorInstance =
					await braintree.dataCollector.create( {
						client: clientInstance,
					} );
				if (
					dataCollectorInstance &&
					dataCollectorInstance.deviceData
				) {
					if ( mounted.current ) {
						setDeviceData( dataCollectorInstance.deviceData );
					}
					responseObj.dataCollectorInstance = dataCollectorInstance;
				}
			} catch ( error ) {
				logData( error );
			}

			// Create Venmo instance if containerId provided.
			if ( containerId ) {
				logData( 'Creating Venmo integration' );
				const venmoInstance = await braintree.venmo.create( {
					client: clientInstance,
					paymentMethodUsage: isSingleUse
						? 'single_use'
						: 'multi_use',
					allowDesktop: true,
				} );

				responseObj.venmoInstance = venmoInstance;
				logData( 'Venmo integration ready' );
			}

			return responseObj;
		},
		[ isSingleUse ]
	);

	const tokenizeVenmo = useCallback( async ( venmoInstance ) => {
		try {
			logData( 'Tokenizing Venmo payment' );
			const payload = await venmoInstance.tokenize();
			logData( 'Payment tokenized.', payload );
			setPaymentNonce( payload.nonce );
			setShouldSubmit( true );
			return payload;
		} catch ( error ) {
			setPaymentNonce( '' );
			logData( `Payment Error: ${ error.message }`, error );
			throw error;
		}
	}, [] );

	const tokenizeVenmoExpress = useCallback( async ( venmoInstance ) => {
		try {
			logData( 'Tokenizing Venmo express payment' );
			const payload = await venmoInstance.tokenize();
			logData( 'Express payment tokenized.', payload );
			// For express checkout, we don't set state - we return the payload
			// so it can be sent to the cart handler
			return payload;
		} catch ( error ) {
			logData( `Express Payment Error: ${ error.message }`, error );
			throw error;
		}
	}, [] );

	const getPaymentMethodData = useCallback( () => {
		const paymentMethodData = {
			wc_braintree_venmo_payment_nonce: paymentNonceRef.current,
			wc_braintree_device_data: deviceData,
		};

		if ( ! isSingleUse ) {
			paymentMethodData[
				`wc-${ PAYMENT_METHOD_NAME }-tokenize-payment-method`
			] = true;
		}

		if ( token ) {
			paymentMethodData[ `wc-${ PAYMENT_METHOD_NAME }-payment-token` ] =
				token;
			paymentMethodData.token = token;
			paymentMethodData.isSavedToken = true;
		}

		return paymentMethodData;
	}, [ deviceData, isSingleUse, token ] );

	return {
		amount,
		loadVenmoSDK,
		tokenizeVenmo,
		tokenizeVenmoExpress,
		getPaymentMethodData,
	};
};
