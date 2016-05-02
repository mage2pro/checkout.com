<?php
namespace Dfe\CheckoutCom;
use com\checkout\ApiClient;
use com\checkout\ApiServices\Cards\ResponseModels\Card;
use com\checkout\ApiServices\Charges\ChargeService;
use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate;
use com\checkout\ApiServices\Charges\ResponseModels\Charge;
use com\checkout\ApiServices\SharedModels\Address as CAddress;
use com\checkout\ApiServices\SharedModels\Phone as CPhone;
use com\checkout\ApiServices\SharedModels\Product as CProduct;
use com\checkout\helpers\ApiHttpClientCustomException as CE;
use Dfe\CheckoutCom\Settings as S;
use Dfe\CheckoutCom\Source\Action;
use Dfe\CheckoutCom\Source\Metadata;
use Exception as E;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Payment\Model\Info;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item as OrderItem;
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
	 * 2016-03-06
	 * @override
	 * @see \Df\Payment\Method::assignData()
	 * @param DataObject $data
	 * @return $this
	 */
	public function assignData(DataObject $data) {
		parent::assignData($data);
		$this->iiaSet(self::$TOKEN, $data[self::$TOKEN]);
		return $this;
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
	 * 2016-03-15
	 * @override
	 * @see \Df\Payment\Method::void()
	 * @param InfoInterface|Info|OrderPayment $payment
	 * @return $this
	 */
	public function void(InfoInterface $payment) {
		$this->_refund($payment);
		return $this;
	}

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
	 * @return ApiClient
	 */
	private function api() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = new ApiClient(
				S::s()->secretKey(), S::s()->test() ? 'sandbox' : 'live'
			);
		}
		return $this->{__METHOD__};
	}

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
			}
			else {
				/** @var \Magento\Sales\Model\Order $order */
				$order = $payment->getOrder();
				/** @var \Magento\Sales\Model\Order\Address|null $sa */
				$sa = $order->getShippingAddress();
				if (!$sa) {
					$sa = $order->getBillingAddress();
				}
				/** @var \Magento\Store\Model\Store $store */
				$store = $order->getStore();
				/** @var string $iso3 */
				$iso3 = $order->getBaseCurrencyCode();
				/** @var ChargeService $charge */
				$charge = $this->api()->chargeService();
				/** @var CardTokenChargeCreate $request */
				$request = new CardTokenChargeCreate;
				$request->setCustomerName($sa->getName());
				/**
				 * 2016-04-21
				 * «The authorised charge must captured within 7 days
				 * or the charge will be automatically voided by the system
				 * and the reserved funds will be released.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/capture-card-charge
				 *
				 * «Accepted values either 'y' or 'n'.
				 * Default is is set to 'y'.
				 * Defines if the charge will be authorised ('n') or captured ('y').
				 * Authorisations will expire in 7 days.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 * Несмотря на то, что в документации буквы 'y' и 'n' — прописные,
				 * в примерах везде используются заглавные.
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#request-example
				 */
				$request->setAutoCapture($capture ? 'Y' : 'N');
				/**
				 * 2016-04-21
				 * «Delayed capture time in hours between 0 and 168 inclusive
				 * that corresponds to 7 days (7x24).
				 * E.g. 0.5 interpreted as 30 mins.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setAutoCapTime(0);
				/**
				 * 2016-04-21
				 * «A valid charge mode: 1 for No 3D, 2 for 3D, 3 Local Payment.
				 * Default is 1 if not provided.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setChargeMode(1);
				/**
				 * 2016-04-21
				 * How are an order's getCustomerEmail() and setCustomerEmail() methods
				 * implemented and used?
				 * https://mage2.pro/t/1308
				 *
				 * «The email address or customer id of the customer.»
				 * «Either email or customerId required.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setEmail($order->getCustomerEmail());
				/**
				 * 2016-04-23
				 * Нельзя одновременно устанавливать и email, и customerId.
				 * Причём товары передаются только при указании email:
				 * https://github.com/CKOTech/checkout-php-library/blob/7c9312e9/com/checkout/ApiServices/Charges/ChargesMapper.php#L142
				 */
				/*if ($order->getCustomerId()) {
					$request->setCustomerId($order->getCustomerId());
				} */
				/** @var array(string => string) $vars */
				$vars = Metadata::vars($store, $order);
				/**
				 * 2016-04-21
				 * «A description that can be added to this object.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setDescription(df_var(S::s()->description(), $vars));
				if (is_null($amount)) {
					$amount = $payment->getBaseAmountOrdered();
				}
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
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setValue(self::amount($payment, $amount));
				/**
				 * 2016-04-21
				 * «Three-letter ISO currency code
				 * representing the currency in which the charge was made.
				 * (refer to currency codes and names)»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setCurrency($iso3);
				/**
				 * 2016-04-21
				 * «Order tracking id generated by the merchant.
				 * Max length of 100 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setTrackId($payment->getId());
				/**
				 * 2016-04-21
				 * «Transaction indicator. 1 for regular, 2 for recurring, 3 for MOTO.
				 * Defaults to 1 if not specified.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setTransactionIndicator(1);
				/**
				 * 2016-04-21
				 * «Customer/Card holder Ip.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setCustomerIp($order->getRemoteIp());
				/**
				 * 2016-04-21
				 * «A valid card token (with prefix card_tok_)»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setCardToken($this->iia(self::$TOKEN));
				foreach ($order->getItems() as $item) {
					/** @var OrderItem $item */
					/**
					 * 2016-03-24
					 * Если товар является настраиваемым, то
					 * @uses \Magento\Sales\Model\Order::getItems()
					 * будет содержать как настраиваемый товар, так и его простой вариант.
					 */
					if (!$item->getChildrenItems()) {
						/** @var CProduct $cProduct */
						$cProduct = new CProduct;
						/**
						 * 2016-04-23
						 * «Name of product. Max of 100 characters.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						// Простые варианты имеют имена типа «New Very Prive-36-Almond»,
						// нам удобнее видеть имена простыми,
						// как у настраиваемого товара: «New Very Prive»).
						$cProduct->setName(
							$item->getParentItem()
							? $item->getParentItem()->getName()
							: $item->getName()
						);
						$cProduct->setProductId($item->getProductId());
						/**
						 * 2016-04-23
						 * «Description of the product.Max of 500 characters.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$cProduct->setDescription($item->getDescription());
						/**
						 * 2016-04-23
						 * «Stock Unit Identifier.
						 * Unique product identifier.
						 * Max length of 100 characters.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$cProduct->setSku($item->getSku());
						/**
						 * 2016-04-23
						 * «Product price per unit. Max. of 6 digits.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$cProduct->setPrice(self::amount($payment, $item->getPrice()));
						/**
						 * 2016-04-23
						 * «Units of the product to be shipped. Max length of 3 digits.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$cProduct->setQuantity($item->getQtyOrdered());
						/**
						 * 2016-04-23
						 * «image link to product on merchant website.»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$cProduct->setImage(df_product_image_url($item->getProduct()));
						/**
						 * 2016-04-23
						 * «An array of Product details»
						 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
						 */
						$request->setProducts($cProduct);
					}
				}
				/** @var CAddress $rsa */
				$rsa = new CAddress;
				/**
				 * 2016-04-23
				 * «Address field line 1. Max length of 100 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setAddressLine1($sa->getStreetLine(1));
				/**
				 * 2016-04-23
				 * «Address field line 2. Max length of 100 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setAddressLine2($sa->getStreetLine(2));
				/**
				 * 2016-04-23
				 * «Address postcode. Max. length of 50 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setPostcode($sa->getPostcode());
				/**
				 * 2016-04-23
				 * «The country ISO2 code e.g. US.
				 * See provided list of supported ISO formatted countries.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setCountry($sa->getCountryId());
				/**
				 * 2016-04-23
				 * «Address city. Max length of 100 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setCity($sa->getCity());
				/**
				 * 2016-04-23
				 * «Address state. Max length of 100 characters.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setState($sa->getRegion());
				/** @var CPhone $phone */
				$phone = new CPhone;
				/**
				 * 2016-04-23
				 * «Contact phone number for the card holder.
				 * Its length should be between 6 and 25 characters.
				 * Allowed characters are: numbers, +, (,) ,/ and ' '.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$phone->setNumber($sa->getTelephone());
				/**
				 * 2016-04-23
				 * «Country code for the phone number of the card holder
				 * e.g. 44 for United Kingdom.
				 * Please refer to Country ISO and Code section
				 * in the Other Codes menu option.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				//$phone->setCountryCode("44");
				/**
				 * 2016-04-23
				 * «Contact phone object for the card holder.
				 * If provided, it will contain the countryCode and number properties
				 * e.g. 'phone':{'countryCode': '44' , 'number':'12345678'}.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$rsa->setPhone($phone);
				/**
				 * 2016-04-23
				 * «Shipping address details.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setShippingDetails($rsa);
				/**
				 * 2016-04-23
				 * «A hash of FieldName and value pairs e.g. {'keys1': 'Value1'}.
				 * Max length of key(s) and value(s) is 100 each.
				 * A max. of 10 KVP are allowed.»
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#cardWithTokenTable
				 */
				$request->setMetadata(array_combine(
					dfa_select(Metadata::s()->map(), S::s()->metadata())
					,dfa_select($vars, S::s()->metadata())
				));
				/**
				 * 2016-04-23
				 * http://developers.checkout.com/docs/server/api-reference/charges/charge-with-card-token#response
				 */
				/** @var Charge $response */
				//xdebug_break();
			    $response = $charge->chargeWithCardToken($request);
				/** @var Card $card */
				$card = $response->getCard();
				if ('Authorised' === $response->getStatus()) {
					/**
					 * 2016-05-02
					 * https://mage2.pro/t/941
					 * «How is the \Magento\Sales\Model\Order\Payment's setCcLast4() / getCcLast4() used?»
					 */
					$payment->setCcLast4($card->getLast4());
					// 2016-05-02
					$payment->setCcType($card->getPaymentMethod());
					/**
					 * 2016-05-02
					 * Иначе операция «void» (отмена авторизации платежа) будет недоступна:
					 * «How is a payment authorization voiding implemented?»
					 * https://mage2.pro/t/938
					 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
					 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
					 */
					$payment->setTransactionId($response->getId());
					/**
					 * 2016-03-15
					 * Аналогично, иначе операция «void» (отмена авторизации платежа) будет недоступна:
					 * https://github.com/magento/magento2/blob/8fd3e8/app/code/Magento/Sales/Model/Order/Payment.php#L540-L555
					 * @used-by \Magento\Sales\Model\Order\Payment::canVoid()
					 * Транзакция ситается завершённой, если явно не указать «false».
					 */
					$payment->setIsTransactionClosed($capture);
				}
				xdebug_break();
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
	 */
	const CODE = 'dfe_checkout_com';
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
	private static function amount(InfoInterface $payment, $amount) {
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