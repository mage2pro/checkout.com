// 2016-04-11
// http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js
var config = {
	paths: {
		'Dfe_CheckoutCom/API/Sandbox': 'https://sandbox.checkout.com/js/v1/checkoutkit'
		,'Dfe_CheckoutCom/API/Production': 'https://cdn.checkout.com/js/checkoutkit'
	}
	// 2016-04-11
	// http://requirejs.org/docs/api.html#config-shim
	// CheckoutKit does not use AMD, it attaches itself to the window.
	,shim: {
		'Dfe_CheckoutCom/API/Sandbox': {exports: 'CheckoutKit'}
		,'Dfe_CheckoutCom/API/Production': {exports: 'CheckoutKit'}
	}
};