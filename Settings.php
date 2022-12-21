<?php
namespace Dfe\CheckoutCom;
use Df\Payment\Settings\_3DS;
use Dfe\CheckoutCom\SDK\ApiClient as API;
use Dfe\CheckoutCom\SDK\ChargeService;
use Dfe\CheckoutCom\Source\Prefill;
use Magento\Sales\Model\Order as O;
/** @method static Settings s() */
final class Settings extends \Df\StripeClone\Settings {
	/**
	 * 2017-10-20
	 * @used-by \Dfe\CheckoutCom\Charge::_build()
	 */
	function _3ds():_3DS {return dfc($this, function():_3DS {return new _3DS($this);});}

	/**
	 * 2016-05-15
	 * @used-by \Dfe\CheckoutCom\Method::isCaptureDesired()
	 * @used-by \Dfe\CheckoutCom\Response::action()
	 */
	function actionDesired(O $o):string {return $this->v(
		df_customer_is_new($o->getCustomerId()) ? 'actionForNew' : 'actionForReturned'
	);}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @used-by \Dfe\CheckoutCom\Response::getCaptureCharge()
	 */
	function apiCharge():ChargeService {return $this->api()->chargeService();}

	/**
	 * 2016-03-09  
	 * @override
	 * @see \Df\Payment\Settings\BankCard::prefill()
	 * @used-by \Df\Payment\ConfigProvider\BankCard::config()
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @return array(string => string)|null
	 */
	function prefill():array {return Prefill::s()->config($this->v());}

	/**
	 * 2016-03-14 «Mage2.PRO» → «Payment» → «Checkout.com» → «Billing Descriptor»
	 * @used-by \Dfe\CheckoutCom\Charge::_build()
	 */
	function statement():string {return $this->v();}

	/**
	 * 2016-05-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Wait for «Capture» transaction on an order placement if the Payment Action is «Capture»?»
	 * @used-by \Dfe\CheckoutCom\Response::action()
	 * @used-by \Dfe\CheckoutCom\Response::waitForCapture()
	 */
	function waitForCapture():bool {return $this->b();}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @used-by self::apiCharge()
	 */
	private function api():API {return dfc($this, function():API {return new API(
		$this->privateKey(), $this->test() ? 'sandbox' : 'live'
	);});}
}