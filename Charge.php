<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiServices\SharedModels\Address as CAddress;
use com\checkout\ApiServices\SharedModels\Phone as CPhone;
use com\checkout\ApiServices\SharedModels\Product as CProduct;
use Df\Config\Source\NoWhiteBlack as NWB;
use Df\Payment\Token;
use Dfe\CheckoutCom\SDK\CardTokenChargeCreate as lCharge;
use Dfe\CheckoutCom\SDK\ChargesMapper;
use libphonenumber\PhoneNumber as lPhone;
use Magento\Sales\Model\Order\Address as OA;
use Magento\Sales\Model\Order\Item as OI;
/**
 * 2016-05-06
 * @method Method m()
 * @method Settings s()
 */
final class Charge extends \Df\Payment\Charge {
	/**
	 * 2016-05-06
	 * @used-by build()
	 * @param bool $capture
	 * @return lCharge
	 */
	private function _build($capture) {
		/** @var lCharge $result */
		$result = new lCharge;
		/**
		 * 2016-05-08
		 * «How To Use Billing Descriptors to Decrease Chargebacks»
		 * https://www.checkout.com/blog/billing-descriptors/
		 */
		$result->setDescriptorDf($this->s()->statement());
		/**
		 * 2016-04-21
		 * «Order tracking id generated by the merchant.
		 * Max length of 100 characters.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 * 2016-05-03
		 * It is not required, but it is pleasant to have the order number in the «Track ID» row
		 * instead of «Unknown» value.
		 *
		 * 2016-05-08
		 * Since now, the «Track ID» is vital for us,
		 * because it is used for the payment identification
		 * when the customer is returned to the store after 3D Secure verification.
		 *
		 * My previous attempt was $result->setUdf1($this->op()->getId());
		 * but it is wrong, because the order does not have ID on its placement,
		 * it is not saved in the database yet.
		 * But Increment ID is pregenerated, and we can rely on it.
		 *
		 * My pre-previous attept was to record a custom transaction to the database,
		 * but Magento 2 has a fixed number of transaction types,
		 * and it would take a lot of effort to add a new transaction type.
		 *
		 * 2016-05-08 (addition)
		 * After thinking more deeply I understand,
		 * that the linking a Checkout.com Charge to Magento Order is not required,
		 * because an order placement and 3D Secure verification is done
		 * in the context of the current customer session,
		 * and we can get the order information from the session
		 * on the customer return from 3D Secure verification.
		 * So we can just call @see \Magento\Checkout\Model\Session::getLastRealOrder()
		 * How to get the last order programmatically? https://mage2.pro/t/1528
		 */
		$result->setTrackId($this->id());
		$result->setCustomerName($this->addressSB()->getName());
		/**
		 * 2016-04-21
		 * «The authorised charge must captured within 7 days
		 * or the charge will be automatically voided by the system
		 * and the reserved funds will be released.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-actions/capture-card-charge
		 *
		 * «Accepted values either 'y' or 'n'.
		 * Default is is set to 'y'.
		 * Defines if the charge will be authorised ('n') or captured ('y').
		 * Authorisations will expire in 7 days.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 *
		 * Although the values «y» and «n» are lowercased in the documentation,
		 * they are uppercased in the documentation's examples:
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-example
		 *
		 * 2016-05-09
		 * It seems that if the payment gateway returns a «Flagged» transaction,
		 * then the autoCapture param is ignored,
		 * and we need to seperately do a Capture transaction.
		 * https://mage2.pro/t/1565
		 * It is then a good idea to do a Review procedure on such transactions.
		 */
		$result->setAutoCapture($capture ? 'y' : 'n');
		/**
		 * 2016-04-21
		 * «Delayed capture time in hours between 0 and 168 inclusive
		 * that corresponds to 7 days (7x24).
		 * E.g. 0.5 interpreted as 30 mins.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setAutoCapTime(0);
		/**
		 * 2016-04-21
		 * «A valid charge mode: 1 for No 3D, 2 for 3D, 3 Local Payment.
		 * Default is 1 if not provided.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 *
		 * 2016-05-03
		 * In the risk settings dashboard
		 * 3D Secure is forced for transactions above 150 USD.
		 */
		$result->setChargeMode($this->use3DS() ? 2 : 1);
		/**
		 * 2016-04-21
		 * How are an order's getCustomerEmail() and setCustomerEmail() methods
		 * implemented and used?
		 * https://mage2.pro/t/1308
		 *
		 * «The email address or customer id of the customer.»
		 * «Either email or customerId required.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setEmail($this->customerEmail());
		/**
		 * 2016-04-23
		 * Email and CustomerId cannot be used simultaneously 
		 * And the products field is only sent when using email
		 * https://github.com/CKOTech/checkout-php-library/blob/7c9312e9/com/checkout/ApiServices/Charges/ChargesMapper.php#L142
		 */
		/*if ($order->getCustomerId()) {
			$request->setCustomerId($order->getCustomerId());
		} */
		/**
		 * 2016-04-21
		 * «A description that can be added to this object.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setDescription($this->description());
		/**
		 * 2016-04-21
		 * «Expressed as a non-zero positive integer
		 * (i.e. decimal figures not allowed).
		 * Divide Bahraini Dinars (BHD), Kuwaiti Dinars (KWD),
		 * Omani Rials (OMR) and Jordanian Dinars (JOD) into 1000 units
		 * (e.g. "value = 1000" is equivalent to 1 Bahraini Dinar).
		 * Divide all other currencies into 100 units
		 * (e.g. "value = 100" is equivalent to 1 US Dollar).
		 * Checkout.com will perform the proper conversions for currencies
		 * that do not support fractional values.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setValue($this->amountF());
		// 2016-04-21
		// «Three-letter ISO currency code representing the currency in which the charge was made.
		// (refer to currency codes and names)»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setCurrency($this->currencyC());
		// 2016-04-21
		// «Transaction indicator. 1 for regular, 2 for recurring, 3 for MOTO.
		// Defaults to 1 if not specified.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setTransactionIndicator(1);
		// 2016-04-21
		// «Customer/Card holder Ip.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		/** @var string $ip */
		if ('127.0.0.1' !== ($ip = $this->customerIp())) {
			$result->setCustomerIp($ip);
		}
		/**
		 * 2016-04-21
		 * «A valid card token (with prefix card_tok_)»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setCardToken(Token::get($this->ii()));
		$this->setProducts($result);
		// 2016-04-23
		// «Shipping address details.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setShippingDetails($this->cAddress());
		// 2016-04-23
		// «A hash of FieldName and value pairs e.g. {'keys1': 'Value1'}.
		// Max length of key(s) and value(s) is 100 each.
		// A max. of 10 KVP are allowed.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setMetadata($this->pMetadata());
		return $result;
	}

	/**
	 * 2016-05-06
	 * @return CAddress
	 */
	private function cAddress() {return dfc($this, function() {
		/** @var CAddress $result */
		$result = new CAddress;
		/** @var OA $a */
		$a = $this->addressSB();		
		// 2016-04-23
		// «Address field line 1. Max length of 100 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setAddressLine1($a->getStreetLine(1));
		// 2016-04-23
		// «Address field line 2. Max length of 100 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setAddressLine2($a->getStreetLine(2));
		// 2016-04-23
		// «Address postcode. Max. length of 50 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setPostcode($a->getPostcode());
		// 2016-04-23
		// «The country ISO2 code e.g. US.
		// See provided list of supported ISO formatted countries.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setCountry($a->getCountryId());
		// 2016-04-23
		// «Address city. Max length of 100 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setCity($a->getCity());
		// 2016-04-23
		// «Address state. Max length of 100 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setState($a->getRegion());
		// 2016-04-23
		// «Contact phone object for the card holder.
		// If provided, it will contain the countryCode and number properties
		// e.g. 'phone':{'countryCode': '44' , 'number':'12345678'}.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setPhone($this->cPhone());
		// 2016-04-23
		// «Shipping address details.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		return $result;
	});}

	/**
	 * 2016-05-06
	 * @return CPhone
	 */
	private function cPhone() {return dfc($this, function() {
		/** @var CPhone $result */
		$result = new CPhone;
		/** @var lPhone|bool $lPhone */
		if ($lPhone = df_phone($this->addressSB(), false)) {
			// 2016-04-23
			// «Contact phone number for the card holder.
			// Its length should be between 6 and 25 characters.
			// Allowed characters are: numbers, +, (,) ,/ and ' '.»
			// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
			$result->setNumber($lPhone->getNationalNumber());
			// 2016-04-23
			// «Country code for the phone number of the card holder
			// e.g. 44 for United Kingdom.
			// Please refer to Country ISO and Code section
			// in the Other Codes menu option.»
			// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
			//
			// 2016-08-18
			// From now, the country code should be a string,
			// https://mail.google.com/mail/u/0/#inbox/1569b34a5375cf7f
			// The following data will fail
			//	"phone": {
			//		"number": "9629197300",
			//		"countryCode": 7
			//	}
			$result->setCountryCode(strval($lPhone->getCountryCode()));
		}
		return $result;
	});}

	/**
	 * 2016-05-06
	 * @used-by setProducts()
	 * @param OI $i
	 * @return CProduct
	 */
	private function cProduct(OI $i) {
		/** @var CProduct $result */
		$result = new CProduct;
		/**
		 * 2016-04-23
		 * «Name of product. Max of 100 characters.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 *
		 * Simple options have name similar to «New Very Prive-36-Almond»,
		 * we'd rather see 'normal' names
		 * like a custom product «New Very Prive»).
		 *
		 * 2017-02-01
		 * Да вот я не уверен теперь, что $parent->getName() — это правильно.
		 * Всё-таки, «New Very Prive-37-Almond» информативнее, чем «New Very Prive»,
		 * а «Victoria's Secret Angel Gold Eau de Parfum-3.4 fl oz» информативнее, чем
		 * «Victoria's Secret Angel Gold Eau de Parfum».
		 * Поэтому заменил $parent->getName() на $i->getName().
		 * @see \Dfe\TwoCheckout\LineItem\Product::nameRaw()
		 */
		$result->setName($i->getName());
		/**
		 * 2016-08-18
		 * It was the following code here:
		 * $result->setProductId($item->getProductId());
		 * But the «productId» parameter disappears from the documentation:
		 * http://docs.checkout.com/reference/merchant-api-reference/complex-request-objects/products
		 */
		$result->setTrackingUrl(df_oqi_url($i));
		/**
		 * 2016-04-23
		 * «Description of the product.Max of 500 characters.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setDescription($i->getDescription());
		/**
		 * 2016-04-23
		 * «Stock Unit Identifier.
		 * Unique product identifier.
		 * Max length of 100 characters.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 */
		$result->setSku($i->getSku());
		/**
		 * 2016-04-23
		 * «Product price per unit. Max. of 6 digits.»
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 *
		 * 2016-05-03
		 * Использовать @see amountF() здесь не требуется.
		 * 2017-02-01
		 * df_oqi_price() использует @see \Magento\Sales\Model\Order\Item::getPrice(),
		 * а не @see \Magento\Sales\Model\Order\Item::getPriceInclTax().
		 * Это нормально для модуля 2Checkout: @see \Dfe\TwoCheckout\LineItem\Product::price(),
		 * потому что там мы передаём налоги платёжной системе отдельной строкой.
		 * Здесь же мы не передаём налоги системе, и поэтому получается,
		 * что сумма стоимостей позиций заказа у нас не будет равна сумме заказа
		 * (стоимость доставки, кстати, тоже не передаём).
		 */
		$result->setPrice($this->cFromDoc(df_oqi_price($i)));
		// 2016-04-23
		// «Units of the product to be shipped. Max length of 3 digits.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		$result->setQuantity(df_oqi_qty($i));
		// 2016-04-23
		// «Image link to product on merchant website. Max length 200 characters.»
		// http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		// 2017-04-22
		// При превышении длины будет предупреждение (не сбой):
		//		"warnings": [{"code": "70181", "description": "Invalid length for product image url"}],
		/** @var string $imageUrl */
		if (201 > mb_strlen($imageUrl = df_oqi_image($i))) {
			$result->setImage($imageUrl);
		}
		return $result;
	}

	/**
	 * 2016-06-25
	 * @used-by _build()
	 * https://github.com/CKOTech/checkout-magento2-plugin/issues/1
	 * @return array(string => string)
	 */
	private function pMetadata() {return df_map('mb_substr', [
		/**
		 * 2016-08-18
		 * It was a «server» key before, but it exceeded the maximum length of a key: 100 characters.
		 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
		 * «A hash of FieldName and value pairs e.g. {'keys1': 'Value1'}.
		 * Max length of key(s) and value(s) is 100 each. A max. of 10 KVP are allowed.»
		 * So I splitted the «server» key into 2 keys: «server» and «user_agent».
		 */
		'server' => df_webserver()
		,'user_agent' => df_request_ua()
		,'quote_id' => $this->id()
		// 2016-06-25
		// Magento version
		,'magento_version' => df_magento_version()
		// 2016-06-26
		// The version of the your Magento/Checkout plugin the merchant is using
		,'plugin_version' => df_package_version($this)
		// 2016-06-25
		// The version of our PHP core library (if you are using the our PHP core library)
		,'lib_version' => \CheckoutApi_Client_Constant::LIB_VERSION
		// 2016-06-25
		// JS/API/Kit
		,'integration_type' => 'Kit'
		// 2016-06-25
		// Merchant\'s server time
		// Something like "2015-02-11T06:16:47+0100" (ISO 8601)
		,'time' => df_now('Y-m-d\TH:i:sO', 'Europe/London')
	], [0, 100]);}

	/**
	 * 2016-05-06
	 * 2016-04-23
	 * «An array of Product details»
	 * http://docs.checkout.com/reference/merchant-api-reference/charges/charge-with-card-token#request-payload-fields
	 * @used-by _build()
	 * @param lCharge $c
	 */
	private function setProducts(lCharge $c) {$this->oiLeafs(function(OI $i) use($c) {
		$c->setProducts($this->cProduct($i))
	;});}

	/**
	 * 2016-05-13
	 * @return bool
	 */
	private function use3DS() {$s = $this->s(); return dfc($this, function() use($s) {return
		$s->force3DS_forAll()
		|| $s->force3DS_forNew() && df_customer_is_new($this->o()->getCustomerId())
		|| $s->force3DS_forShippingDestinations($this->addressSB()->getCountryId())
		// 2016-05-31
		// Today it seems that the PHP request to freegeoip.net stopped returning any value,
		// whereas it still returns results when the request is sent from the browser.
		// Apparently, freegeoip.net banned my User-Agent?
		// In all cases, we cannot rely on freegeoip.net and risk getting an empty response.
		|| $s->force3DS_forIPs(df_visitor()->iso2() ?: $this->addressSB()->getCountryId())
	;});}

	/**
	 * 2016-05-06
	 * @used-by \Dfe\CheckoutCom\Method::request()
	 * @param Method $m
	 * @param bool $capture [optional]
	 * @return array(string => mixed)
	 */
	static function build(Method $m, $capture = true) {return
		(new ChargesMapper((new self($m))->_build($capture)))->requestPayloadConverter()
	;}
}