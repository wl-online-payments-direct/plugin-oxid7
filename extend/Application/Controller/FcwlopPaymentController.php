<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
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
        $this->fcwlopCheckCardGrouping();
        return $this->_oPaymentList;
    }

    /**
     * Checks if credit card grouped checkout is active,
     * to replace the active card methods by a generic credit card
     * and postpone card selection to hosted checkout page
     *
     * @return bool
     * @throws \Exception
     */
    protected function fcwlopCheckCardGrouping()
    {
        $oLang = Registry::getLang();

        $oFilteredPaymentList = [];
        $blFcwlopIsWorldlineCcGrouped = FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlineCreditCardGrouped();
        if (!$blFcwlopIsWorldlineCcGrouped) {
            return true;
        }

        $blIsGenericGroupedCardMethodAdded = false;
        foreach ($this->_oPaymentList as $sOxid => $oMethod) {
            if (FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlineCardsProduct($sOxid)) {
                if (!$blIsGenericGroupedCardMethodAdded) {
                    $oGenericCardMethod = oxNew(Payment::class);
                    $oGenericCardMethod->load('fcwlopgroupedcard');
                    if (!$oGenericCardMethod->oxpayments__oxactive->value == 1) {
                        $oGenericCardMethod->oxpayments__oxactive = new Field(1);
                        $oGenericCardMethod->save();
                    }
                    $oFilteredPaymentList['fcwlopgroupedcard'] = $oGenericCardMethod;
                    $blIsGenericGroupedCardMethodAdded = true;
                }
            } else {
                $oFilteredPaymentList[$sOxid] = $oMethod;
            }
        }

        $this->_oPaymentList = $oFilteredPaymentList;

        return true;
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
            if ($sPaymentId == 'fcwlopgroupedcard') {
                $mRet = 'order';
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
