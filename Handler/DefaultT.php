<?php
namespace Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Handler;
class DefaultT extends Handler {
	/**
	 * 2016-05-11
	 * Override the method in order to return «Not implemented.» instead of «The event is not for our store.»
	 * @override
	 * @see \Dfe\CheckoutCom\eligible::p()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return bool
	 */
	final protected function eligible() {return true;}

	/**
	 * 2016-03-25
	 * @override
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return mixed
	 */
	final protected function process() {return "«{$this->type()}» event handling is not implemented.";}
}


