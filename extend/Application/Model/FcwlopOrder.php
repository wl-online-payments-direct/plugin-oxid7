<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
use OnlinePayments\Sdk\DataObject;
use OnlinePayments\Sdk\Domain\PaymentDetailsResponse;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;

class FcwlopOrder extends Order_parent
{
    /**
     * Temporary field for saving the order nr
     *
     * @var int|null
     */
    protected $iFcwlopTmpOrderNr = null;


    /**
     * Used to trigger the _setNumber() method before the payment-process during finalizeOrder to have the order-number there already
     *
     * @return void
     */
    public function fcwlopSetOrderNumber()
    {
        if (!$this->oxorder__oxordernr->value) {
            $this->setNumber();
        }
    }

    /**
     * Generate Worldline payment model from paymentId
     *
     * @return FcwlopWorldlineGenericMethod
     */
    public function fcwlopGetPaymentModel()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($this->oxorder__oxpaymenttype->value);
    }

    /**
     * Returns if order was paid with a Worldline payment type
     *
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentUsed()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($this->oxorder__oxpaymenttype->value);
    }

    /**
     * Returns if the order is marked as paid, since OXID doesnt have a proper flag
     *
     * @return bool
     */
    public function fcwlopIsPaid()
    {
        if (!empty($this->oxorder__oxpaid->value) && $this->oxorder__oxpaid->value != "0000-00-00 00:00:00") {
            return true;
        }
        return false;
    }

    /**
     * Mark order as paid
     *
     * @return void
     */
    public function fcwlopMarkAsPaid()
    {
        $sDate = date('Y-m-d H:i:s');

        $sQuery = "UPDATE oxorder SET oxpaid = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDate, $this->getId()));

        $this->oxorder__oxpaid = new Field($sDate);
    }

    /**
     * Set order folder
     *
     * @param string $sFolder
     * @return bool
     */
    public function fcwlopSetFolder($sFolder)
    {
        if(!in_array($sFolder, ['new', 'finished', 'problems'])) {
            return false;
        }

        if ($sFolder == 'problems') {
            $sDbFolder = 'ORDERFOLDER_PROBLEMS';
        } elseif ($sFolder == 'finished') {
            $sDbFolder = 'ORDERFOLDER_FINISHED';
        } else{
            $sDbFolder = 'ORDERFOLDER_NEW';
        }

        $sQuery = "UPDATE oxorder SET oxfolder = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDbFolder, $this->getId()));

        $this->oxorder__oxfolder = new Field($sDbFolder);

        return true;
    }

    /**
     * Set order folder
     *
     * @param string $sFolder
     * @return bool
     */
    public function fcwlopSetStatus($sStatus)
    {
        if(!in_array($sStatus, ['not_finished', 'ok', 'error'])) {
            return false;
        }

        if ($sStatus == 'ok') {
            $sDbStatus = 'OK';
        } elseif ($sStatus == 'error') {
            $sDbStatus = 'ERROR';
        } else{
            $sDbStatus = 'NOT_FINISHED';
        }

        $sQuery = "UPDATE oxorder SET oxtransstatus = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDbStatus, $this->getId()));

        $this->oxorder__oxtransstatus = new Field($sDbStatus);

        return true;
    }

    /**
     * Save transaction id in order object
     *
     * @param  string $sTransactionId
     * @return void
     */
    public function fcwlopSetTransactionId($sTransactionId)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET oxtransid = ? WHERE oxid = ?', array($sTransactionId, $this->getId()));

        $this->oxorder__oxtransid = new Field($sTransactionId);
    }

    /**
     * Save external transaction id in order object
     *
     * @param  string $sTransactionId
     * @return void
     */
    public function fcwlopSetExternalTransactionId($sTransactionId)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET fcwlopexternaltransid = ? WHERE oxid = ?', array($sTransactionId, $this->getId()));

        $this->oxorder__fcwlopexternaltransid = new Field($sTransactionId);
    }

    /**
     * @return string
     */
    public function fcwlopGetCaptureMode()
    {
        return $this->oxorder__fcwlopauthmode->value;
    }

    /**
     * @param string|null $sConfigCaptureMode
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcwlopSetCaptureMode($sConfigCaptureMode = null)
    {
        $sConfigCaptureMode = $sConfigCaptureMode ?? FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineCaptureMode();
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET fcwlopauthmode = ? WHERE oxid = ?', array($sConfigCaptureMode, $this->getId()));

        $this->oxorder__fcwloauthpmode = new Field($sConfigCaptureMode);
    }

    /**
     * @param string|null $sConfigMode
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcwlopSetMode($sConfigMode = null)
    {
        $sConfigMode = $sConfigMode ?? FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineMode();
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET fcwlopmode = ? WHERE oxid = ?', array($sConfigMode, $this->getId()));

        $this->oxorder__fcwlopmode = new Field($sConfigMode);
    }

    /**
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function cancelOrder()
    {
        parent::cancelOrder();
        if ($this->fcwlopIsWorldlinePaymentUsed() === true) {
            if (!empty($this->oxorder__oxtransid->value)) {
                $oPaymentDetails = $this->fcwlopGetWorldlinePaymentDetails();
                if ($oPaymentDetails->getStatusOutput()->getIsCancellable()) {
                    $oRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetCancelPaymentRequest($this);
                    $oRequest->execute();
                }
            }
        }
    }

    /**
     * @return string
     */
    public function fcwlopGetExtraInfo()
    {
        $sFcwlopPaymentId = $this->fcwlopGetPaymentModel()->getOxidPaymentId();
        $sTransactionId = $this->oxorder__oxtransid->value;

        if (empty($sTransactionId)) {
            return '';
        }
    }

    /**
     * Checks based on the transaction status received by Worldline whether
     * the capture request is available for this order at the moment.
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function fcwlopAllowCapture(): bool
    {
        $blReturn = true;

        if ($this->oxorder__oxstorno->value == 1) {
            $blReturn = false;
        }

        if ($this->oxorder__fcwlopauthmode->value == 'direct-sales') {
            $blReturn = false;
        }

        if ($blReturn) {
            $iCount = DatabaseProvider::getDb()->getOne("SELECT COUNT(*) FROM fcwloptransactionlog WHERE FCWLOP_TXID = '{$this->oxorder__oxtransid->value}'");
            $blReturn = !(($iCount == 0));
        }

        if ($blReturn) {
            $oWorldlinePayment = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentDetails($this->oxorder__oxtransid->value);
            if ($oWorldlinePayment->getStatus() != 'PENDING_CAPTURE') {
                $blReturn = false;
            }
        }

        return $blReturn;
    }

    /**
     * Checks based on the transaction status received by Worldline whether
     * the refund request is available for this order at the moment.
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function fcwlopAllowRefund(): bool
    {
        $blReturn = true;

        if ($this->oxorder__oxstorno->value == 1) {
            $blReturn = false;
        }
        if ($blReturn) {
            $iCount = DatabaseProvider::getDb()->getOne("SELECT COUNT(*) FROM fcwloptransactionlog WHERE FCWLOP_TXID = '{$this->oxorder__oxtransid->value}' 
                                                                    AND FCWLOP_STATUS IN ('CREATED', 'PENDING_CAPTURE', 'CAPTURED');
                                                                ");
            $blReturn = !(($iCount == 0));
        }
        if ($blReturn) {
            $oWorldlinePayment = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentDetails($this->oxorder__oxtransid->value);
            $blReturn = $oWorldlinePayment->getStatusOutput()->getIsRefundable();
        }

        return $blReturn;
    }

    /**
     * Checks based on the transaction status received by Worldline whether
     * the capture request is available for this order at the moment.
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function fcwlopAllowCancel(): bool
    {
        $blReturn = true;

        if ($this->oxorder__oxstorno->value == 1) {
            $blReturn = false;
        }

        if ($blReturn) {
            $oWorldlinePayment = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentDetails($this->oxorder__oxtransid->value);
            $blReturn = $oWorldlinePayment->getStatusOutput()->getIsCancellable();
        }

        return $blReturn;
    }

    /**
     * @return DataObject|PaymentDetailsResponse|null
     */
    public function fcwlopGetWorldlinePaymentDetails()
    {
        try {
            $iWorldlinePaymentId = $this->oxorder__oxtransid->value;
            return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentDetails($iWorldlinePaymentId);
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx);
            return null;
        }
    }

    /**
     * @return array
     */
    public function fcwlopGetExtraPosition()
    {
        $oLang = Registry::getLang();
        $aPositions = [];

        if ($this->oxorder__oxdelcost->value - $this->oxorder__fcwlopdelcostcaptured->value > 0) {
            if ($this->oxorder__oxdelcost->value > 0) {
                $sLabelPrefix = $oLang->translateString('FCWLOP_SURCHARGE');
            } else {
                $sLabelPrefix = $oLang->translateString('FCWLOP_DEDUCTION');
            }

            $aPositions['oxdelcost'] = [
                'id' => 'oxdelcost',
                'label' => $sLabelPrefix . ' ' . $oLang->translateString('FCWLOP_SHIPPINGCOST'),
                'price' => $this->oxorder__oxdelcost->value,
                'amount' => 1,
            ];
        }

        if ($this->oxorder__oxpaycost->value - $this->oxorder__fcwloppaycostcaptured->value > 0) {
            if ($this->oxorder__oxpaycost->value > 0) {
                $sLabelPrefix = $oLang->translateString('FCWLOP_SURCHARGE');
            } else {
                $sLabelPrefix = $oLang->translateString('FCWLOP_DEDUCTION');
            }
            $aPositions['oxpaycost'] = [
                'id' => 'oxpaycost',
                'label' => $sLabelPrefix . ' ' . $oLang->translateString('FCWLOP_PAYMENTTYPE'),
                'price' => $this->oxorder__oxpaycost->value,
                'amount' => 1,
            ];
        }

        if ($this->oxorder__oxwrapcost->value - $this->oxorder__fcwlopwrapcostcaptured->value > 0) {
            $aPositions['oxwrapcost'] = [
                'id' => 'oxwrapcost',
                'label' => $oLang->translateString('FCWLOP_WRAPPING'),
                'price' => $this->oxorder__oxwrapcost->value,
                'amount' => 1,
            ];
        }

        if ($this->oxorder__oxgiftcardcost->value - $this->oxorder__fcwlopgiftcardcaptured->value > 0) {
            $aPositions['oxgiftcardcost'] = [
                'id' => 'oxgiftcardcost',
                'label' => $oLang->translateString('FCWLOP_GIFTCARD'),
                'price' => $this->oxorder__oxgiftcardcost->value,
                'amount' => 1,
            ];
        }

        if ($this->oxorder__oxvoucherdiscount->value - $this->oxorder__fcwlopvoucherdiscountcaptured->value > 0) {
            $aPositions['oxvoucherdiscount'] = [
                'id' => 'oxvoucherdiscount',
                'label' => $oLang->translateString('FCWLOP_VOUCHER'),
                'price' => $this->oxorder__oxvoucherdiscount->value * -1,
                'amount' => 1,
            ];
        }

        if ($this->oxorder__oxdiscount->value - $this->oxorder__fcwlopdiscountcaptured->value > 0) {
            $aPositions['oxdiscount'] = [
                'id' => 'oxdiscount',
                'label' => $oLang->translateString('FCWLOP_DISCOUNT'),
                'price' => $this->oxorder__oxdiscount->value * -1,
                'amount' => 1,
            ];
        }

        return $aPositions;
    }

    /**
     * @return bool
     */
    public function fcwlopHasRefundableExtraPosition()
    {
        return ($this->oxorder__oxdelcost->value != 0 and $this->oxorder__fcwlopdelcostrefunded->value == 0)
            || ($this->oxorder__oxpaycost->value != 0 and $this->oxorder__fcwloppaycostrefunded->value == 0)
            || ($this->oxorder__oxwrapcost->value != 0 and $this->oxorder__fcwlopwrapcostrefunded->value == 0)
            || ($this->oxorder__oxgiftcardcost->value != 0 and $this->oxorder__fcwlopgiftcardrefunded->value == 0)
            || ($this->oxorder__oxvoucherdiscount->value != 0 and $this->oxorder__fcwlopvoucherdiscountrefunded->value == 0)
            || ($this->oxorder__oxdiscount->value != 0 and $this->oxorder__fcwlopdiscountrefunded->value == 0);
    }

    /**
     * Tries to fetch and set next record number in DB. Returns true on success
     *
     * @return bool
     */
    protected function setNumber()
    {
        if ($this->iFcwlopTmpOrderNr === null) {
            return parent::setNumber();
        }

        if (!$this->oxorder__oxordernr instanceof Field) {
            $this->oxorder__oxordernr = new Field($this->iFcwlopTmpOrderNr);
        } else {
            $this->oxorder__oxordernr->value = $this->iFcwlopTmpOrderNr;
        }

        return true;
    }
}