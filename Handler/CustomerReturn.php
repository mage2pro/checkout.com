<?php
namespace Dfe\CheckoutCom\Handler;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Method;
use Dfe\CheckoutCom\Settings as S;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
class CustomerReturn {
	/**
	 * 2016-05-05
	 * Возвращение покупателя в магазин после проверки 3D-Secure.
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @param string $token
	 * @return bool
	 */
	public static function p($token) {
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
		/** @var Order $order */
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
		/** @var Charge $charge */
		$charge = $api->verifyCharge($token);
		/** @var bool $result */
		$result = Method::isChargeValid($charge);
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
			$order->cancel()->save();
			df_checkout_session()->restoreQuote();
		}
		else {
			/** @var Method $method */
			$method = $payment->getMethodInstance();
			/**
			 * 2016-05-08
			 * По аналогии с @see \Magento\Sales\Model\Order\Payment::place()
			 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L326
			 */
			$method->setStore($order->getStoreId());
			$method->responseSet($charge);
			DfPayment::processActionS(
				$payment
				, 'Y' === $charge->getAutoCapture()
					? M::ACTION_AUTHORIZE_CAPTURE
					: M::ACTION_AUTHORIZE
				, $order
			);
			DfPayment::updateOrderS(
				$payment
				, $order
				, Order::STATE_PROCESSING
				, $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING)
				, $isCustomerNotified = true
			);
			$order->save();
		}
		return $result;
	}
}