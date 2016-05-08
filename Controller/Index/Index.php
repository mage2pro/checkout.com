<?php
namespace Dfe\CheckoutCom\Controller\Index;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge;
use Df\Payment\Transaction;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Method;
use Dfe\CheckoutCom\Settings as S;
use Dfe\CheckoutCom\Source\Action;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
class Index extends \Magento\Framework\App\Action\Action {
	/**
	 * 2016-05-05
	 * Сюда мы можем попать в 2-х случаях:
	 * 1) при возвращении покупателя в магазин после проверки 3D-Secure.
	 * 2) при оповещениях (Webhooks).
	 * В первом случае запрос имеет тип GET и содержит параметр «cko-payment-token».
	 * @override
	 * @see \Magento\Framework\App\Action\Action::execute()
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	public function execute() {return df_leh(function(){
		/** @var string|null $token */
		$token = df_request('cko-payment-token');
		return $token ? $this->customerReturn($token) : $this->webhook();
	});}

	/**
	 * 2016-05-05
	 * Возвращение покупателя в магазин после проверки 3D-Secure.
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @param string $token
	 * @return \Magento\Framework\Controller\Result\Redirect
	 */
	private function customerReturn($token) {
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
		if (!Method::isChargeValid($charge)) {
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
			/**
			 * 2016-05-06
			 * «How to redirect a customer to the checkout payment step?» https://mage2.pro/t/1523
			 */
			$result = $this->_redirect('checkout', ['_fragment' => 'payment']);
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
			$result = $this->_redirect('checkout/onepage/success');
		}
		return $result;
	}

	/**
	 * 2016-03-25
	 * @return string
	 */
	private function file() {
		return df_is_it_my_local_pc() ? BP . '/_my/test/charge.refunded.json' : 'php://input';
	}

	/**
	 * 2016-05-05
	 * Обработка оповещений (Webhooks).
	 * @used-by \Dfe\CheckoutCom\Controller\Index\Index::execute()
	 * @return \Df\Framework\Controller\Result\Json
	 */
	private function webhook() {
		$request = df_json_decode(@file_get_contents($this->file()));
		return df_controller_json(Handler::p($request));
	}
}