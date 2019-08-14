<?php
/**
 * Reviewo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Reviewo
 * @package     Reviewo_AutomaticFeedback
 * @copyright   Copyright (c) 2014 Reviewo Ltd. (https://www.reviewo.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Reviewo_AutomaticFeedback_Model_Observer
{
    const API_ENDPOINT = 'https://www.reviewo.com/api';
    const API_VERSION = 'v1';

    public function __construct()
    {

    }

    /**
     * Gets a field value from the store config
     *
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $path = 'sales/reviewo_automaticfeedback/'.$field;
        return Mage::getStoreConfig($path, $storeId);
    }

    /**
     * Builds an resource uri from the current api version
     * and endpoint
     *
     * @param string $method
     * @return string
     */
    public function getResourceUri($method='')
    {
        return self::API_ENDPOINT.'/'.self::API_VERSION.'/'.$method.'/';
    }

    /**
     * Builds a Zend_Http_Client instance with auth and headers set
     *
     * @param $uri string
     * @return Zend_Http_Client
     */
    public function getClient($uri)
    {
        $extensionVersion = Mage::helper('reviewo_automaticfeedback')->getExtensionVersion();
        $phpVersion = phpversion();
        $magentoVersion = Mage::getVersion();
        $storeUrl = Mage::getBaseUrl();

        $client = new Zend_Http_Client($uri, array(
            'ssltransport' => 'tls',
            'timeout' => 5,
            'useragent' => 'Magento Automatic Feedback Extension - '.$extensionVersion,
        ));
        $client->setAuth($this->getConfigData('api_key'), '');
        $client->setHeaders(array(
            'x-user-agent' => json_encode(array(
                'php' => $phpVersion,
                'magento' => $magentoVersion,
                'extension' => $extensionVersion,
                'store' => $storeUrl
            )),
        ));
        return $client;
    }

    /**
     * Send the specified order to the orders endpoint
     *
     * returns the Reviewo order ID of the created object if successful
     * returns null if unsuccessful
     *
     * @param $order Mage_Sales_Model_Order
     * @return int|null
     */
    public function createOrder($order)
    {
        $client = $this->getClient($this->getResourceUri('order'))
            ->setMethod(Zend_Http_Client::POST)
            ->setRawData(json_encode(array(
                'reference' => $order->getIncrementId(),
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'purchased_at' => substr($order->getCreatedAtDate()->setTimeZone('UTC')->getIso(), 0, -6) . "Z",
            )), "application/json;charset=UTF-8");
        try {
            $response = $client->request();
        } catch (Exception $e) {
            Mage::logException($e);
            return null;
        }

        if ($response->isError()) {
            return null;
        }

        $decoded = json_decode($response->getBody(), true);

        if (!$decoded || !isset($decoded['id'])) {
            return null;
        }

        return $decoded['id'];
    }

    /**
     * Attempts to get the Reviewo order ID for a given order instance
     *
     * returns the Reviewo order ID of the order instance if found
     * returns null if unsuccessful
     *
     * @param $order Mage_Sales_Model_Order
     * @return int|null
     */
    public function fetchOrder($order)
    {
        $client = $this->getClient($this->getResourceUri('order'))
            ->setMethod(Zend_Http_Client::GET)
            ->setParameterGet(array(
                'limit' => 1,
                'reference' => $order->getIncrementId(),
            ));

        try {
            $response = $client->request();
        } catch (Exception $e) {
            Mage::logException($e);
            return null;
        }

        if ($response->isError()) {
            return null;
        }

        $decoded = json_decode($response->getBody(), true);

        if (!$decoded || !isset($decoded['objects'][0]['id'])) {
            return null;
        }

        return $decoded['objects'][0]['id'];
    }

    /**
     * Entry point for cronjob
     */
    public function sendOrders()
    {
        if (!$this->getConfigData('active')) { return; }

        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('reviewo_id', array('null' => true))
            ->setPageSize($this->getConfigData('limit'))
            ->setCurPage(1);

        foreach($orders as $order) {
            $orderId = $this->fetchOrder($order);
            $orderId = $orderId ? $orderId : $this->createOrder($order);
            if ($orderId) {
                try {
                    $order->setReviewoId($orderId)->save();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }
    }
}
