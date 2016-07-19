<?php
/**
*/
namespace Dfe\CheckoutCom\Controller\Cards;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;
    /**
     * @var Magento\Customer\Model\Session
     */
    protected $_customerSession;

    protected $_scopeConfig;

    protected $_helper;
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
        \Dfe\CheckoutCom\Helper\Data $helper
    ) 
    {
        $this->_helper = $helper;
        $this->_customerSession = $customerSession;
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
     * load Checkout.com cards.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $checkoutcomLabel = 'Saved Cards';
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__($checkoutcomLabel ));

        return $resultPage;
    }

}
