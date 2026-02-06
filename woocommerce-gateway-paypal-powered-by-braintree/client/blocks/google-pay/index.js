/**
 * External dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID, EXPRESS_METHOD_NAME } from './constants';
import { getBraintreeGooglePayServerData } from './utils';
import { BraintreeGooglePayExpress } from './components/braintree-google-pay-express';

const { googlePayEnabled, supports } = getBraintreeGooglePayServerData();

/**
 * Payment method content component wrapper
 */
const BraintreeGooglePayComponent = ( { RenderedComponent, ...props } ) => {
	const isEditor = !! select( 'core/editor' );

	// Don't render in editor
	if ( isEditor ) {
		return null;
	}

	return <RenderedComponent { ...props } />;
};

/**
 * Google Pay Express Payment Method configuration
 */
const braintreeGooglePayExpressPaymentMethod = {
	name: EXPRESS_METHOD_NAME,
	paymentMethodId: PAYMENT_METHOD_ID,
	canMakePayment: () => {
		// Check if Google Pay is enabled and available
		return googlePayEnabled;
	},
	content: (
		<BraintreeGooglePayComponent
			RenderedComponent={ BraintreeGooglePayExpress }
		/>
	),
	edit: (
		<BraintreeGooglePayComponent
			RenderedComponent={ BraintreeGooglePayExpress }
		/>
	),
	supports: {
		features: supports || [],
	},
};

export default braintreeGooglePayExpressPaymentMethod;
