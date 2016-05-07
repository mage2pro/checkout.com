<?php
namespace Dfe\CheckoutCom\Source;
use Magento\Payment\Model\Method\AbstractMethod as M;
/**
 * 2016-05-08
 * Режим review убрал, потому что мы ни как не можем повлиять на решение платёжного шлюза
 * использовать проверку 3D-Secure,
 * а администратор, разумеется, не сможет пройти проверку 3D-Secure за клиента.
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