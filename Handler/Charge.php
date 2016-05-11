<?php
namespace Dfe\CheckoutCom\Handler;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use Df\Sales\Model\Order as DfOrder;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Method;
use Dfe\CheckoutCom\Settings as S;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Api\Data\OrderInterface;
abstract class Charge extends Handler {
	/**
	 * 2016-03-28
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @override
	 * @see \Dfe\CheckoutCom\Handler::eligible()
	 * @return bool
	 */
	protected function eligible() {return !!$this->payment();}

	/**
	 * 2016-05-10
	 * @return string|null
	 */
	protected function grandParentId() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_n_set(
				!$this->parentId() ? null : $this->parentCharge()->getOriginalId()
			);
		}
		return df_n_get($this->{__METHOD__});
	}

	/**
	 * 2016-03-27
	 * @return string
	 */
	protected function id() {return $this->o('id');}

	/**
	 * 2016-03-26
	 * @return Order|DfOrder
	 * @throws LE
	 */
	protected function order() {
		if (!isset($this->{__METHOD__})) {
			/** @var Order $result */
			$result = $this->payment()->getOrder();
			if (!$result->getId()) {
				throw new LE(__('The order no longer exists.'));
			}
			/**
			 * 2016-03-26
			 * Очень важно! Иначе order создать свой экземпляр payment:
			 * @used-by \Magento\Sales\Model\Order::getPayment()
			 */
			$result[OrderInterface::PAYMENT] = $this->payment();
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-03-26
	 * @return Payment|DfPayment|null
	 */
	protected function payment() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_n_set($this->paymentByTxnId($this->parentId()));
		}
		return df_n_get($this->{__METHOD__});
	}

	/**
	 * 2016-03-26
	 * @param string|null $id
	 * @return Payment|DfPayment|null
	 */
	protected function paymentByTxnId($id) {
		if (!isset($this->{__METHOD__}[$id])) {
			/** @var Payment|null $result */
			$result = null;
			if ($id) {
				/** @var int|null $paymentId */
				$paymentId = df_fetch_one('sales_payment_transaction', 'payment_id', ['txn_id' => $id]);
				if ($paymentId) {
					$result = df_load(Payment::class, $paymentId);
					$result[Method::WEBHOOK_CASE] = true;
					/**
					 * 2016-05-11
					 * Этот идентификатор надо будет устанавливать в сценариях Webhook.
					 * Идентификатор приходит от платёжного шлюза.
					 * Нам надо его установить,
					 * чтобы Magento не создавала автоматические идентификаторы типа
					 * <идентификатор родителя>-capture
					 *
					 * Система норовит установить автоматический идентификатор для транзакции capture здесь:
					 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
					 * А потом будет использовать его здесь:
					 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
					 * Чтобы перехитрить систему, запоминаем нужный нам идентификатор транзакции,
					 * а потому будем использовать его в методе @see \Dfe\CheckoutCom\Method::capture()
					 * @used-by \Dfe\CheckoutCom\Method::capture()
					 */
					$result[Method::CUSTOM_TRANS_ID] =  $this->id();
				}
			}
			$this->{__METHOD__}[$id] = df_n_set($result);
		}
		return df_n_get($this->{__METHOD__}[$id]);
	}

	/**
	 * 2016-05-10
	 * Идентификатор родительской транзации.
	 * Например, на событие charge.refunded Chechout.com возвращает:
	 * id - идентификатор транзакции refund
	 * originalId - идентификатор транзакции capture.
	 * originalId отсутствует только у оповещения о первичной транзации (charge.succeeded)
	 * @return string|null
	 */
	protected function parentId() {return $this->o('originalId');}
	
	/**
	 * 2016-05-10
	 * @return ChargeResponse|null
	 */
	protected function parentCharge() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_n_set(!$this->parentId() ? null :
				S::s()->apiCharge()->getCharge($this->parentId())
			);
		}
		return df_n_get($this->{__METHOD__});
	}
}