<?php
namespace Dfe\CheckoutCom;
use Df\Config\Source\NoWhiteBlack as NWB;
use Dfe\CheckoutCom\Patch\ApiClient as API;
use Dfe\CheckoutCom\Patch\ChargeService;
use Dfe\CheckoutCom\Source\Prefill;
/** @method static Settings s() */
final class Settings extends \Df\Payment\Settings\BankCard {
	/**
	 * 2016-05-15
	 * @param int $customerId
	 * @return string
	 */
	public function actionDesired($customerId) {
		return df_customer_is_new($customerId) ? $this->actionForNew() : $this->actionForReturned();
	}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return API
	 */
	public function api() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = new API($this->secretKey(), $this->test() ? 'sandbox' : 'live');
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @return ChargeService
	 */
	public function apiCharge() {return $this->api()->chargeService();}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Description»
	 * @return string
	 */
	public function description() {return $this->v();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D-Secure validation for All Customers?»
	 * @return bool
	 */
	public function force3DS_forAll() {return $this->b();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D-Secure validation for the Particular Customer Locations (detected by IP Address)?»
	 * @param string $iso2
	 * @return string
	 */
	public function force3DS_forIPs($iso2) {return $this->nwbn('countries', $iso2);}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D-Secure validation for the New Customers?»
	 * @return bool
	 */
	public function force3DS_forNew() {return $this->b();}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D-Secure validation for the Particular Shipping Destinations?»
	 * @param string $iso2
	 * @return string
	 */
	public function force3DS_forShippingDestinations($iso2) {return $this->nwbn('countries', $iso2);}

	/**
	 * 2016-07-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Failure Message»
	 * @return string
	 */
	public function messageFailure() {return $this->v();}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @return array(string => string)|null
	 */
	public function prefill() {return Prefill::s()->config($this->v());}

	/**
	 * 2016-03-02
	 * @return string
	 */
	public function publishableKey() {return $this->testable();}

	/**
	 * 2016-03-02
	 * @return string
	 */
	public function secretKey() {return $this->testable();}

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

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Publishable Key»
	 * @return string
	 */
	protected function livePublishableKey() {return $this->v();}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Secret Key»
	 * @return string
	 */
	protected function liveSecretKey() {return $this->p();}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Publishable Key»
	 * @return string
	 */
	protected function testPublishableKey() {return $this->v();}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Secret Key»
	 * @return string
	 */
	protected function testSecretKey() {return $this->p();}

	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a New Customer»
	 * @return string
	 */
	private function actionForNew() {return $this->v();}

	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a Returned Customer»
	 * @return string
	 */
	private function actionForReturned() {return $this->v();}
}