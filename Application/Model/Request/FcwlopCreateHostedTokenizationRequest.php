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
use OnlinePayments\Sdk\Domain\CreateHostedTokenizationRequest;
use OnlinePayments\Sdk\Domain\CreditCardSpecificInputHostedTokenization;
use OnlinePayments\Sdk\Domain\PaymentProductFiltersHostedTokenization;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopCreateHostedTokenizationRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'CreateHostedTokenization';

    /**
     * @var CreateHostedTokenizationRequest
     */
    private $oApiRequest;

    /** @var string */
    private $sLocale = '';

    /** @var bool */
    private $blAskConsumerConsent = true;

    /**
     * @var PaymentProductFiltersHostedTokenization
     */
    private $oPaymentProductFilters;

    /**
     * @var CreditCardSpecificInputHostedTokenization
     */
    private $oCreditCardSpecificInput;


    public function __construct()
    {
        $this->oApiRequest = new CreateHostedTokenizationRequest();
    }
    
    public function setLocale($sLocale)
    {
        $this->sLocale = $sLocale;
    }

    public function setAskConsumerConsent($blAskConsumerConsent)
    {
        $this->blAskConsumerConsent = $blAskConsumerConsent;  
    }

    /**
     * @param PaymentProductFiltersHostedTokenization $oFilters
     * @return void
     */
    public function setPaymentProductFilters(PaymentProductFiltersHostedTokenization $oFilters)
    {
        $this->oPaymentProductFilters = $oFilters;
    }

    /**
     * @param CreditCardSpecificInputHostedTokenization $oParameters
     * @return void
     */
    public function setCreditCardSpecificInput(CreditCardSpecificInputHostedTokenization $oParameters)
    {
        $this->oCreditCardSpecificInput = $oParameters;
    }

    /**
     * @return FcwlopGenericResponse
     * @throws \Exception
     */
    public function execute()
    {
        $this->oApiRequest->setLocale($this->sLocale);
        $this->oApiRequest->setAskConsumerConsent($this->blAskConsumerConsent);
        $this->oApiRequest->setPaymentProductFilters($this->oPaymentProductFilters);
        $this->oApiRequest->setCreditCardSpecificInput($this->oCreditCardSpecificInput);
        
        $oRequestLog = oxNew(FcwlopRequestLog::class);

        try {
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetHostedTokenizationApi()->createHostedTokenization($this->oApiRequest);
            $oRequestLog->logRequest($this->toArray(), $oApiResponse->toObject(), Registry::getSession()->getId(), $this->sRequestType, 'SUCCESS');

            $oResponse = new FcwlopGenericResponse();
            $oResponse->setStatus('SUCCESS');
            $oResponse->setStatusCode(200);
            $oResponse->setBody(json_decode($oApiResponse->toJson(), true));

            return $oResponse;
        } catch (ValidationException | ReferenceException $oEx) {
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

        $aParameters['askCustomerConsent'] = $this->blAskConsumerConsent;
        $aParameters['locale'] = $this->sLocale;
        
        if ($this->oPaymentProductFilters) {
            if ($this->oPaymentProductFilters->getRestrictTo()) {
                $aParameters['restrictedTo'] = $this->oPaymentProductFilters->getRestrictTo()->toJson();
            }
            if ($this->oPaymentProductFilters->getExclude()) {
                $aParameters['exclude'] = json_decode($this->oPaymentProductFilters->getExclude()->toJson());
            }
        }
        
        if ($this->oCreditCardSpecificInput) {
            $aParameters['cvvForNewToken'] = $this->oCreditCardSpecificInput->getValidationRules()->getCvvMandatoryForNewToken();
            $aParameters['cvvForExistingToken'] = $this->oCreditCardSpecificInput->getValidationRules()->getCvvMandatoryForExistingToken();
            $aParameters['productPreferredOrder'] = $this->oCreditCardSpecificInput->getPaymentProductPreferredOrder();
        }
        
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
