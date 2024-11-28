<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\TransactionHandler;

use OnlinePayments\Sdk\Domain\WebhooksEvent;
use OxidEsales\Eshop\Application\Model\Order;

abstract class FcwlopBaseTransactionHandler
{
    /**
     * Logfile name
     *
     * @var string
     */
    protected string $sLogFileName = 'WorldlineTransactions.log';

    /**
     *  Process transaction status
     *
     * @param Order $oOrder
     * @param string $sSource
     * @param WebhooksEvent|null $oEvent
     * @return bool
     */
    public function fcwlopProcessTransaction(Order $oOrder, $sSource = 'webhook', WebhooksEvent $oEvent = null)
    {
        try {
            $aResult = $this->handleTransactionStatus($oOrder, $sSource, $oEvent);
        } catch(\Exception $oEx) {
            $aResult = ['success' => 0, 'status' => 'exception', 'error' => $oEx->getMessage()];
        }

        $aResult['transactionId'] = $oOrder->oxorder__oxtransid->value;
        $aResult['orderId'] = $oOrder->getId();
        $aResult['source'] = $sSource;

        $this->logResult($aResult);

        return $aResult['success'];
    }

    protected abstract function handleTransactionStatus(Order $oOrder, $sSource, WebhooksEvent $oEvent = null);

    /**
     * Check for given external trans id
     *
     * @param Order $oOrder
     * @param WebhooksEvent|null $oEvent
     * @return void
     */
    protected function handleExternalTransId(Order $oOrder, WebhooksEvent $oEvent = null)
    {
        $sExternalTransactionId = false;

        if ($sExternalTransactionId !== false) {
            $oOrder->fcwlopSetExternalTransactionId($sExternalTransactionId);
        }
    }

    /**
     * Log transaction status to log file if enabled
     *
     * @param array $aResult
     * @return void
     */
    protected function logResult($aResult)
    {
        $sMessage = (new \DateTimeImmutable())->format('Y-m-d H:i:s')." Transaction handled: ".print_r($aResult, true)." \n";

        $sLogFilePath = getShopBasePath().'/log/'.$this->sLogFileName;
        $oLogFile = fopen($sLogFilePath, "a");
        if ($oLogFile) {
            fwrite($oLogFile, $sMessage);
            fclose($oLogFile);
        }
    }
}
