<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use Dfe\CheckoutCom\Handler\Charge;
// 2016-05-10
// charge.voided
// http://developers.checkout.com/docs/server/api-reference/webhooks
class Voided extends Charge {
	/**
	 * 2016-05-10
	 * @override
	 * How is a payment authorization voiding implemented? https://mage2.pro/t/topic/938
	 * Делаем по аналогии с @see \Magento\Sales\Controller\Adminhtml\Order\VoidPayment::execute()
	 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Controller/Adminhtml/Order/VoidPayment.php#L10-L36
	 * @see \Dfe\CheckoutCom\Handler::_process()
	 * @used-by \Dfe\CheckoutCom\Handler::process()
	 * @return void
	 */
	protected function process() {
		/**
		 * 2016-05-11
		 * Транзакция находится в состоянии «Flagged».
		 * Нам нужно выполнить операцию Accept Payment.
		 */
		if ($this->order()->isPaymentReview()) {
			$this->payment()->deny();
		}
		else {
			$this->payment()->void(new \Magento\Framework\DataObject());
		}
		$this->order()->save();
	}
}