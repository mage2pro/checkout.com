<?php
namespace Dfe\CheckoutCom\Source;
/** @method static Prefill s() */
class Prefill extends \Df\Config\SourceT {
	/**
	 * 2016-05-10
	 * @param string $key
	 * @return array(string => string)|null
	 */
	public function config($key) {return dfa($this->_config(), $key);}

	/**
	 * 2016-04-13
	 * http://docs.checkout.com/getting-started/testing-and-simulating-charges#test-cards
	 * @override
	 * @see \Df\Config\Source::map()
	 * @used-by \Df\Config\Source::toOptionArray()
	 * @return array(string => string)
	 */
	protected function map() {
		/** @var array(string => string) $map */
		$keys = array_keys($this->_config());
		return [0 => 'No'] + array_combine($keys, $keys);
	}

	/**
	 * 2016-05-10
	 * @return array(mixed => mixed)
	 */
	private function _config() {return dfc($this, function() {return
		df_http_json_c('https://mage2.pro/ext/checkout.com/test-card-data.json')
	;});}
}