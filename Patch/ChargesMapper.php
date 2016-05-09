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
				$result['descriptor'] = $descriptor;
			}
		}
		return $result;
	}
}


