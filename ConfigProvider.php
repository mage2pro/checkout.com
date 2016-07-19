<?php
namespace Dfe\CheckoutCom;
use Dfe\CheckoutCom\Settings as S;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
class ConfigProvider implements ConfigProviderInterface {
	
    /**
     * $_helper.
     *
     * @var \Dfe\CheckoutCom\Helper\Data
     */
    protected $_helper;


    public function __construct(
        \Dfe\CheckoutCom\Helper\Data $helper
    )
    {
        $this->_helper = $helper;
    }
    
    /**

	/**
	 * 2016-02-27
	 * @override
	 * @see \Magento\Checkout\Model\ConfigProviderInterface::getConfig()
	 * https://github.com/magento/magento2/blob/cf7df72/app/code/Magento/Checkout/Model/ConfigProviderInterface.php#L15-L20
	 * @return array(string => mixed)
	 */
	public function getConfig() {
		return ['payment' => [Method::CODE => [
			'isActive' => S::s()->enable()
			,'prefill' => S::s()->prefill()
			,'publishableKey' => S::s()->publishableKey()
			,'isTest' => S::s()->test()
			,'saved_cards' => $this->getSavedCards()
		]]];
	}

	/**
	 * getSavedCards function to get customers cards json data
	 * @return json
	 */
	public function getSavedCards()
	{
		$cardsArray = [];
		$cards = $this->_helper->getSavedCards();
		if( $cards )
		{
			foreach ( $cards as $card )
			{
				array_push(
					$cardsArray,
					array(
						'checkoutcom_customer_id' => $card->getData('checkoutcom_customer_id'),
                        'checkoutcom_card_id' => $card->getData('checkoutcom_card_id'),
						'last4' => '****'.$card->getData('last4'),
					)
				);
			}
		}

		return json_encode($cardsArray);
	}
}