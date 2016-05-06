<?php
namespace Dfe\CheckoutCom\Controller\Index;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge;
use Df\Payment\Transaction;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Settings as S;
class Index extends \Magento\Framework\App\Action\Action {
	/**
	 * 2016-05-05
	 * Сюда мы можем попать в 2-х случаях:
	 * 1) при возвращении покупателя в магазин после проверки 3D-Secure.
	 * 2) при оповещениях (Webhooks).
	 * В первом случае запрос имеет тип GET и содержит параметр «cko-payment-token».
	 * @override
	 * @see \Magento\Framework\App\Action\Action::execute()
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	public function execute() {return df_leh(function(){
		/** @var string|null $token */
		$token = df_request('cko-payment-token');
		return $token ? $this->customerReturn($token) : $this->webhook();
	});}

	/**
	 * 2016-05-05
	 * Возвращение покупателя в магазин после проверки 3D-Secure.
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @param string $token
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	private function customerReturn($token) {
		/** @var Transaction $transaction */
		$transaction = Transaction::s($token);
		/** @var ChargeService $api */
		$api = S::s()->apiCharge($transaction->order()->getStore());
		/** @var Charge $charge */
		$charge = $api->verifyCharge($token);
		if ('Authorized' === $charge->getStatus()) {
			$result = null;
		}
		/**
		 * 2016-05-06
		 * «How to cancel the last order and restore the last quote on an unsuccessfull payment?»
		 * https://mage2.pro/t/1525
		 */
		else {
			/**
			 * 2016-05-06
			 * Идентично:
			 * df_checkout_session()->getLastRealOrder()->cancel()->save();
			 */
			$transaction->order()->cancel()->save();
			df_checkout_session()->restoreQuote();
			/**
			 * 2016-05-06
			 * «How to redirect a customer to the checkout payment step?» https://mage2.pro/t/1523
			 */
			$result = $this->_redirect('checkout', ['_fragment' => 'payment']);
		}
		xdebug_break();
		return $result;
	}

	/**
	 * 2016-03-25
	 * @return string
	 */
	private function file() {
		return df_is_it_my_local_pc() ? BP . '/_my/test/charge.refunded.json' : 'php://input';
	}

	/**
	 * 2016-05-05
	 * Обработка оповещений (Webhooks).
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @return \Df\Framework\Controller\Result\Json
	 */
	private function webhook() {
		$request = df_json_decode(@file_get_contents($this->file()));
		return df_controller_json(Handler::p($request));
	}
}