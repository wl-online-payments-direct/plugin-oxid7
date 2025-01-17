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
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopGetHostedTokenizationRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'GetHostedTokenization';

    /** @var string */
    private $sHostedTokenizationId = '';

    /**
     * @return FcwlopGenericResponse
     * @throws \Exception
     */
    public function execute()
    {
        $oRequestLog = oxNew(FcwlopRequestLog::class);

        try {
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetHostedTokenizationApi()->getHostedTokenization($this->sHostedTokenizationId);
            $oRequestLog->logRequest($this->toArray(), $oApiResponse->toObject(), Registry::getSession()->getId(), $this->sRequestType, 'SUCCESS');

            $oResponse = new FcwlopGenericResponse();
            $oResponse->setStatus('SUCCESS');
            $oResponse->setStatusCode(200);
            $oResponse->setBody(json_decode($oApiResponse->toJson(), true));

            return $oResponse;
        } catch (ValidationException|ReferenceException $oEx) {
            foreach ($oEx->getErrors() as $oApiError) {
                $sLogLine = $oApiError->getCode() . ' - '
                    . $oApiError->getPropertyName() . ' : '
                    . $oApiError->getMessage()
                    . ' (' . $oApiError->getId() . ')';

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
        $aParameters['hostedTokenizationId'] = $this->sHostedTokenizationId;

        return $aParameters;
    }

    /**
     * @return string
     */
    public function getHostedTokenizationId()
    {
        return $this->sHostedTokenizationId;
    }

    /**
     * @param string $sHostedTokenizationId
     */
    public function setHostedTokenizationId($sHostedTokenizationId)
    {
        $this->sHostedTokenizationId = $sHostedTokenizationId;
    }
}
