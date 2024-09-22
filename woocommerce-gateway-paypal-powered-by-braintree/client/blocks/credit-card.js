/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import braintreeCreditCardPaymentMethod from './credit-card/index';

// Register Braintree Credit Card payment method.
registerPaymentMethod(braintreeCreditCardPaymentMethod);
