/**
 * External dependencies
 */
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import braintreeApplePayExpressPaymentMethod from './apple-pay/index';

// Register Apple Pay as an express payment method
registerExpressPaymentMethod( braintreeApplePayExpressPaymentMethod );
