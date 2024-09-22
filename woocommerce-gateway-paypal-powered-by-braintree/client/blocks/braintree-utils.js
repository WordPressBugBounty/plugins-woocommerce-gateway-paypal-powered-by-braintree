/**
 * Get the client token from the server.
 *
 * @param {string} ajaxUrl         The AJAX URL.
 * @param {string} paymentMethodId The payment method ID.
 * @param {string} nonce           The ajax nonce to verify at server side.
 */
export const getClientToken = (ajaxUrl, paymentMethodId, nonce) => {
	const formData = new FormData();
	formData.append('action', `wc_${paymentMethodId}_get_client_token`);
	formData.append('nonce', nonce);

	return fetch(ajaxUrl, {
		method: 'POST',
		body: formData,
	})
		.then((response) => response.json())
		.then((res) => {
			if (res && !res.success) {
				const message = (res.data && res.data.message) || '';
				throw new Error(
					`Could not retrieve the client token via AJAX: ${message}`
				);
			}
			if (res && res.success && res.data) {
				return res.data;
			}
		});
};

/**
 * Set PayPal Payment nonce to server.
 *
 * @param {string} cartHandlerUrl  The Cart Handler URL.
 * @param {string} payload         The payload to send to server.
 */
export const setPaymentNonceSession = (cartHandlerUrl, payload) => {
	if (!payload || !payload.nonce) {
		return;
	}

	return fetch(cartHandlerUrl, {
		method: 'POST',
		body: jsonToFormData(payload),
	}).then((response) => response.json());
};

/**
 * Convert JSON to FormData.
 *
 * @param {Object}   json
 * @param {FormData} form
 * @param {string}   namespace
 * @return {FormData} FormData object.
 */
function jsonToFormData(json, form, namespace) {
	const formData = form || new FormData();
	for (const property in json) {
		if (!json.hasOwnProperty(property) || !json[property]) continue;
		const formKey = namespace ? `${namespace}[${property}]` : property;
		if (typeof json[property] === 'object') {
			jsonToFormData(json[property], formData, formKey);
		} else {
			formData.append(formKey, json[property]);
		}
	}
	return formData;
}
