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
            $oFcwlopPaymentModel = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($sPaymentId);
            if (!empty($oFcwlopPaymentModel->getWorldlinePaymentCode())) {
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
