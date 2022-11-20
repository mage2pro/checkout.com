<?php
namespace Dfe\CheckoutCom\Handler;
use Dfe\CheckoutCom\Handler;
final class DefaultT extends Handler {
	/**
	 * 2016-05-11 I override the parent's method to return «Not implemented.» instead of «The event is not for our store.».
	 * @override
	 * @see \Dfe\CheckoutCom\eligible::p()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 */
	protected function eligible():bool {return true;}

	/**
	 * 2016-03-25
	 * @override
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return mixed
	 */
	protected function process() {return "«{$this['eventType']}» event handling is not implemented.";}
}


