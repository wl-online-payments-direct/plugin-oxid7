<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopOrderController extends OrderController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function render()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blFcwlopIsRedirected = Registry::getSession()->getVariable('fcwlop_is_redirected');
        if (!empty($sSessChallenge) && $blFcwlopIsRedirected === true) {
            FcwlopOrderHelper::getInstance()->fcwlopCancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('fcwlop_is_redirected');
        return parent::render();
    }

    /**
     * Load previously created order
     *
     * @return Order|false
     */
    protected function getOrder()
    {
        $sOrderId = Registry::getSession()->getVariable('sess_challenge');
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->isLoaded() === true) {
                return $oOrder;
            }
        }
        return false;
    }

    /**
     * Writes error-status to session and redirects to payment page
     *
     * @param string $sErrorLangIdent
     * @return false
     */
    protected function redirectWithError($sErrorLangIdent)
    {
        Registry::getSession()->setVariable('payerror', -50);
        Registry::getSession()->setVariable('payerrortext', Registry::getLang()->translateString($sErrorLangIdent));
        Registry::getUtils()->redirect(Registry::getConfig()->getCurrentShopUrl().'index.php?cl=payment');
        return false; // execution ends with redirect - return used for unit tests
    }

    /**
     * @return string
     * @throws DatabaseConnectionException
     */
    public function handleWorldlineReturn()
    {
        $oPayment = $this->getPayment();
        if ($oPayment && $oPayment->fcwlopIsWorldlinePaymentMethod()) {
            $sResult = 'pending';

            $oOrder = $this->getOrder();
            if (!$oOrder) {
                return $this->redirectWithError('FCWLOP_ERROR_ORDER_NOT_FOUND');
            }

            $sHostedCheckoutId = Registry::getRequest()->getRequestParameter('hostedCheckoutId');
            $oApi = FcwlopPaymentHelper::getInstance()->fcwlopGetHostedCheckoutApi();
            $sStatus = '';

            $iCounter = 0;
            while ($iCounter < 10 && !in_array($sStatus, ['CANCELLED_BY_CONSUMER', 'PAYMENT_CREATED'])) {
                sleep(1);
                $sStatus = $oApi->getHostedCheckout($sHostedCheckoutId)->getStatus();

                if($sStatus == 'CANCELLED_BY_CONSUMER') {
                    $sResult = 'canceled';
                    break;
                } elseif($sStatus == 'PAYMENT_CREATED') {
                    $sResult = 'success';
                    break;
                }

                $iCounter++;
            }

            if ($sResult == 'canceled') {
                FcwlopOrderHelper::getInstance()->fcwlopCancelCurrentOrder();
                return $this->redirectWithError('FCWLOP_ERROR_ORDER_CANCELED');
            }

            $sTransactionId = $oOrder->oxorder__oxtransid->value;
            if (empty($sTransactionId)) {
                return $this->redirectWithError('FCWLOP_ERROR_TRANSACTIONID_NOT_FOUND');
            }

            if($sResult == 'success') {
                $oPaymentDetails = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentDetails($sTransactionId);
                if ($oPaymentDetails->getStatus() == 'REJECTED') {
                    FcwlopOrderHelper::getInstance()->fcwlopCancelCurrentOrder();
                    return $this->redirectWithError('FCWLOP_ERROR_ORDER_FAILED');
                }
            }

            if ($sResult == 'pending') {
                return 'thankyou?pendingCheckout=1';
            }

            Registry::getSession()->deleteVariable('fcwlop_is_redirected');

            // else - continue to parent::execute since success must be true
        }
        return parent::execute();
    }
}
