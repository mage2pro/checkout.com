<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\RequestModels\ChargeCapture;
use com\checkout\ApiServices\Charges\RequestModels\ChargeRefund;
use com\checkout\ApiServices\Charges\RequestModels\ChargeUpdate;
use com\checkout\ApiServices\Charges\RequestModels\ChargeVoid;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use com\checkout\helpers\ApiHttpClientCustomException as CE;
use Df\Payment\PlaceOrderInternal as PO;
use Df\Payment\Source\AC;
use Df\Payment\Token;
use Dfe\CheckoutCom\SDK\ChargeService;
use Magento\Framework\DataObject as _DO;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Payment\Model\Info as I;
use Magento\Payment\Model\InfoInterface as II;
use Magento\Sales\Model\Order\Payment as OP;
use Magento\Sales\Model\Order\Payment\Transaction as T;
/** @method Settings s() */
final class Method extends \Df\Payment\Method {
	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Payment\Method::canCapture()
	 * 2017-12-07
	 * 1) @used-by \Magento\Sales\Model\Order\Payment::canCapture():
	 *		if (!$this->getMethodInstance()->canCapture()) {
	 *			return false;
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment.php#L246-L269
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment.php#L277-L301
	 * 2) @used-by \Magento\Sales\Model\Order\Payment::_invoice():
	 *		protected function _invoice() {
	 *			$invoice = $this->getOrder()->prepareInvoice();
	 *			$invoice->register();
	 *			if ($this->getMethodInstance()->canCapture()) {
	 *				$invoice->capture();
	 *			}
	 *			$this->getOrder()->addRelatedObject($invoice);
	 *			return $invoice;
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment.php#L509-L526
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment.php#L542-L560
	 * 3) @used-by \Magento\Sales\Model\Order\Payment\Operations\AbstractOperation::invoice():
	 *		protected function invoice(OrderPaymentInterface $payment) {
	 *			$invoice = $payment->getOrder()->prepareInvoice();
	 *			$invoice->register();
	 *			if ($payment->getMethodInstance()->canCapture()) {
	 *				$invoice->capture();
	 *			}
	 *			$payment->getOrder()->addRelatedObject($invoice);
	 *			return $invoice;
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment/Operations/AbstractOperation.php#L56-L75
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment/Operations/AbstractOperation.php#L59-L78
	 */
	function canCapture():bool {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canCapturePartial()
	 */
	function canCapturePartial():bool {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canRefund()
	 * 2017-12-06
	 * 1) @used-by \Magento\Sales\Model\Order\Payment::canRefund():
	 *		public function canRefund() {
	 *			return $this->getMethodInstance()->canRefund();
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment.php#L271-L277
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment.php#L303-L309
	 * 2) @used-by \Magento\Sales\Model\Order\Payment::refund()
	 *		$gateway = $this->getMethodInstance();
	 *		$invoice = null;
	 *		if ($gateway->canRefund()) {
	 *			<...>
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment.php#L617-L654
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment.php#L655-L698
	 * 3) @used-by \Magento\Sales\Model\Order\Invoice\Validation\CanRefund::canPartialRefund()
	 *		private function canPartialRefund(MethodInterface $method, InfoInterface $payment) {
	 *			return $method->canRefund() &&
	 *			$method->canRefundPartialPerInvoice() &&
	 *			$payment->getAmountPaid() > $payment->getAmountRefunded();
	 *		}
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Invoice/Validation/CanRefund.php#L84-L94
	 * It is since Magento 2.2: https://github.com/magento/magento2/commit/767151b4
	 */
	function canRefund():bool {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canRefundPartialPerInvoice()
	 */
	function canRefundPartialPerInvoice():bool {return true;}

	/**
	 * 2016-05-08
	 * I have disabled the Review mode, because if Checkout.com wants 3D Secure,
	 * the shop can not prevent it, and it will break the Review mode,
	 * because a shop's administrator is unable to pass 3D Secure validation
	 * for a customer's bank card.
	 * 2016-05-09
	 * If Checkout.com marks a payment as «Flagged»,
	 * then it ignores the «autoCapture» request parameter,
	 * so the shop should additionally do the «capture» operation: https://mage2.pro/t/1565
	 * So we can employ the Review mode for such payments.
	 * @override
	 * @see \Df\Payment\Method::canReviewPayment()
	 */
	function canReviewPayment():bool {return true;}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::canVoid()
	 * 2017-12-08
	 * @used-by \Magento\Sales\Model\Order\Payment::canVoid():
	 *		public function canVoid() {
	 *			if (null === $this->_canVoidLookup) {
	 *				$this->_canVoidLookup = (bool)$this->getMethodInstance()->canVoid();
	 *				if ($this->_canVoidLookup) {
	 *					$authTransaction = $this->getAuthorizationTransaction();
	 *					$this->_canVoidLookup = (bool)$authTransaction && !(int)$authTransaction->getIsClosed();
	 *				}
	 *			}
	 *			return $this->_canVoidLookup;
	 *		}
	 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment.php#L528-L543
	 * https://github.com/magento/magento2/blob/2.2.1/app/code/Magento/Sales/Model/Order/Payment.php#L562-L578
	 */
	function canVoid():bool {return true;}

	/**
	 * 2016-05-09
	 * A «Flagged» payment can be handled the same way as an «Authorised» payment:
	 * we can «capture» or «void» it.
	 * @override
	 * @see \Df\Payment\Method::denyPayment()
	 */
	function denyPayment(II $p):bool {
		# 2016-05-09 Similar to https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Controller/Adminhtml/Order/VoidPayment.php#L22
		$p->void(new _DO);
		return true;
	}

	/**
	 * 2016-05-11
	 * Mark an event as already processed,
	 * so when Checkout.com will notify us about the event, we will just skip this notification.
	 * @used-by self::_refund()
	 * @used-by self::_void()
	 * @used-by self::capturePreauthorized()
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::action()
	 */
	function disableEvent(string $transactionId, string $eventId):void {
		$charge = $this->api()->getCharge($transactionId); /** @var ChargeResponse $charge */
		$metadata = df_eta($charge->getMetadata()); /** @var array(string => string) $metadata */
		$events = df_csv_parse(dfa($metadata, self::DISABLED_EVENTS, '')); /** @var string[] $events */
		if (!in_array($eventId, $events)) {
			$events[]= $eventId;
		}
		$metadata[self::DISABLED_EVENTS] = df_csv($events);
		# 2016-05-11 «Update a charge» https://github.com/CKOTech/checkout-php-library/wiki/Charges#update-a-charge
		$update = new ChargeUpdate; /** @var ChargeUpdate $update */
		$update->setChargeId($transactionId);
		$update->setMetadata($metadata);
		$this->api()->UpdateCardCharge($update);
	}

	/**
	 * 2016-05-07
	 * We can arrive here only from @used-by \Magento\Sales\Model\Order\Payment::place()
	 * but from 2 different code points.
	 * 2016-05-08
	 * Returns null, if 3D Secure validation is needed.
	 * @used-by \Magento\Sales\Model\Order\Payment::place()
	 * https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Sales/Model/Order/Payment.php#L334-L355
	 * 2016-05-09
	 * If Checkout.com marks a payment as «Flagged»,
	 * then it ignores the «autoCapture» request parameter,
	 * so the shop should additionally do the «capture» operation: https://mage2.pro/t/1565
	 * So we can employ the Review mode for such payments.
	 * @override
	 * @see \Df\Payment\Method::getConfigPaymentAction()
	 * 1) @used-by \Df\StripeClone\Method::isInitializeNeeded()
	 * 2) @used-by \Magento\Sales\Model\Order\Payment::place()
	 * 		$action = $methodInstance->getConfigPaymentAction();
	 * https://github.com/magento/magento2/blob/2.2.0/app/code/Magento/Sales/Model/Order/Payment.php#L354
	 * 3) @used-by \Magento\Sales\Model\Order\Payment::place()
	 * 		$methodInstance->initialize($methodInstance->getConfigData('payment_action'), $stateObject);
	 * https://github.com/magento/magento2/blob/2.2.0/app/code/Magento/Sales/Model/Order/Payment.php#L359-L360
	 * 		'payment_action' => 'getConfigPaymentAction'
	 * https://github.com/mage2pro/core/blob/3.2.31/Payment/Method.php#L898-L904
	 */
	function getConfigPaymentAction():string {return $this->need3DS() ? '' : $this->r()->action();}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::getInfoBlockType()
	 * @used-by \Magento\Payment\Helper\Data::getInfoBlock():
	 *		public function getInfoBlock(InfoInterface $info, LayoutInterface $layout = null) {
	 *			$layout = $layout ?: $this->_layout;
	 *			$blockType = $info->getMethodInstance()->getInfoBlockType();
	 *			$block = $layout->createBlock($blockType);
	 *			$block->setInfo($info);
	 *			return $block;
	 *		}
	 * https://github.com/magento/magento2/blob/2.2.0-RC1.6/app/code/Magento/Payment/Helper/Data.php#L182-L196
	 */
	function getInfoBlockType():string {return \Magento\Payment\Block\Info\Cc::class;}

	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::action()
	 */
	function responseSet(ChargeResponse $response):void {$this->_response = $response;}

	/**
	 * 2016-03-15
	 * 2016-05-11
	 * Checkout.com refund request supports a description
	 * and a list of refunded items (products with prices and quantities):
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-actions/refund-card-charge
	 * @todo Use this feature.
	 * @override
	 * @see \Df\Payment\Method::_refund()
	 * @used-by \Df\Payment\Method::refund()
	 */
	protected function _refund(float $a):void {$this->leh(function() use($a) {
		$refund = new ChargeRefund; /** @var ChargeRefund $refund */
		/**
		 * 2016-05-09
		 * The «capture» transaction's ID differs from the previous transaction's ID.
		 * We should use the «capture» transaction's ID
		 * as a parameter of the Checkout.com «refund» transaction.
		 *
		 * $payment->getRefundTransactionId() and $payment->getParentTransactionId()
		 * return the same value.
		 *
		 * «refund_transaction_id» is set here:
		 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L652
		 */
		$refund->setChargeId($this->ii()->getRefundTransactionId());
		$refund->setValue($this->amountFormat($a));
		$this->disableEvent($this->ii()->getRefundTransactionId(), 'charge.refunded');
		$response = $this->api()->refundCardChargeRequest($refund); /** @var ChargeResponse $response */
		/**
		 * 2016-05-09
		 * A sample success response:
		 *	{
		 *		"id": "charge_test_033B66645E5K7A9812E5",
		 *		"originalId": "charge_test_427BB6745E5K7A9813C9",
		 *		"responseMessage": "Approved",
		 *		"responseAdvancedInfo": "Approved",
		 *		"responseCode": "10000",
		 *		"status": "Refunded",
		 *		<...>
		 *	}
		 */
		df_assert_eq('Refunded', $response->getStatus());
		$this->ii()->setTransactionId($response->getId());
	});}

	/**
	 * 2016-05-03
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#void-a-charge
	 * @override
	 * @see \Df\Payment\Method::_void()
	 */
	protected function _void():void {$this->leh(function() {
		if ($auth = $this->ii()->getAuthorizationTransaction()) {
			/** @var T|false|null $auth */
			$void = new ChargeVoid; /** @var ChargeVoid $void */
			/**
			 * 2016-05-03
			 * http://developers.checkout.com/docs/server/api-reference/charges/void-card-charge#request-payload-fields
			 * Although the documentation states that the «track_id» parameter is optional,
			 * the transaction will fail with empty ChargeVoid object:
			 * {
			 *	"errorCode": "70000",
			 *	"message": "Validation error",
			 *	"errors": ["An error was experienced while parsing the payload. Please ensure that the structure is correct."],
			 *	"errorMessageCodes": ["70002"],
			 *	"eventId": "cce6a001-ff91-451d-9b9e-0094d3c57984"
			 * }
			 */
			$void->setTrackId($this->ii()->getOrder()->getIncrementId());
			$this->disableEvent($auth->getTxnId(), 'charge.voided');
			$response = $this->api()->voidCharge($auth->getTxnId(), $void); /** @var ChargeResponse $response */
			# 2016-05-13
			# This makes the «void» transaction ID the same in Magento and in Checkout.com
			# (prevents Magento from generating a transaction ID like <Parent Identifier>-void).
			$this->ii()->setTransactionId($response->getId());
		}
	});}

	/**
	 * 2016-11-13
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/calculating-charge-amount
	 * @override
	 * @see \Df\Payment\Method::amountFactorTable()
	 * @used-by \Df\Payment\Method::amountFactor()
	 * @see minimumAmount()
	 * @return array(int => string)
	 */
	protected function amountFactorTable():array {return [
		1000 => 'BHD,KWD,OMR,JOD', 1 => 'BYR,BIF,DJF,GNF,KMF,XAF,CLF,XPF,JPY,PYG,RWF,KRW,VUV,VND,XOF'
	];}

	/**
	 * 2017-02-08
	 * @override
	 * The result should be in the basic monetary unit (like dollars), not in fractions (like cents).
	 * I did not find such information on the Checkout.com website.
	 * «Does Checkout.com have minimum and maximum amount limitations on a single payment?»
	 * https://mage2.pro/t/2687
	 * 2017-02-10 I have got an answer from the Checkout.com support: https://mage2.pro/t/2687/3
	 * @see \Df\Payment\Method::amountLimits()
	 * @used-by \Df\Payment\Method::isAvailable()
	 */
	protected function amountLimits():\Closure {return function($c) {return [$this->minimumAmount($c), null];};}

	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Payment\Method::charge()
	 * @used-by \Df\Payment\Method::authorize()
	 * @used-by \Df\Payment\Method::capture()
	 * @throws Exception
	 */
	protected function charge(bool $capture = true):void {
		if ($auth = !$capture ? null : $this->ii()->getAuthorizationTransaction()) {
			/** @var T|false|null $auth */
			$this->capturePreauthorized($auth);
		}
		else {
			# 2016-04-23
			# http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#response
		    $response = $this->res(); /** @var ChargeResponse $response */
			if (!$this->r()->valid()) {
				throw new Exception($this->r(), $this->request());
			}
			/**
			 * 2016-05-02
			 * Without it, a «void» operation will be unavailable:
			 * «How is a payment authorization voiding implemented?»
			 * https://mage2.pro/t/938
			 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
			 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
			 */
			$this->ii()->setTransactionId($this->r()->magentoTransactionId());
			$card = $response->getCard(); /** @var Card $card */
			# 2016-05-02
			# https://mage2.pro/t/941
			# «How is the \Magento\Sales\Model\Order\Payment's setCcLast4() / getCcLast4() used?»
			$this->ii()->setCcLast4($card->getLast4());
			# 2016-05-02
			$this->ii()->setCcType($card->getPaymentMethod());
			/**
			 * 2016-03-15
			 * Если оставить открытой транзакцию «capture»,
			 * то операция «void» (отмена авторизации платежа) будет недоступна:
			 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
			 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
			 * Транзакция считается закрытой, если явно не указать «false».
			 *
			 * 2017-01-16
			 * Наоборот: если закрыть транзакцию типа «authorize»,
			 * то операция «Capture Online» из административного интерфейса будет недоступна:
			 * @see \Magento\Sales\Model\Order\Payment::canCapture()
			 *		if ($authTransaction && $authTransaction->getIsClosed()) {
			 *			$orderTransaction = $this->transactionRepository->getByTransactionType(
			 *				Transaction::TYPE_ORDER,
			 *				$this->getId(),
			 *				$this->getOrder()->getId()
			 *			);
			 *			if (!$orderTransaction) {
			 *				return false;
			 *			}
			 *		}
			 * https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Sales/Model/Order/Payment.php#L263-L281
			 * «How is \Magento\Sales\Model\Order\Payment::canCapture() implemented and used?»
			 * https://mage2.pro/t/650
			 * «How does Magento 2 decide whether to show the «Capture Online» dropdown
			 * on a backend's invoice screen?»: https://mage2.pro/t/2475
			 */
			$this->ii()->setIsTransactionClosed($capture);
			if ($this->r()->flagged()) {
				/**
				 * 2016-05-06
				 * Unfortunately, we can set
				 * the @see \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW state here
				 * with the code $order->setState(O::STATE_PAYMENT_REVIEW);
				 * because the state will be owerwritten:
				 * @see \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand::execute()
				 * https://github.com/magento/magento2/blob/135f967/app/code/Magento/Sales/Model/Order/Payment/State/AuthorizeCommand.php#L15-L49
				 *
				 * So, we are acting differently.
				 * The «IsTransactionPending» flag will be read
				 * in the same method:
				 * @used-by \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand::execute()
				 * https://github.com/magento/magento2/blob/135f967/app/code/Magento/Sales/Model/Order/Payment/State/AuthorizeCommand.php#L26-L31
				 * And the @see \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW state will be set.
				 */
				$this->ii()->setIsTransactionPending(true);
				/**
				 * 2016-05-09
				 * How is @used-by \Magento\Sales\Model\Order\Payment.::getIsFraudDetected()
				 * implemented and used?
				 * https://mage2.pro/t/1574
				 */
				$this->ii()->setIsFraudDetected(true);
			}
		}
	}

	/**
	 * 2016-05-03
	 * @override
	 * @see \Df\Payment\Method::iiaKeys()
	 * @used-by \Df\Payment\Method::assignData()
	 * @return string[]
	 */
	protected function iiaKeys():array {return [Token::KEY];}

	/**
	 * 2016-04-21
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 */
	private function api():ChargeService {return $this->s()->apiCharge();}

	/**
	 * 2016-05-11
	 * @used-by self::charge()
	 */
	private function capturePreauthorized(T $auth):void {
		$this->leh(function() use($auth) {
			# 2016-05-03 https://github.com/CKOTech/checkout-php-library/wiki/Charges#capture-a-charge
			$capture = new ChargeCapture; /** @var ChargeCapture $capture */
			$this->disableEvent($auth->getTxnId(), 'charge.captured');
			$capture->setChargeId($auth->getTxnId());
			/**
			 * 2016-05-03
			 * «Positive integer (without decimal separator) representing the capture amount.
			 * Cannot exceed the authorised charge amount.
			 * Partial captures (capture amount is less than the authorised amount) are allowed.
			 * Only one partial capture is allowed per authorised charge.
			 * If not specified, the default is authorisation charge amount.»
			 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-actions/capture-card-charge#request-payload-fields
			 */
			$capture->setValue($this->amountFormat(dfp_due($this)));
			$response = $this->api()->CaptureCardCharge($capture); /** @var ChargeResponse $response */
			/**
			 * 2016-06-08
			 * A sample success response:
			 *	{
			 *		"id": "charge_test_910FE7244E5J7A98EFFA",
			 *		"originalId": "charge_test_352BA7344E5Z7A98EFE5",
			 *		"liveMode": false,
			 *		"created": "2016-05-08T11:01:06Z",
			 *		"value": 31813,
			 *		"currency": "USD",
			 *		"trackId": "ORD-2016/05-00123",
			 *		"chargeMode": 2,
			 *		"responseMessage": "Approved",
			 *		"responseAdvancedInfo": "Approved",
			 *		"responseCode": "10000",
			 *		"status": "Captured",
			 *		"hasChargeback": "N"
			 * 		...
			 *	}
			 */
			df_assert_eq(Response::S__CAPTURED, $response->getStatus());
			/**
			 * 2016-05-09
			 * As you can see in the sample response above,
			 * the «capture» transaction's ID differs from the ID of the previous transaction.
			 * We would need the «capture» transaction's ID for a future «refund» transaction,
			 * so save it.
			 * The payment is already assigned an autogenerated transaction ID like
			 * <the previous transaction>-capture,
			 * so we owerwrite the ID when we set the new one.
			 */
			$this->ii()->setTransactionId($response->getId());
		});
	}

	/**
	 * 2016-05-08
	 * A sample response when a 3D Secure validation is needed:
	 *	{
	 *		"id": "pay_tok_8fba2ead-625c-420d-80bf-c831d82951f4",
	 *		"liveMode": false,
	 *		"chargeMode": 2,
	 *		"responseCode": "10000",
	 *		"redirectUrl": "https://sandbox.checkout.com/api2/v2/3ds/acs/55367"
	 *	}
	 * @used-by self::getConfigPaymentAction()
	 */
	private function need3DS():bool {return dfc($this, function() {
		if ($url = $this->r()->a('redirectUrl')) { /** @var string|null $url */
			# 2016-05-07
			# If a 3D Secure validation is needed,
			# then $response->getId() returns a token (see a sample response above),
			# not the transaction's ID.
			# In this case, we postpone creating a Magento transaction yet,
			# so we do not call $payment->setTransactionId($response->getId());
			PO::setRedirectData($this, $url);
			# 2016-05-06
			# Postpone sending an order confirmation email to the customer,
			# because the customer should pass 3D Secure validation first.
			# «How is a confirmation email sent on an order placement?» https://mage2.pro/t/1542
			$this->o()->setCanSendNewEmailFlag(false);
		}
		return !!$url;
	});}

	/**
	 * 2016-05-08
	 * We use actionDesired() instead of @see \Dfe\CheckoutCom\Method::action()
	 * to avoid an infinite recursion.
	 * If Checkout.com marks a payment as «flagged»,
	 * then the @see \Dfe\CheckoutCom\Method::isCaptureDesired() method's result will be ignored,
	 * and the «authorize» action will be used instead.
	 * @used-by self::response()
	 */
	private function isCaptureDesired():bool {return AC::c($this->s()->actionDesired($this->o()));}

	/**
	 * 2016-04-23
	 * @used-by self::_refund()
	 * @used-by self::_void()
	 * @used-by self::capturePreauthorized()
	 * @return mixed
	 * @throws \Exception
	 */
	private function leh(\Closure $f) {return df_try($f, function(\Exception $e):void {
		if ($e instanceof CE) {
			$e = new LE(__($e->getErrorMessage()), $e);
		}
		df_sentry($this, $e);
		throw df_lx($e);
	});}

	/**
	 * 2017-02-10
	 * The result should be in the basic monetary unit (like dollars), not in fractions (like cents).
	 * https://mage2.pro/t/2687/3
	 * @used-by self::amountLimits()
	 */
	private function minimumAmount(string $c):float {return dfa([
		'AED' => 5, 'ARS' => 20, 'AUD' => 1.5, 'BHD' => .5, 'BIF' => 2000, 'BYR' => 20000, 'BZD' => 3
		,'CAD' => 1.5, 'CHF' => 1, 'CLF' => .05, 'CLP' => 700, 'COP' => 3000, 'DJF' => 200, 'DKK' => 7
		,'GBP' => 1, 'GNF' => 9300, 'EUR' => 1, 'HKD' => 8, 'IDR' => 13500, 'INR' => 70, 'ISK' => 120
		,'JPY' => 120, 'JOD' => 1, 'KMF' => 500, 'KRW' => 1200, 'KWD' => .5, 'NGN' => 350, 'NZD' => 1.5
		,'MXN' => 20, 'MYR' => 4.5, 'OMR' => .5, 'PEN' => 5, 'PHP' => 50, 'PYG' => 6000, 'RWF' => 850
		,'SGD' => 1.5, 'VND' => 22800, 'USD' => 1, 'VUV' => 120, 'XAF' => 650, 'XOF' => 650, 'XPF' => 120
		, 'ZAR' => 15
	], $c, 1);}

	/**
	 * 2016-05-15
	 * @used-by self::charge()
	 * @used-by self::getConfigPaymentAction()
	 * @used-by self::need3DS()
	 */
	private function r():Response {return dfc($this, function() {return new Response($this->res(), $this->o());});}

	/**
	 * 2016-05-07
	 * https://github.com/CKOTech/checkout-php-library/blob/V1.2.3/com/checkout/ApiServices/Charges/ResponseModels/Charge.php#L123
	 * @used-by self::charge()
	 * @used-by self::r()
	 */
	private function res():ChargeResponse {
		if (!isset($this->_response)) {$this->_response = self::leh(function() {return $this->api()->chargeWithCardTokenDf(
			Charge::build($this, $this->isCaptureDesired())
		);});}
		return $this->_response;
	}

	/**
	 * 2016-05-08
	 * @used-by self::response()
	 * @used-by self::responseSet()
	 * @var ChargeResponse
	 */
	private $_response;

	/**
	 * 2016-02-29
	 * @used-by https://github.com/mage2pro/checkout.com/blob/1.3.10/etc/di.xml#L9
	 * @used-by https://github.com/mage2pro/checkout.com/blob/1.3.10/etc/frontend/di.xml#L16
	 * @used-by \Df\Payment\Method::codeS()
	 * @see \Df\Payment\Settings::prefix()
	 */
	const CODE = 'dfe_checkout_com';

	/**
	 * 2016-05-11
	 * It is a metadata key, which stores the list of Checkout.com events,
	 * which we will ignore (do not process), because they are already processed.
	 * @used-by self::disableEvent()
	 * @used-by \Dfe\CheckoutCom\Handler::isInitiatedByMyself()
	 */
	const DISABLED_EVENTS = 'disabled_events';
}