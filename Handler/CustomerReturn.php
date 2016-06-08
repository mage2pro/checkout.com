<?php
namespace Dfe\CheckoutCom\Handler;
use com\checkout\ApiServices\Charges\ChargeService;
/**
 * 2016-06-08
 * I renamed it to get rid of the following
 * Magento 2 compiler (bin/magento setup:di:compile) failure:
 * «Fatal error: Cannot use com\checkout\ApiServices\Charges\ResponseModels\Charge as Charge
 * because the name is already in use
 * in vendor/mage2pro/checkout.com/Handler/CustomerReturn.php on line 4»
 * http://stackoverflow.com/questions/17746481
 */
use com\checkout\ApiServices\Charges\ResponseModels\Charge as CCharge;
use Df\Sales\Model\Order as DfOrder;
use Df\Sales\Model\Order\Invoice as DfInvoice;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Method;
use Dfe\CheckoutCom\Response;
use Dfe\CheckoutCom\Settings as S;
use Magento\Framework\DB\Transaction;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
class CustomerReturn {
	/**
	 * 2016-05-05
	 * Возвращение покупателя в магазин после проверки 3D-Secure.
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @param string $token
	 * @return bool
	 */
	public static function p($token) {
		df_log(__METHOD__);
		/**
		 * 2016-05-08 (дополнение)
		 * И размещение заказа, и провека 3D-Secure
		 * происходят в контексте сессии покупателя,
		 * и мы можем получить последний размещённый покупателем заказ
		 * простым вызовом @see \Magento\Checkout\Model\Session::getLastRealOrder()
		 * How to get the last order programmatically? https://mage2.pro/t/1528
		 *
		 * Мы также могли получить increment_id последнего заказа вызовом $charge->getTrackId(),
		 * а затем загрузить заказ по increment_id:
		 * How to get an order by its increment id programmatically?
		 * https://mage2.pro/t/topic/1561
		 */
		/** @var Order|DfOrder $order */
		$order = df_checkout_session()->getLastRealOrder();
		/**
		 * 2016-05-08
		 * Вообще говоря, у заказа может быть много платежей,
		 * и @uses \Magento\Sales\Model\Order::getPayment()
		 * возвращает платёж от балды: https://mage2.pro/t/1559
		 * Однако в нашем случае непосредственно после размещения заказа и проверки 3D-Secure
		 * платёж гарантированно только один, поэтому получаем его самым простым способом.
		 */
		/** @var Payment|DfPayment $payment */
		$payment = $order->getPayment();
		/**
		 * How to get the last order programmatically? https://mage2.pro/t/1528
		 * How to get an order programmatically? https://mage2.pro/t/1562
		 */
		/** @var ChargeService $api */
		$api = S::s()->apiCharge($order->getStore());
		/**
		 * 2016-05-15
		 * Даже если в запросе было autoCapture = true,
		 * здесь по токену мы всё равно имеем транзакцию Authorize, а не Capture.
		 * Более того, событие charge.captured может вызываться
		 * как до возвращения покупателя в магазин после проверки 3D-Secure, так и после
		 * (наблюдал оба случая).
		 */
		/** @var CCharge $charge */
		$charge = $api->verifyCharge($token);
		df_log($charge->json);
		/** @var Response $r */
		$r = Response::s($charge, $order);
		/** @var bool $result */
		$result = $r->valid();
		if (!$result) {
			/**
			 * 2016-05-06
			 * «How to cancel the last order and restore the last quote on an unsuccessfull payment?»
			 * https://mage2.pro/t/1525
			 */
			/**
			 * 2016-05-06
			 * Идентично:
			 * df_checkout_session()->getLastRealOrder()->cancel()->save();
			 */
			$order->cancel();
			$order->save();
			df_checkout_session()->restoreQuote();
		}
		else {
			self::action($order, $payment, $charge, $r->action());
			if (
				M::ACTION_AUTHORIZE === $r->action()
				&& 'Y' === $charge->getAutoCapture()
				&& !$r->flagged()
			) {
				/** @var CCharge $captureCharge */
				$captureCharge = Response::getCaptureCharge($charge->getId());
				$order->unsetData(Order::PAYMENT);
				$payment = $order->getPayment();
				$payment->unsetData('method_instance');
				$payment[Method::WEBHOOK_CASE] = true;
				$payment[Method::CUSTOM_TRANS_ID] = $captureCharge->getId();
				/** @var InvoiceService $invoiceService */
				$invoiceService = df_o(InvoiceService::class);
				/** @var Invoice|DfInvoice $invoice */
				$invoice = $invoiceService->prepareInvoice($order);
				df_register('current_invoice', $invoice);
				$invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
				$invoice->register();
				$order->setIsInProcess(true);
				$order->setCustomerNoteNotify(true);
				/** @var Transaction $t */
				$t = df_db_transaction();
				$t->addObject($invoice);
				$t->addObject($order);
				$t->save();
				/** @var InvoiceSender $sender */
				$sender = df_o(InvoiceSender::class);
				$sender->send($invoice);
				//self::action($order, $payment, $captureCharge, M::ACTION_AUTHORIZE_CAPTURE);
			}
		}
		return $result;
	}

	/**
	 * 2016-05-16
	 * @param Order $order
	 * @param Payment $payment
	 * @param CCharge $charge
	 * @param string $action
	 * @return void
	 */
	private static function action(Order $order, Payment $payment, CCharge $charge, $action) {
		/** @var Method $method */
		$method = $payment->getMethodInstance();
		$method->setStore($order->getStoreId());
		if (M::ACTION_AUTHORIZE === $action) {
			/**
			 * 2016-05-15
			 * Отключаем это оповещение, потому что мы проведём Capture вручную.
			 */
			$method->disableEvent($charge->getId(), 'charge.captured');
		}
		$method->responseSet($charge);
		/** @var Response $r */
		$r = Response::s($charge, $order);
		DfPayment::processActionS($payment, $action, $order);
		DfPayment::updateOrderS(
			$payment
			, $order
			, Order::STATE_PROCESSING
			, $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING)
			, $isCustomerNotified = true
		);
		$order->save();
	}
}