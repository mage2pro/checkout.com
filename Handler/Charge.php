<?php
namespace Dfe\CheckoutCom\Handler;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use Df\Sales\Model\Order as DfOrder;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Settings as S;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
// 2016-05-10
abstract class Charge extends Handler {
	/**
	 * 2016-03-28
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @override
	 * @see \Dfe\CheckoutCom\Handler::eligible()
	 * @return bool
	 */
	final protected function eligible() {return !!$this->payment();}

	/**
	 * 2016-05-10
	 * @return string|null
	 */
	final protected function grandParentId() {return dfc($this, function() {return
		!$this->parentId() ? null : $this->parentCharge()->getOriginalId()
	;});}

	/**
	 * 2016-03-27
	 * @return string
	 */
	final protected function id() {return $this->r('id');}

	/**
	 * 2016-03-26
	 * @used-by \Dfe\CheckoutCom\Handler\Charge\Captured::invoice()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge\Captured::process()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge\Refunded::process()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge\Voided::process()
	 * @return Order|DfOrder
	 * @throws LE
	 */
	final protected function o() {return df_order($this->payment());}

	/**
	 * 2016-03-26
	 * @return Payment|DfPayment|null
	 */
	final protected function payment() {return dfc($this, function() {return
		$this->paymentByTxnId($this->parentId())
	;});}

	/**
	 * 2016-03-26
	 * @param string|null $id
	 * @return Payment|DfPayment|null
	 */
	final protected function paymentByTxnId($id) {return dfc($this, function($id) {
		/** @var Payment|null $result */
		$result = null;
		if ($id) {
			/** @var int|null $paymentId */
			$paymentId = df_fetch_one('sales_payment_transaction', 'payment_id', ['txn_id' => $id]);
			if ($paymentId) {
				$result = dfp_webhook_case(df_load(Payment::class, $paymentId));
				/**
				 * 2016-05-11
				 * This ID will have to be used in scenarios involving webhook.
				 * The ID originates on the payment gateway.
				 * We need to store it,
				 * to prevent Magento from generating an automatic IDs like
				 * <Parent Identifier>-capture
				 *
				 * The system attempts to store an automatic capture transation ID here:
				 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
				 * In order to cheat the system, we store the correct transaction ID,
				 * so we can use it in this method: @see \Dfe\CheckoutCom\Method::capture()
				 * @used-by \Dfe\CheckoutCom\Method::capture()
				 *
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
				$result->setTransactionId($this->id());
				// 2017-01-05
				// Раньше я этого вообще не делал.
				// Видимо, потому что Checkout.com был моим всего лишь вторым платёжным модулем
				// для Magento 2, и я был ещё недостаточно опытен.
				$result->setParentTransactionId($id);
			}
		}
		return $result;
	}, func_get_args());}

	/**
	 * 2016-05-10
	 * Parent Transaction ID
	 * In the charge.refunded event Checkout.com sends back 2 IDs:
 	 * id: refund transaction ID
 	 * originalId: capture transaction ID
	 * originalId is absent only for the primary transaction (charge.succeeded)
	 * @return string|null
	 */
	final protected function parentId() {return $this->r('originalId');}

	/**
	 * 2016-05-10
	 * @return ChargeResponse|null
	 */
	final protected function parentCharge() {return dfc($this, function() {return
		!$this->parentId() ? null : $this->ss()->apiCharge()->getCharge($this->parentId())
	;});}

	/**
	 * 2017-03-27
	 * @used-by parentCharge()
	 * @return S
	 */
	private function ss() {return dfps($this->payment());}
}