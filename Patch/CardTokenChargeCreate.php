<?php
namespace Dfe\CheckoutCom\Patch;
use com\checkout\ApiServices\Charges\RequestModels\CardTokenChargeCreate as _Parent;
class CardTokenChargeCreate extends _Parent {
	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Patch\ChargesMapper::requestPayloadConverter()
	 * @return string|null
	 */
	function getDescriptorDf() {return $this->_descriptorDf;}

	/**
	 * 2016-05-08
	 * @used-by \Dfe\CheckoutCom\Patch\ChargesMapper::requestPayloadConverter()
	 * @param string|null $value
	 */
	function setDescriptorDf($value) {$this->_descriptorDf = $value;}

	/** @var string|null */
	private $_descriptorDf;
}


