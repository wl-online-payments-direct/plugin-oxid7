<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment\Methods;

use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedCheckoutRequest;
use FC\FCWLOP\Application\Model\TransactionHandler\FcwlopPaymentTransactionHandler;
use OxidEsales\Eshop\Application\Model\Order;

class FcwlopWorldlineGenericCardMethod extends FcwlopWorldlineGroupedCardMethod
{
    /**
     * Determines if the payment methods has to add a redirect url to the request
     *
     * @var bool
     */
    protected $blIsRedirectUrlNeeded = false;

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'fcwlopcreditcard';
}
