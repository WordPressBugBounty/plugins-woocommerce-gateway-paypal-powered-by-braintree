/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { ValidationInputError } from '@woocommerce/blocks-checkout';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../constants';
import { getBraintreeCreditCardServerData } from '../utils';

const { fieldsErrorMessages, cscRequired } = getBraintreeCreditCardServerData();

/**
 * Render the credit card fields.
 *
 * @param {Object} props Incoming props
 */
export const BraintreeCreditCardFields = (props) => {
	const {
		components: { LoadingMask },
		isLoaded,
		hostedFieldsInstance,
		token = null,
	} = props;
	const [error, setError] = useState({
		number: '',
		expirationDate: '',
		cvv: '',
	});

	useEffect(() => {
		if (hostedFieldsInstance) {
			hostedFieldsInstance.on('validityChange', function (event) {
				const field = event.fields[event.emittedBy];
				if (!field.isValid && !field.isPotentiallyValid) {
					setError((prevState) => ({
						...prevState,
						[event.emittedBy]:
							fieldsErrorMessages[
								`card_${event.emittedBy}_invalid`
							] ||
							sprintf(
								/** translators: Placeholders: %s - invalid field name */
								__(
									'%s is invalid',
									'woocommerce-gateway-paypal-powered-by-braintree'
								),
								event.emittedBy
							),
					}));
					field.container.classList.add('has-error');
				} else {
					setError((prevState) => ({
						...prevState,
						[event.emittedBy]: '',
					}));
					field.container.classList.remove('has-error');
				}
			});
		}
	}, [hostedFieldsInstance]);

	return (
		<LoadingMask isLoading={!isLoaded} showSpinner={true}>
			<div className="wc-block-card-elements payment_method_braintree_credit_card">
				{!token && (
					<>
						<div className="wc-block-gateway-container wc-card-number-element">
							<div
								id={`wc-${PAYMENT_METHOD_NAME}-account-number-hosted`}
								className="wc-block-gateway-input empty wc-braintree-hosted-field-card-number"
							/>
							<label
								htmlFor={`wc-${PAYMENT_METHOD_NAME}-account-number-hosted`}
							>
								{__(
									'Card Number',
									'woocommerce-gateway-paypal-powered-by-braintree'
								)}
							</label>
							<ValidationInputError errorMessage={error.number} />
						</div>

						<div className="wc-block-gateway-container wc-card-expiry-element">
							<div
								id={`wc-${PAYMENT_METHOD_NAME}-expiry-hosted`}
								className="wc-block-gateway-input empty wc-braintree-hosted-field-expiry"
							/>
							<label
								htmlFor={`wc-${PAYMENT_METHOD_NAME}-expiry-hosted`}
							>
								{__(
									'Expiration (MMYY)',
									'woocommerce-gateway-paypal-powered-by-braintree'
								)}
							</label>
							<ValidationInputError
								errorMessage={error.expirationDate}
							/>
						</div>
					</>
				)}
				{cscRequired && (
					<div className="wc-block-gateway-container wc-card-cvc-element">
						<div
							id={`wc-${PAYMENT_METHOD_NAME}-csc-hosted${
								token ? '-token' : ''
							}`}
							className="wc-block-gateway-input empty wc-braintree-hosted-field-csc"
						/>
						<label
							htmlFor={`wc-${PAYMENT_METHOD_NAME}-csc-hosted${
								token ? '-token' : ''
							}`}
						>
							{__(
								'Card Security Code',
								'woocommerce-gateway-paypal-powered-by-braintree'
							)}
						</label>
						<ValidationInputError errorMessage={error.cvv} />
					</div>
				)}
			</div>
		</LoadingMask>
	);
};
