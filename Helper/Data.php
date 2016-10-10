<?php
/**
*/
namespace Dfe\CheckoutCom\Helper;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Checkout.com data helper.
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const METHOD_CODE = 'checkout_com';

    const MAX_SAVED_CARDS = 5;

    const CARD_IS_ACTIVE = 1;

    const CARD_NOT_ACTIVE = 0;

    /**
     *@var Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_date;

    /**
     * Customer session.
     * 
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $_formKeyValidator;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @param Magento\Framework\App\Helper\Context        $context
     * @param Magento\Directory\Model\Currency            $currency
     * @param Magento\Customer\Model\Session              $customerSession
     * @param Magento\Framework\UrlInterface              $url
     * @param Magento\Catalog\Model\ResourceModel\Product $product
     * @param Magento\Store\Model\StoreManagerInterface   $_storeManager
     */
    public function __construct(
        Session $customerSession,
        \Magento\Framework\App\Helper\Context $context,
        FormKeyValidator $formKeyValidator,
        DateTime $date,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository

    ) 
    {
        $this->_date = $date;
        $this->_customerSession = $customerSession;
        $this->_objectManager = $objectManager;
        $this->_formKeyValidator = $formKeyValidator;
        $this->_storeManager = $storeManager;
        $this->_productRepository = $productRepository;

        parent::__construct($context);
    }

    /**
     * function to get Config Data.
     * 
     * @return string
     */
    public function getConfigValue($field = false)
    {
        if ($field) {
            return $this->scopeConfig
                    ->getValue(
                        'payment/'.self::METHOD_CODE.'/'.$field,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    );
        } else {
            return;
        }
    }

    /**
     * getIsActive check if payment method active.
     *
     * @return bool
     */
    public function getIsActive()
    {
        return $this->getConfigValue('active');
    }

    /**
     * saveCheckoutComCustomer save Checkout.com customer id for future payment.
     *
     * @return string Checkout.com customer id
     */
    public function saveCheckoutComCustomer($email, $checkoutComCustomerId, $last4, $card_id, $card_type)
    {
        if ($this->_customerSession->isLoggedIn()) {
            $savedCards = $this->getSavedCards();
            $cardCount = 0;
            if ($savedCards) {
                $cardCount = $savedCards->getSize();
            } else {
                $cardCount = 0;
            }
            if ($cardCount < self::MAX_SAVED_CARDS) {
                $data = [
                    'customer_id' => $this->_customerSession->getCustomer()->getId(),
                    'is_active' => self::CARD_IS_ACTIVE,
                    'checkoutcom_email' => $email,
                    'checkoutcom_customer_id' => $checkoutComCustomerId,
                    'last4' => $last4,
                    'website_id' => $this->_storeManager->getStore()->getWebsiteId(),
                    'store_id' => $this->_storeManager->getStore()->getId(),
                    'checkoutcom_card_id' => $card_id,
                    'checkoutcom_card_type' => $card_type,
                ];
                try {
                    $model = $this->_objectManager
                                ->create('Dfe\CheckoutCom\CheckoutComCustomer');
                    $model->setData($data);
                    $model->save();
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }
        }
    }

    /**
     * getSavedCards function to get saved cards of the customer.
     *
     * @return Dfe\CheckoutCom\CheckoutComCustomer
     */
    public function getSavedCards()
    {
        if ($this->_customerSession->isLoggedIn()) {
            $customerId = $this->_customerSession->getCustomer()->getId();
            $collection = $this->_objectManager
                            ->create('Dfe\CheckoutCom\CheckoutComCustomer')
                            ->getCollection()
                            ->addFieldToFilter('customer_id', ['eq' => $customerId]);
            if ($collection->getSize() > 0) {
                return $collection;
            } else {
                return false;
            }
        }
    }

    
}
