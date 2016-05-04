define ([
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/url-builder',
	'Magento_Customer/js/model/customer',
	'Magento_Checkout/js/model/place-order'
], function (quote, urlBuilder, customer, placeOrderService) {
	'use strict';
	return function (paymentData, messageContainer) {
		var serviceUrl, payload;
		payload = {
			cartId: quote.getQuoteId (), billingAddress: quote.billingAddress(), paymentMethod: paymentData
		};
		if (customer.isLoggedIn ()) {
			serviceUrl = urlBuilder.createUrl('/dfe-checkout-com/mine/place-order', {});
		}
		else {
			serviceUrl = urlBuilder.createUrl('/dfe-checkout-com/:quoteId/place-order', {
				quoteId: quote.getQuoteId ()
			});
			payload.email = quote.guestEmail;
		}
		return placeOrderService(serviceUrl, payload, messageContainer);
	};
});
