/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { ValidatedTextInput } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../constants';
import { getBraintreePayPalServerData } from '../utils';

const { description, isTestEnvironment } = getBraintreePayPalServerData();

/**
 * Render the payment method description.
 *
 * @param {Object}   props               Incoming props
 * @param {string}   props.testAmount    The test amount.
 * @param {Function} props.setTestAmount The test amount setter.
 *
 * @return {JSX.Element} The payment method description.
 */
export const PayPalDescription = ({ testAmount, setTestAmount }) => {
	return (
		<>
			{!!isTestEnvironment && (
				<span>
					{__(
						'TEST MODE ENABLED',
						'woocommerce-gateway-paypal-powered-by-braintree'
					)}
				</span>
			)}
			<p>{decodeEntities(description || '')}</p>
			{!!isTestEnvironment && (
				<>
					<ValidatedTextInput
						id={`wc-${PAYMENT_METHOD_NAME}-test-amount`}
						type="text"
						label={__(
							'Test Amount',
							'woocommerce-gateway-paypal-powered-by-braintree'
						)}
						value={testAmount}
						onChange={setTestAmount}
					/>
					<p
						style={{ fontSize: '10px' }}
						dangerouslySetInnerHTML={{
							__html: sprintf(
								/** translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
								__(
									'Enter a %1$stest amount%2$s to trigger a specific error response, or leave blank to use the order total.',
									'woocommerce-gateway-paypal-powered-by-braintree'
								),
								'<a href="https://developers.braintreepayments.com/reference/general/testing/php#test-amounts">',
								'</a>'
							),
						}}
					/>
				</>
			)}
		</>
	);
};
