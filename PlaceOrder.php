<?php
namespace Dfe\CheckoutCom;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface as IGuest;
use Magento\Checkout\Api\PaymentInformationManagementInterface as IRegistered;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface as PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
class PlaceOrder {
	/**
	 * 2016-05-04
	 * @param string $cartId
	 * @param string $email
	 * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
	 * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 * @return string
	 */
	public function guest(
		$cartId,
		$email,
		\Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
		\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
	) {
		/** @var IGuest $iGuest */
		$iGuest = df_o(IGuest::class);
		return $this->response($iGuest->savePaymentInformationAndPlaceOrder(
			$cartId, $email, $paymentMethod, $billingAddress
		));
	}

	/**
	 * 2016-05-04
	 * @param int $cartId
	 * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
	 * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 * @return string
	 */
	public function registered(
		$cartId,
		\Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
		\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
	) {
		/** @var IRegistered $iRegistered */
		$iRegistered = df_o(IRegistered::class);
		return $this->response($iRegistered->savePaymentInformationAndPlaceOrder(
			$cartId, $paymentMethod, $billingAddress
		));
	}

	/**
	 * 2016-05-04
	 * @param int $orderId
	 * @return string|null
	 */
	private function response($orderId) {
		return df_order($orderId)->getPayment()->getAdditionalInformation(Method::REDIRECT_URL);
	}
}


