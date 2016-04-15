define ([
	'Magento_Payment/js/view/payment/cc-form'
	,'jquery'
	, 'df'
	, 'mage/translate'
	, 'underscore'
], function(Component, $, df, $t, _) {
	'use strict';
	return Component.extend({
		defaults: {
			active: false
			,clientConfig: {id: 'dfe-checkout-com'}
			,code: 'dfe_checkout_com'
			,template: 'Dfe_CheckoutCom/item'
		},
		imports: {onActiveChange: 'active'},
		/**
		 * 2016-03-02
		 * @param {?String} key
		 * @returns {Object}|{*}
	 	 */
		config: function(key) {
			/** @type {Object} */
			var result =  window.checkoutConfig.payment[this.getCode()];
			return !key ? result : result[key];
		},
		/**
		 * 2016-03-01
		 * 2016-03-08
		 * Раньше реализация была такой:
		 * return _.keys(this.getCcAvailableTypes())
		 *
		 * https://support.stripe.com/questions/which-cards-and-payment-types-can-i-accept-with-stripe
		 * «Which cards and payment types can I accept with Stripe?
		 * With Stripe, you can charge almost any kind of credit or debit card:
		 * U.S. businesses can accept
		  		Visa, MasterCard, American Express, JCB, Discover, and Diners Club.
		 * Australian, Canadian, European, and Japanese businesses can accept
		 * 		Visa, MasterCard, and American Express.»
		 *
		 * Не стал делать реализацию на сервере, потому что там меня не устраивал
		 * порядок следования платёжных систем (первой была «American Express»)
		 * https://github.com/magento/magento2/blob/cf7df72/app/code/Magento/Payment/etc/payment.xml#L10-L44
		 * А изменить этот порядок коротко не получается:
		 * https://github.com/magento/magento2/blob/487f5f45/app/code/Magento/Payment/Model/CcGenericConfigProvider.php#L105-L124
		 *
		 * @returns {String[]}
	 	 */
		getCardTypes: function() {
			return ['VI', 'MC', 'AE'];
		},
		/** @returns {String} */
		getCode: function() {return this.code;},
		/**
		 * 2016-03-06
   		 * @override
   		 */
		getData: function () {
			return {
				method: this.item.method,
				additional_data: {token: this.token}
			};
		},
		/**
		 * 2016-03-08
		 * @return {String}
		*/
		getTitle: function() {
			var result = this._super();
			return result + (!this.isTest() ? '' : ' [<b>Checkout.com TEST MODE</b>]');
		},
		/**
		 * 2016-03-02
		 * @return {Object}
		*/
		initialize: function() {
			this._super();
			var _this = this;
			/** @type {String} */
			var library = 'Dfe_CheckoutCom/API/' + (this.isTest() ? 'Sandbox' : 'Production');
			// 2016-04-14
			// http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js
			debugger;
			window.CKOConfig = {
				debugMode: this.isTest()
				,publicKey: this.config('publishableKey')
				// 2016-04-14
				// «Charges Required-Field Matrix»
				// http://developers.checkout.com/docs/server/integration-guide/charges#a1
				,customerEmail: 'user@email.com'
				,ready: function(event) {
					console.log("CheckoutKit.js is ready");
					// 2016-04-14
					 // http://developers.checkout.com/docs/browser/integration-guide/checkoutkit-js/charge-via-card-token#step-2-capture-and-send-credit-card-details
					CheckoutKit.monitorForm('form.dfe-checkout-com', CheckoutKit.CardFormModes.CARD_TOKENISATION);
				}
				,apiError: function (event) {
					// ...
				}
			};
			// 2016-04-11
			// CheckoutKit не использует AMD и прикрепляет себя к window.
			require([library], function() {
				//CheckoutKit.setPublishableKey(this.config('publishableKey'));
				// 2016-03-09
				// «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
				/** {String|Boolean} */
				var prefill = _this.config('prefill');
				if (prefill) {
					this.creditCardNumber(prefill);
					this.creditCardExpMonth(7);
					this.creditCardExpYear(2019);
					this.creditCardVerificationNumber(111);
				}
			});
			return this;
		},
		/**
		 * 2016-04-11
		 * @return {Boolean}
		*/
		isTest: function() {return this.config('isTest');},
		pay: function() {
			var _this = this;
			// 2016-03-02
			// https://stripe.com/docs/custom-form#step-2-create-a-single-use-token
			/**
			 * 2016-03-07
			 * https://support.stripe.com/questions/which-cards-and-payment-types-can-i-accept-with-stripe
			 * Which cards and payment types can I accept with Stripe?
			 * With Stripe, you can charge almost any kind of credit or debit card:
			 * U.S. businesses can accept:
			 * 		Visa, MasterCard, American Express, JCB, Discover, and Diners Club.
			 * Australian, Canadian, European, and Japanese businesses can accept:
			 * 		Visa, MasterCard, and American Express.
			 */
			CheckoutKit.card.createToken($('form.dfe-checkout-com'),
				/**
				 * 2016-03-02
			 	 * @param {Number} status
				 * @param {Object} response
				 */
				function(status, response) {
					//debugger;
					if (200 === status) {
						// 2016-03-02
						// https://stripe.com/docs/custom-form#step-3-sending-the-form-to-your-server
						_this.token = response.id;
						_this.placeOrder();
					}
					else {
						// 2016-03-02
						// https://stripe.com/docs/api#errors
						_this.messageContainer.addErrorMessage({
							'message': $t(response.error.message)
						});
					}
				}
			);
		}
	});
});
