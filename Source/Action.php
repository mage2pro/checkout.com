<?php
namespace Dfe\CheckoutCom\Source;
use Magento\Payment\Model\Method\AbstractMethod as M;
class Action extends \Df\Config\SourceT {
	/**
	 * 2016-03-07
	 * @override
	 * @see \Df\Config\Source::map()
	 * @used-by \Df\Config\Source::toOptionArray()
	 * @return array(string => string)
	 */
	protected function map() {
		return [
			M::ACTION_AUTHORIZE => 'Authorize'
			, M::ACTION_AUTHORIZE_CAPTURE => 'Capture'
			/**
			 * 2016-05-06
			 * Если мы 3D-Secure отключить не сможем, то и от режима review толку нет,
			 * потому что в административной части мы будем не в состоянии пройти проверку 3D-Secure
			 */
			, self::REVIEW => 'Review'
		];
	}

	const REVIEW = 'review';
}