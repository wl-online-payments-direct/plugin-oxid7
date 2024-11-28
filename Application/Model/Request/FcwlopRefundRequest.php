<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Request;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\FcwlopRequestLog;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericErrorResponse;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericResponse;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\PaymentReferences;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopRefundRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'Refund';

    /**
     * @var \OnlinePayments\Sdk\Domain\RefundRequest
     */
    private $oApiRequest;

    /**
     * @var CoreOrder
     */
    private CoreOrder $oOrder;


    /**
     * @param CoreOrder $oOrder
     */
    public function __construct(CoreOrder $oOrder)
    {
        $this->oApiRequest = new \OnlinePayments\Sdk\Domain\RefundRequest();
        $this->oOrder = $oOrder;
    }

    /**
     * @param int $iAmount
     * @param string $sCurrency
     * @return void
     */
    public function addAmountParameter($iAmount, $sCurrency)
    {
        $oAmountParam = new AmountOfMoney();
        $oAmountParam->setAmount($iAmount);
        $oAmountParam->setCurrencyCode($sCurrency);
        $this->oApiRequest->setAmountOfMoney($oAmountParam);
    }

    /**
     * @param PaymentReferences $oOperationReferences
     * @return void
     */
    public function addOperationReferencesParameter(PaymentReferences $oOperationReferences)
    {
        $this->oApiRequest->setReferences($oOperationReferences);
    }

    /**
     * @return PaymentReferences
     */
    public function fcwlopBuildOperationReferencesParameter()
    {
        $oParams = new PaymentReferences();
        $oParams->setMerchantReference($this->oOrder->oxorder__oxordernr->value);
        return $oParams;
    }

    /**
     * @return FcwlopGenericErrorResponse|FcwlopGenericResponse
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function execute()
    {
        $oRequestLog = oxNew(FcwlopRequestLog::class);

        try {
            $sPaymentId = $this->oOrder->oxorder__oxtransid->value . '_0';
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetPaymentApi()->refundPayment($sPaymentId, $this->oApiRequest);
            $oRequestLog->logRequest($this->toArray(), $oApiResponse->toObject(), $this->oOrder->getId(), $this->sRequestType, 'SUCCESS');

            $oResponse = new FcwlopGenericResponse();
            $oResponse->setStatus('SUCCESS');
            $oResponse->setStatusCode(200);
            $oResponse->setBody(json_decode($oApiResponse->toJson(), true));
        } catch (ValidationException | ReferenceException $oEx) {
            foreach ($oEx->getErrors() as $oApiError) {
                $sLogLine = $oApiError->getCode() . ' - '
                    . $oApiError->getPropertyName() . ' : '
                    . $oApiError->getMessage()
                    . ' (' . $oApiError->getId() . ')' . PHP_EOL;

                Registry::getLogger()->error($sLogLine);
            }
            $oRequestLog->logErrorRequest($this->toArray(), $oEx, $this->oOrder->getId(), $this->sRequestType);
            $oResponse = new FcwlopGenericErrorResponse();
            $oResponse->setStatus(400);
            $oResponse->setBody(json_decode($oEx->getResponse()->toJson(), true));
        } catch (\Exception $oEx) {
            $oRequestLog->logErrorRequest($this->toArray(), $oEx, $this->oOrder->getId(), $this->sRequestType);
            $oResponse = new FcwlopGenericErrorResponse();
            $oResponse->setStatus($oEx->getCode());
            $oResponse->setBody($oEx->getTrace());
        }

        return $oResponse;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $aParameters = parent::toArray();
        $aParameters['amount'] = $this->oApiRequest->getAmountOfMoney() ? $this->oApiRequest->getAmountOfMoney()->getAmount() : '';
        $aParameters['currency'] = $this->oApiRequest->getAmountOfMoney() ? $this->oApiRequest->getAmountOfMoney()->getCurrencyCode() : '';
        $aParameters['references'] = $this->oApiRequest->getReferences() ? $this->oApiRequest->getReferences()->toJson() : '';
        return $aParameters;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return $this->oApiRequest->toJson();
    }
}
