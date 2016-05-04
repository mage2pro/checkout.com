<?php
namespace Dfe\CheckoutCom;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface as IGuest;
use Magento\Checkout\Api\PaymentInformationManagementInterface as IRegistered;
class PlaceOrder {
	/**
	 * 2016-05-04
	 * @param string $cartId
	 * @param string $email
	 * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
	 * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 * @return array
	 */
	public function guest(
		$cartId,
		$email,
		\Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
		\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
	) {
		/** @var IGuest $iGuest */
		$iGuest = df_o(IGuest::class);
		/** @var int $orderId */
		$orderId = $iGuest->savePaymentInformationAndPlaceOrder(
			$cartId, $email, $paymentMethod, $billingAddress
		);
		return [$orderId];
	}

	/**
	 * 2016-05-04
	 * @param int $cartId
	 * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
	 * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
	 * @throws \Magento\Framework\Exception\CouldNotSaveException
	 * @return array
	 */
	public function registered(
		$cartId,
		\Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
		\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
	) {
		/** @var IRegistered $iRegistered */
		$iRegistered = df_o(IRegistered::class);
		/** @var int $orderId */
		$orderId = $iRegistered->savePaymentInformationAndPlaceOrder(
			$cartId, $paymentMethod, $billingAddress
		);
		return [$orderId];
	}
}


