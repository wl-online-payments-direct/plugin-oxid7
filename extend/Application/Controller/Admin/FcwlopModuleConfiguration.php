<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller\Admin;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Core\Registry;

class FcwlopModuleConfiguration extends ModuleConfiguration_parent
{
    /**
     * @return string
     */
    public function fcwlopGetConfigUpdateUrl()
    {
        return Registry::getConfig()->getShopUrl().'?cl=fcwlopConfiguration&fnc=fcwlopUpdatePaymentMethodsConfig'; 
    }

    /**
     * @return string
     */
    public function fcwlopTestConnectionUrl()
    {
        return Registry::getConfig()->getShopUrl().'?cl=fcwlopConfiguration&fnc=fcwlopTestConnection';
    }

    /**
     * @return string
     */
    public function fcwlopAccountCreationUrl()
    {
        return 'https://docs.direct.worldline-solutions.com/en/getting-started/';
    }

    /**
     * @return string
     */
    public function fcwlopWebhookDestinationUrl()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopGetWebhookUrl();
    }
}