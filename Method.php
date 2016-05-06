<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as ChargeResponse;
use com\checkout\ApiServices\Charges\RequestModels\ChargeCapture;
use com\checkout\ApiServices\Charges\RequestModels\ChargeVoid;
use com\checkout\ApiServices\Charges\RequestModels\ChargeRefund;
use com\checkout\helpers\ApiHttpClientCustomException as CE;
use Dfe\CheckoutCom\Settings as S;
use Dfe\CheckoutCom\Source\Action;
use Exception as E;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Payment\Model\Info;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
class Method extends \Df\Payment\Method {
	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::acceptPayment()
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @return bool
	 */
	public function acceptPayment(InfoInterface $payment) {
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
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param float $amount
	 * @return $this
	 */
	public function authorize(InfoInterface $payment, $amount) {
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
	 * 2016-03-15
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
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param float $amount
	 * @return $this
	 * @throws \Stripe\Error\Card
	 */
	public function capture(InfoInterface $payment, $amount) {
		if (!$payment[self::ALREADY_DONE]) {
			$this->charge($payment, $amount);
		}
		return $this;
	}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::denyPayment()
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @return bool
	 */
	public function denyPayment(InfoInterface $payment) {return true;}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::getConfigPaymentAction()
	 * @return string
	 */
	public function getConfigPaymentAction() {
		return $this->isTheCustomerNew() ? S::s()->actionForNew() : S::s()->actionForReturned();
	}

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
	 * @see \Df\Payment\Method::initialize()
	 * @param string $paymentAction
	 * @param object $stateObject
	 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L2336-L346
	 * @see \Magento\Sales\Model\Order::isPaymentReview()
	 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order.php#L821-L832
	 * @return $this
	 */
	public function initialize($paymentAction, $stateObject) {
		$stateObject['state'] = Order::STATE_PAYMENT_REVIEW;
		return $this;
	}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::isInitializeNeeded()
	 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L2336-L346
	 * @return bool
	 */
	public function isInitializeNeeded() {return Action::REVIEW === $this->getConfigPaymentAction();}

	/**
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::refund()
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param float $amount
	 * @return $this
	 */
	public function refund(InfoInterface $payment, $amount) {
		if (!$payment[self::ALREADY_DONE]) {
			$this->_refund($payment, $amount);
		}
		return $this;
	}

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
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @return $this
	 */
	public function void(InfoInterface $payment) {
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
				$this->api()->voidCharge($auth->getTxnId(), $void);
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
	 * 2016-03-17
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param float|null $amount [optional]
	 * @return void
	 */
	private function _refund(InfoInterface $payment, $amount = null) {
		$this->api(function() use($payment, $amount) {
			/**
			 * 2016-03-17
			 * Метод @uses \Magento\Sales\Model\Order\Payment::getAuthorizationTransaction()
			 * необязательно возвращает транзакцию типа «авторизация»:
			 * в первую очередь он стремится вернуть родительскую транзакцию:
			 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment/Transaction/Manager.php#L31-L47
			 * Это как раз то, что нам нужно, ведь наш модуль может быть настроен сразу на capture,
			 * без предварительной транзакции типа «авторизация».
			 */
			/** @var Transaction|false $parent */
			$parent = $payment->getAuthorizationTransaction();
			if ($parent) {
				/** @var Creditmemo $cm */
				$cm = $payment->getCreditmemo();
				/**
				 * 2016-03-24
				 * Credit Memo и Invoice отсутствуют в сценарии Authorize / Capture
				 * и присутствуют в сценарии Capture / Refund.
				 */
				if (!$cm) {
					$metadata = [];
				}
				else {
					/** @var Invoice $invoice */
					$invoice = $cm->getInvoice();
					$metadata = df_clean([
						'Comment' => $payment->getCreditmemo()->getCustomerNote()
						,'Credit Memo' => $cm->getIncrementId()
						,'Invoice' => $invoice->getIncrementId()
					])
						+ $this->metaAdjustments($cm, 'positive')
						+ $this->metaAdjustments($cm, 'negative')
					;
				}
				// 2016-03-16
				// https://stripe.com/docs/api#create_refund
				\Stripe\Refund::create(df_clean([
					// 2016-03-17
					// https://stripe.com/docs/api#create_refund-amount
					'amount' => !$amount ? null : self::amount($payment, $amount)
					/**
					 * 2016-03-18
					 * Хитрый трюк,
					 * который позволяет нам не ханиматься хранением идентификаторов платежей.
					 * Система уже хранит их в виде «ch_17q00rFzKb8aMux1YsSlBIlW-capture»,
					 * а нам нужно лишь отсечь суффиксы (Stripe не использует символ «-»).
					 */
					,'charge' => df_first(explode('-', $parent->getTxnId()))
					// 2016-03-17
					// https://stripe.com/docs/api#create_refund-metadata
					,'metadata' => $metadata
					// 2016-03-18
					// https://stripe.com/docs/api#create_refund-reason
					,'reason' => 'requested_by_customer'
				]));
			}
		});
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
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @param float|null $amount [optional]
	 * @param bool|null $capture [optional]
	 * @return $this
	 * @throws \Stripe\Error\Card
	 */
	private function charge(InfoInterface $payment, $amount = null, $capture = true) {
		self::leh(function() use($payment, $amount, $capture) {
			/** @var Transaction|false|null $auth */
			$auth = !$capture ? null : $payment->getAuthorizationTransaction();
			if ($auth) {
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
				$this->api()->CaptureCardCharge($capture);
			}
			else {
				/**
				 * 2016-04-23
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#response
				 */
				/** @var ChargeResponse $response */
				//xdebug_break();
			    $response = $this->api()->chargeWithCardToken(Charge::build(
					$payment, $this->iia(self::$TOKEN), $amount, $capture
				));
				/**
				 * 2016-05-02
				 * Иначе операция «void» (отмена авторизации платежа) будет недоступна:
				 * «How is a payment authorization voiding implemented?»
				 * https://mage2.pro/t/938
				 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
				 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
				 */
				$payment->setTransactionId($response->getId());
				/** @var \Magento\Sales\Model\Order $order */
				$order = $payment->getOrder();
				/**
				 * 2016-05-03
				 * https://github.com/CKOTech/checkout-php-library/blob/V1.2.3/com/checkout/ApiServices/Charges/ResponseModels/Charge.php#L123
				 * Пример ответа сервера в случае необъодимости проверки 3D-Secure:
					{
						"id": "pay_tok_8fba2ead-625c-420d-80bf-c831d82951f4",
						"liveMode": false,
						"chargeMode": 2,
						"responseCode": "10000",
						"redirectUrl": "https://sandbox.checkout.com/api2/v2/3ds/acs/55367"
					}
				 * @var string|null $redirectUrl
				 */
				$redirectUrl = dfa(df_json_decode($response->json), 'redirectUrl');
				if ($redirectUrl) {
					$payment->setAdditionalInformation(self::REDIRECT_URL, $redirectUrl);
					$payment->setIsTransactionClosed(false);
					$order->setState(Order::STATE_NEW);
				}
				else if ('Authorised' === $response->getStatus()) {
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
				}
				//xdebug_break();
			}
		});
		return $this;
	}

	/**
	 * 2016-03-18
	 * @param Creditmemo $cm
	 * @param string $type
	 * @return array(string => float)
	 */
	private function metaAdjustments(Creditmemo $cm, $type) {
		/** @var string $iso3Base */
		$iso3Base = $cm->getBaseCurrencyCode();
		/** @var string $iso3 */
		$iso3 = $cm->getOrderCurrencyCode();
		/** @var bool $multiCurrency */
		$multiCurrency = $iso3Base !== $iso3;
		/**
		 * 2016-03-18
		 * @uses \Magento\Sales\Api\Data\CreditmemoInterface::ADJUSTMENT_POSITIVE
		 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Api/Data/CreditmemoInterface.php#L32-L35
		 * @uses \Magento\Sales\Api\Data\CreditmemoInterface::ADJUSTMENT_NEGATIVE
		 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Api/Data/CreditmemoInterface.php#L72-L75
		 */
		/** @var string $key */
		$key = 'adjustment_' . $type;
		/** @var float $a */
		$a = $cm[$key];
		/** @var string $label */
		$label = ucfirst($type) . ' Adjustment';
		return !$a ? [] : (
			!$multiCurrency
			? [$label => $a]
			: [
				"{$label} ({$iso3})" => $a
				/**
				 * 2016-03-18
				 * @uses \Magento\Sales\Api\Data\CreditmemoInterface::BASE_ADJUSTMENT_POSITIVE
				 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Api/Data/CreditmemoInterface.php#L112-L115
				 * @uses \Magento\Sales\Api\Data\CreditmemoInterface::BASE_ADJUSTMENT_NEGATIVE
				 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Api/Data/CreditmemoInterface.php#L56-L59
				 */
				,"{$label} ({$iso3Base})" => $cm['base_' . $key]
			]
		);
	}

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
	 * @used-by https://code.dmitry-fedyuk.com/m2e/checkout.com/blob/fc9f731/etc/di.xml#L9
	 */
	const CODE = 'dfe_checkout_com';

	/**
	 * 2016-05-04
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @used-by
	 */
	const REDIRECT_URL = 'dfe_redirect_url';

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
	 * @param $payment InfoInterface|Info|OrderPayment
	 * @param float $amount
	 * @return int
	 */
	public static function amount(InfoInterface $payment, $amount) {
		/** @var string[] $m1000 */
		static $m1000 = ['BHD', 'KWD', 'OMR', 'JOD'];
		/** @var string $iso3 */
		$iso3 = $payment->getOrder()->getBaseCurrencyCode();
		return ceil($amount * (in_array($iso3, $m1000) ? 1000 : 100));
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