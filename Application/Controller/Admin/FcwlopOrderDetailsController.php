<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Controller\Admin;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OnlinePayments\Sdk\Domain\PaymentDetailsResponse;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopOrderDetailsController extends AdminDetailsController
{
    /**
     * Template to be used
     *
     * @var string
     */
    protected $_sTemplate = "@fcwlop/admin/fcwlop_order_details";

    /**
     * Order object
     *
     * @var Order|null
     */
    protected $_oOrder = null;

    /**
     * Payment details object
     *
     * @var PaymentDetailsResponse|null
     */
    protected $_oWorldlinePaymentDetails = null;

    /**
     * Error message property
     *
     * @var string|bool
     */
    protected $_sErrorMessage = false;

    /**
     * Flag if a successful refund was executed
     *
     * @var bool|null
     */
    protected $_blSuccessfulRefund = null;

    /**
     * Array of refund items
     *
     * @var array|null
     */
    protected $_aRefundItems = null;

    /**
     * @var string
     */
    protected $_sLastRequestMessage = '';


    /**
     * @return string
     */
    public function render()
    {
        parent::render();

        $oOrder = $this->getOrder();
        if ($oOrder) {
            $this->_aViewData["edit"] = $oOrder;
        }

        return $this->_sTemplate;
    }

    /**
     * Loads current order
     *
     * @return null|object|Order
     */
    public function getOrder()
    {
        if ($this->_oOrder === null) {
            $oOrder = oxNew(Order::class);

            $soxId = $this->getEditObjectId();
            if (isset($soxId) && $soxId != "-1") {
                $oOrder->load($soxId);

                $this->_oOrder = $oOrder;
            }
        }
        return $this->_oOrder;
    }

    /**
     * Checks if there were previous partial refunds and therefore full refund is not available anymore
     *
     * @return bool
     */
    public function isFullRefundAvailable()
    {
        $oOrder = $this->getOrder();
        foreach ($oOrder->getOrderArticles() as $orderArticle) {
            if ((double)$orderArticle->oxorderarticles__fcwlopamountrefunded->value > 0) {
                return false;
            }
        }

        if ($oOrder->oxorder__fcwlopdelcostrefunded->value > 0
            || $oOrder->oxorder__fcwloppaycostrefunded->value > 0
            || $oOrder->oxorder__fcwlopwrapcostrefunded->value > 0
            || $oOrder->oxorder__fcwlopgiftcardrefunded->value > 0
            || $oOrder->oxorder__fcwlopvoucherdiscountrefunded->value > 0
            || $oOrder->oxorder__fcwlopdiscountrefunded->value > 0) {
            return false;
        }
        return true;
    }

    /**
     * Checks if order was paid with Worldline
     *
     * @return bool
     */
    public function fcwlopIsWorldlineOrder()
    {
        return FcwlopPaymentHelper::getInstance()->fcwlopIsWorldlinePaymentMethod($this->getOrder()->oxorder__oxpaymenttype->value);
    }

    /**
     * @return mixed
     */
    public function fcwlopGetCaptureMode()
    {
        return $this->getOrder()->fcwlopGetCaptureMode();
    }

    /**
     * Fetch existing capture or refund transactions linked to this order
     *
     * @return array
     */
    public function fcwlopGetCaptureRefundEntries()
    {
        try {
            $aEntries = [
                'hasOperations' => 0,
                'capture' => [],
                'refund' => [],
                'totalCapture' => 0,
                'totalRefund' => 0,
                'totalBalance' => 0
            ];

            $aCaptures = $this->fcwlopGetWorldlineCaptureDetails();
            $aRefunds = $this->fcwlopGetWorldlineRefundDetails();

            foreach ($aCaptures as $aCaptureEntry) {
                if ($aEntries['hasOperations'] == 0) {
                    $aEntries['hasOperations'] = 1;
                }

                $fAmount = FcwlopOrderHelper::getInstance()->fcwlopGetPriceFromCent($aCaptureEntry['amount']);
                $aEntries['capture'][] = [
                    'date' => $aCaptureEntry['date'],
                    'amount' => $fAmount
                ];
                $aEntries['totalCapture'] += $fAmount;
                $aEntries['totalBalance'] += $fAmount;
            }

            foreach ($aRefunds as $aRefundEntry) {
                if ($aEntries['hasOperations'] == 0) {
                    $aEntries['hasOperations'] = 1;
                }

                $fAmount = FcwlopOrderHelper::getInstance()->fcwlopGetPriceFromCent($aRefundEntry['amount']);
                $aEntries['refund'][] = [
                    'date' => $aRefundEntry['date'],
                    'amount' => $fAmount
                ];
                $aEntries['totalRefund'] += $fAmount;
                $aEntries['totalBalance'] -= $fAmount;
            }

            return $aEntries;
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            return [];
        }
    }

    /**
     * @return \OnlinePayments\Sdk\DataObject|PaymentDetailsResponse|null
     */
    public function fcwlopGetWorldlinePaymentDetails()
    {
        if ($this->_oWorldlinePaymentDetails == null) {
            $this->_oWorldlinePaymentDetails = $this->getOrder()->fcwlopGetWorldlinePaymentDetails();
        }

        return $this->_oWorldlinePaymentDetails;
    }

    /**
     * @return array
     */
    public function fcwlopGetWorldlineCaptureDetails()
    {
        try {
            $iWorldlinePaymentId = $this->getOrder()->oxorder__oxtransid->value;
            return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentCaptures($iWorldlinePaymentId);
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            return [];
        }
    }

    /**
     * @return array
     */
    public function fcwlopGetWorldlineRefundDetails()
    {
        try {
            $iWorldlinePaymentId = $this->getOrder()->oxorder__oxtransid->value;
            return FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentRefunds($iWorldlinePaymentId);
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            return [];
        }
    }

    /**
     * Returns errormessage
     *
     * @return bool|string
     */
    public function getErrorMessage()
    {
        return $this->_sErrorMessage;
    }

    /**
     * Sets error message
     *
     * @param string $sError
     */
    public function setErrorMessage($sError)
    {
        $this->_sErrorMessage = $sError;
    }

    /**
     * @return string
     */
    public function fcwlopGetLastRequestMessage()
    {
        return $this->_sLastRequestMessage;
    }

    /**
     * Triggers capture request to Worldline API and displays the result
     *
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function capture()
    {
        $oOrder = $this->getOrder();
        if ($oOrder->getId()) {
            $blCompleteOrder = false;
            $dAmount = 0;
            $sCaptureSource = Registry::getRequest()->getRequestParameter('fcwlop_capture_source');
            if ($sCaptureSource == 'by-amount') {
                $sAmount = Registry::getRequest()->getRequestParameter('capture_amount');
                if ($sAmount) {
                    $dAmount = floatval(str_replace(',', '.', (string)$sAmount));
                }
            } elseif ($sCaptureSource == 'by-position') {
                $aPositions = Registry::getRequest()->getRequestParameter('capture_positions');
                foreach ($aPositions as $sOrderArtKey => $aOrderArt) {
                    if ($aOrderArt['capture'] == '0') {
                        unset($aPositions[$sOrderArtKey]);
                        continue;
                    }
                    $dAmount += $aOrderArt['price'] * $aOrderArt['amount'];
                }

                $blCompleteOrder = intval(Registry::getRequest()->getRequestParameter("capture_completeorder"));
                $blCompleteOrder = $blCompleteOrder === null || $blCompleteOrder;
            }

            if ($dAmount > 0) {
                $oRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetCaptureRequest($oOrder, $blCompleteOrder, $dAmount);

                $oResponse = $oRequest->execute();

                $oLang = Registry::getLang();
                if ($oResponse->getStatus() != 'SUCCESS') {
                    $this->_sLastRequestMessage = '<span style="color: red;">' . $oLang->translateString('FCWLOP_CAPTURE_FAILED', null, true) . '</span>';
                } else {
                    if ($sCaptureSource == 'by-position') {
                        FcwlopPaymentHelper::getInstance()->fcwlopProcessCapturePositions($oOrder, $aPositions);
                    }

                    $this->_sLastRequestMessage = '<span style="color: green;">' . $oLang->translateString('FCWLOP_CAPTURE_APPROVED', null, true) . '</span>';
                }
            }
        }
    }

    /**
     * Triggers refund request to Worldline API and displays the result
     *
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function refund()
    {
        $oOrder = $this->getOrder();
        if ($oOrder->getId()) {
            $dAmount = 0;
            $sRefundSource = Registry::getRequest()->getRequestParameter('fcwlop_refund_source');
            if ($sRefundSource == 'by-amount') {
                $sAmount = Registry::getRequest()->getRequestParameter('refund_amount');
                if ($sAmount) {
                    $dAmount = floatval(str_replace(',', '.', (string)$sAmount));
                }
            } elseif ($sRefundSource == 'by-position') {
                $aPositions = Registry::getRequest()->getRequestParameter('refund_positions');
                foreach ($aPositions as $sOrderArtKey => $aOrderArt) {
                    if ($aOrderArt['refund'] == '0') {
                        unset($aPositions[$sOrderArtKey]);
                        continue;
                    }
                    $dAmount += $aOrderArt['price'] * $aOrderArt['amount'];
                }
            }

            if ($dAmount > 0) {
                $oRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetRefundRequest($oOrder, $dAmount);

                $oResponse = $oRequest->execute();

                $oLang = Registry::getLang();
                if ($oResponse->getStatus() != 'SUCCESS') {
                    $this->_sLastRequestMessage = '<span style="color: red;">' . $oLang->translateString('FCWLOP_REFUND_FAILED', null, true) . '</span>';
                } else {
                    if ($sRefundSource == 'by-position') {
                        FcwlopPaymentHelper::getInstance()->fcwlopProcessRefundPositions($oOrder, $aPositions);
                    }

                    $this->_sLastRequestMessage = '<span style="color: green;">' . $oLang->translateString('FCWLOP_REFUND_APPROVED', null, true) . '</span>';
                }
            }
        }
    }

    /**
     * Triggers cancel request to Worldline API and displays the result
     *
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function cancel()
    {
        $oOrder = $this->getOrder();
        if ($oOrder->getId()) {
            $oRequest = FcwlopPaymentHelper::getInstance()->fcwlopGetCancelPaymentRequest($oOrder);

            $oResponse = $oRequest->execute();

            $oLang = Registry::getLang();
            if ($oResponse->getStatus() != 'SUCCESS') {
                $this->_sLastRequestMessage = '<span style="color: red;">' . $oLang->translateString('FCWLOP_CANCEL_FAILED', null, true) . '</span>';
            } else {
                $this->_sLastRequestMessage = '<span style="color: green;">' . $oLang->translateString('FCWLOP_CANCEL_APPROVED', null, true) . '</span>';
            }
        }
    }
}