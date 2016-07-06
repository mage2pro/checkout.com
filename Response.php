<?php
namespace Dfe\CheckoutCom;
/**
 * 2016-06-08
 * I renamed it to get rid of the following
 * Magento 2 compiler (bin/magento setup:di:compile) failure:
 * «Fatal error: Cannot use com\checkout\ApiServices\Charges\ResponseModels\Charge as Charge
 * because the name is already in use in vendor/mage2pro/checkout.com/Response.php on line 4»
 * http://stackoverflow.com/questions/17746481
 */
use com\checkout\ApiServices\Charges\ResponseModels\Charge as CCharge;
use com\checkout\ApiServices\Charges\ResponseModels\ChargeHistory;
use com\checkout\ApiServices\SharedModels\Charge as SCharge;
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
	 * It turns out that if a payment gateway transaction returns a «Flagged» state,
	 * The parameter autoCapture is ignored by the gateway
	 * A transaction capture needs to be separately triggered 
	 * https://mage2.pro/t/1565
	 * It is then a good idea to do a Review procedure on such transactions.
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
	 * This method solves the problem described below
	 *
	 * 2016-05-10
	 * If autoCapture is enabled, Checkout.com executes 2 transactions: Authorize and Capture.
 	 * The response sent back by Checkout.com only contains the Authorize transaction ID. 
	 * We assign this Authorize transaction ID within Magento
	 *
	 * 2016-05-11
	 * Side note stated in the documentation:
	 * http://developers.checkout.com/docs/server/api-reference/charges/refund-card-charge
	 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
	 * «For an Automatic Capture, the Charge Response will contain
	 * the Charge ID of the Auth Charge. This ID cannot be used.»
	 *
	 * 2016-05-11
	 * About the use case described above (autoCapture)
	 * We cannot get Capture transaction ID from the Authorize transaction ID.
 	 * But we can use «Get Charge History» for this request :
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
			 * Previously this was simply done: ('Y' !== $response->getAutoCapture())
			 * This is wrong, because the transaction can be marked as Flagged,
			 * and in that case the transaction will be equivalent to Authorize
			 * although the response will have autoCapture set to 'Y'.
			 *
			 * 2016-05-15
			 * Previously:
			 * 'Y' !== $response->getAutoCapture() || $this->isChargeFlagged()
			 */
			$this->{__METHOD__} =
				M::ACTION_AUTHORIZE === $this->action()
				? $response->getId()
				: self::getCaptureCharge($response->getId())->getId()
			;
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * Handled the additional "Flagged" status: https://mage2.pro/t/1565
		{
			"id": "charge_test_253DB7144E5Z7A98EED4",
			"responseMessage": "40142 - Threshold Risk",
			"responseAdvancedInfo": "",
			"responseCode": "10100",
			"status": "Flagged",
			"authCode": "188986"
		}
	 *
	 * 2016-05-15
	 * Although Checkout.com's interface can show «Authorised - 3D»,
	 * the object will remain «Authorised».
	 *
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @return bool
	 */
	public function valid() {
		return in_array($this->charge()->getStatus(), [self::$S__AUTHORISED, self::$S__FLAGGED]);
	}

	/** @return CCharge */
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
			->_prop(self::$P__CHARGE, CCharge::class)
			->_prop(self::$P__ORDER, Order::class)
		;
	}

	/**
	 * 2016-05-15
	 * @param string $authId
	 * @return CCharge
	 * @throws \Exception
	 */
	public static function getCaptureCharge($authId) {
		/** @bar CCharge $result */
		$result = null;
		try {
			/**
			 * 2016-05-11
			 * When this code is executed without a debugger,
			 * the response doesn't contain 2 transactions (Authorized and Сaptured),
			 * but one transaction (Pending).
			 * If this happens, we should wait...
			 * then the response will show 1 Authorized transaction.
			 * We have to wait again...
			 *
			 * 2016-05-15
			 * When testing, transactions were taking up to 14 seconds to get Captured.
			 * I concluded that we should force the real buyer to wait,
			 * it is therefore always better to do a first Authorize transaction,
			 * and then if the Magento merchant Captured the transaction from the Checkout.com hub,
			 * capture the transaction on Magento using Webhooks
			 */
			/** @var int $numRetries */
			/**
			 * 2016-05-15
			 * For now the maximum wait time was 14 seconds.
			 * The limit is set to 60 seconds just in case.
			 * It can be set to more, but it is unlikely that more than 60s are required.
			 */
			$numRetries = 60;
			$result = null;
			while ($numRetries && !$result) {
				/** @var ChargeHistory $history */
				$history = S::s()->apiCharge()->getChargeHistory($authId);
				df_log(print_r($history->getCharges(), true));
				/**
				 * 2016-05-11
				 * The Captured transaction is returned in the first array of the response.
				 * The Authorised transaction is in the second one
				 * «[Checkout.com]
				 * @uses \com\checkout\ApiServices\Charges\ChargeService::getChargeHistory()
				 * sample response»
				 * https://mage2.pro/t/1601
				 */
				/** @var SCharge $sCharge */
				$sCharge = df_first($history->getCharges());
				/**
				 * 2016-05-15
				 * Although Checkout.com's interface can show «Captured - 3D»,
	 			 * the object will remain «Captured».
				 */
				if (self::S__CAPTURED === $sCharge->getStatus()) {
					$result = S::s()->apiCharge()->getCharge($sCharge->getId());
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
		df_assert($result);
		return $result;
	}

	/**
	 * 2016-05-15
	 * @param CCharge $charge
	 * @param Order $order
	 * @return $this
	 */
	public static function s(CCharge $charge, Order $order) {
		/** @var array(string => $this) */
		static $cache;
		if (!isset($cache[$charge->getId()])) {
			$cache[$charge->getId()] = new self([self::$P__CHARGE => $charge, self::$P__ORDER => $order]);
		}
		return $cache[$charge->getId()];
	}

	/**
	 * 2016-05-15
	 * Although Checkout.com's interface can show «Captured - 3D»,
	 * the object will remain «Captured».
	 * @var string
	 */
	const S__CAPTURED = 'Captured';

	/** @var string */
	private static $P__CHARGE = 'charge';
	/** @var string */
	private static $P__ORDER = 'order';

	/**
	 * 2016-05-15
	 * Although Checkout.com's interface can show «Authorised - 3D»,
	 * the object will remain «Authorised».
	 * @var string
	 */
	private static $S__AUTHORISED = 'Authorised';
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