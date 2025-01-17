<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodCodes;
use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedCheckoutRequest;
use FC\FCWLOP\Application\Model\Request\FcwlopCreatePaymentRequest;
use FC\FCWLOP\Application\Model\Request\FcwlopGetHostedTokenizationRequest;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Registry;

class FcwlopPaymentGateway extends FcwlopPaymentGateway_parent
{
    /**
     * OXID URL parameters to copy from initial order execute request
     *
     * @var array
     */
    protected $aWorldlineUrlCopyParameters = [
        'stoken',
    ];

    /**
     * Initiate Worldline payment functionality for Worldline payment types
     *
     * Executes payment, returns true on success.
     *
     * @param double $dAmount Goods amount
     * @param object $oOrder  User ordering object
     *
     * @extend executePayment
     * @return bool
     */
    public function executePayment($dAmount, &$oOrder)
    {
        if(!FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($oOrder->oxorder__oxpaymenttype->value)) {
            return parent::executePayment($dAmount, $oOrder);
        }
        return $this->handleWorldlinePayment($oOrder, $dAmount);
    }

    /**
     * Collect parameters from the current order execute call and add them to the return URL
     * Also add parameters needed for the return process
     *
     * @return string
     */
    protected function fcwlopGetAdditionalParameters()
    {
        $oRequest = Registry::getRequest();
        $oSession = Registry::getSession();

        $sAddParams = '';

        foreach ($this->aWorldlineUrlCopyParameters as $sParamName) {
            $sValue = $oRequest->getRequestEscapedParameter($sParamName);
            if (!empty($sValue)) {
                $sAddParams .= '&'.$sParamName.'='.$sValue;
            }
        }

        $sSid = $oSession->sid(true);
        if ($sSid != '') {
            $sAddParams .= '&'.$sSid;
        }

        if (!$oRequest->getRequestEscapedParameter('stoken')) {
            $sAddParams .= '&stoken='.$oSession->getSessionChallengeToken();
        }
        $sAddParams .= '&ord_agb=1';
        $sAddParams .= '&rtoken='.$oSession->getRemoteAccessToken();

        return $sAddParams;
    }

    /**
     * Execute Worldline API request and redirect to Worldline for payment
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @return bool
     */
    protected function handleWorldlinePayment(CoreOrder &$oOrder, $dAmount)
    {
        $oOrder->fcwlopSetOrderNumber();
        if (empty($oOrder->oxorder__fcwlopmode->value)) {
            $oOrder->fcwlopSetMode();
        }
        if (empty($oOrder->oxorder__fcwlopauthmode->value)) {
            $oOrder->fcwlopSetCaptureMode();
        }

        // In problem folder until receiving the TxStatus payment.created
        $oOrder->fcwlopSetFolder('problems');

        try {
            $iPaymentMethodId = Registry::getSession()->getVariable('fcwlop_current_payment_method_id');

            if ($iPaymentMethodId == 0 && !empty(Registry::getSession()->getVariable('fcwlop_hosted_tokenization_id'))) {
                $sHostedTokenizationId = Registry::getSession()->getVariable('fcwlop_hosted_tokenization_id');

                $oGetHostedTokenizationRequest = new FcwlopGetHostedTokenizationRequest();
                $oGetHostedTokenizationRequest->setHostedTokenizationId($sHostedTokenizationId);
                $oHostedTokenization = $oGetHostedTokenizationRequest->execute();

                if ($oHostedTokenization->getStatus() != 'SUCCESS') {
                    return false;
                }

                $aTokenData = $oHostedTokenization->getBody()['token'];
                $iPaymentMethodId = $aTokenData['paymentProductId'];
                $sPaymentType = FcwlopPaymentMethodCodes::fcwlopGetWorldlinePaymentType($iPaymentMethodId);
                
                $oCreatePaymentRequest = new FcwlopCreatePaymentRequest($oOrder);

                $oApiOrder = $oCreatePaymentRequest->buildApiOrderParameter($oOrder);
                $oCustomer = $oCreatePaymentRequest->buildCustomerData($oOrder);
                $oApiOrder->setCustomer($oCustomer);
                $oCreatePaymentRequest->addOrderParameter($oApiOrder);

                $oCreatePaymentRequest->setHostedTokenizationId($sHostedTokenizationId);
                $oCreatePaymentRequest->addCardPaymentSpecificInput($this->fcwlopGetRedirectUrl());

                $oResponse = $oCreatePaymentRequest->execute();
                
                if ($oResponse->getStatus() != 'SUCCESS') {
                    return false;
                }

                $aApiResponse = $oResponse->getBody();

                if (!empty($aApiResponse['payment']['id'])) {
                    $aTransactionId = explode('_', $aApiResponse['payment']['id']);
                    if (!empty($aTransactionId)) {
                        $oOrder->fcwlopSetTransactionId($aTransactionId[0]);
                    }
                }
                
                if (isset($aApiResponse['merchantAction']['actionType']) && $aApiResponse['merchantAction']['actionType'] == 'REDIRECT') {
                    $sRedirectUrl = $aApiResponse['merchantAction']['redirectData']['redirectURL'];
                    Registry::getSession()->setVariable('fcwlop_needs_redirection', true);
                    Registry::getSession()->setVariable('fcwlop_redirect_url', $sRedirectUrl);
                }

                return true;
            } else {
                $oFcwlopPaymentModel = $oOrder->fcwlopGetPaymentModel();

                /** @var FcwlopCreateHostedCheckoutRequest $oCreateHostedCheckoutRequest */
                $oCreateHostedCheckoutRequest = $oFcwlopPaymentModel->getCreateHostedCheckoutRequest($oOrder);

                $oHostedParam = $oCreateHostedCheckoutRequest->buildApiHostedParameter($iPaymentMethodId, $this->fcwlopGetRedirectUrl(), $oOrder);
                $oCreateHostedCheckoutRequest->addHostedSpecificParameters($oHostedParam);

                $oOrderParam = $oCreateHostedCheckoutRequest->buildApiOrderParameter($oOrder);
                $oCreateHostedCheckoutRequest->addOrderParameter($oOrderParam);

                $oResponse = $oCreateHostedCheckoutRequest->execute();

                if ($oResponse->getStatus() != 'SUCCESS') {
                    return false;
                }

                $aApiResponse = $oResponse->getBody();

                $oOrder->fcwlopSetTransactionId($aApiResponse['hostedCheckoutId']);

                if (!empty($aApiResponse['redirectUrl'] && $oFcwlopPaymentModel->isRedirectUrlNeeded())) {
                    Registry::getSession()->setVariable('fcwlop_is_redirected', true);
                    Registry::getUtils()->redirect($aApiResponse['redirectUrl']);
                }
            }
        } catch(\Exception $oEx) {
            $this->_iLastErrorNo = $oEx->getCode();
            $this->_sLastError = $oEx->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Generate a return url with all necessary return flags
     *
     * @return string
     */
    protected function fcwlopGetRedirectUrl()
    {
        $sBaseUrl = Registry::getConfig()->getCurrentShopUrl().'index.php?cl=order&fnc=handleWorldlineReturn';

        return $sBaseUrl.$this->fcwlopGetAdditionalParameters();
    }
}