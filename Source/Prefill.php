<?php
namespace Dfe\CheckoutCom\Source;
/** @method static Prefill s() */
final class Prefill extends \Df\Config\Source {
	/**
	 * 2016-05-10
	 * @param string $key
	 * @return array(string => string)|null
	 */
	function config($key) {return dfa($this->_config(), $key);}

	/**
	 * 2016-04-13
	 * http://docs.checkout.com/getting-started/testing-and-simulating-charges#test-cards
	 * @override
	 * @see \Df\Config\Source::map()
	 * @used-by \Df\Config\Source::toOptionArray()
	 * @return array(string => string)
	 */
	protected function map() {return [0 => 'No'] + dfa_combine_self(array_keys($this->_config()));}

	/**
	 * 2016-05-10
	 * @return array(mixed => mixed)
	 */
	private function _config() {return
		df_http_json_c('https://mage2.pro/ext/checkout.com/test-card-data.json')
	;}
}