<?php
namespace Dfe\CheckoutCom\Controller\Index;
use Df\Framework\Controller\Response\Json;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Handler\CustomerReturn;
class Index extends \Magento\Framework\App\Action\Action {
	/**
	 * 2016-05-05
	 * We get here in 2 cases:
	 * 1) On a customer's return from the 3D Secure verification.
	 * 2) On a Checkout.com's webhook notification.
	 * In the first case, a GET request is used and contains the parameter «cko-payment-token».
	 *
	 * 2016-05-30
	 * Checkout.com does not encrypt or sign the webhooks' data.
	 * Also, it does not require HTTPS protocol for webhooks.
	 * @todo I think, we need to validate the data using http://developers.checkout.com/docs/server/api-reference/charges/verify-charge
	 *
	 * 2016-12-25
	 * We use the same URL for the both cases (3D Secure and Webhooks),
	 * because these URLs are need to be set up manually by humans
	 * (Webhooks — by a store's owner, 3D Secure — by Checkout.com support),
	 * so we want to make these URLs simpler, shorter, and unified.
	 *
	 * @override
	 * @see \Magento\Framework\App\Action\Action::execute()  
	 * @used-by \Magento\Framework\App\Action\Action::dispatch():
	 * 		$result = $this->execute();
	 * https://github.com/magento/magento2/blob/2.2.0-RC1.8/lib/internal/Magento/Framework/App/Action/Action.php#L84-L125
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	function execute() {return df_leh(function(){
		/** @var string|null $token */
		return !($token = df_request('cko-payment-token')) ? $this->webhook() :
			(CustomerReturn::p($token) ? $this->_redirect('checkout/onepage/success')
				// 2016-05-06
				// «How to redirect a customer to the checkout payment step?» https://mage2.pro/t/1523
				: $this->_redirect('checkout', ['_fragment' => 'payment'])
			)
		;
	});}

	/**
	 * 2016-03-25
	 * @return string
	 */
	private function file() {return
		df_my_local() ? BP . '/_my/test/checkout.com/charge.voided.json' : 'php://input'
	;}

	/**
	 * 2016-05-05
	 * Processing notifications (Webhooks).
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @return Json
	 */
	private function webhook() {
		// 2016-12-30
		// Checkout.com does not pass the «User-Agent» HTTP Header.
		df_sentry_m($this)->user_context([
			'id' => df_is_localhost() ? 'Checkout.com webhook on localhost' : 'Checkout.com'
		]);
		return Json::i(Handler::p(df_json_decode(@file_get_contents($this->file()))));
	}
}