<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Helper;

use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodCodes;
use OnlinePayments\Sdk\Domain\PaymentDetailsResponse;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

class FcwlopOrderHelper
{
    /**
     * @var FcwlopOrderHelper
     */
    protected static $oInstance = null;

    /**
     * Create singleton instance of customer helper
     *
     * @return FcwlopOrderHelper
     */
    static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Cancel current order because it failed i.e. because customer canceled payment
     *
     * @return void
     */
    public function fcwlopCancelCurrentOrder()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');

        $oOrder = oxNew(Order::class);
        if ($oOrder->load($sSessChallenge) === true) {
            if ($oOrder->oxorder__oxtransstatus->value != 'OK') {
                $oOrder->cancelOrder();
            }
        }
        Registry::getSession()->deleteVariable('sess_challenge');
    }

    /**
     * @return mixed|string|null
     */
    public function fcwlopGetPaymentId()
    {
        return Registry::getRequest()->getRequestParameter('paymentid');
    }

    /**
     * Loads country object and return country iso code
     *
     * @param string $sCountryId
     * @return string
     */
    public function fcwlopGetCountryCode($sCountryId)
    {
        $oCountry = oxNew('oxcountry');
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Convert region id into region title
     *
     * @param string $sRegionId
     * @return string
     */
    public function fcwlopGetRegionTitle($sRegionId)
    {
        $oState = oxNew('oxState');
        return $oState->getTitleById($sRegionId);
    }

    /**
     * @return mixed
     */
    public function fcwlopGetWorldlinePaymentCode()
    {
        $oCorePayment = new Payment();
        $oCorePayment->load(FcwlopOrderHelper::getInstance()->fcwlopGetPaymentId());
        return $oCorePayment->oxpayments__fcwlopextid->value;
    }

    /**
     * Get amount array
     *
     * @param Order $oOrder
     * @param double $dAmount
     * @return array
     */
    public function fcwlopGetAmountParameters(Order $oOrder, $dAmount)
    {
        return [
            'currency' => $oOrder->oxorder__oxcurrency->value,
            'valueInCent' => $this->fcwlopGetpriceInCent($dAmount),
            'valueFormatted' => number_format($dAmount, 2, '.', ''),
        ];
    }

    /**
     * Returns a floating price as integer in cents
     *
     * @param float $fPrice
     * @return int
     */
    public function fcwlopGetPriceInCent(float $fPrice)
    {
        return (int) number_format($fPrice * 100, 0,'','');
    }

    /**
     * Returns a cent int price as float value in main currency
     *
     * @param int $iPrice
     * @return float
     */
    public function fcwlopGetPriceFromCent(int $iPrice)
    {
        return number_format($iPrice/100, 2,localeconv()['decimal_point'],localeconv()['thousands_sep']);
    }

    /**
     * @param Order $oOrder
     * @return string
     */
    public function fcwlopGetLocale(Order $oOrder)
    {
        $aAvailableLanguages = Registry::getLang()->getActiveShopLanguageIds();
        $iCurrentLangId = Registry::getConfig()->getActiveShop()->getLanguage();
        $sCurrentLang = $aAvailableLanguages[$iCurrentLangId] ?? 'de';
        $sCurrentCountry = $this->fcwlopGetCountryCode($oOrder->oxorder__oxbillcountryid->value) ?? 'DE';

        return $sCurrentLang.'_'.$sCurrentCountry;
    }

    /**
     * Retrieves order id connected to given order number and tries to load it
     * Returns if order was found and loading was a success
     *
     * @param int $iOrderNr
     * @return bool|Order
     * @throws DatabaseConnectionException
     */
    public function fcwlopLoadOrderByOrderNumber($iOrderNr)
    {
        $sQuery = "SELECT oxid FROM oxorder WHERE oxordernr = ?";

        $sOrderId = DatabaseProvider::getDb()->getOne($sQuery, array($iOrderNr));
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            return $oOrder;
        }
        return null;
    }

    /**
     * Retrieves order id connected to given transaction id and tries to load it
     * Returns if order was found and loading was a success
     *
     * @param string $sTransactionId
     * @return false|mixed|Order
     * @throws DatabaseConnectionException
     */
    public function fcwlopLoadOrderByTransactionId($sTransactionId)
    {
        $sQuery = "SELECT oxid FROM oxorder WHERE oxtransid = ?";

        $sOrderId = DatabaseProvider::getDb()->getOne($sQuery, array($sTransactionId));
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            return $oOrder;
        }
        return false;
    }

    /**
     * Fills captured db-fields with full costs
     *
     * @param $oOrder
     * @return void
     */
    public function fcwlopMarkOrderAsFullyCaptured($oOrder)
    {
        $oOrder->oxorder__fcwlopdelcostcaptured = new Field($oOrder->oxorder__oxdelcost->value);
        $oOrder->oxorder__fcwloppaycostcaptured = new Field($oOrder->oxorder__oxpaycost->value);
        $oOrder->oxorder__fcwlopwrapcostcaptured = new Field($oOrder->oxorder__oxwrapcost->value);
        $oOrder->oxorder__fcwlopgiftcardcaptured = new Field($oOrder->oxorder__oxgiftcardcost->value);
        $oOrder->oxorder__fcwlopvoucherdiscountcaptured = new Field($oOrder->oxorder__oxvoucherdiscount->value);
        $oOrder->oxorder__fcwlopdiscountcaptured = new Field($oOrder->oxorder__oxdiscount->value);
        $oOrder->save();

        foreach ($oOrder->getOrderArticles() as $oOrderArticle) {
            $oOrderArticle->oxorderarticles__fcwlopamountcaptured = new Field($oOrderArticle->oxorderarticles__oxbrutprice->value);
            $oOrderArticle->save();
        }
    }

    /**
     * Fills refunded db-fields with full costs
     *
     * @param $oOrder
     * @return void
     */
    public function fcwlopMarkOrderAsFullyRefunded($oOrder)
    {
        $oOrder->oxorder__fcwlopdelcostrefunded = new Field($oOrder->oxorder__oxdelcost->value);
        $oOrder->oxorder__fcwloppaycostrefunded = new Field($oOrder->oxorder__oxpaycost->value);
        $oOrder->oxorder__fcwlopwrapcostrefunded = new Field($oOrder->oxorder__oxwrapcost->value);
        $oOrder->oxorder__fcwlopgiftcardrefunded = new Field($oOrder->oxorder__oxgiftcardcost->value);
        $oOrder->oxorder__fcwlopvoucherdiscountrefunded = new Field($oOrder->oxorder__oxvoucherdiscount->value);
        $oOrder->oxorder__fcwlopdiscountrefunded = new Field($oOrder->oxorder__oxdiscount->value);
        $oOrder->save();

        foreach ($oOrder->getOrderArticles() as $oOrderArticle) {
            $oOrderArticle->oxorderarticles__fcwlopamountrefunded = new Field($oOrderArticle->oxorderarticles__oxbrutprice->value);
            $oOrderArticle->save();
        }
    }

    /**
     * @param string $sTransactionId
     * @param Order $oOrder
     * @param PaymentDetailsResponse $oPaymentDetails
     * @return void
     * @throws DatabaseConnectionException
     */
    public function fcwlopRestoreCardPaymentId($sTransactionId, Order $oOrder, PaymentDetailsResponse $oPaymentDetails)
    {
        $oCardPaymentMethodSpecificOutput = $oPaymentDetails->getPaymentOutput()->getCardPaymentMethodSpecificOutput();
        if($oCardPaymentMethodSpecificOutput) {
            $iProductId = $oCardPaymentMethodSpecificOutput->getPaymentProductId();
            $sPaymentType = FcwlopPaymentMethodCodes::fcwlopGetWorldlinePaymentType($iProductId);
            if (!is_null($sPaymentType)) {
                $oUserPayment = $oOrder->getPaymentType();
                $oUserPayment->oxuserpayments__oxpaymentsid = new Field($sPaymentType);
                $oOrder->oxorder__oxpaymenttype = new Field($sPaymentType);

                $oUserPayment->save();
                $oOrder->save();
            }    
        }
    }
}
