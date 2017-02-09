<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use Dfe\CheckoutCom\Handler\Charge;
/**
 * 2016-05-10
 * charge.refunded
 * http://docs.checkout.com/getting-started/webhooks
 *
 * If autoCapture is enabled, Checkout.com executes 2 transactions: Authorize and Capture.
 * The response sent back by Checkout.com only contains the Authorize transaction ID. 
 * Using that ID it not possible to know the Capture transaction ID. 
 * In the charge.refunded event Checkout.com sends back 2 IDs:
 * id: refund transaction ID
 * originalId: capture transaction ID
 *
 * 2016-05-11
 * Side note stated in the documentation:
 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-actions/refund-card-charge
 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
 * «For an Automatic Capture, the Charge Response will contain
 * the Charge ID of the Auth Charge. This ID cannot be used.»
 *
 * 2016-05-11
 * About the use case described above (autoCapture)
 * We cannot get Capture transaction ID from the Authorize transaction ID.
 * But we can use «Get Charge History» for this request :
 * http://docs.checkout.com/reference/merchant-api-reference/charges/get-charge-history
 *
 * 2016-05-11
 * Problem was solved.
 * autoCapture mode now is saved in the Magento Capture transaction ID
 * as it should be.
 * https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/3a1d36/Method.php#L593
 */
class Refunded extends Charge {
	/**
	 * 2016-03-27
	 * @override
	 * «How is an online refunding implemented?» https://mage2.pro/t/959
	 *
	 * First step is similar to @see \Magento\Paypal\Model\Ipn::_registerPaymentRefund()
	 * https://github.com/magento/magento2/blob/9546277/app/code/Magento/Paypal/Model/Ipn.php#L467-L501
	 * However, this method is used @see \Magento\Sales\Model\Order\Payment::registerRefundNotification()
	 * And doesn't work: «Invalid method Magento\Sales\Model\Order\Creditmemo::register»
	 * https://mage2.pro/t/1029
	 *
	 * So we're doing the below, similarly to
	 * @see \Magento\Sales\Controller\Adminhtml\Order\Creditmemo\Save::execute()
	 *
	 * 2016-03-28
	 * @todo While this scenario handles full refunds
	 * We have to handle partial refunds and not forget about the fractionless currencies.
	 *
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return mixed
	 */
	final protected function process() {return dfp_refund(
		$this->payment(), df_invoice_by_trans($this->order(), $this->parentId())
	) ?: 'skipped';}
}