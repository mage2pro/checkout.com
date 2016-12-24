<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\RequestModels\ChargeCapture;
use com\checkout\ApiServices\Charges\RequestModels\ChargeRefund;
use com\checkout\ApiServices\Charges\RequestModels\ChargeUpdate;
use com\checkout\ApiServices\Charges\RequestModels\ChargeVoid;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use com\checkout\helpers\ApiHttpClientCustomException as CE;
use Df\Payment\PlaceOrder;
use Dfe\CheckoutCom\Patch\ChargeService;
use Dfe\CheckoutCom\Settings as S;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Payment\Model\Info as I;
use Magento\Payment\Model\InfoInterface as II;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order as O;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Payment as OP;
use Magento\Sales\Model\Order\Payment\Transaction;
class Method extends \Df\Payment\Method {
	/**
	 * 2016-05-09
	 * A «Flagged» payment can be handled the same way as an «Authorised» payment:
	 * we can «capture» or «void» it.
	 * @override
	 * @see \Df\Payment\Method::acceptPayment()
	 * @param II|I|OP $payment
	 * @return bool
	 */
	public function acceptPayment(II $payment) {
		// 2016-03-15
		// The obvious $this->charge($payment) is not quite correct,
		// because an invoice will not be created in this case.
		$payment->capture();
		return true;
	}

	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Payment\Method::canCapture()
	 * @return bool
	 */
	public function canCapture() {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canCapturePartial()
	 * @return bool
	 */
	public function canCapturePartial() {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canRefund()
	 * @return bool
	 */
	public function canRefund() {return true;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::canRefundPartialPerInvoice()
	 * @return bool
	 */
	public function canRefundPartialPerInvoice() {return true;}

	/**
	 * 2016-05-08
	 * I have disabled the Review mode, because if Checkout.com wants 3D-Secure,
	 * the shop can not prevent it, and it will break the Review mode,
	 * because a shop's administrator is unable to pass 3D-Secure validation
	 * for a customer's bank card.
	 *
	 * 2016-05-09
	 * If Checkout.com marks a payment as «Flagged»,
	 * then it ignores the «autoCapture» request parameter,
	 * so the shop should additionally do the «capture» operation: https://mage2.pro/t/1565
	 * So we can employ the Review mode for such payments.
	 *
	 * @override
	 * @see \Df\Payment\Method::canReviewPayment()
	 * @return bool
	 */
	public function canReviewPayment() {return true;}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::canVoid()
	 * @return bool
	 */
	public function canVoid() {return true;}

	/**
	 * 2016-05-09
	 * A «Flagged» payment can be handled the same way as an «Authorised» payment:
	 * we can «capture» or «void» it.
	 * @override
	 * @see \Df\Payment\Method::denyPayment()
	 * @param II|I|OP  $payment
	 * @return bool
	 */
	public function denyPayment(II $payment) {
		/**
		 * 2016-05-09
		 * Similar to https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Controller/Adminhtml/Order/VoidPayment.php#L22
		 */
		$payment->void(new \Magento\Framework\DataObject());
		return true;
	}

	/**
	 * 2016-05-11
	 * Mark an event as already processed,
	 * so when Checkout.com will notify us about the event,
	 * we will just skip this notification.
	 * @param string $transactionId
	 * @param string $eventId
	 * @return void
	 */
	public function disableEvent($transactionId, $eventId) {
		/** @var ChargeResponse $charge */
		$charge = $this->api()->getCharge($transactionId);
		/** @var array(string => string) $metadata */
		$metadata = df_nta($charge->getMetadata());
		/** @var string[] $events */
		$events = df_csv_parse(dfa($metadata, self::DISABLED_EVENTS, ''));
		if (!in_array($eventId, $events)) {
			$events[]= $eventId;
		}
		$metadata[self::DISABLED_EVENTS] = df_csv($events);
		// 2016-05-11
		// «Update a charge» https://github.com/CKOTech/checkout-php-library/wiki/Charges#update-a-charge
		/** @var ChargeUpdate $update */
		$update = new ChargeUpdate;
		$update->setChargeId($transactionId);
		$update->setMetadata($metadata);
		$this->api()->UpdateCardCharge($update);
	}

	/**
	 * @override
	 * @see \Df\Payment\Method::getConfigPaymentAction()
	 * @return string
	 *
	 * 2016-05-07
	 * We can arrive here only from @used-by \Magento\Sales\Model\Order\Payment::place()
	 * but from 2 different code points.
	 *
	 * 2016-05-08
	 * Returns null, if 3D-Secure validation is needed.
	 * @used-by \Magento\Sales\Model\Order\Payment::place()
	 * https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Sales/Model/Order/Payment.php#L334-L355
	 *
	 * 2016-05-09
	 * If Checkout.com marks a payment as «Flagged»,
	 * then it ignores the «autoCapture» request parameter,
	 * so the shop should additionally do the «capture» operation: https://mage2.pro/t/1565
	 * So we can employ the Review mode for such payments.
	 */
	public function getConfigPaymentAction() {return $this->redirectUrl() ? null : $this->r()->action();}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::getInfoBlockType()
	 * @return string
	 */
	public function getInfoBlockType() {return \Magento\Payment\Block\Info\Cc::class;}

	/**
	 * 2016-05-08
	 * @param ChargeResponse $response
	 */
	public function responseSet(ChargeResponse $response) {$this->_response = $response;}

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
	 * @param float $amount
	 * @return void
	 */
	protected function _refund($amount) {$this->leh(function() use($amount) {
		/** @var ChargeRefund $refund */
		$refund = new ChargeRefund;
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
		$refund->setValue($this->amountFormat($amount));
		$this->disableEvent($this->ii()->getRefundTransactionId(), 'charge.refunded');
		/** @var ChargeResponse $response */
		$response = $this->api()->refundCardChargeRequest($refund);
		/**
		 * 2016-05-09
		 * A sample success response:
			{
				"id": "charge_test_033B66645E5K7A9812E5",
				"originalId": "charge_test_427BB6745E5K7A9813C9",
				"responseMessage": "Approved",
				"responseAdvancedInfo": "Approved",
				"responseCode": "10000",
				"status": "Refunded",
				<...>
			}
		 */
		df_assert_eq('Refunded', $response->getStatus());
		$this->ii()->setTransactionId($response->getId());
	});}

	/**
	 * 2016-05-03
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#void-a-charge
	 * @override
	 * @see \Df\Payment\Method::_void()
	 * @return void
	 */
	protected function _void() {$this->leh(function() {
		/** @var Transaction|false|null $auth */
		$auth = $this->ii()->getAuthorizationTransaction();
		if ($auth) {
			/** @var ChargeVoid $void */
			$void = new ChargeVoid;
			/**
			 * 2016-05-03
			 * http://developers.checkout.com/docs/server/api-reference/charges/void-card-charge#request-payload-fields
			 * Although the documentation states that the «track_id» parameter is optional,
			 * the transaction will fail with empty ChargeVoid object:
			  {
				"errorCode": "70000",
				"message": "Validation error",
				"errors": ["An error was experienced while parsing the payload. Please ensure that the structure is correct."],
				"errorMessageCodes": ["70002"],
				"eventId": "cce6a001-ff91-451d-9b9e-0094d3c57984"
			 }
			 */
			$void->setTrackId($this->ii()->getOrder()->getIncrementId());
			$this->disableEvent($auth->getTxnId(), 'charge.voided');
			/** @var ChargeResponse $response */
			$response = $this->api()->voidCharge($auth->getTxnId(), $void);
			/**
			 * 2016-05-13
			 * This makes the «void» transaction ID the same in Magento and in Checkout.com
			 * (prevents Magento from generating a transaction ID like <Parent Identifier>-void).
			 */
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
	 * @return int
	 */
	protected function amountFactorTable() {return [
		1000 => 'BHD,KWD,OMR,JOD', 1 => 'BYR,BIF,DJF,GNF,KMF,XAF,CLF,XPF,JPY,PYG,RWF,KRW,VUV,VND,XOF'
	];}

	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Payment\Method::charge()
	 * @param float $amount
	 * @param bool|null $capture [optional]
	 * @return void
	 * @throws Exception
	 */
	protected function charge($amount, $capture = true) {
		/** @var Transaction|false|null $auth */
		$auth = !$capture ? null : $this->ii()->getAuthorizationTransaction();
		if ($auth) {
			$this->capturePreauthorized($auth, $amount);
		}
		else {
			/**
			 * 2016-04-23
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#response
			 */
			/** @var ChargeResponse $response */
		    $response = $this->response();
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
			/** @var Card $card */
			$card = $response->getCard();
			/**
			 * 2016-05-02
			 * https://mage2.pro/t/941
			 * «How is the \Magento\Sales\Model\Order\Payment's setCcLast4() / getCcLast4() used?»
			 */
			$this->ii()->setCcLast4($card->getLast4());
			// 2016-05-02
			$this->ii()->setCcType($card->getPaymentMethod());
			/**
			 * 2016-03-15
			 * Without it, a «void» operation will be unavailable:
			 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
			 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
			 * The transaction is considered complete unless «false» is explicitly specified.
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
	protected function iiaKeys() {return [self::$TOKEN];}

	/**
	 * 2016-04-21
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return ChargeService
	 */
	private function api() {return S::s()->apiCharge();}

	/**
	 * 2016-05-11
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @param Transaction $auth
	 * @param float $amount [optional]
	 * @return void
	 */
	private function capturePreauthorized(Transaction $auth, $amount) {
		$this->leh(function() use($auth, $amount) {
			// 2016-05-03
			// https://github.com/CKOTech/checkout-php-library/wiki/Charges#capture-a-charge
			/** @var ChargeCapture $capture */
			$capture = new ChargeCapture;
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
			$capture->setValue($this->amountFormat($amount));
			/** @var ChargeResponse $response */
			$response = $this->api()->CaptureCardCharge($capture);
			/**
			 * 2016-06-08
			 * A sample success response:
				{
					"id": "charge_test_910FE7244E5J7A98EFFA",
					"originalId": "charge_test_352BA7344E5Z7A98EFE5",
					"liveMode": false,
					"created": "2016-05-08T11:01:06Z",
					"value": 31813,
					"currency": "USD",
					"trackId": "ORD-2016/05-00123",
					"chargeMode": 2,
					"responseMessage": "Approved",
					"responseAdvancedInfo": "Approved",
					"responseCode": "10000",
					"status": "Captured",
					"hasChargeback": "N"
			 		...
				}
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
	 * We use @uses \Dfe\CheckoutCom\Method::actionDesired()
	 * instead of @see \Dfe\CheckoutCom\Method::action()
	 * to avoid a infinite recursion.
	 *
	 * If Checkout.com marks a payment as «flagged»,
	 * then the @see \Dfe\CheckoutCom\Method::isCaptureDesired() method's result will be ignored,
	 * and the «authorize» action will be used instead.
	 * @return bool
	 */
	private function isCaptureDesired() {return
		M::ACTION_AUTHORIZE_CAPTURE === S::s()->actionDesired($this->o()->getCustomerId())
	;}

	/**
	 * 2016-04-23
	 * @param callable $function
	 * @return mixed
	 * @throws \Exception
	 */
	private function leh(callable $function) {
		/** @var string|null $label */
		if (!$this->needLog()) {
			$label = null;
		}
		else {
			$label = df_caller_m();
			$this->log($label . ' BEFORE');
		}
		/** @var mixed $result */
		try {$result = $function();}
		catch (CE $e) {throw new LE(__($e->getErrorMessage()), $e);}
		catch (\Exception $e) {throw $e;}
		if ($label) {
			$this->log($label . ' AFTER');
		}
		return $result;
	}

	/**
	 * 2016-05-15
	 * @param string $message
	 * @return void
	 */
	private function log($message) {df_log($message);}

	/**
	 * 2016-05-15
	 * @return bool
	 */
	private function needLog() {return true;}

	/**
	 * 2016-05-15
	 * @return Response
	 */
	private function r() {return dfc($this, function() {return
		Response::sp($this->response(), $this->o())
	;});}

	/**
	 * 2016-05-08
	 * A sample response when a 3D-Secure validation is needed:
		{
			"id": "pay_tok_8fba2ead-625c-420d-80bf-c831d82951f4",
			"liveMode": false,
			"chargeMode": 2,
			"responseCode": "10000",
			"redirectUrl": "https://sandbox.checkout.com/api2/v2/3ds/acs/55367"
		}
	 * @return string|null
	 */
	private function redirectUrl() {return dfc($this, function() {
		/** @var string|null $result */
		$result = $this->r()->a('redirectUrl');
		if ($result) {
			/**
			 * 2016-05-07
			 * If a 3D-Secure validation is needed,
			 * then $response->getId() returns a token (see a sample response above),
			 * not the transaction's ID.
			 * In this case, we postpone creating a Magento transaction yet,
			 * so we do not call $payment->setTransactionId($response->getId());
			 */
			$this->iiaSet(PlaceOrder::DATA, $result);
			/**
			 * 2016-05-06
			 * Postpone sending an order confirmation email to the customer,
			 * because the customer should pass 3D-Secure validation first.
			 * «How is a confirmation email sent on an order placement?» https://mage2.pro/t/1542
			 */
			$this->o()->setCanSendNewEmailFlag(false);
		}
		return $result;
	});}

	/**
	 * 2016-08-21
	 * @return array(string => mixed)
	 */
	private function request() {return dfc($this, function() {return
		Charge::build($this, $this->iia(self::$TOKEN), $this->isCaptureDesired())
	;});}

	/**
	 * 2016-05-07
	 * https://github.com/CKOTech/checkout-php-library/blob/V1.2.3/com/checkout/ApiServices/Charges/ResponseModels/Charge.php#L123
	 * @return ChargeResponse
	 */
	private function response() {
		if (!isset($this->_response)) {$this->_response = self::leh(function() {
			return $this->api()->chargeWithCardTokenDf($this->request());
		});}
		return $this->_response;
	}

	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Method::response()
	 * @used-by \Dfe\CheckoutCom\Method::responseSet()
	 * @var ChargeResponse
	 */
	private $_response;

	/**
	 * 2016-02-29
	 * @used-by magento2/checkout.com/etc/di.xml
	 * @used-by magento2/checkout.com/etc/frontend/di.xml
	 * @used-by \Df\Payment\Method::codeS()
	 * @used-by https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/fa6d87f/etc/di.xml#L9
	 */
	const CODE = 'dfe_checkout_com';

	/**
	 * 2016-05-11
	 * It is a metadata key, which stores the list of Checkout.com events,
	 * which we will ignore (do not process), because they are already processed.
	 * @used-by \Dfe\CheckoutCom\Method::disableEvent()
	 * @used-by \Dfe\CheckoutCom\Handler::isInitiatedByMyself()
	 */
	const DISABLED_EVENTS = 'disabled_events';

	/**
	 * 2016-03-06
	 * @used-by \Dfe\CheckoutCom\Method::iiaKeys()
	 * @used-by \Dfe\CheckoutCom\Method::response()
	 * @var string
	 */
	private static $TOKEN = 'token';
}