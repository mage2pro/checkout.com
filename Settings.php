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
	function _3ds():_3DS {return dfc($this, function() {return new _3DS($this);});}

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
	 */
	function api():API {return dfc($this, function() {return new API(
		$this->privateKey(), $this->test() ? 'sandbox' : 'live'
	);});}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @used-by \Dfe\CheckoutCom\Response::getCaptureCharge()
	 * @return ChargeService
	 */
	function apiCharge() {return $this->api()->chargeService();}

	/**
	 * 2016-03-09  
	 * @override
	 * @see \Df\Payment\Settings\BankCard::prefill()
	 * @used-by \Df\Payment\ConfigProvider\BankCard::config()
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @return array(string => string)|null
	 */
	function prefill() {return Prefill::s()->config($this->v());}

	/**
	 * 2016-03-14
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Billing Descriptor»
	 * @return string[]
	 */
	function statement() {return $this->v();}

	/**
	 * 2016-05-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Wait for «Capture» transaction on an order placement if the Payment Action is «Capture»?»
	 * @return bool
	 */
	function waitForCapture() {return $this->b();}
}