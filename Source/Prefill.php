<?php
namespace Dfe\CheckoutCom\Source;
/** @method static Prefill s() */
final class Prefill extends \Df\Config\Source {
	/**
	 * 2016-05-10
	 * @used-by \Dfe\CheckoutCom\Settings::prefill()
	 * @param string $k
	 * @return array(string => string)|null
	 */
	function config(string $k):array {return dfa(df_module_json($this, 'test-card-data'), $k);}

	/**
	 * 2016-04-13 http://docs.checkout.com/getting-started/testing-and-simulating-charges#test-cards
	 * @override
	 * @see \Df\Config\Source::map()
	 * @used-by \Df\Config\Source::toOptionArray()
	 * @return array(string => string)
	 */
	protected function map():array {return [0 => 'No'] + dfa_combine_self(array_keys($this->_config()));}
}