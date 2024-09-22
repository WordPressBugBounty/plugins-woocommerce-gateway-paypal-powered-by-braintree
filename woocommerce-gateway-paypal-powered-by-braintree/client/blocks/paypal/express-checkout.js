/**
 * External dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';
import { getBraintreePayPalServerData } from './utils';
import { BraintreePayPalExpress } from './components/braintree-paypal-express';

const { supports } = getBraintreePayPalServerData();

/**
 * Payment method content component
 *
 * @param {Object}                 props                   Incoming props for component (including props from Payments API)
 * @param {BraintreePayPalExpress} props.RenderedComponent Component to render
 */
const BraintreePayPalComponent = ({ RenderedComponent, ...props }) => {
	const isEditor = !!select('core/editor');
	// Don't render anything if we're in the editor.
	if (isEditor) {
		return null;
	}
	return <RenderedComponent {...props} />;
};

const braintreePayPalExpressPaymentMethod = {
	name: PAYMENT_METHOD_ID + '_express',
	canMakePayment: () => true,
	content: (
		<BraintreePayPalComponent RenderedComponent={BraintreePayPalExpress} />
	),
	edit: (
		<BraintreePayPalComponent RenderedComponent={BraintreePayPalExpress} />
	),
	supports: {
		features: supports || [],
	},
};

export default braintreePayPalExpressPaymentMethod;
