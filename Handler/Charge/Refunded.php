<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use com\checkout\ApiServices\Charges\ChargeService;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Handler\Charge;
use Dfe\CheckoutCom\Settings as S;
use Magento\Sales\Api\CreditmemoManagementInterface as ICreditmemoService;
use Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Service\CreditmemoService;
/**
 * 2016-05-10
 * charge.refunded
 * http://developers.checkout.com/docs/server/api-reference/webhooks
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
 * http://developers.checkout.com/docs/server/api-reference/charges/refund-card-charge
 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
 * «For an Automatic Capture, the Charge Response will contain
 * the Charge ID of the Auth Charge. This ID cannot be used.»
 *
 * 2016-05-11
 * About the use case described above (autoCapture)
 * We cannot get Capture transaction ID from the Authorize transaction ID.
 * But we can use «Get Charge History» for this request :
 * http://developers.checkout.com/docs/server/api-reference/charges/get-charge-history
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
	 * @see \Dfe\CheckoutCom\Handler::_process()
	 * @used-by \Dfe\CheckoutCom\Handler::process()
	 * @return mixed
	 */
	protected function process() {
		/**
		 * 2016-05-11
		 * @todo Надо ещё устанавливать присланное в запросе примечание к возврату.
		 */
		/** @var CreditmemoService|ICreditmemoService $cmi */
		$cmi = df_om()->create(ICreditmemoService::class);
		$cmi->refund($this->cm(), false);
		/**
		 * 2016-03-28
		 * @todo We should send the customer an email notification when refunds are made.
		 * 2016-05-15
		 * To note: when refunding from the admin patnel of Magento 2,
		 * customer doesn't receive an email notification
		 */
		return $this->cm()->getId();
	}

	/**
	 * 2016-03-27
	 * @return Creditmemo
	 */
	private function cm() {
		if (!isset($this->{__METHOD__})) {
			/** @var CreditmemoLoader $cmLoader */
			$cmLoader = df_o(CreditmemoLoader::class);
			$cmLoader->setOrderId($this->order()->getId());
			$cmLoader->setInvoiceId($this->invoice()->getId());
			/** @varCreditmemo  $result */
			$result = $cmLoader->load();
			df_assert($result);
			/**
			 * 2016-03-28
			 * Important! if not done the order will automatically capture the payment instead of us
			 * and the flag @see \Dfe\CheckoutCom\Method::WEBHOOK_CASE will be lost
			 */
			$result->getOrder()->setData(Order::PAYMENT, $this->payment());
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-03-27
	 * @return Invoice
	 */
	private function invoice() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_invoice_by_transaction($this->order(), $this->parentId());
			df_assert($this->{__METHOD__});
		}
		return $this->{__METHOD__};
	}
}