<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Controller;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\FcwlopTransactionLog;
use OnlinePayments\Sdk\Domain\WebhooksEvent;
use OnlinePayments\Sdk\Webhooks\SignatureValidationException;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopWebhookController extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = '@fcwlop/fcwlopwebhook';


    /**
     * @return void
     */
    public function handle()
    {
        $sPayload = @file_get_contents('php://input');
        $sSigHeader = $_SERVER['HTTP_X_GCS_SIGNATURE'] ?? '';
        $sKeyHeader = $_SERVER['HTTP_X_GCS_KEYID'] ?? '';

        try {
            $oTransactionLog = new FcwlopTransactionLog();
            $oTransactionLog->logTransaction(json_decode($sPayload, true));

            $oWebhookHelper = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineWebhookHelper();
            $oEvent = $oWebhookHelper->unmarshal($sPayload, [
                'X-GCS-SIGNATURE' => $sSigHeader,
                'X-GCS-KEYID' => $sKeyHeader,
            ]);

            try {
                $this->handleEvent($oEvent);
            } catch (\Exception $oEx) {
                Registry::getLogger()->error('Webhook event process failed : ' . $oEx->getMessage());
            }
        } catch(\UnexpectedValueException $oEx) {
            // Invalid payload
            http_response_code(400);
            echo Registry::getLang()->translateString('FCWLOP_WEBHOOK_EVENT_UNEXPECTED').':'.$oEx->getMessage();
            exit();
        } catch(SignatureValidationException $oEx) {
            // Invalid signature
            http_response_code(400);
            echo Registry::getLang()->translateString('FCWLOP_WEBHOOK_SIGNATURE_FAILED').':'.$oEx->getMessage();
            exit();
        } catch (\Exception $oEx) {
            http_response_code(400);
            echo $oEx->getMessage();
            exit();
        }
    }

    /**
     * The render function
     */
    public function render()
    {
        return $this->_sThisTemplate;
    }

    /**
     * @throws DatabaseConnectionException
     * @throws \Exception
     */
    protected function handleEvent(WebhooksEvent $oEvent)
    {
        $sType = $oEvent->getType();

        if (!in_array($sType, [
            'payment.created',
            'payment.pending_capture',
            'payment.authorization_requested',
            'payment.capture_requested',
            'payment.captured',
            'refund.refund_requested',
            'payment.refunded',
            'payment.cancelled',
            'payment.redirected',
            'payment.rejected',
            'payment.rejected_capture'
        ])) {
            throw new \Exception('Ignored event type ' . $sType);
        }

        $oOrder = $this->fcwlopLoadOrderByEvent($oEvent);
        $oOrder->fcwlopGetPaymentModel()->fcwlopGetTransactionHandler()->fcwlopProcessTransaction($oOrder, 'webhook', $oEvent);
    }

    /**
     * @param WebhooksEvent $oEvent
     * @return false|mixed|Order
     * @throws DatabaseConnectionException
     */
    protected function fcwlopLoadOrderByEvent(WebhooksEvent $oEvent)
    {
        if ($oEvent->getPayment()) {
            $aWorldlineTxId = explode('_', $oEvent->getPayment()->getId());
        } elseif ($oEvent->getRefund()) {
            $aWorldlineTxId = explode('_', $oEvent->getRefund()->getId());
        } else {
            return false;
        }

        if (empty($aWorldlineTxId)) {
            return false;
        }

        $oOrder = FcwlopOrderHelper::getInstance()->fcwlopLoadOrderByTransactionId($aWorldlineTxId[0]);

        if (!$oOrder) {
            throw new \Exception('Order for txid "' . $aWorldlineTxId[0] . '" not found.');
        }

        return $oOrder;
    }
}