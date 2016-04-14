define([
	'uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'
], function(Component, rendererList) {
	'use strict';
	/** @type {String} */
	var code = 'dfe_checkout_com';
	if (window.checkoutConfig.payment[code].isActive) {
		rendererList.push({type: code, component: 'Dfe_CheckoutCom/item'});
	}
	return Component.extend ({});
});
