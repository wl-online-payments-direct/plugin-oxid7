<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\TransactionHandler;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OnlinePayments\Sdk\Domain\PaymentDetailsResponse;
use OnlinePayments\Sdk\Domain\WebhooksEvent;
use OxidEsales\Eshop\Application\Model\Order;

class FcwlopPaymentTransactionHandler extends FcwlopBaseTransactionHandler
{
    /**
     * Handle order according to the given transaction status
     *
     * @param Order $oOrder
     * @param string $sSource
     * @param WebhooksEvent|null $oEvent
     * @return array
     * @throws \Exception
     */
    protected function handleTransactionStatus(Order $oOrder, $sSource, WebhooksEvent $oEvent = null)
    {
        if ($sSource == 'webhook' && is_null($oEvent)) {
            return ['success' => 0];
        }

        $blSuccess = 1;
        $sTransactionType = $oEvent->getType();
        $sStatus = $this->getStatusCode($oEvent);
        $sTransactionCurrency = $this->getTransactionCurrency($oEvent);

        if ($sTransactionType == 'payment.created') {
            if ($sStatus == 0) {
                if ($sTransactionCurrency != strtolower($oOrder->oxorder__oxcurrency->value)) {
                    throw new \Exception('Currency does not match.');
                }

                if (!$oEvent->getRefund()) {
                    $oOrder->fcwlopSetFolder('new');
                    $oOrder->save();
                }
            }
        } elseif ($sTransactionType == 'payment.captured') {
            if ($sTransactionCurrency != strtolower($oOrder->oxorder__oxcurrency->value)) {
                throw new \Exception('Currency does not match.');
            }
            if ($sStatus == 9) {
                /** @var PaymentDetailsResponse $oWorldlinePayment */
                $oWorldlinePayment = $oOrder->fcwlopGetWorldlinePaymentDetails();
                if ($oWorldlinePayment->getStatus() == 'CAPTURED') {
                    $oOrder->fcwlopSetStatus('ok');
                    $oOrder->fcwlopMarkAsPaid();
                    FcwlopOrderHelper::getInstance()->fcwlopMarkOrderAsFullyCaptured($oOrder);
                    $oOrder->save();
                }
            }
        } elseif ($sTransactionType == 'payment.refunded') {
            if ($sTransactionCurrency != strtolower($oOrder->oxorder__oxcurrency->value)) {
                throw new \Exception('Currency does not match.');
            }

            /** @var PaymentDetailsResponse $oWorldlinePayment */
            $oWorldlinePayment = $oOrder->fcwlopGetWorldlinePaymentDetails();
            if ($oWorldlinePayment->getStatus() == 'REFUNDED') {
                $oOrder->fcwlopSetFolder('finished');
                $oOrder->fcwlopSetStatus('ok');
                $oOrder->fcwlopMarkAsPaid();
                $oOrder->save();
            }
        } elseif ($sTransactionType == 'payment.cancelled') {
            if ($sTransactionCurrency != strtolower($oOrder->oxorder__oxcurrency->value)) {
                throw new \Exception('Currency does not match.');
            }
            $oOrder->fcwlopSetFolder('finished');
            $oOrder->cancelOrder();
            $oOrder->save();
        } elseif ($sTransactionType == 'payment.rejected') {
            if ($sTransactionCurrency != strtolower($oOrder->oxorder__oxcurrency->value)) {
                throw new \Exception('Currency does not match.');
            }

            $oOrder->fcwlopSetFolder('problems');
            $oOrder->fcwlopSetStatus('error');
            $oOrder->save();
        } elseif ($sTransactionType == 'payment.pending_capture') {
            if ($oOrder->fcwlopGetCaptureMode() == 'direct-sales') {
                $oCaptureRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetFullCaptureRequest($oOrder);
                $oCaptureRequest->execute();
            }
        }

        return [
            'success' => $blSuccess,
            'transactionType' => $sTransactionType,
            'status' => $sStatus,
        ];
    }

    /**
     * @param WebhooksEvent $oEvent
     * @return string
     */
    protected function getTransactionCurrency(WebhooksEvent $oEvent)
    {
        if ($oEvent->getPayment()) {
            return strtolower($oEvent->getPayment()->getPaymentOutput()->getAmountOfMoney()->getCurrencyCode());
        } elseif ($oEvent->getRefund()) {
            return strtolower($oEvent->getRefund()->getRefundOutput()->getAmountOfMoney()->getCurrencyCode());
        }

        return '';
    }

    /**
     * @param WebhooksEvent $oEvent
     * @return int
     */
    protected function getStatusCode(WebhooksEvent $oEvent)
    {
        if ($oEvent->getPayment()) {
            return $oEvent->getPayment()->getStatusOutput()->getStatusCode();
        } elseif ($oEvent->getRefund()) {
            return $oEvent->getRefund()->getStatusOutput()->getStatusCode();
        }

         return -1;
    }
}
