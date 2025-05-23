<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedTokenizationRequest;
use OnlinePayments\Sdk\Domain\CreateHostedTokenizationRequest;
use OnlinePayments\Sdk\Domain\PaymentProductFilter;
use OnlinePayments\Sdk\Domain\PaymentProductFiltersHostedTokenization;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Registry;

class FcwlopPaymentController extends FcwlopPaymentController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function init()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blFcwlopIsRedirected = Registry::getSession()->getVariable('fcwlop_is_redirected');
        if (!empty($sSessChallenge) && $blFcwlopIsRedirected === true) {
            FcwlopOrderHelper::getInstance()->fcwlopCancelCurrentOrder();
            Registry::getSession()->deleteVariable('fcwlop_sepadirectdebit_iban');
        }
        Registry::getSession()->deleteVariable('fcwlop_is_redirected');
        parent::init();
    }

    /**
     * Template variable getter. Returns paymentlist
     *
     * @return object
     */
    public function getPaymentList()
    {
        parent::getPaymentList();

        $this->fcwlopGroupCards();
        $this->fcwlopCheckApplePay();

        return $this->_oPaymentList;
    }

    /**
     * @return string
     */
    public function validatepayment()
    {
        $mRet = parent::validatepayment();

        $sPaymentId = Registry::getRequest()->getRequestParameter('paymentid');
        if (!FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($sPaymentId)) {
            return $mRet;
        }
        try {
            if (!($aDynvalue = Registry::getRequest()->getRequestEscapedParameter('dynvalue'))) {
                $aDynvalue = Registry::getSession()->getVariable('dynvalue');
            }

            if ($sPaymentId == 'fcwlopgroupedcard') {
                $mRet = 'order';
            }

            if ($sPaymentId == 'fcwlopsepadirectdebit') {
                Registry::getSession()->deleteVariable('fcwlop_payment_errors');
                Registry::getSession()->deleteVariable('fcwlop_sepadirectdebit_iban');
                $mRet = 'order';

                $sIban = $aDynvalue['fcwlop_sepadirectdebit_iban'];
                if (empty($sIban)) {
                    Registry::getSession()->setVariable('fcwlop_payment_errors', [Registry::getLang()->translateString('FCWLOP_ERROR_SEPA_IBAN_MISSING')]);
                    $mRet = 'payment';
                }
                Registry::getSession()->setVariable('fcwlop_sepadirectdebit_iban', $sIban);
            }

            $oFcwlopPaymentModel = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($sPaymentId);
            if (!is_null($oFcwlopPaymentModel->getWorldlinePaymentCode())) {
                Registry::getSession()->setVariable('fcwlop_current_payment_method_id', $oFcwlopPaymentModel->getWorldlinePaymentCode());
            }    
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getTraceAsString());
            $mRet = 'payment';
        }

        return $mRet;
    }

    /**
     * @return void
     */
    public function fcwlopGetErrors()
    {
        return Registry::getSession()->getVariable('fcwlop_payment_errors');
    }

    /**
     * @return string
     */
    public function fcwlopGetTokenizationJsToolsUrl()
    {
        $sApiUrl = FcwlopPaymentHelper::getInstance()->fcwlopGetApiEndpoint();
        
        return $sApiUrl . '/hostedtokenization/js/client/tokenizer.min.js';
    }

    /**
     * @return string
     */
    public function fcwlopGetCardTokenizationUrl()
    {
        try {
            $oCreateHostedTokenizationRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetCreateHostedTokenizationRequest();
            $oResponse = $oCreateHostedTokenizationRequest->execute();

            if ($oResponse->getStatus() != 'SUCCESS') {
                return '';
            }

            Registry::getSession()->setVariable('fcwlop_hosted_tokenization_id', $oResponse->getBody()['hostedTokenizationId']);
            Registry::getSession()->setVariable('fcwlop_hosted_tokenization_url', $oResponse->getBody()['hostedTokenizationUrl']);

            return $oResponse->getBody()['hostedTokenizationUrl'];
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            return '';
        }
    }

    /**
     * @return array
     */
    public function fcwlopGetAllowedCardLogos()
    {
        $aCardsLogos = FcwlopPaymentHelper::getInstance()->fcwlopGetActivatedCreditCardsLogos();

        return $aCardsLogos;
    }

    /**
     * Checks if credit card grouping is necessary,
     * to replace the active card methods by a generic credit card
     * and postpone card selection to hosted checkout page or to the embedded iframe formular (hosted tokenization)
     *
     * @return bool
     * @throws \Exception
     */
    protected function fcwlopGroupCards()
    {
        $oLang = Registry::getLang();

        $oFilteredPaymentList = [];

        $sGroupCardGenericMethod = 'fcwlopgroupedcard';
        $oGenericCardMethod = oxNew(Payment::class);
        $oGenericCardMethod->load($sGroupCardGenericMethod);

        $blFcwlopIsWorldlineCcGrouped = FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlineCreditCardGrouped()
            || FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineCreditCardMode() == 'embedded';
        if (!$blFcwlopIsWorldlineCcGrouped) {
            $oGenericCardMethod->oxpayments__oxactive = new Field(0);
            $oGenericCardMethod->save();
            unset($oFilteredPaymentList[$sGroupCardGenericMethod]);
            return true;
        }

        foreach ($this->_oPaymentList as $sOxid => $oMethod) {
            if (!FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlineCardsProduct($sOxid)) {
                $oFilteredPaymentList[$sOxid] = $oMethod;
            }
        }

        $aActivatedCards = FcwlopPaymentHelper::getInstance()->fcwlopGetActivatedCreditCards();
        if (!empty($aActivatedCards)) {
            $oGenericCardMethod->oxpayments__oxactive = new Field(1);
            $oGenericCardMethod->save();
            $oFilteredPaymentList[$sGroupCardGenericMethod] = $oGenericCardMethod;
        } else {
            $oGenericCardMethod->oxpayments__oxactive = new Field(0);
            $oGenericCardMethod->save();
            unset($oFilteredPaymentList[$sGroupCardGenericMethod]);
        }

        $this->_oPaymentList = $oFilteredPaymentList;

        return true;
    }

    /**
     * @return bool
     */
    protected function fcwlopCheckApplePay()
    {
        $blFcwlopHideApplePay = false;
        if (!$this->_oPaymentList['fcwlopapplepay']) {
            return true;
        }
        $oFcwlopApplePayMethod = $this->_oPaymentList['fcwlopapplepay'];

        if ($oFcwlopApplePayMethod->oxpayments__oxactive->value != 1) {
            $blFcwlopHideApplePay = true;
        }

        $sUserAgent = Registry::getUtilsServer()->getServerVar('HTTP_USER_AGENT');
        if (!str_contains(strtolower($sUserAgent), 'safari')) {
            $blFcwlopHideApplePay = true;
        }

        if ($blFcwlopHideApplePay) {
            unset($this->_oPaymentList['fcwlopapplepay']);
        }

        return true;
    }

    /**
     * Returns billing country code of current basket
     *
     * @param  Basket $oBasket
     * @return string
     */
    protected function fcwlopGetBillingCountry(Basket $oBasket)
    {
        $oUser = $oBasket->getBasketUser();

        $oCountry = oxNew(Country::class);
        $oCountry->load($oUser->oxuser__oxcountryid->value);

        if (!$oCountry->oxcountry__oxisoalpha2) {
            return '';
        }

        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Returns if current order is being considered as a B2B order
     *
     * @param  Basket $oBasket
     * @return bool
     */
    protected function fcwlopIsB2BOrder(Basket $oBasket)
    {
        $oUser = $oBasket->getBasketUser();
        if (!empty($oUser->oxuser__oxcompany->value)) {
            return true;
        }
        return false;
    }

}
