<?php
namespace Dfe\CheckoutCom;
/**
 * 2016-07-17
 * A sample failure response:
 *	{
 *		"id": "charge_test_153AF6744E5J7A98E1D9",
 *		"responseMessage": "40144 - Threshold Risk - Decline",
 *		"responseAdvancedInfo": null,
 *		"responseCode": "40144",
 *		"status": "Declined",
 *		"authCode": "00000"
 *		...
 *	}
 */
final class Exception extends \Df\Payment\Exception {
	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::__construct()
	 * @param array(string => mixed) $request [optional]
	 */
	function __construct(Response $res, array $req = []) {parent::__construct(); $this->_r = $res; $this->_request = $req;}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Payment\Exception::message()
	 */
	function message():string {return dfc($this, function() {return df_api_rr_failed('Checkout.com',
		$this->_r->a(!$this->_r->hasId() ? null : [
			'status', 'responseMessage', 'id', 'responseCode', 'authCode', 'responseAdvancedInfo'
		])
		,$this->_request
	);});}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Core\Exception::messageC()
	 * @used-by \Df\Payment\PlaceOrderInternal::message()
	 */
	function messageC():string {return $this->_r->messageC();}

	/**
	 * 2016-07-17
	 * @used-by self::__construct()
	 * @used-by self::message()
	 * @used-by self::messageC()
	 * @var Response
	 */
	private $_r;

	/**
	 * 2016-08-20
	 * @used-by self::__construct()
	 * @used-by self::message()
	 * @var array(string => mixed)
	 */
	private $_request;
}