<?php
namespace Dfe\CheckoutCom\Handler\Charge;
use Df\Sales\Model\Order as DfOrder;
use Df\Sales\Model\Order\Invoice as DfInvoice;
use Dfe\CheckoutCom\Handler\Charge;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Service\InvoiceService;
// 2016-05-10
// charge.captured
// http://docs.checkout.com/getting-started/webhooks
class Captured extends Charge {
	/**
	 * 2016-03-25
	 * @override
	 * Similar to: @see \Magento\Sales\Controller\Adminhtml\Order\Invoice\Save::execute()
	 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Controller/Adminhtml/Order/Invoice/Save.php#L102-L235
	 * How does the backend invoicing work? https://mage2.pro/t/933
	 * @see \Dfe\CheckoutCom\Handler::process()
	 * @used-by \Dfe\CheckoutCom\Handler::p()
	 * @return string|null
	 * @throws LE
	 */
	final protected function process() {
		/** @var string|null $result */
		$result = null;
		// 2016-05-11
		// The payment is «Flagged».
		// The «Accept» operation should be performed.
		if ($this->order()->isPaymentReview()) {
			$this->payment()->accept();
			$this->order()->save();
		}
		// 2016-05-11
		// The payment is in the «Authorized» state.
		else {
			/**
			 * 2016-12-30
			 * Мы не должны считать исключительной ситуацией повторное получение
			 * ранее уже полученного оповещения.
			 * В документации к Stripe, например, явно сказано:
			 * «Webhook endpoints may occasionally receive the same event more than once.
			 * We advise you to guard against duplicated event receipts
			 * by making your event processing idempotent.»
			 * https://stripe.com/docs/webhooks#best-practices
			 */
			if (!$this->order()->canInvoice()) {
				$result = __('The order does not allow an invoice to be created.');
			}
			else {
				$this->order()->setIsInProcess(true);
				$this->order()->setCustomerNoteNotify(true);
				/** @var Transaction $t */
				$t = df_db_transaction();
				$t->addObject($this->invoice());
				$t->addObject($this->order());
				$t->save();
				/** @var InvoiceSender $sender */
				$sender = df_o(InvoiceSender::class);
				$sender->send($this->invoice());
			}
		}
		return $result;
	}

	/**
	 * 2016-03-26
	 * @return Invoice|DfInvoice
	 * @throws LE
	 */
	private function invoice() {
		if (!isset($this->{__METHOD__})) {
			/** @var InvoiceService $invoiceService */
			$invoiceService = df_o(InvoiceService::class);
			/** @var Invoice|DfInvoice $result */
			$result = $invoiceService->prepareInvoice($this->order());
			if (!$result) {
				throw new LE(__('We can\'t save the invoice right now.'));
			}
			if (!$result->getTotalQty()) {
				throw new LE(__('You can\'t create an invoice without products.'));
			}
			df_register('current_invoice', $result);
			/**
			 * 2016-03-26
			 * @used-by \Magento\Sales\Model\Order\Invoice::register()
			 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Invoice.php#L599-L609
			 * We use \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE,
			 * and not \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFINE,
			 * that was created by the transaction capture.
			 */
			$result->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
			$result->register();
			$this->{__METHOD__} = $result;
		}
		return $this->{__METHOD__};
	}
}