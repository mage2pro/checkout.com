<?php
namespace Dfe\CheckoutCom;
/** @method Settings s() */
class ConfigProvider extends \Df\Payment\ConfigProvider\BankCard {
	/**
	 * 2016-08-04
	 * @override
	 * @see \Df\Payment\ConfigProvider::config()
	 * @used-by \Df\Payment\ConfigProvider::getConfig()
	 * @return array(string => mixed)
	 */
	protected function config() {return [
		'prefill' => $this->s()->prefill()
		,'publishableKey' => $this->s()->publishableKey()
	] + parent::config();}
}