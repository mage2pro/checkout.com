<?php
namespace Dfe\CheckoutCom\Patch;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as Response;
final class ChargeService extends \com\checkout\ApiServices\Charges\ChargeService {
	/**
	 * 2016-05-08
	 * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardToken()
	 * @param array(string => mixed) $params
	 * @return Response
	 */
	function chargeWithCardTokenDf(array $params) {
		return new Response(ApiHttpClient::postRequest($this->_apiUrl->getCardTokensApiUri(), [
			'authorization' => $this->_apiSetting->getSecretKey(),
			'mode' => $this->_apiSetting->getMode(),
			'postedParam' => $params
		]));
	}
}


