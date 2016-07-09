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
	 * @param string|null $path
	 * @return string|array(string => mixed)
	 */
	protected function o($path = null) {
		// 2016-03-25
		// A null-value could ne a key of a PHP array: https://3v4l.org/hWmWC
		if (!isset($this->{__METHOD__}[$path])) {
			/** @var string|mixed $result */
			$result = dfa_deep($this->_data, 'message');
			$this->{__METHOD__}[$path] = df_n_set(is_null($path) ? $result : dfa_deep($result, $path));
		}
		return df_n_get($this->{__METHOD__}[$path]);
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
	private function isInitiatedByMyself() {
		return in_array($this->type(), df_csv_parse($this->o('metadata/' . Method::DISABLED_EVENTS)));
	}

	/**
	 * 2016-03-25
	 * @param array(string => mixed) $request
	 * @return mixed
	 * @throws E
	 */
	public static function p(array $request) {
		/** @var mixed $result */
		try {
			/**
			 * 2016-05-13
			 * Unlike Stripe, Checkout.com does not use the underline character («_»)
			 * as an event's parts separator, it uses only dot («.») as a separator.
			 * http://developers.checkout.com/docs/server/api-reference/webhooks
			 */
			/** @var string $suffix */
			$suffix = df_implode_class('handler', explode('.', $request['eventType']));
			$class = df_convention(__CLASS__, $suffix, DefaultT::class);
			/** @var Handler $i */
			$i = df_create($class, $request);
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
			if (df_is_it_my_local_pc()) {
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