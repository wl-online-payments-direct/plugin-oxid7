<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodTypes;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
use OxidEsales\Eshop\Application\Model\Payment;

class FcwlopPayment extends FcwlopPayment_parent
{
    /**
     * Check if given payment method is a Worldline method
     *
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentMethod()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($this->getId());
    }

    /**
     * Return Worldline payment model
     *
     * @return FcwlopWorldlineGenericMethod
     */
    public function fcwlopGetWorldlinePaymentModel()
    {
        if ($this->fcwlopIsWorldlinePaymentMethod()) {
            return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($this->getId());
        }
        return null;
    }

    /**
     * Function checks if loaded payment is valid to current basket
     *
     * @param array                                    $aDynValue    dynamical value (in this case oxiddebitnote is checked only)
     * @param string                                   $sShopId      id of current shop
     * @param \OxidEsales\Eshop\Application\Model\User $oUser        the current user
     * @param double                                   $dBasketPrice the current basket price (oBasket->dPrice)
     * @param string                                   $sShipSetId   the current ship set
     *
     * @return bool true if payment is valid
     */
    public function isValidPayment($aDynValue, $sShopId, $oUser, $dBasketPrice, $sShipSetId)
    {
        $blReturn = parent::isValidPayment($aDynValue, $sShopId, $oUser, $dBasketPrice, $sShipSetId);

        if ($this->fcwlopIsWorldlinePaymentMethod()) {
            if ($this->fcwlopGetWorldlinePaymentModel()->getOxidPaymentId() == 'fcwlopgroupedcard') {

                foreach (FcwlopPaymentMethodTypes::WORLDLINE_CARDS_PRODUCTS as $sPaymentId => $sName) {
                    $oPayment = oxNew(Payment::class);
                    $oPayment->load($sPaymentId);
                    $blReturnDistinct = $oPayment->isValidPayment($aDynValue, $sShopId, $oUser, $dBasketPrice, $sShipSetId);
                    if ($blReturnDistinct) {
                        return true;
                    }
                }
            }
        }

        return $blReturn;
    }
}
