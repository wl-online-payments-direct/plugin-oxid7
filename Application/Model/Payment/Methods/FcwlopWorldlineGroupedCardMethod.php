<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment\Methods;

use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedCheckoutRequest;
use FC\FCWLOP\Application\Model\TransactionHandler\FcwlopPaymentTransactionHandler;
use OxidEsales\Eshop\Application\Model\Order;

class FcwlopWorldlineGroupedCardMethod extends FcwlopWorldlineGenericMethod
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'fcwlopgroupedcard';

    /**
     * Method code used for API request
     *
     * @var int
     */
    protected $iWorldlinePaymentCode = 0;

    /**
     * Determines if the payment method is hidden at first when payment list is displayed
     *
     * @var bool
     */
    protected $blIsMethodHiddenInitially = false;

}
