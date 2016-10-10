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
use com\checkout\ApiServices\Charges\RequestModels\CardIdChargeCreate as CCardIdChargeCreate;
use com\checkout\ApiServices\Charges\ResponseModels\Charge as Response;
use com\checkout\helpers\ApiHttpClient;
use Dfe\CheckoutCom\Settings as S;
class ChargeService extends \com\checkout\ApiServices\Charges\ChargeService {
    /**
     * 2016-05-08
     * @override
     * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardToken()
     * @param CCardTokenChargeCreate $requestModel
     * @return Response
     */
    public function chargeWithCardToken(CCardTokenChargeCreate $requestModel, $isAmex = 0) {
        if ($isAmex) {
            $secretKey = S::s()->amexSecretKey();
        }
        else {
            $secretKey = $this->_apiSetting->getSecretKey();
        }
        return new Response(ApiHttpClient::postRequest(
            $this->_apiUrl->getCardTokensApiUri()
            , $secretKey
            , [
                'authorization' => $secretKey,
                'mode' => $this->_apiSetting->getMode(),
                'postedParam'   => (new ChargesMapper($requestModel))->requestPayloadConverter()
            ]
        ));
    }


    /**
     * 2016-07-14
     * @override
     * @see \com\checkout\ApiServices\Charges\ChargeService::chargeWithCardId()
     * @param CCardIdChargeCreate $requestModel
     * @return Response
     */
    public function chargeWithCardId(CCardIdChargeCreate $requestModel, $isAmex = 0) {
        if ($isAmex) {
            $secretKey = S::s()->amexSecretKey();
        }
        else {
            $secretKey = $this->_apiSetting->getSecretKey();
        }
        return new Response(ApiHttpClient::postRequest(
            $this->_apiUrl->getCardChargesApiUri()
            , $secretKey
            , [
                'authorization' => $secretKey,
                'mode' => $this->_apiSetting->getMode(),
                'postedParam'   => (new ChargesMapper($requestModel))->requestPayloadConverter()
            ]
        ));
    }

}