<?php
/**
*/
namespace Dfe\CheckoutCom\ResourceModel;

/**
 * Checkout.com CheckoutComCustomer ResourceModel.
 */
class CheckoutComCustomer extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
/**
 * Store model
 *
 * @var null|\Magento\Store\Model\Store
 */
protected $_store = null;

/**
 * Construct
 *
 * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
 * @param string $connectionName
 */
public function __construct(
\Magento\Framework\Model\ResourceModel\Db\Context $context,
$connectionName = null
) 
{
parent::__construct($context, $connectionName);
}

/**
 * Initialize resource model
 *
 * @return void
 */
protected function _construct()
{
$this->_init('checkoutcom_customer', 'entity_id');
}

/**
 * Load an object using 'identifier' field if there's no field specified and value is not numeric
 *
 * @param \Magento\Framework\Model\AbstractModel $object
 * @param mixed $value
 * @param string $field
 * @return $this
 */
public function load(\Magento\Framework\Model\AbstractModel $object, $value, $field = null)
{
if (!is_numeric($value) && is_null($field)) {
$field = 'identifier';
}

return parent::load($object, $value, $field);
}

/**
 * Set store model
 *
 * @param \Magento\Store\Model\Store $store
 * @return $this
 */
public function setStore($store)
{
$this->_store = $store;
return $this;
}

/**
 * Retrieve store model
 *
 * @return \Magento\Store\Model\Store
 */
public function getStore()
{
return $this->_storeManager->getStore($this->_store);
}
}