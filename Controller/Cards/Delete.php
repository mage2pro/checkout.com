<?php
/**
*/
namespace Dfe\CheckoutCom\Controller\Cards;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class Delete extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /** 
     * @var Dfe\CheckoutCom\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $_formKeyValidator;

    /**
     * @param Context                         $context
     * @param PageFactory                     $resultPageFactory
     * @param \Magento\Customer\Model\Session $customerSession   customer session
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Session $customerSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Dfe\CheckoutCom\Helper\Data $helper,
        FormKeyValidator $formKeyValidator
    ) 
    {
        $this->_helper = $helper;
        $this->_customerSession = $customerSession;
        $this->_formKeyValidator = $formKeyValidator;
        $this->_resultPageFactory = $resultPageFactory;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * Retrieve customer session object.
     *
     * @return \Magento\Customer\Model\Session
     */
    protected function _getSession()
    {
        return $this->_customerSession;
    }

    /**
     * Check customer authentication.
     *
     * @param RequestInterface $request
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        $loginUrl = $this->_objectManager->get('Magento\Customer\Model\Url')->getLoginUrl();

        if (!$this->_customerSession->authenticate($loginUrl)) {
            $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
        }

        return parent::dispatch($request);
    }

    /**
     * delete Checkout.com cards.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {

        if ($this->getRequest()->isPost()) {
            try {
                if (!$this->_formKeyValidator->validate($this->getRequest())) {
                    return $this->resultRedirectFactory
                                    ->create()
                                    ->setPath('*/*/index', ['_secure' => $this->getRequest()->isSecure()]);
                }

                $requestData = $this->getRequest()->getParams();
                if (isset($requestData['card_id'])) {
                    $cardIds = $requestData['card_id'];
                    $response = $this->deleteCards($cardIds, $this->_customerSession->getCustomerId());
                    if ($response) {
                        $this->messageManager->addSuccess(__('Cards successfully deleted'));
                    } else {
                        $this->messageManager->addError(__('Not able to delete the cards'));
                    }
                }
            } catch (Exception $e) {
                $this->messageManager->addError(__($e->getMessage()));
            }
        }

        return $this->resultRedirectFactory
                        ->create()
                        ->setPath('*/*/index', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     * deleteCards function to delete cards of the customer.
     *
     * @return bool
     */
    public function deleteCards($cards = array(), $customerId = 0)
    {
        if ($customerId && count($cards) > 0) {
            $collection = $this->_objectManager
                            ->create('Dfe\CheckoutCom\CheckoutComCustomer')
                            ->getCollection()
                            ->addFieldToFilter('customer_id', ['eq' => $customerId])
                            ->addFieldToFilter('entity_id', ['in' => $cards]);
            if ($collection->getSize() > 0) {
                foreach ($collection as $card) {
                    try {
                        $card->delete();
                    } catch (Exception $e) {
                        return $e->getMessage();
                    }
                }
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }
}
