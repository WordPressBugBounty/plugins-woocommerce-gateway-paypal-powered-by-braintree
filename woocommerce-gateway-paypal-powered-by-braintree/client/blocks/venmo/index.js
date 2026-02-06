/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';
import { getBraintreeVenmoServerData } from './utils';
import { BraintreeVenmo } from './components/braintree-venmo';
import { BraintreeVenmoSavedToken } from './components/braintree-venmo-saved-token';

const { title, showSavedCards, showSaveOption, supports } =
	getBraintreeVenmoServerData();

const BraintreeVenmoLabel = () => {
	return <span>{ title }</span>;
};

/**
 * Payment method content component
 *
 * @param {Object}          props                   Incoming props for component (including props from Payments API)
 * @param {BraintreeVenmo} props.RenderedComponent Component to render
 */
const BraintreeVenmoComponent = ( { RenderedComponent, ...props } ) => {
	return <RenderedComponent { ...props } />;
};

const braintreeVenmoPaymentMethod = {
	name: PAYMENT_METHOD_ID,
	label: <BraintreeVenmoLabel />,
	ariaLabel: __(
		'Braintree Venmo Payment Method',
		'woocommerce-gateway-paypal-powered-by-braintree'
	),
	canMakePayment: () => true,
	content: <BraintreeVenmoComponent RenderedComponent={ BraintreeVenmo } />,
	edit: <BraintreeVenmoComponent RenderedComponent={ BraintreeVenmo } />,
	savedTokenComponent: (
		<BraintreeVenmoComponent
			RenderedComponent={ BraintreeVenmoSavedToken }
		/>
	),
	supports: {
		showSavedCards: showSavedCards || false,
		showSaveOption: showSaveOption || false,
		features: supports || [],
	},
};

export default braintreeVenmoPaymentMethod;
