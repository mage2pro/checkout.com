<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use Df\Sales\Model\Order as DfOrder;
use Df\Sales\Model\Order\Invoice as DfInvoice;
use Dfe\CheckoutCom\Handler\Charge;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order as O;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
# 2016-05-10 charge.captured http://docs.checkout.com/getting-started/webhooks
final class Captured extends Charge {
	/**
	 * 2016-03-25
	 * @override
	 * Similar to: @see \Magento\Sales\Controller\Adminhtml\Order\Invoice\Save::execute()
	 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Controller/Adminhtml/Order/Invoice/Save.php#L102-L235
	 * How does the backend invoicing work? https://mage2.pro/t/933
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return Phrase|null
	 * @throws LE
	 */
	protected function process() {
		$r = null; /** @var string|null $r */
		$o = $this->o(); /** @var O|DfOrder $o */
		# 2016-05-11 The payment is «Flagged». The «Accept» operation should be performed.
		if ($o->isPaymentReview()) {
			$this->op()->accept();
			$o->save();
		}
		# 2016-05-11 The payment is in the «Authorized» state.
		# 2016-12-30
		# Мы не должны считать исключительной ситуацией повторное получение
		# ранее уже полученного оповещения.
		# В документации к Stripe, например, явно сказано:
		# «Webhook endpoints may occasionally receive the same event more than once.
		# We advise you to guard against duplicated event receipts
		# by making your event processing idempotent.»
		# https://stripe.com/docs/webhooks#best-practices
		elseif (!$o->canInvoice()) {
			$r = __('The order does not allow an invoice to be created.');
		}
		else {
			$o->setIsInProcess(true);
			$o->setCustomerNoteNotify(true);
			df_db_transaction()->addObject($i = $this->invoice())->addObject($o)->save(); /** @var Invoice|DfInvoice $i */
			df_mail_invoice($i);
		}
		return $r;
	}

	/**
	 * 2016-03-26
	 * @used-by self::process()
	 * @return Invoice|DfInvoice
	 * @throws LE
	 */
	private function invoice() {
		$invoiceService = df_o(InvoiceService::class); /** @var InvoiceService $invoiceService */
		$r = $invoiceService->prepareInvoice($this->o()); /** @var Invoice|DfInvoice $r */
		if (!$r) {
			throw new LE(__("We can't save the invoice right now."));
		}
		if (!$r->getTotalQty()) {
			throw new LE(__("You can't create an invoice without products."));
		}
		df_register('current_invoice', $r);
		/**
		 * 2016-03-26
		 * @used-by \Magento\Sales\Model\Order\Invoice::register()
		 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Invoice.php#L599-L609
		 * We use \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE,
		 * and not \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFINE,
		 * that was created by the transaction capture.
		 */
		$r->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
		$r->register();
		return $r;
	}
}