<?php
namespace Dfe\CheckoutCom\Handler;
use com\checkout\ApiServices\Charges\ChargeService;
/**
 * 2016-06-08
 * I renamed it to get rid of the following
 * Magento 2 compiler (bin/magento setup:di:compile) failure:
 * «Fatal error: Cannot use com\checkout\ApiServices\Charges\ResponseModels\Charge as Charge
 * because the name is already in use
 * in vendor/magento2/checkout.com/Handler/CustomerReturn.php on line 4»
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
use Magento\Sales\Model\Service\InvoiceService;
class CustomerReturn {
	/**
	 * 2016-05-05
	 * Handles a customer return to the store after a 3D-Secure verification
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @param string $token
	 * @return bool
	 */
	public static function p($token) {
		/**
		 * 2016-05-08 (дополнение)
		 * The order placement and the 3D-Secure verification
		 * both occur in the user's sessions.
		 * So we can get the user's last order
		 * by calling @see \Magento\Checkout\Model\Session::getLastRealOrder()
		 * How to get the last order programmatically? https://mage2.pro/t/1528
		 *
		 * We can also get the increment_id through the last order call $charge->getTrackId(),
		 * and then fetch the order by increment_id:
		 * How to get an order by its increment id programmatically?
		 * https://mage2.pro/t/topic/1561
		 */
		/** @var Order|DfOrder $order */
		$order = df_checkout_session()->getLastRealOrder();
		/**
		 * 2016-05-08
		 * Generally speaking an order can be made out of many payments,
		 * and @uses \Magento\Sales\Model\Order::getPayment()
		 * returns the payment from : https://mage2.pro/t/1559
		 * However in this case, immediately after placing the order 
		 * and doing the 3D-Secure verification, the payment is only guaranteed
		 * after retrieving it 
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
		 * Even in the case of a request with autoCapture = true,
		 * at this point the token still has an Authorize status, and not a Capture status.
		 * Also, a charge.captured event may be triggered when the user is redirected to the store
		 * after the 3D-Secure verification.
		 * (both cases were observed)
		 */
		/** @var CCharge $charge */
		$charge = $api->verifyCharge($token);
		/**
		 * 2016-09-07
		 * @see https://github.com/CKOTech/checkout-php-library/blob/v1.2.4/com/checkout/ApiServices/Charges/ResponseModels/Charge.php?ts=4#L129
		 */
		dfp_report(__CLASS__, json_decode($charge->{'json'}), 'customerReturn');
		/** @var Response $r */
		$r = Response::sp($charge, $order);
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
			 * Similarly:
			 * df_checkout_session()->getLastRealOrder()->cancel()->save();
			 */
			$order->cancel();
			$order->save();
			df_checkout_session()->restoreQuote();
			// 2016-07-14
			// Show an explanation message to the customer
			// when it returns to the store after an unsuccessful payment attempt.
			df_checkout_error($r->messageC());
		}
		else {
			self::action($order, $payment, $charge, $r->action());
			df_order_send_email($order);
			if (
				M::ACTION_AUTHORIZE === $r->action()
				&& 'y' === strtolower($charge->getAutoCapture())
				&& !$r->flagged()
			) {
				/** @var CCharge $captureCharge */
				$captureCharge = Response::getCaptureCharge($charge->getId());
				$order->unsetData(Order::PAYMENT);
				$payment = $order->getPayment();
				$payment->unsetData('method_instance');
				dfp_webhook_case($payment);
				/**
				 * 2017-01-05
				 * Прежде я думал, что здесь нам ешё нельзя устанавливать свой нестандартный
				 * идентификатор транзакции, потому что метод
				 * @see \Magento\Sales\Model\Order\Payment\Operations\CaptureOperation::capture()
				 * перетрёт наш идентификатор кодом:
						$payment->setTransactionId(
							$this->transactionManager->generateTransactionId(
								$payment,
								Transaction::TYPE_CAPTURE,
								$payment->getAuthorizationTransaction()
							)
						);
				 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
				 * Однако мне следовало посмотреть глубже, в реализацию метода
				 * @see \Magento\Sales\Model\Order\Payment\Transaction\Manager::generateTransactionId()
				 * чтобы понять, что когда нестандартный идентификатор транзакции уже установлен,
				 * то метод его не перетирает:
					if (!$payment->getParentTransactionId()
						&& !$payment->getTransactionId() && $transactionBasedOn
					) {
						$payment->setParentTransactionId($transactionBasedOn->getTxnId());
					}
					// generate transaction id for an offline action or payment method that didn't set it
					if (
				 		($parentTxnId = $payment->getParentTransactionId())
				 		&& !$payment->getTransactionId()
				 	) {
						return "{$parentTxnId}-{$type}";
					}
					return $payment->getTransactionId();
				 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment/Transaction/Manager.php#L73-L80
				 * Поэтому никакие обходные манёвры нам не нужны,
				 * и смело устанвливаем транзакции наш нестандартный идентификатор прямо здесь.
				 */
				$payment->setTransactionId($captureCharge->getId());
				// 2017-01-05
				// Раньше я этого вообще не делал.
				// Видимо, потому что Checkout.com был моим всего лишь вторым платёжным модулем
				// для Magento 2, и я был ещё недостаточно опытен.
				$payment->setParentTransactionId($charge->getId());
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
		$method = df_ar($payment->getMethodInstance(), Method::class);
		$method->setStore($order->getStoreId());
		if (M::ACTION_AUTHORIZE === $action) {
			/**
			 * 2016-05-15
			 * Disable this event because we will trigger Capture manually.
			 */
			$method->disableEvent($charge->getId(), 'charge.captured');
		}
		$method->responseSet($charge);
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