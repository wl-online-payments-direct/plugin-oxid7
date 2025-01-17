<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Core\Registry;

class FcwlopThankYouController extends FcwlopThankYouController_parent
{
    /**
     * Delete session variable after order finish
     */
    public function render()
    {
        FcwlopPaymentHelper::getInstance()->fcwlopCleanWorldlineSession();
        return parent::render();
    }
    
    /**
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentMethod()
    {
        $oOrder = $this->getOrder();
        if (!$oOrder) {
            return false;
        }

        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        return FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($sPaymentId);
    }

    /**
     * @return string
     */
    public function fcwlopIsPendingCheckout()
    {
        Registry::getLogger()->error(Registry::getRequest()->getRequestEscapedParameter('pendingCheckout'));
        return Registry::getRequest()->getRequestEscapedParameter('pendingCheckout') == 1;
    }
}
