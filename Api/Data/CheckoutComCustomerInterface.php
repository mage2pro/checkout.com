<?php
/**
*
*/
namespace Dfe\CheckoutCom\Api\Data;

/**
 * Checkout.com CheckoutComCustomer interface.
 *
 * @api
 */
interface CheckoutComCustomerInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const ENTITY_ID = 'entity_id';
    /**#@-*/

    /**
     * Get ID.
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set ID.
     *
     * @param int $id
     *
     * @return Dfe\CheckoutCom\Api\Data\ReasonsInterface
     */
    public function setId($id);
}