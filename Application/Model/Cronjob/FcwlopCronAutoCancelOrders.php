<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Cronjob;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcwlopCronAutoCancelOrders extends FcwlopCronBase
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
     * Checks if cronjob is activated in config
     * Hook to be overloaded by child classes
     * Return true if enabled in config
     * Return false if disabled
     *
     * @return bool
     */
    public function isCronjobActivated()
    {
        return (int) FcwlopPaymentHelper::getInstance()->fcwlopGetAutoCancellationDelay() > 0;
    }

    /**
     * Collects all orders that are unpaid and pending
     *
     * @return array
     * @throws \Exception
     */
    protected function fcwlopGetUnfinishedOrders()
    {
        $iAutoCancellationDelay = FcwlopPaymentHelper::getInstance()->fcwlopGetAutoCancellationDelay();
        $oStartDate = date_sub(
            new \DateTime(),
            new \DateInterval('PT' . $iAutoCancellationDelay . 'H')
        );

        $sQuery = "SELECT OXID FROM oxorder
            WHERE OXFOLDER != 'ORDERFOLDER_FINISHED'
            AND OXTRANSSTATUS != 'OK'
            AND OXORDERDATE >= '" . $oStartDate->format('Y-m-d H:i:s') . "'";

        $aOrders = DatabaseProvider::getDb()->getAll($sQuery);
        array_walk($aOrders, function(&$aItem) {
            return $aItem = $aItem[0];
        });

        return $aOrders;
    }

    /**
     * Collects expired order ids and finishes these orders
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        $oPaymentHelper = FcwlopPaymentHelper::getInstance();

        foreach ($this->fcwlopGetUnfinishedOrders() as $sOxid) {
            $oOrder = new Order();
            $oOrder->load($sOxid);

            // Order not found, skip
            if (!$oOrder) {
                continue;
            }


            // Order not from Worldline, skip
            if (!$oPaymentHelper->fcwlopIsWorldlinePaymentMethod($oOrder->oxorder__oxpaymenttype->value)) {
                continue;
            }

            $iWorldlineTransactionId = $oOrder->oxorder__oxtransid->value;
            if (!$iWorldlineTransactionId) {
                self::outputInfo('ORDER : ' . $oOrder->oxorder__oxordernr->value . ' to be cancelled. (empty WL transaction id)');
                $this->proceedOrderCancellation($oOrder);
                continue;
            }

            $oWorldlineTransaction = $oPaymentHelper->fcwlopGetWorldlinePaymentDetails($iWorldlineTransactionId);

            $blMustCancel = false;
            $sCancellationReason = '';
            if (!$blMustCancel && in_array($oWorldlineTransaction->getStatus(), ['CANCELLED', 'REJECTED', 'REJECTED_CAPTURE'])) {
                $blMustCancel = true;
                $sCancellationReason = 'payment status cancelled or rejected';
            }

            if (!$blMustCancel && !$oWorldlineTransaction->getStatusOutput()->getIsAuthorized()) {
                $blMustCancel = true;
                $sCancellationReason = 'payment not authorized';
            }

            if (!$blMustCancel && in_array($oWorldlineTransaction->getStatusOutput()->getStatusCategory(), ['UNSUCCESSFUL'])) {
                $blMustCancel = true;
                $sCancellationReason = 'payment status category : UNSUCCESSFUL';
            }

            /*
             * 0 - Invalid or incomplete
             * 1 - Cancelled by customer
             * 2 - Authorisation declined
             * 6 - Authorised and cancelled
             * 93 - Payment refused
             */
            if (!$blMustCancel && in_array($oWorldlineTransaction->getStatusOutput()->getStatusCode(), [0, 1, 2, 6, 93])) {
                $blMustCancel = true;
                $sCancellationReason = 'payment status code is either invalid, incomplete, cancelled, declined, refused or deleted';
            }

            if ($blMustCancel) {
                self::outputInfo('ORDER : ' . $oOrder->oxorder__oxordernr->value . ' to be cancelled. ( ' . $sCancellationReason . ')');
                $this->proceedOrderCancellation($oOrder);
            }
        }

        return true;
    }

    /**
     * @param Order $oOrder
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    protected function proceedOrderCancellation($oOrder)
    {
        $oOrder->cancelOrder();

        $sQuery = "UPDATE oxorder SET oxfolder = ?, oxtransstatus = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array('ORDERFOLDER_FINISHED', 'ERROR', $oOrder->getId()));
    }
}
