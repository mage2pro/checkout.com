<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use com\checkout\ApiServices\Charges\RequestModels\ChargeCapture;
use com\checkout\ApiServices\Charges\RequestModels\ChargeVoid;
use com\checkout\ApiServices\Charges\RequestModels\ChargeRefund;
use com\checkout\helpers\ApiHttpClientCustomException as CE;
use Df\Sales\Model\Order\Payment as DfPayment;
use Dfe\CheckoutCom\Settings as S;
use Dfe\CheckoutCom\Source\Action;
use Exception as E;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Payment\Model\Info as I;
use Magento\Payment\Model\InfoInterface as II;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order as O;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OP;
use Magento\Sales\Model\Order\Payment\Transaction;
class Method extends \Df\Payment\Method {
	/**
	 * 2016-05-09
	 * Состояние «Flagged» равноценно состоянию «Authorised»:
	 * можно выполнить либо capture, либо void.
	 * @override
	 * @see \Df\Payment\Method::acceptPayment()
	 * @param II|I|OP $payment
	 * @return bool
	 */
	public function acceptPayment(II $payment) {
		// 2016-03-15
		// Напрашивающееся $this->charge($payment) не совсем верно:
		// тогда не будет создан invoice.
		$payment->capture();
		return true;
	}

	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Payment\Method::::authorize()
	 * @param II|I|OP $payment
	 * @param float $amount
	 * @return $this
	 */
	public function authorize(II $payment, $amount) {
		return $this->charge($payment, $amount, $capture = false);
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
	 * Режим review убрал, потому что мы ни как не можем повлиять на решение платёжного шлюза
	 * использовать проверку 3D-Secure,
	 * а администратор, разумеется, не сможет пройти проверку 3D-Secure за клиента.
	 *
	 * 2016-05-09
	 * Оказывается, что если платёжный шлюз наделяет транзакцию состоянием «Flagged»,
	 * то параметр autoCapture шлюзом игнорируется,
	 * и нужно отдельно проводить транзакцию capture.
	 * https://mage2.pro/t/1565
	 * Пришёл к разумной мысли для таких транзакций проводить процедуру Review.
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
	 * 2016-03-06
	 * @override
	 * @see \Df\Payment\Method::capture()
	 * @see https://stripe.com/docs/charges
	 *
	 * $amount содержит значение в учётной валюте системы.
	 * https://github.com/magento/magento2/blob/6ce74b2/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L37-L37
	 * https://github.com/magento/magento2/blob/6ce74b2/app/code/Magento/Sales/Model/Order/Payment/Operations/CaptureOperation.php#L76-L82
	 *
	 * @param II|I|OP $payment
	 * @param float $amount
	 * @return $this
	 * @throws \Stripe\Error\Card
	 */
	public function capture(II $payment, $amount) {
		if (!$payment[self::ALREADY_DONE]) {
			$this->charge($payment, $amount);
		}
		return $this;
	}

	/**
	 * 2016-05-09
	 * Состояние «Flagged» равноценно состоянию «Authorised»:
	 * можно выполнить либо capture, либо void.
	 * @override
	 * @see \Df\Payment\Method::denyPayment()
	 * @param II|I|OP  $payment
	 * @return bool
	 */
	public function denyPayment(II $payment) {return $this->void($payment);}

	/**
	 * @override
	 * @see \Df\Payment\Method::getConfigPaymentAction()
	 * @return string
	 *
	 * 2016-05-07
	 * Сюда мы попадаем только из метода @used-by \Magento\Sales\Model\Order\Payment::place()
	 * причём там наш метод вызывается сразу из двух мест и по-разному.
	 *
	 * 2016-05-08
	 * При необходимости проверки 3D-Secure возвращаем null.
	 * @used-by \Magento\Sales\Model\Order\Payment::place()
	 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L334-L355
	 *
	 * 2016-05-09
	 * Оказывается, что если платёжный шлюз наделяет транзакцию состоянием «Flagged»,
	 * то параметр autoCapture шлюзом игнорируется,
	 * и нужно отдельно проводить транзакцию capture.
	 * https://mage2.pro/t/1565
	 *
	 * Есть мысль проводить для транзакций Flagged процедуру Review.
	 */
	public function getConfigPaymentAction() {return $this->redirectUrl() ? null : $this->action();}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::getInfoBlockType()
	 * @return string
	 */
	public function getInfoBlockType() {return \Magento\Payment\Block\Info\Cc::class;}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::refund()
	 * @param II|I|OP $payment
	 * @param float $amount
	 * @return $this
	 */
	public function refund(II $payment, $amount) {
		if (!$payment[self::ALREADY_DONE]) {
			$this->_refund($payment, $amount);
		}
		return $this;
	}

	/**
	 * 2016-05-08
	 * @param ChargeResponse $response
	 */
	public function responseSet(ChargeResponse $response) {$this->_response = $response;}

	/**
	 * 2016-03-08
	 * @override
	 * @see \Df\Payment\Method::setStore()
	 * @param int $storeId
	 * @return void
	 */
	public function setStore($storeId) {
		parent::setStore($storeId);
		S::s()->setScope($storeId);
	}

	/**
	 * 2016-05-03
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#void-a-charge
	 * @override
	 * @see \Df\Payment\Method::void()
	 * @param II|I|OP $payment
	 * @return $this
	 */
	public function void(II $payment) {
		self::leh(function() use($payment) {
			/** @var Transaction|false|null $auth */
			$auth = $payment->getAuthorizationTransaction();
			if ($auth) {
				/** @var ChargeVoid $void */
				$void = new ChargeVoid;
				/**
				 * 2016-05-03
				 * http://developers.checkout.com/docs/server/api-reference/charges/void-card-charge#request-payload-fields
				 * Хотя в документации сказано, что track_id не является обязательным параметром,
				 * при пустом объекте ChargeVoid происходит сбой:
				  {
				 	"errorCode": "70000",
				 	"message": "Validation error",
				 	"errors": ["An error was experienced while parsing the payload. Please ensure that the structure is correct."],
				 	"errorMessageCodes": ["70002"],
				 	"eventId": "cce6a001-ff91-451d-9b9e-0094d3c57984"
				 }
				 */
				$void->setTrackId($payment->getOrder()->getIncrementId());
				/** @var ChargeResponse $response */
				$response = $this->api()->voidCharge($auth->getTxnId(), $void);
			}
		});
		return $this;
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
	 * 2016-05-08
	 * По аналогии с @see \Magento\Sales\Model\Order\Payment::processAction()
	 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L414
	 * и @see \Magento\Sales\Model\Order\Payment\Operations\AuthorizeOperation::authorize()
	 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment/Operations/AuthorizeOperation.php#L36-L36
	 * @return float
	 */
	private function _amount() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = $this->ii()->formatAmount($this->o()->getBaseTotalDue(), true);
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-03-17
	 * @param II|I|OP|DfPayment $payment
	 * @param float|null $amount [optional]
	 * @return void
	 */
	private function _refund(II $payment, $amount = null) {
		self::leh(function() use($payment, $amount) {
			/** @var ChargeRefund $refund */
			$refund = new ChargeRefund;
			/**
			 * 2016-05-09
			 * Идентификатор транзакции capture
			 * отличается от идентификатора предыдущей транзации.
			 * Для транзации refund
			 * нужно будет указывать именно идентификатор транзакции capture,
			 *
			 * Здесь вызовы $payment->getRefundTransactionId()
			 * и $payment->getParentTransactionId()
			 * равнозначны: они возвращают одно и то же значение.
			 *
			 * refund_transaction_id устанавливается здесь:
			 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L652
			 */
			$refund->setChargeId($payment->getRefundTransactionId());
			$refund->setValue(self::amount($payment, $amount));
			/** @var ChargeResponse $response */
			$response = $this->api()->refundCardChargeRequest($refund);
			/**
			 * 2016-05-09
			 * В случае успеха ответ сервера выглядит так:
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
			$payment->setTransactionId($response->getId());
		});
	}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * Оказывается, что если платёжный шлюз наделяет транзакцию состоянием «Flagged»,
	 * то параметр autoCapture шлюзом игнорируется,
	 * и нужно отдельно проводить транзакцию capture.
	 * https://mage2.pro/t/1565
	 * Пришёл к разумной мысли для таких транзакций проводить процедуру Review.
	 * @return string
	 */
	private function action() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = $this->isChargeFlagged() ? M::ACTION_AUTHORIZE : (
				$this->isTheCustomerNew() ? S::s()->actionForNew() : S::s()->actionForReturned()
			);
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-04-21
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return ChargeService
	 */
	private function api() {return S::s()->apiCharge();}

	/**
	 * 2016-03-07
	 * @override
	 * @see https://stripe.com/docs/charges
	 * @see \Df\Payment\Method::capture()
	 * @param II|I|OP $payment
	 * @param float|null $amount [optional]
	 * @param bool|null $capture [optional]
	 * @return $this
	 * @throws \Stripe\Error\Card
	 */
	private function charge(II $payment, $amount = null, $capture = true) {
		/** @var Transaction|false|null $auth */
		$auth = !$capture ? null : $payment->getAuthorizationTransaction();
		if ($auth) {
			self::leh(function() use($auth, $payment, $amount, $capture) {
				/**
				 * 2016-05-03
				 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#capture-a-charge
				 */
				/** @var ChargeCapture $capture */
				$capture = new ChargeCapture;
				$capture->setChargeId($auth->getTxnId());
				/**
				 * 2016-05-03
				 * «Positive integer (without decimal separator) representing the capture amount.
				 * Cannot exceed the authorised charge amount.
				 * Partial captures (capture amount is less than the authorised amount) are allowed.
				 * Only one partial capture is allowed per authorised charge.
				 * If not specified, the default is authorisation charge amount.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/capture-card-charge#request-payload-fields
				 */
				$capture->setValue(self::amount($payment, $amount));
				/** @var ChargeResponse $response */
				$response = $this->api()->CaptureCardCharge($capture);
				/**
				 * 2016-06-08
				 * В случае успеха ответ сервера выглядит так:
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
				df_assert_eq('Captured', $response->getStatus());
				/**
				 * 2016-05-09
				 * Как видно из приведённого выше ответа сервера,
				 * идентификатор транзакции capture
				 * отличается от идентификатора предыдущей транзации.
				 * Я так понял, что для транзации refund
				 * нужно будет указывать именно идентификатор транзакции capture,
				 * поэтому сохраняем его.
				 *
				 * При этом payment уже содержит transaction_id
				 * вида <предыдущая транзакция>-capture,
				 * и мы его перетираем, устанавливая свой.
				 */
				$payment->setTransactionId($response->getId());
			});
		}
		else {
			/**
			 * 2016-04-23
			 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#response
			 */
			/** @var ChargeResponse $response */
		    $response = $this->response();
			/**
			 * 2016-05-02
			 * Иначе операция «void» (отмена авторизации платежа) будет недоступна:
			 * «How is a payment authorization voiding implemented?»
			 * https://mage2.pro/t/938
			 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
			 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
			 */
			$payment->setTransactionId($response->getId());
			if (!self::isChargeValid($response)) {
				/**
				 * 2016-05-08
				 * Если платёжный шлюз отклонил транзакцию,
				 * то мы получаем ответ типа
					{
						"id": "charge_test_153AF6744E5J7A98E1D9",
						"responseMessage": "40144 - Threshold Risk - Decline",
						"responseAdvancedInfo": null,
						"responseCode": "40144",
						"status": "Declined",
						"authCode": "00000"
						...
					}
				 * Вот что с этим добром делать? Надо подумать...
				 */
				df_error(df_dump($this->responseA([
					'status', 'responseMessage', 'id', 'responseCode', 'authCode', 'responseAdvancedInfo'
				])));
			}
			else {
				/** @var Card $card */
				$card = $response->getCard();
				/**
				 * 2016-05-02
				 * https://mage2.pro/t/941
				 * «How is the \Magento\Sales\Model\Order\Payment's setCcLast4() / getCcLast4() used?»
				 */
				$payment->setCcLast4($card->getLast4());
				// 2016-05-02
				$payment->setCcType($card->getPaymentMethod());
				/**
				 * 2016-03-15
				 * Аналогично, иначе операция «void» (отмена авторизации платежа) будет недоступна:
				 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
				 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
				 * Транзакция ситается завершённой, если явно не указать «false».
				 */
				$payment->setIsTransactionClosed($capture);
				/**
				 * 2016-05-06
				 * Не получается здесь явно устанавливать состояние заказа вызовом
				 * $order->setState(O::STATE_PAYMENT_REVIEW);
				 * потому что это состояние перетрётся:
				 * @see \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand::execute()
				 * https://github.com/magento/magento2/blob/135f967/app/code/Magento/Sales/Model/Order/Payment/State/AuthorizeCommand.php#L15-L49
				 *
				 * Поэтому поступаем иначе.
				 * Флаг IsTransactionPending будет считан в том же методе
				 * @used-by \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand::execute()
				 * https://github.com/magento/magento2/blob/135f967/app/code/Magento/Sales/Model/Order/Payment/State/AuthorizeCommand.php#L26-L31
				 * И будет установлено состояние @see Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW
				 */
				$payment->setIsTransactionPending($this->isChargeFlagged());
			}
		}
		return $this;
	}

	/**
	 * 2016-05-08
	 * @return bool
	 */
	private function isCapture() {return M::ACTION_AUTHORIZE_CAPTURE === $this->action();}

	/**
	 * 2016-05-09
	 * «[Checkout.com] - What is a «Flagged» transaction?» https://mage2.pro/t/1565
		{
			"id": "charge_test_253DB7144E5Z7A98EED4",
			"responseMessage": "40142 - Threshold Risk",
			"responseAdvancedInfo": "",
			"responseCode": "10100",
			"status": "Flagged",
			"authCode": "188986"
		}
	 * @return bool
	 */
	private function isChargeFlagged() {
		return self::$S__FLAGGED === $this->response()->getStatus();
	}

	/**
	 * 2016-05-08
	 * Пример ответа сервера в случае необъодимости проверки 3D-Secure:
		{
			"id": "pay_tok_8fba2ead-625c-420d-80bf-c831d82951f4",
			"liveMode": false,
			"chargeMode": 2,
			"responseCode": "10000",
			"redirectUrl": "https://sandbox.checkout.com/api2/v2/3ds/acs/55367"
		}
	 * @return string|null
	 */
	private function redirectUrl() {
		if (!isset($this->{__METHOD__})) {
			/** @var string|null $result */
			$result = $this->responseA('redirectUrl');
			if ($result) {
				/**
				 * 2016-05-07
				 * В случае необходимости проверки 3D-Secure
				 * $response->getId() вернёт не идентификатор charge, а токен.
				 * Транзакцию пока не создаём, т.е.
				 * $payment->setTransactionId($response->getId());
				 * не вызываем.
				 *
				 * Вместо создания транзации запоминаем идентификатор платежа в udf1:
				 * https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/d8dcfd/Charge.php#L37
				 * @see \Dfe\CheckoutCom\Charge::_build()
				 *
				 * Извлекаем его здесь: https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/f57128/Controller/Index/Index.php#L65
				 */
				$this->iiaSet(self::REDIRECT_URL, $result);
				/**
				 * 2016-05-06
				 * Письмо-оповещение о заказе здесь ещё не должно отправляться.
				 * «How is a confirmation email sent on an order placement?» https://mage2.pro/t/1542
				 */
				$this->o()->setCanSendNewEmailFlag(false);
			}
			$this->{__METHOD__} = df_n_set($result);
		}
		return df_n_get($this->{__METHOD__});
	}

	/**
	 * 2016-05-07
	 * https://github.com/CKOTech/checkout-php-library/blob/V1.2.3/com/checkout/ApiServices/Charges/ResponseModels/Charge.php#L123
	 * @return ChargeResponse
	 */
	private function response() {
		if (!isset($this->_response)) {$this->_response = self::leh(function() {
			return $this->api()->chargeWithCardToken(Charge::build(
				$this->ii(), $this->iia(self::$TOKEN), $this->_amount(), $this->isCapture()
			));
		});}
		return $this->_response;
	}

	/**
	 * 2016-05-08
	 * @param string|string[]|null $key [optional]
	 * @return array(string => string)
	 */
	private function responseA($key = null) {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_json_decode($this->response()->json);
		}
		return is_null($key) ? $this->{__METHOD__} : (
			is_array($key)
			? df_clean(dfa_select_ordered($this->{__METHOD__}, $key))
			: dfa($this->{__METHOD__}, $key)
		);
	}

	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Method::response()
	 * @used-by \Dfe\CheckoutCom\Method::responseSet()
	 * @var ChargeResponse
	 */
	private $_response;

	/**
	 * 2016-03-26
	 * @used-by \Dfe\CheckoutCom\Method::capture()
	 * @used-by \Dfe\CheckoutCom\Method::refund()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge::payment()
	 */
	const ALREADY_DONE = 'dfe_already_done';

	/**
	 * 2016-02-29
	 * @used-by Dfe/Stripe/etc/frontend/di.xml
	 * @used-by \Dfe\CheckoutCom\ConfigProvider::getConfig()
	 * @used-by https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/fa6d87f/etc/di.xml#L9
	 */
	const CODE = 'dfe_checkout_com';

	/**
	 * 2016-05-04
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @used-by
	 */
	const REDIRECT_URL = 'dfe_redirect_url';

	/**
	 * 2016-05-09
	 * «[Checkout.com] - What is a «Flagged» transaction?» https://mage2.pro/t/1565
		{
			"id": "charge_test_253DB7144E5Z7A98EED4",
			"responseMessage": "40142 - Threshold Risk",
			"responseAdvancedInfo": "",
			"responseCode": "10100",
			"status": "Flagged",
			"authCode": "188986"
		}
	 * @var string
	 */
	private static $S__FLAGGED = 'Flagged';

	/**
	 * 2016-03-06
	 * @var string
	 */
	private static $TOKEN = 'token';

	/**
	 * 2016-04-21
	 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
	 * Expressed as a non-zero positive integer (i.e. decimal figures not allowed).
	 * Divide Bahraini Dinars (BHD), Kuwaiti Dinars (KWD),
	 * Omani Rials (OMR) and Jordanian Dinars (JOD) into 1000 units
	 * (e.g. "value = 1000" is equivalent to 1 Bahraini Dinar).
	 * Divide all other currencies into 100 units
	 * (e.g. "value = 100" is equivalent to 1 US Dollar).
	 * @param $payment II|I|OP
	 * @param float $amount
	 * @return int
	 */
	public static function amount(II $payment, $amount) {
		return ceil($amount * self::amountFactor($payment));
	}

	/**
	 * @param $payment II|I|OP
	 * @param int $amount
	 * @return float
	 */
	public static function amountReverse(II $payment, $amount) {
		return $amount / self::amountFactor($payment);
	}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * Учёл ещё состояние Flagged: https://mage2.pro/t/1565
		{
			"id": "charge_test_253DB7144E5Z7A98EED4",
			"responseMessage": "40142 - Threshold Risk",
			"responseAdvancedInfo": "",
			"responseCode": "10100",
			"status": "Flagged",
			"authCode": "188986"
		}
	 * @param ChargeResponse $charge
	 * @return bool
	 */
	public static function isChargeValid(ChargeResponse $charge) {
		return in_array($charge->getStatus(), ['Authorised', self::$S__FLAGGED]);
	}

	/**
	 * 2016-05-06
	 * @param $payment II|I|OP
	 * @return int
	 */
	private static function amountFactor(II $payment) {
		/** @var string[] $m1000 */
		static $m1000 = ['BHD', 'KWD', 'OMR', 'JOD'];
		return in_array($payment->getOrder()->getBaseCurrencyCode(), $m1000) ? 1000 : 100;
	}

	/**
	 * 2016-04-23
	 * @param callable $function
	 * @return mixed
	 * @throws LE
	 */
	private static function leh($function) {
		/** @var mixed $result */
		try {$result = $function();}
		catch (CE $e) {throw new LE(__($e->getErrorMessage()), $e);}
		catch (E $e) {throw df_le($e);}
		return $result;
	}
}