<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment\Methods;

class FcwlopWorldlineSepaDirectDebit extends FcwlopWorldlineGenericMethod
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = 'fcwlopsepadirectdebit';

    /**
     * Method code used for API request
     *
     * @var int
     */
    protected $iWorldlinePaymentCode = 771;

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'fcwlopsepadirectdebit';
}
