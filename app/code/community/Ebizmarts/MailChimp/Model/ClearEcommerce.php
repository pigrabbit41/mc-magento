<?php

/**
 * MailChimp For Magento
 *
 * @category  Ebizmarts_MailChimp
 * @author    Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date:     7/24/19 2:47 PM
 * @file:     ClearEcommerce.php
 */
class Ebizmarts_MailChimp_Model_ClearEcommerce
{
    const DISABLED_STATUS = 2;
    const NOT_ACTIVE_STATUS = 0;

    /**
     * @var Ebizmarts_MailChimp_Helper_Data
     */
    protected $_helper;

    /**
     * @var Ebizmarts_MailChimp_Helper_Date
     */
    protected $_dateHelper;

    public function __construct()
    {
        $this->_helper = Mage::helper('mailchimp');
        $this->_dateHelper = Mage::helper('mailchimp/date');
    }

    /**
     * @return Ebizmarts_MailChimp_Helper_Data|Mage_Core_Helper_Abstract
     */
    protected function getHelper()
    {
        return $this->_helper;
    }

    /**
     * @return Ebizmarts_MailChimp_Helper_Date|Mage_Core_Helper_Abstract
     */
    protected function getDateHelper()
    {
        return $this->_dateHelper;
    }

    /**
     * Process all types of data from eCommerce data to delete
     * non active products, quotes, customers, etc. from the table.
     */
    public function cleanEcommerceData()
    {
        $this->processData(
            $this->getItemsToDelete(
                Ebizmarts_MailChimp_Model_Config::IS_PRODUCT
            ),
            Ebizmarts_MailChimp_Model_Config::IS_PRODUCT
        );
        $this->processData(
            $this->getItemsToDelete(
                Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER
            ),
            Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER
        );
        $this->processData(
            $this->getItemsToDelete(
                Ebizmarts_MailChimp_Model_Config::IS_QUOTE
            ),
            Ebizmarts_MailChimp_Model_Config::IS_QUOTE
        );
        $this->processData(
            $this->getItemsToDelete(
                Ebizmarts_MailChimp_Model_Config::IS_PROMO_RULE
            ),
            Ebizmarts_MailChimp_Model_Config::IS_PROMO_RULE
        );
        $this->processData(
            $this->getItemsToDelete(
                Ebizmarts_MailChimp_Model_Config::IS_PROMO_CODE
            ),
            Ebizmarts_MailChimp_Model_Config::IS_PROMO_CODE
        );
    }

    /**
     * @param $data
     * @param $type
     */
    protected function processData($data, $type)
    {
        $ids = array();
        foreach ($data as $item) {
            $ids []= $item->getId();
        }

        $reverseIds = $this->processDeletedData($type);
        $ids = array_merge($ids, $reverseIds);

        if (!empty($ids)) {
            $this->deleteEcommerceRows($ids, $type);
        }
    }

    /**
     * @param $type
     * @return array
     */
    protected function processDeletedData($type)
    {
        $ids = array();
        $eData = $this->getDeletedRows($type);

        foreach ($eData as $eItem) {
            $ids []= $eItem['related_id'];
        }

        return $ids;
    }

    /**
     * Get the items from eCommerce data that had been disabled.
     *
     * @param $type
     * @param bool $filter
     * @return array
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getItemsToDelete($type, $filter = true)
    {
        $items = array();
        switch ($type) {
            case Ebizmarts_MailChimp_Model_Config::IS_PRODUCT:
                $items = $this->getProductItems($filter);
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_QUOTE:
                $items = $this->getQuoteItems($filter);
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER:
                $items = $this->getCustomerItems($filter);
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_PROMO_RULE:
                $items = $this->getPromoRuleItems($filter);
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_PROMO_CODE:
                $items = $this->getPromoCodeItems($filter);
                break;
        }

        return $items;
    }

    /**
     * @param $filter
     * @return array
     */
    protected function getProductItems($filter)
    {
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->setPageSize(100)
            ->setCurPage(1);
        if ($filter) {
            $collection->addFieldToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
        }

        return $collection->getItems();
    }

    /**
     * @param $filter
     * @return array
     */
    protected function getQuoteItems($filter)
    {
        $collection = Mage::getModel('sales/quote')
            ->getCollection()
            ->setPageSize(100)
            ->setCurPage(1);
        if ($filter) {
            $collection->addFieldToFilter('is_active', array('eq' => self::NOT_ACTIVE_STATUS));
        }

        return $collection->getItems();
    }

    /**
     * @param $filter
     * @return array
     */
    protected function getCustomerItems($filter)
    {
        $items = array();
        $collection = Mage::getModel('customer/customer')
            ->getCollection()
            ->setPageSize(100)
            ->setCurPage(1);
        if ($filter) {
            $customers = $collection->getItems();
            foreach ($customers as $item) {
                if ($item->getIsActive() == self::NOT_ACTIVE_STATUS) {
                    $items [] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param $filter
     * @return array
     */
    protected function getPromoRuleItems($filter)
    {
        $collection = Mage::getModel('salesrule/rule')
            ->getCollection()
            ->setPageSize(100)
            ->setCurPage(1);
        if ($filter) {
            $collection->addFieldToFilter('is_active', array('eq' => self::NOT_ACTIVE_STATUS));
        }

        return $collection->getItems();
    }

    /**
     * @param $filter
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getPromoCodeItems($filter)
    {
        $collection = Mage::getModel('salesrule/coupon')
            ->getCollection()
            ->setPageSize(100)
            ->setCurPage(1);
        if ($filter) {
            $date = $this->getDateHelper()->formatDate(null, 'YYYY-mm-dd H:i:s');
            $collection->addFieldToFilter('expiration_date', array('lteq' => $date));
        }

        return $collection->getItems();
    }

    /**
     * Returns the rows that still exist in eCommerce data but
     * that had been deleted in it respective entity (product,
     * quote, promo code, etc.)
     *
     * @param $type
     * @return array
     */
    protected function getDeletedRows($type)
    {
        $resource = Mage::getSingleton('core/resource');
        switch ($type) {
            case Ebizmarts_MailChimp_Model_Config::IS_PRODUCT:
                $entityTable = $resource->getTableName('catalog/product');
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_QUOTE:
                $entityTable = $resource->getTableName('sales/quote');
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_CUSTOMER:
                $entityTable = $resource->getTableName('customer/customer');
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_PROMO_RULE:
                $entityTable = $resource->getTableName('salesrule/rule');
                break;
            case Ebizmarts_MailChimp_Model_Config::IS_PROMO_CODE:
                $entityTable = $resource->getTableName('salesrule/coupon');
                break;
        }

        $ecommerceData = Mage::getModel('mailchimp/ecommercesyncdata')
            ->getCollection()
            ->addFieldToSelect('related_id')
            ->setPageSize(100);
        $ecommerceData->addFieldToFilter('type', array('eq' => $type));
        $ecommerceData->addFieldToFilter('ent.entity_id', array('null' => true));
        $ecommerceData->getSelect()->joinLeft(
            array('ent' => $entityTable),
            'main_table.related_id = ent.entity_id'
        );

        return $ecommerceData->getData();
    }

    /**
     * @param $ids
     * @param $type
     */
    protected function deleteEcommerceRows($ids, $type)
    {
        $ids = implode($ids, ', ');
        $where = array(
            "related_id IN ($ids)",
            "type = '$type'"
        );

        $helper = $this->getHelper();
        $resource = $helper->getCoreResource();
        $connection = $resource->getConnection('core_write');
        $tableName = $resource->getTableName('mailchimp/ecommercesyncdata');
        $connection->delete($tableName, $where);
    }
}

