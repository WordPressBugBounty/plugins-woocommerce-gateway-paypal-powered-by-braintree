/**
 * Internal dependencies
 */
import { getBraintreeVenmoServerData } from '../utils';

const { description } = getBraintreeVenmoServerData();

/**
 * Renders the Venmo payment method description.
 *
 * @return {JSX.Element|null} The description component.
 */
export const VenmoDescription = () => {
	if ( ! description ) {
		return null;
	}

	return <div className="braintree-venmo-description">{ description }</div>;
};
