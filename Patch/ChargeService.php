<?php
namespace Dfe\CheckoutCom\Patch;
use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as Response;
use com\checkout\helpers\ApiHttpClient;
class ChargeService extends \com\checkout\ApiServices\Charges\ChargeService {
	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardToken()
	 * @param CardTokenChargeCreate $requestModel
	 * @return Response
	 */
	public function chargeWithCardToken(CardTokenChargeCreate $requestModel) {
		return new Response(ApiHttpClient::postRequest(
			$this->_apiUrl->getCardTokensApiUri()
			, $this->_apiSetting->getSecretKey()
			, [
				'authorization' => $this->_apiSetting->getSecretKey(),
				'mode' => $this->_apiSetting->getMode(),
				'postedParam'   => (new ChargesMapper($requestModel))->requestPayloadConverter()
			]
		));
	}
}


