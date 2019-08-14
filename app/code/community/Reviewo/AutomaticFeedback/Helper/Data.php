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

class Reviewo_AutomaticFeedback_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Gets the current extension version
     *
     * @return string
     */
    public function getExtensionVersion()
    {
        $version = (string) Mage::getConfig()->getNode()->modules->Reviewo_AutomaticFeedback->version;
        return $version;
    }
}