/**
 * External dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID, EXPRESS_METHOD_NAME } from './constants';
import { getBraintreeApplePayServerData, isApplePayAvailable } from './utils';
import { BraintreeApplePayExpress } from './components/braintree-apple-pay-express';

const { applePayEnabled, supports } = getBraintreeApplePayServerData();

/**
 * Payment method content component wrapper
 */
const BraintreeApplePayComponent = ( { RenderedComponent, ...props } ) => {
	const isEditor = !! select( 'core/editor' );

	// Don't render in editor
	if ( isEditor ) {
		return null;
	}

	return <RenderedComponent { ...props } />;
};

/**
 * Apple Pay Express Payment Method configuration
 */
const braintreeApplePayExpressPaymentMethod = {
	name: EXPRESS_METHOD_NAME,
	paymentMethodId: PAYMENT_METHOD_ID,
	canMakePayment: () => {
		// Check if Apple Pay is enabled and available
		return applePayEnabled && isApplePayAvailable();
	},
	content: (
		<BraintreeApplePayComponent
			RenderedComponent={ BraintreeApplePayExpress }
		/>
	),
	edit: (
		<BraintreeApplePayComponent
			RenderedComponent={ BraintreeApplePayExpress }
		/>
	),
	supports: {
		features: supports || [],
	},
};

export default braintreeApplePayExpressPaymentMethod;
