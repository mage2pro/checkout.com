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
		// null может быть ключом массива: https://3v4l.org/hWmWC
		if (!isset($this->{__METHOD__}[$path])) {
			/** @var string|mixed $result */
			$result = dfa_deep($this->_data, 'message');
			$this->{__METHOD__}[$path] = df_n_set(is_null($path) ? $result : dfa_deep($result, $path));
		}
		return df_n_get($this->{__METHOD__}[$path]);
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
			 * 2016-05-10
			 * Событий с обоими разделителями (типа «charge.dispute.funds_reinstated», как в Stripe),
			 * у нас пока нет: http://developers.checkout.com/docs/server/api-reference/webhooks
			 * Но код оставил таким же, как для Stripe
			 */
			/** @var string $suffix */
			$suffix = df_implode_class('handler', df_explode_multiple(['.', '_'], $request['eventType']));
			$class = df_convention(__CLASS__, $suffix, DefaultT::class);
			/** @var Handler $i */
			$i = df_create($class, $request);
			$result = $i->eligible() ? $i->process() : 'The event is not for our store.';
		}
		catch (E $e) {
			/**
			 * 2016-03-27
			 * https://en.wikipedia.org/wiki/List_of_HTTP_status_codes#5xx_Server_Error
			 */
			df_response()->setStatusCode(500);
			if (df_is_it_my_local_pc()) {
				// 2016-03-27
				// Удобно видеть стек на экране.
				throw $e;
			}
			else {
				$result = __($e->getMessage());
			}
		}
		return $result;
	}
}