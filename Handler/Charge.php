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
			 * Very Important! If not done the order will create a duplicate payment
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
					 * This ID will have to be used in scenarios involving webhook.
					 * The ID originates on the payment gateway.
					 * We need to store it,
					 * to prevent Magento from generating an automatic IDs like
					 * <Parent Identifier>-capture
					 *
					 * The system attempts to store an automatic capture transation ID here:
					 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
					 * It will be then used here:
					 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L40-L46
					 * In order to cheat the system, we store the correct transaction ID,
					 * so we can use it in this method: @see \Dfe\CheckoutCom\Method::capture()
					 * @used-by \Dfe\CheckoutCom\Method::capture()
					 */
					$result[Method::CUSTOM_TRANS_ID] = $this->id();
				}
			}
			$this->{__METHOD__}[$id] = df_n_set($result);
		}
		return df_n_get($this->{__METHOD__}[$id]);
	}

	/**
	 * 2016-05-10
	 * Parent Transaction ID
	 * In the charge.refunded event Checkout.com sends back 2 IDs:
 	 * id: refund transaction ID
 	 * originalId: capture transaction ID
	 * originalId is absent only for the primary transaction (charge.succeeded)
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