<?php
namespace Dfe\CheckoutCom;
use Df\Config\Source\NoWhiteBlack as NWB;
use Dfe\CheckoutCom\Patch\ApiClient as API;
use Dfe\CheckoutCom\Patch\ChargeService;
use Dfe\CheckoutCom\Source\Prefill;
/** @method static Settings s() */
final class Settings extends \Df\StripeClone\Settings {
	/**
	 * 2016-05-15
	 * @param int $customerId
	 * @return string
	 */
	public function actionDesired($customerId) {return
		$this->v(df_customer_is_new($customerId) ? 'actionForNew' : 'actionForReturned')
	;}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return API
	 */
	public function api() {return dfc($this, function() {return
		new API($this->privateKey(), $this->test() ? 'sandbox' : 'live')
	;});}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return ChargeService
	 */
	public function apiCharge() {return $this->api()->chargeService();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D Secure validation for All Customers?»
	 * @return bool
	 */
	public function force3DS_forAll() {return $this->b();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D Secure validation for the Particular Customer Locations (detected by IP Address)?»
	 * @param string $iso2
	 * @return string
	 */
	public function force3DS_forIPs($iso2) {return $this->nwbn('countries', $iso2);}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D Secure validation for the New Customers?»
	 * @return bool
	 */
	public function force3DS_forNew() {return $this->b();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D Secure validation for the Particular Shipping Destinations?»
	 * @param string $iso2
	 * @return string
	 */
	public function force3DS_forShippingDestinations($iso2) {return $this->nwbn('countries', $iso2);}

	/**
	 * 2016-03-09  
	 * @override
	 * @see \Df\Payment\Settings\BankCard::prefill()
	 * @used-by \Df\Payment\ConfigProvider\BankCard::config()
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @return array(string => string)|null
	 */
	public function prefill() {return Prefill::s()->config($this->v());}

	/**
	 * 2016-03-14
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Billing Descriptor»
	 * @return string[]
	 */
	public function statement() {return $this->v();}

	/**
	 * 2016-05-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Wait for «Capture» transaction on an order placement if the Payment Action is «Capture»?»
	 * @return bool
	 */
	public function waitForCapture() {return $this->b();}
}