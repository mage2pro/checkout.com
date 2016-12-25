<?php
namespace Dfe\CheckoutCom\Controller\Index;
use Df\Framework\Controller\Result\Json;
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
	 * so we want to make these URLs simplier, shorter, and unified.
	 *
	 * @override
	 * @see \Magento\Framework\App\Action\Action::execute()
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	public function execute() {return df_leh(function(){
		/** @var string|null $token */
		$token = df_request('cko-payment-token');
		return !$token ? $this->webhook() :
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
	 * 2016-05-11
	 * @param mixed $message
	 * @return void
	 */
	private function log($message) {if (!df_my_local()) {df_log($message);}}

	/**
	 * 2016-05-05
	 * Processing notifications (Webhooks).
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @return Json
	 */
	private function webhook() {
		$this->log(__METHOD__);
		/** @var string $request */
		$request = @file_get_contents($this->file());
		$this->log($request);
		/** @var string $response */
		$response = Handler::p(df_json_decode($request));
		$this->log($response);
		return Json::i($response);
	}
}