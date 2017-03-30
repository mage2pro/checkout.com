<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use Dfe\CheckoutCom\Handler\Charge;
use Magento\Framework\DataObject as O;
// 2016-05-10
// charge.voided
// http://docs.checkout.com/getting-started/webhooks
class Voided extends Charge {
	/**
	 * 2016-05-10
	 * @override
	 * How is a payment authorization voiding implemented? https://mage2.pro/t/topic/938
	 * Similar to @see \Magento\Sales\Controller\Adminhtml\Order\VoidPayment::execute()
	 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Controller/Adminhtml/Order/VoidPayment.php#L10-L36
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 */
	final protected function process() {
		// 2016-05-11
		// isPaymentReview() means that the transaction is «Flagged».
		// We need to void it.
		$this->o()->isPaymentReview() ? $this->payment()->deny() : $this->payment()->void(new O);
		$this->o()->save();
	}
}