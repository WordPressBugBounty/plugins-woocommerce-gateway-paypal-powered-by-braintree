/**
 * External dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';
import { getBraintreeVenmoServerData } from './utils';
import { BraintreeVenmoExpress } from './components/braintree-venmo-express';

const { supports } = getBraintreeVenmoServerData();

/**
 * Payment method content component
 *
 * @param {Object}                props                  Incoming props for component (including props from Payments API)
 * @param {BraintreeVenmoExpress} props.RenderedComponent Component to render
 */
const BraintreeVenmoComponent = ( { RenderedComponent, ...props } ) => {
	const isEditor = !! select( 'core/editor' );
	// Don't render anything if we're in the editor.
	if ( isEditor ) {
		return null;
	}
	return <RenderedComponent { ...props } />;
};

const braintreeVenmoExpressPaymentMethod = {
	name: PAYMENT_METHOD_ID + '_express',
	canMakePayment: () => true,
	content: (
		<BraintreeVenmoComponent RenderedComponent={ BraintreeVenmoExpress } />
	),
	edit: (
		<BraintreeVenmoComponent RenderedComponent={ BraintreeVenmoExpress } />
	),
	supports: {
		features: supports || [],
	},
};

export default braintreeVenmoExpressPaymentMethod;
