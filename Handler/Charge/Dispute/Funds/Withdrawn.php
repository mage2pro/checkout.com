<?php
namespace Dfe\CheckoutCom\Handler\Charge\Dispute\Funds;
use Dfe\CheckoutCom\Handler\Charge\Dispute\Funds;
// 2016-03-25
// https://stripe.com/docs/api#event_types-charge.dispute.funds_withdrawn
// Occurs when funds are removed from your account due to a dispute.
class Withdrawn extends Funds {
	/**
	 * 2016-03-25
	 * @override
	 * @see \Dfe\CheckoutCom\Handler::_process()
	 * @used-by \Dfe\CheckoutCom\Handler::process()
	 * @return mixed
	 */
	protected function process() {
		/** @var int $paymentId */
		//$paymentId = df_fetch_one('sales_payment_transaction', 'payment_id', [
		//	'txn_id' => dfa_deep($request, 'data/object/id')
		//]);
		return __CLASS__;
	}
}