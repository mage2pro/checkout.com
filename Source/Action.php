<?php
namespace Dfe\CheckoutCom\Source;
use Magento\Payment\Model\Method\AbstractMethod as M;
/**
 * 2016-05-08
 * Review mode is disabled because 3D-Secure mode cannot be triggered 
 * from the Magento side for testing.
 * Administrators can obviously test this for merchants
 */
class Action extends \Df\Config\SourceT {
	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Config\Source::map()
	 * @used-by \Df\Config\Source::toOptionArray()
	 * @return array(string => string)
	 */
	protected function map() {
		return [M::ACTION_AUTHORIZE => 'Authorize', M::ACTION_AUTHORIZE_CAPTURE => 'Capture'];
	}
}