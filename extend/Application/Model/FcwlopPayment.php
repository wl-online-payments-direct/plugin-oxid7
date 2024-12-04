<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;

class FcwlopPayment extends FcwlopPayment_parent
{
    /**
     * Check if given payment method is a Worldline method
     *
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentMethod()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($this->getId());
    }

    /**
     * Return Worldline payment model
     *
     * @return FcwlopWorldlineGenericMethod
     */
    public function fcwlopGetWorldlinePaymentModel()
    {
        if ($this->fcwlopIsWorldlinePaymentMethod()) {
            return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($this->getId());
        }
        return null;
    }
}
