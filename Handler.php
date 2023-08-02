<?php
namespace Dfe\CheckoutCom;
use Dfe\CheckoutCom\Handler\DefaultT;
use Magento\Framework\Phrase;
use \Throwable as Th; # 2023-08-02 "Treat `\Throwable` similar to `\Exception`": https://github.com/mage2pro/core/issues/311
/**
 * @see \Dfe\CheckoutCom\Handler\Charge
 * @see \Dfe\CheckoutCom\Handler\CustomerReturn
 * @see \Dfe\CheckoutCom\Handler\DefaultT
 */
abstract class Handler extends \Df\Core\O {
	/**
	 * 2016-03-25
	 * @used-by self::p()
	 * @see \Dfe\CheckoutCom\Handler\DefaultT::process()
	 * @return mixed
	 */
	abstract protected function process();

	/**
	 * 2016-03-28
	 * @used-by self::p()
	 * @see \Dfe\CheckoutCom\Handler\Charge::eligible()
	 * @see \Dfe\CheckoutCom\Handler\DefaultT::eligible()
	 */
	protected function eligible():bool {return false;}

	/**
	 * 2016-05-10
	 * @used-by self::isInitiatedByMyself()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge::id()
	 * @used-by \Dfe\CheckoutCom\Handler\Charge::parentId()
	 * @return string|array(string => mixed)|null
	 */
	final protected function r(string $p = '') {return dfa_deep($this['message'], $p);}

	/**
	 * 2016-05-11
	 * @used-by self::isInitiatedByMyself()
	 * @used-by \Dfe\CheckoutCom\Handler\DefaultT::process()
	 */
	final protected function type():string {return $this['eventType'];}

	/**
	 * 2016-05-11
	 * This method determines whether this action is initiated in the same store.
	 * If this is the case, then we do not handle it
	 * (On a side note, this handling can lead to hard to debug failures).
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 */
	private function isInitiatedByMyself():bool {return in_array($this->type(), df_csv_parse($this->r(
		'metadata/' . Method::DISABLED_EVENTS
	)));}

	/**
	 * 2016-03-25
	 * @param array(string => mixed) $req
	 * @return Phrase|string
	 */
	static function p(array $req) {/** @var Phrase|string $r */
		try {
			dfp_report(__CLASS__, $req, $req['eventType']);
			# 2016-05-13
			# Unlike Stripe, Checkout.com does not use the underline character («_»)
			# as an event's parts separator, it uses only dot («.») as a separator.
			# http://docs.checkout.com/getting-started/webhooks
			$suffix = df_cc_class_uc('handler', explode('.', $req['eventType'])); /** @var string $suffix */
			$i = df_new(df_con(__CLASS__, $suffix, DefaultT::class), $req); /** @var Handler $i */
			$r =
				!$i->eligible() ? 'The event is not for our store.' : (
					$i->isInitiatedByMyself()
					? 'The action is initiated inside the store, so we do not need to process the notification.'
					: $i->process()
				)
			; 
		}
		catch (Th $t) {
			df_500();
			# 2023-07-25
			# "Change the 3rd argument of `df_sentry` from `$context` to `$extra`": https://github.com/mage2pro/core/issues/249
			df_sentry(__CLASS__, $t, ['request' => $req]);
			if (df_my_local()) {
				throw $t; # 2016-03-27 Show the stack trace on the screen
			}
			$r = __($t->getMessage());
		}
		return $r;
	}
}