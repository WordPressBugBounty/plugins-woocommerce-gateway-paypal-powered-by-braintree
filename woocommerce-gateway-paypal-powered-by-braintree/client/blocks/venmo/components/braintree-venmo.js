/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from '../constants';
import { VenmoButton } from './venmo-button';
import { VenmoDescription } from './description';
import { usePaymentForm } from '../use-payment-form';
import { CheckoutHandler } from '../checkout-handler';
import { getBraintreeVenmoServerData } from '../utils';

/**
 * Renders the Braintree Venmo Button.
 *
 * @param {Object} props Incoming props
 *
 * @return {JSX.Element} The Braintree Venmo component.
 */
export const BraintreeVenmo = ( props ) => {
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ isLoaded, setIsLoaded ] = useState( false );

	const { isCheckoutConfirmation, checkoutConfirmationDescription } =
		getBraintreeVenmoServerData();

	const errorNoticeClass = 'wc-block-components-notice-banner is-error';

	const {
		eventRegistration,
		emitResponse,
		activePaymentMethod,
		components: { LoadingMask },
		billing,
		onSubmit,
		shouldSavePayment,
		token,
	} = props;

	const paymentForm = usePaymentForm( {
		billing,
		onSubmit,
		shouldSavePayment,
		token,
	} );
	const { loadVenmoSDK, tokenizeVenmo } = paymentForm;

	// Disable the place order button when Venmo is active.
	useEffect( () => {
		if ( isCheckoutConfirmation ) {
			return;
		}

		const button = document.querySelector(
			'button.wc-block-components-checkout-place-order-button'
		);
		if ( button ) {
			if ( activePaymentMethod === PAYMENT_METHOD_ID ) {
				button.disabled = true;
			}
			return () => {
				button.disabled = false;
			};
		}
	}, [ activePaymentMethod ] );

	return (
		<>
			{ ! isCheckoutConfirmation && <VenmoDescription /> }
			{ isCheckoutConfirmation && checkoutConfirmationDescription && (
				<div className="braintree-venmo-checkout-confirmation-description">
					{ checkoutConfirmationDescription }
				</div>
			) }
			{ errorMessage && (
				<div className={ errorNoticeClass }>{ errorMessage }</div>
			) }
			{ ! errorMessage && (
				<LoadingMask isLoading={ ! isLoaded } showSpinner={ true }>
					<VenmoButton
						loadVenmoSDK={ loadVenmoSDK }
						tokenizeVenmo={ tokenizeVenmo }
						onError={ setErrorMessage }
						setButtonLoaded={ setIsLoaded }
						isCheckoutConfirmation={ isCheckoutConfirmation }
					/>
				</LoadingMask>
			) }
			<CheckoutHandler
				checkoutFormHandler={ paymentForm }
				eventRegistration={ eventRegistration }
				emitResponse={ emitResponse }
			/>
		</>
	);
};
