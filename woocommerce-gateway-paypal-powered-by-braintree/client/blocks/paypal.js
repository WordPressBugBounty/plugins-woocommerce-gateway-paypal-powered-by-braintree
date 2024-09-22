/**
 * External dependencies
 */
import {
	registerPaymentMethod,
	registerExpressPaymentMethod,
} from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { getBraintreePayPalServerData } from './paypal/utils';
import braintreePayPalPaymentMethod from './paypal/index';
import braintreePayPalExpressPaymentMethod from './paypal/express-checkout';

const { cartCheckoutEnabled } = getBraintreePayPalServerData();

// Register Braintree PayPal payment method.
registerPaymentMethod(braintreePayPalPaymentMethod);

// Register Braintree PayPal Express payment method only on cart page and if cart checkout is enabled.
if (cartCheckoutEnabled) {
	// Register Braintree PayPal Express payment method.
	registerExpressPaymentMethod(braintreePayPalExpressPaymentMethod);
}
