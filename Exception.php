<?php
namespace Dfe\CheckoutCom;
/**
 * 2016-07-17
 * A sample failure response:
	{
		"id": "charge_test_153AF6744E5J7A98E1D9",
		"responseMessage": "40144 - Threshold Risk - Decline",
		"responseAdvancedInfo": null,
		"responseCode": "40144",
		"status": "Declined",
		"authCode": "00000"
		...
	}
 */
final class Exception extends \Df\Payment\Exception {
	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::__construct()
	 * @param Response $response
	 * @param array(string => mixed) $request [optional]
	 */
	function __construct(Response $response, array $request = []) {
		parent::__construct();
		$this->_r = $response;
		$this->_request = $request;
	}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Payment\Exception::message()
	 * @return string
	 */
	function message() {return dfc($this, function() {return df_api_rr_failed('Checkout.com',
		$this->_r->a(!$this->_r->hasId() ? null : [
			'status', 'responseMessage', 'id', 'responseCode', 'authCode', 'responseAdvancedInfo'
		])
		,$this->_request
	);});}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::messageC()
	 * @return string
	 */
	function messageC() {return $this->_r->messageC();}

	/**
	 * 2016-07-17
	 * @var Response
	 */
	private $_r;

	/**
	 * 2016-08-20
	 * @used-by \Dfe\CheckoutCom\Exception::__construct()
	 * @used-by \Dfe\CheckoutCom\Exception::message()
	 * @var array(string => mixed)
	 */
	private $_request;
}