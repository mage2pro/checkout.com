<?php
namespace Dfe\CheckoutCom\Patch;
use com\checkout\helpers\AppSetting;
class ApiClient extends \com\checkout\ApiClient {
	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiClient::chargeService()
	 * @return ChargeService
	 */
	function chargeService() {return $this->_chargeService;}

	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiClient::__construct()
	 * @param string $secretKey
	 * @param string $env [optional]
	 * @param bool $debugMode [optional]
	 * @param int $connectTimeout
	 * @param int $readTimeout
	 */
	function __construct(
		$secretKey, $env = 'sandbox' ,$debugMode = false, $connectTimeout = 60, $readTimeout = 60
	) {
		parent::__construct($secretKey, $env ,$debugMode, $connectTimeout, $readTimeout);
		$this->_chargeService = new ChargeService(AppSetting::getSingletonInstance());
	}

	/** @var ChargeService */
	private  $_chargeService;
}


