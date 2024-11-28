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
use OnlinePayments\Sdk\DataObject;
use OnlinePayments\Sdk\Domain\CapturePaymentRequest;
use OnlinePayments\Sdk\Domain\CaptureResponse;
use OnlinePayments\Sdk\Domain\PaymentReferences;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopCapturePaymentRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'CapturePayment';

    /**
     * @var CapturePaymentRequest
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
        $this->oApiRequest = new CapturePaymentRequest();
        $this->oOrder = $oOrder;
    }

    /**
     * @param int $iAmount
     * @return void
     */
    public function addAmountParameter($iAmount)
    {
        $this->oApiRequest->setAmount($iAmount);
    }

    /**
     * @param bool $blIsFinal
     * @return void
     */
    public function setIsFinal($blIsFinal)
    {
        $this->oApiRequest->setIsFinal($blIsFinal);
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
    public function buildOperationReferencesParameter()
    {
        $oParams = new PaymentReferences();
        $oParams->setMerchantReference($this->oOrder->oxorder__oxordernr->value);

        return $oParams;
    }

    /**
     * @return mixed|DataObject|CaptureResponse
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function execute()
    {
        $oRequestLog = oxNew(FcwlopRequestLog::class);

        try {
            $sPaymentId = $this->oOrder->oxorder__oxtransid->value . '_0';
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetPaymentApi()->capturePayment($sPaymentId, $this->oApiRequest);
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
        $aParameters['amount'] = $this->oApiRequest->getAmount();
        $aParameters['isFinal'] = $this->oApiRequest->getIsFinal() ? 1 : 0;
        $aParameters['references'] = $this->oApiRequest->getReferences() ? $this->oApiRequest->getReferences()->toJson() : '';
        return $aParameters;
    }
}
