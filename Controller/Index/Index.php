<?php
namespace Dfe\CheckoutCom\Controller\Index;
use Df\Framework\Controller\Result\Json;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Handler\CustomerReturn;
class Index extends \Magento\Framework\App\Action\Action {
	/**
	 * 2016-05-05
	 * There are 2 cases:
	 * 1) The buyer returns to the store after the 3D-Secure checks.
	 * 2) Notifications (Webhooks).
	 * In the first case, a GET request is used and contains the parameter «cko-payment-token».
	 *
	 * 2016-05-30
	 * Checkout.com does not encrypt or sign the webhooks' data.
	 * Also, it does not require HTTPS protocol for webhooks.
	 * @todo I think, we need to validate the data using http://developers.checkout.com/docs/server/api-reference/charges/verify-charge
	 *
	 * @override
	 * @see \Magento\Framework\App\Action\Action::execute()
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	public function execute() {return df_leh(function(){
		/** @var string|null $token */
		$token = df_request('cko-payment-token');
		return
			$token
			? (CustomerReturn::p($token)
				? $this->_redirect('checkout/onepage/success')
				// 2016-05-06
				// «How to redirect a customer to the checkout payment step?» https://mage2.pro/t/1523
				: $this->_redirect('checkout', ['_fragment' => 'payment'])
			)
			: $this->webhook()
		;
	});}

	/**
	 * 2016-03-25
	 * @return string
	 */
	private function file() {
		return df_is_it_my_local_pc()
			? BP . '/_my/test/checkout.com/charge.voided.json'
			: 'php://input'
		;
	}

	/**
	 * 2016-05-11
	 * @param mixed $message
	 * @return void
	 */
	private function log($message) {if (!df_is_it_my_local_pc()) {df_log($message);}}

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