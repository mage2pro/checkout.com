<?php
namespace Dfe\CheckoutCom\SDK;
use com\checkout\helpers\AppSetting;
class ApiClient extends \com\checkout\ApiClient {
	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiClient::chargeService()
	 */
	function chargeService():ChargeService {return $this->_chargeService;}

	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiClient::__construct()
	 */
	function __construct(
		string $secret, string $env = 'sandbox', bool $debug = false, int $connectTimeout = 60, int $readTimeout = 60
	) {
		parent::__construct($secret, $env ,$debug, $connectTimeout, $readTimeout);
		$this->_chargeService = new ChargeService(AppSetting::getSingletonInstance());
	}

	/** @var ChargeService */
	private  $_chargeService;
}


