<?php
/**
*/
namespace Dfe\CheckoutCom\Block;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Checkout.com block.
 *
 */
class Cards extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_date;
    /**
     * @var Dfe\CheckoutCom\ResourceModel\CheckoutComCustomer\CollectionFactory
     */
    protected $_checkoutComCustomerFactory;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * 
     * @param \Magento\Framework\View\Element\Template\Context                        $context           
     * @param \Dfe\CheckoutCom\ResourceModel\CheckoutComCustomer\CollectionFactory $vacationFactory
     * @param \Magento\Customer\Model\Session                                         $customerSession
     * @param \Magento\Framework\Message\ManagerInterface                             $messageManager
     * @param DateTime                                                                $date              
     * @param Store                                                                   $store            
     * @param array                                                                   $data              
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Dfe\CheckoutCom\ResourceModel\CheckoutComCustomer\CollectionFactory $checkoutComCustomerFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        DateTime $date,
        array $data = []
    ) 
    {
        $this->_date = $date;
        $this->_messageManager = $messageManager;
        $this->_checkoutComCustomerFactory = $checkoutComCustomerFactory;
        $this->_customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    /**
     * getSavedCards get customer saved cards
     * 
     * @return Dfe\CheckoutCom\StripeCustomer
     */
    public function getSavedCards()
    {
        $customerId = $this->_customerSession->getCustomerId();
        $cardData = $this->_checkoutComCustomerFactory->create()
                    ->addFieldToFilter('customer_id', ['eq' => $customerId]);
        if($cardData->getSize() > 0)
            return $cardData;

        return false;
    }

    

}