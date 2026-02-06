/**
 * External dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { getBraintreeVenmoServerData, logData } from '../utils';
import { setPaymentNonceSession } from '../../braintree-utils';
import { usePaymentForm } from '../use-payment-form';

/**
 * Renders the Braintree Venmo Express Checkout button for the Cart page.
 *
 * @param {Object} props Incoming props
 *
 * @return {JSX.Element} The Braintree Venmo express checkout component.
 */
export const BraintreeVenmoExpress = ( props ) => {
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ isLoaded, setIsLoaded ] = useState( false );
	const [ venmoInstance, setVenmoInstance ] = useState( null );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ isSupported, setIsSupported ] = useState( true );
	const mounted = useRef( false );

	const errorNoticeClass = 'wc-block-components-notice-banner is-error';
	const { pluginUrl, cartHandlerUrl, cartHandlerNonce } =
		getBraintreeVenmoServerData();
	const logoUrl = `${ pluginUrl }/assets/images/white_venmo_logo.svg`;

	const {
		components: { LoadingMask },
		billing,
		onSubmit,
		shouldSavePayment,
	} = props;

	const paymentForm = usePaymentForm( {
		billing,
		onSubmit,
		shouldSavePayment,
		token: null,
	} );
	const { loadVenmoSDK, tokenizeVenmoExpress } = paymentForm;

	const containerId = 'braintree-venmo-express-button';

	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		const initVenmo = async () => {
			try {
				const { venmoInstance: instance } = await loadVenmoSDK(
					containerId,
					mounted
				);
				if ( mounted.current ) {
					// Check if Venmo is supported in this browser
					if ( ! instance.isBrowserSupported() ) {
						logData( 'Venmo not supported in this browser' );
						setIsSupported( false );
						return;
					}

					setVenmoInstance( instance );
					setIsLoaded( true );
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Error loading Venmo SDK', error );
			}
		};

		initVenmo();
	}, [ loadVenmoSDK ] );

	const handleVenmoClick = async () => {
		if ( ! venmoInstance || isProcessing ) {
			return;
		}

		setIsProcessing( true );
		setErrorMessage( null );

		try {
			if ( ! venmoInstance.isBrowserSupported() ) {
				throw new Error(
					__(
						'Venmo is not supported in this browser.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)
				);
			}

			// Tokenize the Venmo payment
			const payload = await tokenizeVenmoExpress( venmoInstance );

			if ( payload && payload.nonce ) {
				// Send the nonce to the server via cart handler
				payload.wp_nonce = cartHandlerNonce;
				const result = await setPaymentNonceSession(
					cartHandlerUrl,
					payload
				);

				if ( result && result.redirect_url ) {
					window.location = result.redirect_url;
					return;
				}

				throw new Error(
					__(
						'Failed to process Venmo payment. Please try again.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)
				);
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Venmo express error', error );
			if ( mounted.current ) {
				if (
					error.code === 'VENMO_CANCELED' ||
					error.code === 'VENMO_DESKTOP_CANCELED' ||
					error.code === 'VENMO_APP_CANCELED'
				) {
					// User canceled, silently allow retry
					setErrorMessage( null );
				} else {
					setErrorMessage(
						error.message ||
							__(
								'Venmo authorization failed. Please try again.',
								'woocommerce-gateway-paypal-powered-by-braintree'
							)
					);
				}
			}
		} finally {
			if ( mounted.current ) {
				setIsProcessing( false );
			}
		}
	};

	if ( ! isSupported ) {
		return null;
	}

	return (
		<>
			{ errorMessage && (
				<div className={ errorNoticeClass }>{ errorMessage }</div>
			) }
			{ ! errorMessage && (
				<LoadingMask isLoading={ ! isLoaded }>
					<div className="wc-braintree-venmo-button-container wc-braintree-venmo-express-container">
						<button
							id={ containerId }
							type="button"
							className="wc-braintree-venmo-button wc-braintree-venmo-express-button"
							onClick={ handleVenmoClick }
							disabled={ ! venmoInstance || isProcessing }
						>
							<img
								src={ logoUrl }
								alt={ __(
									'Pay with Venmo',
									'woocommerce-gateway-paypal-powered-by-braintree'
								) }
							/>
						</button>
					</div>
				</LoadingMask>
			) }
		</>
	);
};
