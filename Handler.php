<?php
namespace Dfe\CheckoutCom;
use Dfe\CheckoutCom\Handler\DefaultT;
use Exception as E;
abstract class Handler extends \Df\Core\O {
	/**
	 * 2016-03-25
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return mixed
	 */
	abstract protected function process();

	/**
	 * 2016-03-28
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return bool
	 */
	protected function eligible() {return false;}

	/**
	 * 2016-05-10
	 * @used-by isInitiatedByMyself()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge::id()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge::parentId()
	 * @param string|null $path [optional]
	 * @return string|array(string => mixed)
	 */
	final protected function r($path = null) {
		/** @var array(string => mixed) $o */
		$o = dfa($this->_data, 'message');
		return !$path ? $o : dfc($this, function($path) use($o) {return
			dfa_deep($o, $path)
		;}, [$path]);
	}

	/**
	 * 2016-05-11
	 * @return string
	 */
	protected function type() {return $this['eventType'];}

	/**
	 * 2016-05-11
	 * This method determines whether this action is initiated in the same store.
	 * If this is the case, then we do not handle it
	 * (On a side note, this handling can lead to hard to debug failures).
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return bool
	 */
	private function isInitiatedByMyself() {return
		in_array($this->type(), df_csv_parse($this->r('metadata/' . Method::DISABLED_EVENTS)))
	;}

	/**
	 * 2016-03-25
	 * @param array(string => mixed) $request
	 * @return mixed
	 * @throws E
	 */
	static function p(array $request) {
		/** @var mixed $result */
		try {
			dfp_report(__CLASS__, $request, $request['eventType']);
			/**
			 * 2016-05-13
			 * Unlike Stripe, Checkout.com does not use the underline character («_»)
			 * as an event's parts separator, it uses only dot («.») as a separator.
			 * http://docs.checkout.com/getting-started/webhooks
			 */
			/** @var string $suffix */
			$suffix = df_cc_class_uc('handler', explode('.', $request['eventType']));
			/** @var Handler $i */
			$i = df_create(df_con(__CLASS__, $suffix, DefaultT::class), $request);
			/** @var string $result */
			$result =
				!$i->eligible() ? 'The event is not for our store.' : (
					$i->isInitiatedByMyself()
					? 'The action is initiated inside the store,'
						. ' so we do not need to process the notification.'
					: $i->process()
				)
			;
		}
		catch (E $e) {
			/**
			 * 2016-03-27
			 * https://en.wikipedia.org/wiki/List_of_HTTP_status_codes#5xx_Server_Error
			 */
			df_response()->setStatusCode(500);
			df_sentry(__CLASS__, $e, ['extra' => ['request' => $request]]);
			if (df_my_local()) {
				// 2016-03-27
				// Show the stack trace on the screen
				throw $e;
			}
			else {
				$result = __($e->getMessage());
			}
		}
		return $result;
	}
}