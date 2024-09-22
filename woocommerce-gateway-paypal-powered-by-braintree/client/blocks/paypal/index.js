/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { select, dispatch } from '@wordpress/data';
import { addAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_ID } from './constants';
import { getBraintreePayPalServerData } from './utils';
import { BraintreePayPal } from './components/braintree-paypal';
import { BraintreePayPalSavedToken } from './components/braintree-paypal-saved-token';
const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;

const {
	logoUrl,
	title,
	showSavedCards,
	showSaveOption,
	supports,
	isCheckoutConfirmation,
} = getBraintreePayPalServerData();

const BraintreePayPalLabel = () => {
	return <img src={logoUrl} alt={title} />;
};

/**
 * Payment method content component
 *
 * @param {Object}                                  props                   Incoming props for component (including props from Payments API)
 * @param {BraintreePayPal} props.RenderedComponent Component to render
 */
const BraintreePayPalComponent = ({ RenderedComponent, ...props }) => {
	const isEditor = !!select('core/editor');
	// Don't render anything if we're in the editor.
	if (isEditor) {
		return null;
	}
	return <RenderedComponent {...props} />;
};

let features = supports;
if (isCheckoutConfirmation) {
	// Set the payment method as active when the checkout form is rendered.
	addAction(
		'experimental__woocommerce_blocks-checkout-render-checkout-form',
		'woocommerce-gateway-paypal-powered-by-braintree',
		() => {
			dispatch(PAYMENT_STORE_KEY).__internalSetActivePaymentMethod(
				PAYMENT_METHOD_ID
			);
		}
	);

	// Add 'braintree_paypal_checkout_confirmation' feature to the list of features.
	features = [...supports, 'braintree_paypal_checkout_confirmation'];
}

const braintreePayPalPaymentMethod = {
	name: PAYMENT_METHOD_ID,
	label: <BraintreePayPalLabel />,
	ariaLabel: __(
		'Braintree PayPal Payment Method',
		'woocommerce-gateway-paypal-powered-by-braintree'
	),
	canMakePayment: () => true,
	content: <BraintreePayPalComponent RenderedComponent={BraintreePayPal} />,
	edit: <BraintreePayPalComponent RenderedComponent={BraintreePayPal} />,
	savedTokenComponent: (
		<BraintreePayPalComponent
			RenderedComponent={BraintreePayPalSavedToken}
		/>
	),
	supports: {
		// Use `false` as fallback values in case server provided configuration is missing.
		showSavedCards: showSavedCards || false,
		showSaveOption: showSaveOption || false,
		features: features || [],
	},
};

export default braintreePayPalPaymentMethod;
