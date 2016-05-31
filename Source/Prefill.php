<?php
namespace Dfe\CheckoutCom\Source;
class Prefill extends \Df\Config\SourceT {
	/**
	 * 2016-05-10
	 * @param string $key
	 * @return array(string => string)|null
	 */
	public function config($key) {return dfa($this->_config(), $key);}

	/**
	 * 2016-04-13
	 * http://developers.checkout.com/docs/server/api-reference/charges/simulator#test-cards
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
	private function _config() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = df_http_json('https://mage2.pro/ext/checkout.com/test-card-data.json');
		}
		return $this->{__METHOD__};
	}

	/** @return self */
	public static function s() {static $r; return $r ? $r : $r = new self;}
}