/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';
import { getBraintreeCreditCardServerData, getCardIcons } from './utils';
import { BraintreeCreditCard } from './components/credit-card';
import { BraintreeCreditCardSavedToken } from './components/credit-card-saved-token';

const cardIcons = getCardIcons();
const { title, showSavedCards, showSaveOption, supports } =
	getBraintreeCreditCardServerData();

const BraintreeCreditCardLabel = (props) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={title} />;
};

/**
 * Payment method content component
 *
 * @param {Object}              props                   Incoming props for component (including props from Payments API)
 * @param {BraintreeCreditCard} props.RenderedComponent Component to render
 */
const BraintreeCreditCardComponent = ({ RenderedComponent, ...props }) => {
	const isEditor = !!select('core/editor');
	// Don't render anything if we're in the editor.
	if (isEditor) {
		return null;
	}

	return <RenderedComponent {...props} />;
};

const braintreeCreditCardPaymentMethod = {
	name: PAYMENT_METHOD_ID,
	label: <BraintreeCreditCardLabel />,
	ariaLabel: __(
		'Braintree CreditCard Payment Method',
		'woocommerce-gateway-paypal-powered-by-braintree'
	),
	canMakePayment: () => true,
	content: (
		<BraintreeCreditCardComponent RenderedComponent={BraintreeCreditCard} />
	),
	edit: (
		<BraintreeCreditCardComponent RenderedComponent={BraintreeCreditCard} />
	),
	savedTokenComponent: (
		<BraintreeCreditCardComponent
			RenderedComponent={BraintreeCreditCardSavedToken}
		/>
	),
	icons: cardIcons,
	supports: {
		// Use `false` as fallback values in case server provided configuration is missing.
		showSavedCards: showSavedCards || false,
		showSaveOption: showSaveOption || false,
		features: supports || [],
	},
};

export default braintreeCreditCardPaymentMethod;
