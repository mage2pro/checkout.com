<?php
namespace Dfe\CheckoutCom\Source;
use Magento\Payment\Model\Method\AbstractMethod as M;
/**
 * 2016-05-08
 * The Review mode is removed, because we are unable to bypass 3D-Secure validation if the payment gateway wants it, and an administration is unable to bypass such validation.
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