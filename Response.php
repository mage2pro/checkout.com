<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as CCharge;
use com\checkout\ApiServices\Charges\ResponseModels\ChargeHistory;
use com\checkout\ApiServices\SharedModels\Charge as SCharge;
use Df\Payment\Source\AC;
use Dfe\CheckoutCom\SDK\ChargeService as API;
use Dfe\CheckoutCom\Settings as S;
use Magento\Sales\Model\Order;
// 2016-05-15
//
// 2016-06-08
// I renamed it to get rid of the following
// Magento 2 compiler (bin/magento setup:di:compile) failure:
// «Fatal error: Cannot use com\checkout\ApiServices\Charges\ResponseModels\Charge as Charge
// because the name is already in use in vendor/mage2pro/checkout.com/Response.php on line 4»
// http://stackoverflow.com/questions/17746481
//
// 2016-07-17
// A sample failure response:
//	{
//		"id": "charge_test_153AF6744E5J7A98E1D9",
//		"responseMessage": "40144 - Threshold Risk - Decline",
//		"responseAdvancedInfo": null,
//		"responseCode": "40144",
//		"status": "Declined",
//		"authCode": "00000"
//		...
//	}
//
// 2016-08-05
// A sample failure response when some request params are invalid:
//	{
//		"errorCode": "70000",
//		"message": "Validation error",
//		"errors": [
//			"Invalid value for 'token'"
//		],
//		"errorMessageCodes": [
//			"70006"
// 		],
//		"eventId": "96320dfb-672c-4317-93d0-04317e8ea9bf"
//	}
final class Response {
	/**
	 * 2017-03-27
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @used-by \Dfe\CheckoutCom\Method::r()
	 * @param CCharge $c
	 * @param Order $o
	 */
	function __construct(CCharge $c, Order $o) {$this->_c = $c; $this->_o = $o; $this->_s = dfps($o);}

	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @used-by \Dfe\CheckoutCom\Method::ckoRedirectUrl()
	 * @param string|string[]|null $k [optional]
	 * @return array(string => string)
	 */
	function a($k = null) {
		/** @var array(string => string) $result */
		$result = dfc($this, function() {
			// 2016-09-07
			// https://github.com/CKOTech/checkout-php-library/blob/v1.2.4/com/checkout/ApiServices/Charges/ResponseModels/Charge.php?ts=4#L129
			/** @var array(string => string) $result */
			$result = df_json_decode($this->_c->{'json'});
			dfp_report($this, $result, 'response');
			return $result;
		});
		return is_null($k) ? $result : (
			is_array($k) ? df_clean(dfa_select_ordered($result, $k)) : dfa($result, $k)
		);
	}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * If Checkout.com marks a payment as «Flagged»,
	 * then it ignores the «autoCapture» request parameter,
	 * so the shop should additionally do the «capture» operation: https://mage2.pro/t/1565
	 * So we can employ the Review mode for such payments.
	 * @used-by \Dfe\CheckoutCom\Method::getConfigPaymentAction()
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @return string
	 */
	function action() {return dfc($this, function() {return $this->flagged() || !$this->waitForCapture()
		? AC::A : $this->_s->actionDesired($this->_o)
	;});}

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
	function flagged() {return self::$S__FLAGGED === $this->_c->getStatus();}

	/**
	 * 2016-08-05
	 * @used-by \Dfe\CheckoutCom\Exception::message()
	 * @used-by \Dfe\CheckoutCom\Response::messageC()
	 * @return bool
	 */
	function hasId() {return !!$this->a('id');}

	/**
	 * 2016-05-11
	 * The method solves the problem described below.
	 *
	 * 2016-05-10
	 * If we make a mayment with the «autoCapture» option enabled,
	 * then Checkout.com really makes 2 transactions (not 1): «authorize» and «capture».
	 * But in this case Checkout.com does not give us the ID of the «capture» transaction:
	 * it gives only the ID of the «authorize» transaction.
	 * We a forced to use the «authorize» transaction ID
	 * as the corresponding Magento «capture» transaction ID, which is bad.
	 *
	 * 2016-05-11
	 * The documentation confirms such Checkout.com behaviour:
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-actions/refund-card-charge
	 * «To process a refund the merchant must send the Charge ID of the Captured transaction»
	 * «For an Automatic Capture, the Charge Response will contain
	 * the Charge ID of the Auth Charge. This ID cannot be used.»
	 *
	 * 2016-05-11
	 * I have found the solution:
	 * we can use a «Get Charge History» API request
	 * to find out the ID of the «capture» transaction:
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/get-charge-history
	 * «This is a quick way to view a charge status, rather than searching through webhooks»
	 *
	 * 2016-05-11
	 * The previous code was ('Y' !== $response->getAutoCapture()).
	 * It was wrong, because Checkout.com could mark the payment as «Flagged».
	 * A «Flagged» transaction requires an additional «capture» transaction,
	 * so it should be treated as an «authorize» transaction
	 * despite the «autoCapture» option has the «Y» value.
	 *
	 * 2016-05-15
	 * The previous code was:
	 * 'Y' !== $response->getAutoCapture() || $this->isChargeFlagged()
	 *
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @return string
	 * @throws \Exception
	 */
	function magentoTransactionId() {return dfc($this, function() {return AC::A === $this->action()
		? $this->_c->getId() : self::getCaptureCharge($this->_c->getId())->getId()
	;});}

	/**
	 * 2016-07-17
	 * @used-by \Dfe\CheckoutCom\Exception::messageC()
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @return string
	 */
	function messageC() {return dfc($this, function() {
		/** @var string $result */
		if (!$this->hasId()) {
			$result = __(
				'Sorry, this payment method is not working now.'
				 .'<br/>Please use another payment method.'
			);
		}
		else {
			/** @var string $m1 */
			/** @var string $m2 */
			list($m1, $m2) = array_values($this->a(['responseMessage', 'responseAdvancedInfo']));
			/** @var string $m */
			$m = !$m2 || $m2 === $m1 ? $m1 : "{$m1} ({$m2})";
			$result = df_var($this->_s->messageFailure(), ['message' => $m]);
		}
		return $result;
	});}

	/**
	 * 2016-05-08
	 * 2016-05-09
	 * Added support for the «Flagged» status: https://mage2.pro/t/1565
	 *	{
	 *		"id": "charge_test_253DB7144E5Z7A98EED4",
	 *		"responseMessage": "40142 - Threshold Risk",
	 *		"responseAdvancedInfo": "",
	 *		"responseCode": "10100",
	 *		"status": "Flagged",
	 *		"authCode": "188986"
	 *	}
	 *
	 * 2016-05-15
	 * For the transactions, which the Checkout.com merchant backend shows as «Authorised - 3D»,
	 * the object «status» field's value will be «Authorised», not «Authorised - 3D».
	 *
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @used-by \Dfe\CheckoutCom\Method::charge()
	 * @return bool
	 */
	function valid() {return in_array($this->_c->getStatus(), [self::$S__AUTHORISED, self::$S__FLAGGED]);}

	/**
	 * 2016-05-15
	 * @return bool
	 */
	private function waitForCapture() {return dfc($this, function() {return
		df_is_localhost() || $this->_s->waitForCapture()
	;});}

	/**
	 * 2016-05-15
	 * @used-by magentoTransactionId()
	 * @used-by \Dfe\CheckoutCom\Handler\CustomerReturn::p()
	 * @param string $authId
	 * @return CCharge
	 * @throws \Exception
	 */
	static function getCaptureCharge($authId) {
		/** @bar CCharge $result */
		$result = null;
		try {
			/**
			 * 2016-05-11
			 * When this code is executed without a debugger,
			 * then the response does not contain 2 transactions
			 * (with the «Authorized» and «Сaptured» statuses),
			 * but a single transaction with the «Pending» status.
			 * In this case we sould wait.
			 * The next response will contain a single transaction with the «Authorized» status.
			 * We should wait again.
			 *
			 * 2016-05-15
			 * Yesterday, I waited 14 seconds for a «Сaptured» transaction.
			 * I concluded that we should not force a real buyer to wait so long.
			 * So, from now, we always make an «authorize» transaction first,
			 * and if the store's administrator enables the automatic capture feature,
			 * then we make the «capture» transaction in a webhook, not here.
			 */
			/** @var int $numRetries */
			$numRetries = 60;
			$result = null;
			/** @var S $s */
			$s = dfps(__CLASS__);
			/** @var API $api */
			$api = $s->apiCharge();
			while ($numRetries && !$result) {
				/** @var ChargeHistory $history */
				$history = $api->getChargeHistory($authId);
				df_log(print_r($history->getCharges(), true));
				/**
				 * 2016-05-11
				 * A «Сaptured» transaction is always first in the response array.
				 * An «Authorized» transaction is the second.
				 * https://mage2.pro/t/1601
				 */
				/** @var SCharge $sCharge */
				$sCharge = df_first($history->getCharges());
				/**
				 * 2016-05-15
				 * For the transactions,
				 * which the Checkout.com merchant backend shows as «Captured - 3D»,
				 * the object «status» field's value will be «Captured», not «Captured - 3D».
				 */
				if (self::S__CAPTURED === $sCharge->getStatus()) {
					$result = $api->getCharge($sCharge->getId());
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
		return df_assert($result);
	}

	/**
	 * 2017-03-27
	 * @used-by __construct()
	 * @used-by a()
	 * @used-by flagged()
	 * @used-by magentoTransactionId()
	 * @used-by valid()
	 * @var CCharge
	 */
	private $_c;

	/**
	 * 2017-03-27
	 * @used-by __construct()
	 * @used-by action()
	 * @var Order
	 */
	private $_o;

	/**
	 * 2017-03-27
	 * @used-by __construct()
	 * @used-by action()
	 * @used-by messageC()
	 * @used-by waitForCapture()
	 * @used-by
	 * @used-by
	 * @used-by
	 * @var S
	 */
	private $_s;

	/**
	 * 2016-05-15
	 * For the transactions,
	 * which the Checkout.com merchant backend shows as «Captured - 3D»,
	 * the object «status» field's value will be «Captured», not «Captured - 3D».
	 * @var string
	 */
	const S__CAPTURED = 'Captured';

	/**
	 * 2016-05-15
	 * For the transactions, which the Checkout.com merchant backend shows as «Authorised - 3D»,
	 * the object «status» field's value will be «Authorised», not «Authorised - 3D».
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