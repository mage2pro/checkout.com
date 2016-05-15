<?php
namespace Dfe\CheckoutCom;
use Df\Config\Source\NoWhiteBlack as NWB;
use Dfe\CheckoutCom\Patch\ApiClient as API;
use Dfe\CheckoutCom\Source\Prefill;
use com\checkout\ApiServices\Charges\ChargeService;
use Magento\Framework\App\ScopeInterface;
class Settings extends \Df\Core\Settings {
	/**
	 * 2016-05-15
	 * @param int $customerId
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function actionDesired($customerId, $s = null) {
		return df_customer_is_new($customerId) ? $this->actionForNew($s) : $this->actionForReturned($s);
	}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return API
	 */
	public function api($s = null) {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = new API($this->secretKey($s), $this->test($s) ? 'sandbox' : 'live');
		}
		return $this->{__METHOD__};
	}

	/**
	 * 2016-05-05
	 * https://github.com/CKOTech/checkout-php-library#example
	 * https://github.com/CKOTech/checkout-php-library/wiki/Charges#creates-a-charge-with-cardtoken
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return ChargeService
	 */
	public function apiCharge($s = null) {return $this->api($s)->chargeService();}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Description»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function description($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-02-27
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Enable?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function enable($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D-Secure validation for All Customers?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function force3DS_forAll($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D-Secure validation for the Particular Customer Locations (detected by IP Address)?»
	 * @param string $countryIso2
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function force3DS_forIPs($countryIso2, $s = null) {
		return $this->nwb(__FUNCTION__, 'countries', $countryIso2, $s);
	}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Force 3D-Secure validation for the New Customers?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function force3DS_forNew($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-05-13
	 * «Mage2.PRO» → «Payment» → «Checkout.com» →
	 * «Force 3D-Secure validation for the Particular Shipping Destinations?»
	 * @param string $countryIso2
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function force3DS_forShippingDestinations($countryIso2, $s = null) {
		return $this->nwb(__FUNCTION__, 'countries', $countryIso2, $s);
	}

	/**
	 * 2016-03-14
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Metadata»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string[]
	 */
	public function metadata($s = null) {return $this->csv(__FUNCTION__, $s);}

	/**
	 * 2016-03-09
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Prefill the Payment Form with Test Data?»
	 * @see \Dfe\CheckoutCom\Source\Prefill::map()
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return array(string => string)|null
	 */
	public function prefill($s = null) {return Prefill::s()->config($this->v(__FUNCTION__, $s));}

	/**
	 * 2016-03-02
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function publishableKey($s = null) {
		return $this->test($s) ? $this->testPublishableKey($s) : $this->livePublishableKey($s);
	}

	/**
	 * 2016-03-02
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	public function secretKey($s = null) {
		return $this->test($s) ? $this->testSecretKey($s) : $this->liveSecretKey($s);
	}

	/**
	 * 2016-03-14
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Billing Descriptor»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string[]
	 */
	public function statement($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-05-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Wait for «Capture» transaction on an order placement if the Payment Action is «Capture»?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function waitForCapture($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * @override
	 * @used-by \Df\Core\Settings::v()
	 * @return string
	 */
	protected function prefix() {return 'df_payment/checkout_com/';}

	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a New Customer»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function actionForNew($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-15
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Payment Action for a Returned Customer»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function actionForReturned($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Publishable Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function livePublishableKey($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Live Secret Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function liveSecretKey($s = null) {return $this->p(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Mode?»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return bool
	 */
	public function test($s = null) {return $this->b(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Publishable Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function testPublishableKey($s = null) {return $this->v(__FUNCTION__, $s);}

	/**
	 * 2016-03-02
	 * «Mage2.PRO» → «Payment» → «Checkout.com» → «Test Secret Key»
	 * @param null|string|int|ScopeInterface $s [optional]
	 * @return string
	 */
	private function testSecretKey($s = null) {return $this->p(__FUNCTION__, $s);}

	/** @return $this */
	public static function s() {static $r; return $r ? $r : $r = df_o(__CLASS__);}
}


