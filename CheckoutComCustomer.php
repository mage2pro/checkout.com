<?php
/**
*

*/
namespace Dfe\CheckoutCom;

use Dfe\CheckoutCom\Api\Data\CheckoutComCustomerInterface;
use Magento\Framework\DataObject\IdentityInterface;

/**
 * Checkout.com CheckoutComCustomer Model.
 */
class CheckoutComCustomer extends \Magento\Framework\Model\AbstractModel
implements CheckoutComCustomerInterface, IdentityInterface
{
    /**
     * No route page id.
     */
    const NOROUTE_ENTITY_ID = 'no-route';

    /**
     * Checkout.com CheckoutComCustomer cache tag.
     */
    const CACHE_TAG = 'checkoutcom_customer';

    /**
     * @var string
     */
    protected $_cacheTag = 'checkoutcom_customer';

    /**
     * Prefix of model events names.
     *
     * @var string
     */
    protected $_eventPrefix = 'checkoutcom_customer';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('Dfe\CheckoutCom\ResourceModel\CheckoutComCustomer');
    }

    /**
     * Load object data.
     *
     * @param int|null $id
     * @param string   $field
     *
     * @return $this
     */
    public function load($id, $field = null)
    {
        if ($id === null) {
            return $this->noRouteReasons();
        }

        return parent::load($id, $field);
    }

    /**
     * Load No-Route CheckoutComCustomer.
     *
     * @return Dfe\CheckoutCom\CheckoutComCustomer
     */
    public function noRouteReasons()
    {
        return $this->load(self::NOROUTE_ENTITY_ID, $this->getIdFieldName());
    }

    /**
     * Get identities.
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG.'_'.$this->getId()];
    }

    /**
     * Get ID.
     *
     * @return int
     */
    public function getId()
    {
        return parent::getData(self::ENTITY_ID);
    }

    /**
     * Set ID.
     *
     * @param int $id
     *
     * @return Dfe\CheckoutCom\Api\Data\CheckoutComCustomerInterface
     */
    public function setId($id)
    {
        return $this->setData(self::ENTITY_ID, $id);
    }
}
