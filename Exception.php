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
class Exception extends \Df\Payment\Exception {
	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Payment\Exception::__construct()
	 * @param Response $response
	 * @param array(string => mixed) $request [optional]
	 */
	public function __construct(Response $response, array $request = []) {
		parent::__construct();
		$this->_r = $response;
		$this->_request = $request;
	}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Payment\Exception::getMessageForCustomer()
	 * @return string
	 */
	public function getMessageForCustomer() {return $this->_r->messageForCustomer();}

	/**
	 * 2016-07-17
	 * @override
	 * @see \Df\Payment\Exception::getMessageRm()
	 * @return string
	 */
	public function getMessageRm() {return df_cc_n(
		'The Checkout.com request is failed.'
		,"Response:", df_json_encode_pretty($this->_r->a(!$this->_r->hasId() ? null : [
			'status', 'responseMessage', 'id', 'responseCode', 'authCode', 'responseAdvancedInfo'
		]))
		,!$this->_request ? null : ['Request:', df_json_encode_pretty($this->_request)]
	);}

	/**
	 * 2016-07-17
	 * @var Response
	 */
	private $_r;

	/**
	 * 2016-08-20
	 * @used-by \Dfe\CheckoutCom\Exception::__construct()
	 * @used-by \Dfe\CheckoutCom\Exception::getMessageRm()
	 * @var array(string => mixed)
	 */
	private $_request;
}