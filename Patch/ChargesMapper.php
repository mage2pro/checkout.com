<?php
namespace Dfe\CheckoutCom\Patch;
class ChargesMapper extends \com\checkout\ApiServices\Charges\ChargesMapper {
	/**
	 * 2016-05-08
	 * @override
	 * @see \com\checkout\ApiServices\Charges\ChargesMapper::requestPayloadConverter()
	 * @param CardTokenChargeCreate|object|null $requestModel [optional]
	 * @return array(string => mixed)
	 */
	public function requestPayloadConverter($requestModel = null) {
		/** @var array(string => mixed) $result */
		$result = parent::requestPayloadConverter($requestModel);
		$requestModel = $requestModel ?: $this->getRequestModel();
		if ($requestModel instanceof CardTokenChargeCreate) {
			/** @var string|null $descriptor */
			$descriptor = $requestModel->getDescriptorDf();
			if ($descriptor) {
				//$result['descriptor'] = $descriptor;
				/**
				 * 2016-05-09
				 * Tried to put the code here
				 * $result['descriptor'] = $descriptor;
				 * но он не работает:
					{
						"errorCode": "70000",
						"message": "Validation error",
						"errors": ["An error was experienced while parsing the payload. Please ensure that the structure is correct."],
						"errorMessageCodes": ["70002"],
						"eventId": "5a957fcc-670a-4e26-bf89-303b9e025253"
					}
				 */
			}
		}
		return $result;
	}
}


