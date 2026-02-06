/**
 * External dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	getBraintreeApplePayServerData,
	isApplePayAvailable,
	convertApplePayContactToAddress,
	formatCurrencyAmount,
} from '../utils';
import { PAYMENT_METHOD_ID } from '../constants';
import { getClientToken } from '../../braintree-utils';

/**
 * Apple Pay Express Payment Component
 */
export const BraintreeApplePayExpress = ( {
	billing,
	shippingData,
	onClick,
	onClose,
	onSubmit,
	eventRegistration,
	components = {},
} ) => {
	const { LoadingMask } = components;
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ applePayInstance, setApplePayInstance ] = useState( null );
	const [ paymentNonce, setPaymentNonce ] = useState( null );
	const [ billingContact, setBillingContact ] = useState( null );
	const [ shippingContact, setShippingContact ] = useState( null );
	const braintreeClientRef = useRef( null );
	const containerRef = useRef( null );
	const paymentNonceRef = useRef( null );
	const billingContactRef = useRef( null );
	const shippingContactRef = useRef( null );

	const {
		applePayEnabled,
		buttonStyle,
		merchantName,
		clientTokenNonce,
		tokenizationForced,
		cartContainsSubscription,
		ajaxUrl,
		recalculateTotalsNonce,
	} = getBraintreeApplePayServerData();

	// Initialize Braintree when component mounts
	useEffect( () => {
		if ( ! applePayEnabled || ! isApplePayAvailable() ) {
			setIsLoading( false );
			return;
		}

		if (
			window.braintree &&
			window.braintree.client &&
			window.braintree.applePay
		) {
			initializeBraintree();
		} else {
			console.error( 'Braintree SDK not loaded' );
			setError(
				__(
					'Payment scripts not loaded. Please refresh the page.',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			setIsLoading( false );
		}
	}, [ applePayEnabled ] );

	// Initialize Braintree client and Apple Pay.
	const initializeBraintree = useCallback( async () => {
		try {
			// Get client token using shared utility
			const token = await getClientToken(
				ajaxUrl,
				PAYMENT_METHOD_ID,
				clientTokenNonce
			);

			// Create Braintree client
			const client = await window.braintree.client.create( {
				authorization: token,
			} );

			braintreeClientRef.current = client;

			// Create Apple Pay instance
			const applePayInst = await window.braintree.applePay.create( {
				client: client,
			} );

			setApplePayInstance( applePayInst );
			setIsLoading( false );
		} catch ( err ) {
			console.error( 'Failed to initialize Braintree:', err );
			setError(
				__(
					'Failed to initialize payment method',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			setIsLoading( false );
		}
	}, [ ajaxUrl, clientTokenNonce ] );

	const recalculateTotals = useCallback(
		async ( data = {} ) => {
			const formData = new FormData();
			formData.append(
				'action',
				`wc_${ PAYMENT_METHOD_ID }_apple_pay_recalculate_totals`
			);
			formData.append( 'nonce', recalculateTotalsNonce );

			if ( data.contact ) {
				formData.append(
					'contact[administrativeArea]',
					data.contact.administrativeArea || ''
				);
				formData.append(
					'contact[countryCode]',
					data.contact.countryCode || ''
				);
				formData.append(
					'contact[locality]',
					data.contact.locality || ''
				);
				formData.append(
					'contact[postalCode]',
					data.contact.postalCode || ''
				);
			}

			if ( data.method ) {
				formData.append( 'method', data.method );
			}

			try {
				const response = await fetch( ajaxUrl, {
					method: 'POST',
					body: formData,
				} );

				const result = await response.json();
				return result;
			} catch ( error ) {
				return { success: false, data: { message: error.message } };
			}
		},
		[ ajaxUrl, recalculateTotalsNonce ]
	);

	// Handle Apple Pay button click
	const handleApplePayClick = useCallback( async () => {
		if ( ! applePayInstance ) {
			console.error( 'Apple Pay instance not initialized' );
			return;
		}

		onClick();

		try {
			// Get payment request from cart/checkout data
			const paymentRequest = getPaymentRequest();

			// Create Braintree payment request
			const braintreePaymentRequest =
				applePayInstance.createPaymentRequest( paymentRequest );

			// Apple Pay SDK version
			const APPLE_PAY_VERSION = 3;

			// Create Apple Pay session
			const session = new ApplePaySession(
				APPLE_PAY_VERSION,
				braintreePaymentRequest
			);

			// Handle merchant validation
			session.onvalidatemerchant = async ( event ) => {
				try {
					const merchantSession =
						await applePayInstance.performValidation( {
							validationURL: event.validationURL,
							displayName: merchantName,
						} );
					session.completeMerchantValidation( merchantSession );
				} catch ( err ) {
					console.error( 'Merchant validation failed:', err );
					session.abort();
				}
			};

			// Handle shipping contact selection
			if ( shippingData?.needsShipping ) {
				session.onshippingcontactselected = async ( event ) => {
					try {
						const contact = event.shippingContact;
						const response = await recalculateTotals( {
							contact: {
								administrativeArea:
									contact.administrativeArea || '',
								countryCode: contact.countryCode || '',
								locality: contact.locality || '',
								postalCode: contact.postalCode || '',
							},
						} );

						if ( response.success ) {
							const update = {};
							if ( response.data.total ) {
								update.newTotal = response.data.total;
							}
							if ( response.data.line_items ) {
								update.newLineItems = response.data.line_items;
							}

							// Check if shipping methods are available
							if (
								response.data.shipping_methods &&
								response.data.shipping_methods.length === 0
							) {
								const errors = [
									new ApplePayError(
										'shippingContactInvalid',
										'country',
										__(
											'Shipping is not available to this location',
											'woocommerce-gateway-paypal-powered-by-braintree'
										)
									),
								];
								session.completeShippingContactSelection( {
									...update,
									errors,
								} );
								return;
							}

							if (
								response.data.shipping_methods &&
								response.data.shipping_methods.length > 0
							) {
								update.newShippingMethods =
									response.data.shipping_methods;
							}
							session.completeShippingContactSelection( update );
						} else {
							setError(
								__(
									'Something went wrong calculating shipping cost. Please refresh the page and try again.',
									'woocommerce-gateway-paypal-powered-by-braintree'
								)
							);
							session.abort();
						}
					} catch ( err ) {
						setError(
							__(
								'Something went wrong calculating shipping cost. Please refresh the page and try again.',
								'woocommerce-gateway-paypal-powered-by-braintree'
							)
						);
						session.abort();
					}
				};

				// Handle shipping method selection
				session.onshippingmethodselected = async ( event ) => {
					try {
						const response = await recalculateTotals( {
							method: event.shippingMethod.identifier,
						} );

						if ( response.success ) {
							const update = {};
							if ( response.data.total ) {
								update.newTotal = response.data.total;
							}
							if ( response.data.line_items ) {
								update.newLineItems = response.data.line_items;
							}
							session.completeShippingMethodSelection( update );
						} else {
							setError(
								__(
									'Something went wrong calculating shipping cost. Please refresh the page and try again.',
									'woocommerce-gateway-paypal-powered-by-braintree'
								)
							);
							session.abort();
						}
					} catch ( err ) {
						setError(
							__(
								'Something went wrong calculating shipping cost. Please refresh the page and try again.',
								'woocommerce-gateway-paypal-powered-by-braintree'
							)
						);
						session.abort();
					}
				};
			}

			// Handle payment authorization
			session.onpaymentauthorized = async ( event ) => {
				try {
					const payload = await applePayInstance.tokenize( {
						token: event.payment.token,
					} );

					// Store the payment data for processing
					setPaymentNonce( payload.nonce );
					setBillingContact( event.payment.billingContact );
					setShippingContact( event.payment.shippingContact );

					// Immediately update refs for payment processing
					paymentNonceRef.current = payload.nonce;
					billingContactRef.current = event.payment.billingContact;
					shippingContactRef.current = event.payment.shippingContact;

					// Complete the session
					session.completePayment( ApplePaySession.STATUS_SUCCESS );

					// Trigger checkout submission
					onSubmit();
				} catch ( err ) {
					console.error( 'Payment authorization failed:', err );
					session.completePayment( ApplePaySession.STATUS_FAILURE );
				}
			};

			// Handle cancellation
			session.oncancel = () => {
				onClose();
			};

			// Begin the session
			session.begin();
		} catch ( err ) {
			console.error( 'Apple Pay payment failed:', err );
			setError(
				__(
					'Payment failed. Please try again.',
					'woocommerce-gateway-paypal-powered-by-braintree'
				)
			);
			onClose();
		}
	}, [
		applePayInstance,
		onClick,
		onClose,
		onSubmit,
		merchantName,
		shippingData,
		billing,
	] );

	// Initialize refs with current values
	useEffect( () => {
		paymentNonceRef.current = paymentNonce;
		billingContactRef.current = billingContact;
		shippingContactRef.current = shippingContact;
	}, [ paymentNonce, billingContact, shippingContact ] );

	// Register payment processing handler only once
	useEffect( () => {
		const { onPaymentProcessing } = eventRegistration || {};

		if ( ! onPaymentProcessing ) {
			return;
		}
		const unsubscribe = onPaymentProcessing( async () => {
			if ( ! paymentNonceRef.current ) {
				return {
					type: 'error',
					message: __(
						'Payment not authorized. Please try again.',
						'woocommerce-gateway-paypal-powered-by-braintree'
					),
					messageContext: 'wc/checkout/payments',
				};
			}

			// Convert Apple Pay contacts to WooCommerce address format
			const billingAddress = billingContactRef.current
				? convertApplePayContactToAddress( billingContactRef.current )
				: {};
			const shippingAddress = shippingContactRef.current
				? convertApplePayContactToAddress( shippingContactRef.current )
				: {};

			// Apple Pay provides email only in shipping contact, copy it to billing
			if ( shippingAddress.email && ! billingAddress.email ) {
				billingAddress.email = shippingAddress.email;
			}

			// Prepare payment method data
			const paymentMethodData = {
				wc_braintree_credit_card_payment_nonce: paymentNonceRef.current,
				wc_braintree_apple_pay: '1',
				'wc-braintree_credit_card-new-payment-method':
					tokenizationForced || cartContainsSubscription,
			};

			// Add billing data from Apple Pay if available
			if ( billingContactRef.current ) {
				paymentMethodData.apple_pay_billing_contact = JSON.stringify(
					billingContactRef.current
				);
			}

			// Add shipping data from Apple Pay if available
			if ( shippingContactRef.current ) {
				paymentMethodData.apple_pay_shipping_contact = JSON.stringify(
					shippingContactRef.current
				);
			}

			return {
				type: 'success',
				meta: {
					paymentMethodData,
					billingAddress,
					shippingAddress: shippingData?.needsShipping
						? shippingAddress
						: undefined,
				},
			};
		} );

		return () => {
			unsubscribe();
		};
	}, [
		// Only depend on the function itself, not the whole eventRegistration object
		eventRegistration?.onPaymentProcessing,
		tokenizationForced,
		cartContainsSubscription,
		shippingData?.needsShipping,
	] );

	// Get payment request data
	const getPaymentRequest = () => {
		// Get the total from billing with validation
		const total = formatCurrencyAmount( billing?.cartTotal?.value );

		// Build line items array similar to classic implementation
		const lineItems = [];

		// Get cart total items from billing object
		const cartTotalItems = billing?.cartTotalItems || [];

		// Process each cart total item
		cartTotalItems.forEach( ( item ) => {
			if ( item.value > 0 ) {
				switch ( item.key ) {
					case 'total_items':
						lineItems.push( {
							type: 'final',
							label: __(
								'Subtotal',
								'woocommerce-gateway-paypal-powered-by-braintree'
							),
							amount: formatCurrencyAmount( item.value ),
						} );
						break;
					case 'total_discount':
						lineItems.push( {
							type: 'final',
							label: __(
								'Discount',
								'woocommerce-gateway-paypal-powered-by-braintree'
							),
							amount: '-' + formatCurrencyAmount( item.value ),
						} );
						break;
					case 'total_shipping':
						lineItems.push( {
							type: 'final',
							label: __(
								'Shipping',
								'woocommerce-gateway-paypal-powered-by-braintree'
							),
							amount: formatCurrencyAmount( item.value ),
						} );
						break;
					case 'total_fees':
						lineItems.push( {
							type: 'final',
							label: __(
								'Fees',
								'woocommerce-gateway-paypal-powered-by-braintree'
							),
							amount: formatCurrencyAmount( item.value ),
						} );
						break;
					case 'total_tax':
						lineItems.push( {
							type: 'final',
							label: __(
								'Taxes',
								'woocommerce-gateway-paypal-powered-by-braintree'
							),
							amount: formatCurrencyAmount( item.value ),
						} );
						break;
				}
			}
		} );

		const paymentRequest = {
			total: {
				label: merchantName,
				amount: total,
			},
			requiredBillingContactFields: [ 'postalAddress' ],
			requiredShippingContactFields: shippingData?.needsShipping
				? [ 'postalAddress', 'email', 'phone' ]
				: [],
		};

		// Add line items if available
		if ( lineItems.length > 0 ) {
			paymentRequest.lineItems = lineItems;
		}

		// Add shipping methods if shipping is needed
		if (
			shippingData?.needsShipping &&
			shippingData?.shippingRates?.length > 0
		) {
			const shippingMethods = [];

			const shippingRates =
				shippingData.shippingRates[ 0 ]?.shipping_rates || [];

			shippingRates.forEach( ( rate ) => {
				const method = {
					label: rate.name,
					detail: '',
					amount: formatCurrencyAmount( parseInt( rate.price, 10 ) ),
					identifier: rate.rate_id,
				};
				shippingMethods.push( method );
			} );

			if ( shippingMethods.length > 0 ) {
				paymentRequest.shippingMethods = shippingMethods;
			}
		}

		return paymentRequest;
	};

	// Don't render if Apple Pay is not enabled or available
	if ( ! applePayEnabled || ! isApplePayAvailable() ) {
		return null;
	}

	// Get Apple Pay button CSS classes (same as classic implementation)
	const getApplePayButtonClasses = () => {
		const classes = [ 'sv-wc-apple-pay-button' ];

		// Add style-specific class
		switch ( buttonStyle ) {
			case 'white':
				classes.push( 'apple-pay-button-white' );
				break;
			case 'white-with-line':
				classes.push( 'apple-pay-button-white-with-line' );
				break;
			case 'black':
			default:
				classes.push( 'apple-pay-button-black' );
				break;
		}

		// Add subscription class if tokenization is forced
		if ( tokenizationForced ) {
			classes.push( 'apple-pay-button-subscription' );
		}

		return classes.join( ' ' );
	};

	const buttonContent = (
		<button
			className={ getApplePayButtonClasses() }
			style={ { display: 'block' } }
			onClick={ handleApplePayClick }
			type="button"
			aria-label={ __(
				'Pay with Apple Pay',
				'woocommerce-gateway-paypal-powered-by-braintree'
			) }
			disabled={ isLoading }
		/>
	);

	if ( error ) {
		return (
			<div className="wc-block-components-express-payment__item">
				<div className="wc-block-components-express-payment-apple-pay-error">
					{ error }
				</div>
			</div>
		);
	}

	return (
		<div
			className="wc-block-components-express-payment__item"
			ref={ containerRef }
		>
			<LoadingMask isLoading={ isLoading } showSpinner={ true }>
				{ buttonContent }
			</LoadingMask>
		</div>
	);
};
