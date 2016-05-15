<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\ResponseModels\Charge;
use com\checkout\ApiServices\Charges\ResponseModels\ChargeHistory;
use Dfe\CheckoutCom\Settings as S;
use Magento\Payment\Model\Method\AbstractMethod as M;
use Magento\Sales\Model\Order;
class Response extends \Df\Core\O {
	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @used-by \Dfe\CheckoutCom\Method::redirectUrl()
	 * @param string|string[]|null $key [optional]
	 * @return array(string => string)
	 */
	public function a($key = null) {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_json_decode($this->charge()->json);
			df_log($this->charge()->json);
		}
		return is_null($key) ? $this->{__METHOD__} : (
			is_array($key)
			? df_clean(dfa_select_ordered($this->{__METHOD__}, $key))
			: dfa($this->{__METHOD__}, $key)
		);
	}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * Оказывается, что если платёжный шлюз наделяет транзакцию состоянием «Flagged»,
	 * то параметр autoCapture шлюзом игнорируется,
	 * и нужно отдельно проводить транзакцию capture.
	 * https://mage2.pro/t/1565
	 * Пришёл к разумной мысли для таких транзакций проводить процедуру Review.
	 * @used-by \Dfe\CheckoutCom\Method::getConfigPaymentAction()
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @return string
	 */
	public function action() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} =
				$this->flagged() || !$this->waitForCapture()
				? M::ACTION_AUTHORIZE
				: S::s()->actionDesired($this->order()->getCustomerId())
			;
		}
		return $this->{__METHOD__};
	}

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
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @used-by \Dfe\CheckoutCom\Response::action()
	 * @return bool
	 */
	public function flagged() {return self::$S__FLAGGED === $this->charge()->getStatus();}

	/**
	 * 2016-05-11
	 * Этот метод решает описанную ниже проблему.
	 *
	 * 2016-05-10
	 * Если мы проводили платёж с параметром autoCapture,
	 * то Checkout.com на самом деле сразу проводит 2 транзации: authorize и capture.
	 * При этом в ответе Checkout.com присылает только идентификатор транзации authorize.
	 * А получается, что мы присваиваем этот идентификатор транзакции capture внутри Magento.
	 *
	 * 2016-05-11
	 * Кстати, в документации так и сказано:
	 * http://developers.checkout.com/docs/server/api-reference/charges/refund-card-charge
	 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
	 * «For an Automatic Capture, the Charge Response will contain
	 * the Charge ID of the Auth Charge. This ID cannot be used.»
	 *
	 * 2016-05-11
	 * Вчера я думал, что в описанной выше ситуации (autoCapture)
	 * мы не можем узнать идентификатор транзации capture по идентификатору транзации authorize.
	 * Но вот теперь пришёл к мысли использовать для этого запрос «Get Charge History»:
	 * http://developers.checkout.com/docs/server/api-reference/charges/get-charge-history
	 * «This is a quick way to view a charge status, rather than searching through webhooks»
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @return string
	 * @throws \Exception
	 */
	public function magentoTransactionId() {
		if (!isset($this->{__METHOD__})) {
			/** @var Response $response */
		    $response = $this->charge();
			/**
			 * 2016-05-11
			 * Раньше тут стояло просто ('Y' !== $response->getAutoCapture())
			 * Это неправильно, потому что транзакция могла быть помечена как Flagged,
			 * и тогда такая транзакция эквивалентна authorize, а не capture,
			 * хотя в ответе параметр autoCapture будет иметь значение 'Y'.
			 *
			 * 2016-05-15
			 * Раньше тут стояло:
			 * 'Y' !== $response->getAutoCapture() || $this->isChargeFlagged()
			 */
			/** @var string $result */
			if (M::ACTION_AUTHORIZE === $this->action()) {
				$result = $response->getId();
			}
			else {
				try {
					/**
					 * 2016-05-11
					 * Когда выполняешь этот код без отладчика,
					 * то в результате приходит не 2 транзации в состояниях Authorized и Сaptured,
					 * а одна транзакция в состоянии Pending.
					 * В этом случае надо просто подождать...
					 * Потом в ответе приходит одна транзакция в состоянии Authorized.
					 * Надо снова подождать...
					 *
					 * 2016-05-15
					 * Вчера пришлось ждать транзакцию в состоянии Captured аж 14 секунд.
					 * Я пришёл к мысли, что не стоит реального покупателя заставлять так ждать,
					 * и поэтому для реальных покупателей лучше ВСЕГДА
					 * в Magento первой транзакцией делать Authorize, а далее,
					 * если администратор магазина указал в настройках, что он хочет транзацию Capture,
					 * то принимать транзакцию Capture через Webhooks.
					 */
					/** @var int $numRetries */
					/**
					 * 2016-05-15
					 * Пока максимум приходилось ждать 14 секунд,
					 * но на всякий случай поставил 60.
					 * Можно, конечно, ждать и дольше, но вряд ли это нужно.
					 */
					$numRetries = 60;
					$result = null;
					while ($numRetries && !$result) {
						/** @var ChargeHistory $history */
						$history = $this->api()->getChargeHistory($response->getId());
						df_log(print_r($history->getCharges(), true));
						/**
						 * 2016-05-11
						 * Транзация capture содержится в массиве первой, затем идёт транзация authorize.
						 * «[Checkout.com]
						 * @uses \com\checkout\ApiServices\Charges\ChargeService::getChargeHistory()
						 * sample response»
						 * https://mage2.pro/t/1601
						 */
						/** @var Charge $chargeCapture */
						$chargeCapture = df_first($history->getCharges());
						if ('Captured' === $chargeCapture->getStatus()) {
							$result = $chargeCapture->getId();
						}
						else {
							sleep(1);
							$numRetries--;
						}
					}
				}
				catch (\Exception $e) {
					df_log($e);
					throw $e;
				}
			}
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
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
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @return bool
	 */
	public function valid() {
		return in_array($this->charge()->getStatus(), ['Authorised', self::$S__FLAGGED]);
	}

	/**
	 * 2016-04-21
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return ChargeService
	 */
	private function api() {return S::s()->apiCharge();}

	/** @return Charge */
	private function charge() {return $this[self::$P__CHARGE];}

	/** @return Order */
	private function order() {return $this[self::$P__ORDER];}

	/**
	 * 2016-05-15
	 * @return bool
	 */
	private function waitForCapture() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_is_localhost() || S::s()->waitForCapture();
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-15
	 * @override
	 * @return void
	 */
	protected function _construct() {
		parent::_construct();
		$this
			->_prop(self::$P__CHARGE, Charge::class)
			->_prop(self::$P__ORDER, Order::class)
		;
	}

	/**
	 * 2016-05-15
	 * @param Charge $charge
	 * @param Order $order
	 * @return $this
	 */
	public static function s(Charge $charge, Order $order) {
		/** @var array(string => $this) */
		static $cache;
		if (!isset($cache[$charge->getId()])) {
			$cache[$charge->getId()] = new self([self::$P__CHARGE => $charge, self::$P__ORDER => $order]);
		}
		return $cache[$charge->getId()];
	}

	/** @var string */
	private static $P__CHARGE = 'charge';
	/** @var string */
	private static $P__ORDER = 'order';

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
}