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
import { getBraintreeVenmoServerData } from './venmo/utils';
import braintreeVenmoPaymentMethod from './venmo/index';
import braintreeVenmoExpressPaymentMethod from './venmo/express-checkout';

const { cartCheckoutEnabled } = getBraintreeVenmoServerData();

// Register Braintree Venmo payment method.
registerPaymentMethod( braintreeVenmoPaymentMethod );

// Register Braintree Venmo Express payment method only on cart page and if cart checkout is enabled.
if ( cartCheckoutEnabled ) {
	registerExpressPaymentMethod( braintreeVenmoExpressPaymentMethod );
}
