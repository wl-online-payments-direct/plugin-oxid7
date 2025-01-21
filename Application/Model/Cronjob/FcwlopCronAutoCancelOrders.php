<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Cronjob;

class FcwlopCronAutoCancelOrders extends Base
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = 'worldline_auto_cancel_orders';

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = 5;

    /**
     * Collects all orders that are unpaid and pending
     *
     * @return array
     */
    protected function fcwlopGetUnfinishedOrders()
    {
        $aOrders = [];

        return $aOrders;
    }

    /**
     * Collects expired order ids and finishes these orders
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        return true;
    }
}
