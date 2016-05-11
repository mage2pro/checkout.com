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
 * Если мы проводили платёж с параметром autoCapture,
 * то Checkout.com на самом деле сразу проводит 2 транзации: authorize и capture.
 * При этом в ответе Checkout.com присылает только идентификатор транзации authorize.
 * По идентификатору транзации authorize мы не можем узнать идентификатор транзакции capture.
 * Сюда же, в событие charge.refunded, Checkout.com присылает 2 идентификатора:
 * id: идентификатор транзакции refund
 * originalId: идентификатор транзакции capture.
 *
 * 2016-05-11
 * Кстати, в документации так и сказано:
 * http://developers.checkout.com/docs/server/api-reference/charges/refund-card-charge
 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
 * «For an Automatic Capture, the Charge Response will contain
 * the Charge ID of the Auth Charge. This ID cannot be used.»
 *
 * 2016-05-11
 * Вчера я думал, что в описанной выше ситуации (autoCapture)
 * мы не можем узнать идентификатор транзации capture по идентификатору транзации authorize.
 * Но вот теперь пришёл к мысли использовать для этого запрос «Get Charge History»:
 * http://developers.checkout.com/docs/server/api-reference/charges/get-charge-history
 *
 * 2016-05-11
 * Всё, решил проблему самым правильным способом :-)
 * Теперь в режиме autoCapture в Magento сохраняется идентификатор транзации capture,
 * как и должно быть.
 *
 */
class Refunded extends Charge {
	/**
	 * 2016-03-27
	 * @override
	 * «How is an online refunding implemented?» https://mage2.pro/t/959
	 *
	 * Сначала хотел cделать по аналогии с @see \Magento\Paypal\Model\Ipn::_registerPaymentRefund()
	 * https://github.com/magento/magento2/blob/9546277/app/code/Magento/Paypal/Model/Ipn.php#L467-L501
	 * Однако используемый там метод @see \Magento\Sales\Model\Order\Payment::registerRefundNotification()
	 * нерабочий: «Invalid method Magento\Sales\Model\Order\Creditmemo::register»
	 * https://mage2.pro/t/1029
	 *
	 * Поэтому делаю по аналогии с
	 * @see \Magento\Sales\Controller\Adminhtml\Order\Creditmemo\Save::execute()
	 *
	 * 2016-03-28
	 * @todo Пока поддерживается лишь сценарий полного возврата.
	 * Надо сделать ещё частичный возврат, при это не забывать про бескопеечные валюты.
	 *
	 * @see \Dfe\CheckoutCom\Handler::_process()
	 * @used-by \Dfe\CheckoutCom\Handler::process()
	 * @return mixed
	 */
	protected function process() {
		/** @var CreditmemoService|ICreditmemoService $cmi */
		$cmi = df_om()->create(ICreditmemoService::class);
		$cmi->refund($this->cm(), false);
		// 2016-03-28
		// @todo Надо отослать покупателю письмо-оповещение о возврате оплаты.
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
			 * Важно! Иначе order загрузат payment автоматически вместо нашего,
			 * и флаг @see \Dfe\CheckoutCom\Method::ALREADY_DONE будет утерян
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