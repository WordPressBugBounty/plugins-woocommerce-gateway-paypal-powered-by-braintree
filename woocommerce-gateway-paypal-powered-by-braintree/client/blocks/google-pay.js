/**
 * External dependencies
 */
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import braintreeGooglePayExpressPaymentMethod from './google-pay/index';

// Register Google Pay as an express payment method
registerExpressPaymentMethod( braintreeGooglePayExpressPaymentMethod );
