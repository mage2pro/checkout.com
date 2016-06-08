<?php
namespace Dfe\CheckoutCom\Patch;
/**
 * 2016-06-08
 * I renamed it to get rid of the following
 * Magento 2 compiler (bin/magento setup:di:compile) failure:
 * «Fatal error: Cannot use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate
 * as CardTokenChargeCreate because the name is already in use
 * in vendor/mage2pro/checkout.com/Patch/ChargeService.php on line 3»
 * http://stackoverflow.com/questions/17746481
 */
use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate as CCardTokenChargeCreate;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as Response;
use com\checkout\helpers\ApiHttpClient;
class ChargeService extends \com\checkout\ApiServices\Charges\ChargeService {
	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardToken()
	 * @param CCardTokenChargeCreate $requestModel
	 * @return Response
	 */
	public function chargeWithCardToken(CCardTokenChargeCreate $requestModel) {
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


