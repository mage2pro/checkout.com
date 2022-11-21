<?php
namespace Dfe\CheckoutCom\SDK;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as Response;
final class ChargeService extends \com\checkout\ApiServices\Charges\ChargeService {
	/**
	 * 2016-05-08
	 * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardToken()
	 * @param array(string => mixed) $params
	 */
	function chargeWithCardTokenDf(array $params):Response {
		return new Response(ApiHttpClient::postRequest($this->_apiUrl->getCardTokensApiUri(), [
			'authorization' => $this->_apiSetting->getSecretKey(),
			'mode' => $this->_apiSetting->getMode(),
			'postedParam' => $params
		]));
	}
}


