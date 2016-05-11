<?php
namespace Dfe\CheckoutCom\Controller\Index;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Handler\CustomerReturn;
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
		return df_is_it_my_local_pc() ? BP . '/_my/test/charge.refunded.json' : 'php://input';
	}

	/**
	 * 2016-05-05
	 * Обработка оповещений (Webhooks).
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @return \Df\Framework\Controller\Result\Json
	 */
	private function webhook() {
		df_log(__METHOD__);
		/** @var string $request */
		$request = @file_get_contents($this->file());
		df_log($request);
		return df_controller_json(Handler::p(df_json_decode($request)));
	}
}