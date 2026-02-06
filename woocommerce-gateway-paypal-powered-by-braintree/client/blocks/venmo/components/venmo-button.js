/**
 * External dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { logData, getBraintreeVenmoServerData } from '../utils';

/**
 * Renders the Venmo Button
 *
 * @param {Object}   props                        Incoming props
 * @param {Function} props.loadVenmoSDK           Function to load Venmo SDK
 * @param {Function} props.tokenizeVenmo          Function to tokenize Venmo payment
 * @param {Function} props.onError                Error handler callback
 * @param {Function} props.setButtonLoaded        Callback when button is loaded
 * @param {boolean}  props.isCheckoutConfirmation Whether in checkout confirmation state
 *
 * @return {JSX.Element} The Venmo button component.
 */
export const VenmoButton = ( {
	loadVenmoSDK,
	tokenizeVenmo,
	onError,
	setButtonLoaded,
	isCheckoutConfirmation = false,
} ) => {
	const [ venmoInstance, setVenmoInstance ] = useState( null );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const mounted = useRef( false );
	const containerId = 'braintree-venmo-button';
	const { pluginUrl } = getBraintreeVenmoServerData();
	const logoUrl = `${ pluginUrl }/assets/images/white_venmo_logo.svg`;

	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		if ( isCheckoutConfirmation ) {
			setButtonLoaded( true );
			return;
		}

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
						onError(
							__(
								'Venmo is not supported in this browser. Please try another payment method.',
								'woocommerce-gateway-paypal-powered-by-braintree'
							)
						);
						setButtonLoaded( true ); // Still mark as loaded to remove loading mask
						return;
					}

					setVenmoInstance( instance );
					setButtonLoaded( true );
				}
			} catch ( error ) {
				logData( 'Error loading Venmo SDK', error );
				if ( mounted.current ) {
					onError(
						__(
							'Venmo is currently unavailable. Please try another payment method.',
							'woocommerce-gateway-paypal-powered-by-braintree'
						)
					);
				}
			}
		};

		initVenmo();
	}, [ loadVenmoSDK, setButtonLoaded, onError, isCheckoutConfirmation ] );

	const handleVenmoClick = async () => {
		if ( ! venmoInstance || isProcessing ) {
			return;
		}

		setIsProcessing( true );
		onError( null ); // Clear any previous errors

		try {
			if ( ! venmoInstance.isBrowserSupported() ) {
				throw new Error(
					__(
						'Venmo is not supported in this browser.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)
				);
			}

			await tokenizeVenmo( venmoInstance );
		} catch ( error ) {
			logData( 'Venmo error', error );
			if (
				error.code === 'VENMO_CANCELED' ||
				error.code === 'VENMO_DESKTOP_CANCELED' ||
				error.code === 'VENMO_APP_CANCELED'
			) {
				// User canceled, silently allow retry
				onError( null );
			} else {
				onError(
					__(
						'Venmo authorization failed. Please try again.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)
				);
			}
		} finally {
			if ( mounted.current ) {
				setIsProcessing( false );
			}
		}
	};

	if ( isCheckoutConfirmation ) {
		return null;
	}

	return (
		<div className="wc-braintree-venmo-button-container">
			<button
				id={ containerId }
				type="button"
				className="wc-braintree-venmo-button"
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
	);
};
