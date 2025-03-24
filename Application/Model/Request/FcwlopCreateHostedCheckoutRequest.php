<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Request;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\FcwlopRequestLog;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodTypes;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericErrorResponse;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericResponse;
use OnlinePayments\Sdk\Domain\BankAccountIban;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutRequest;
use OnlinePayments\Sdk\Domain\CreateMandateRequest;
use OnlinePayments\Sdk\Domain\HostedCheckoutSpecificInput;
use OnlinePayments\Sdk\Domain\MandateAddress;
use OnlinePayments\Sdk\Domain\MandateContactDetails;
use OnlinePayments\Sdk\Domain\MandateCustomer;
use OnlinePayments\Sdk\Domain\MandatePersonalInformation;
use OnlinePayments\Sdk\Domain\MandatePersonalName;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\PaymentProductFilter;
use OnlinePayments\Sdk\Domain\PaymentProductFiltersHostedCheckout;
use OnlinePayments\Sdk\Domain\SepaDirectDebitPaymentMethodSpecificInput;
use OnlinePayments\Sdk\Domain\SepaDirectDebitPaymentProduct771SpecificInput;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User as CoreUser;
use OxidEsales\Eshop\Core\Registry;

class FcwlopCreateHostedCheckoutRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'CreateHostedCheckout';

    /**
     * @var CreateHostedCheckoutRequest
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
        $this->oApiRequest = new CreateHostedCheckoutRequest();
        $this->oOrder = $oOrder;
    }

    /**
     * @param Order $oOrder
     * @return void
     */
    public function addOrderParameter(Order $oOrder)
    {
        $this->oApiRequest->setOrder($oOrder);
    }

    /**
     * @param HostedCheckoutSpecificInput $oParameters
     * @return void
     */
    public function addHostedSpecificParameters(HostedCheckoutSpecificInput $oParameters)
    {
        $this->oApiRequest->setHostedCheckoutSpecificInput($oParameters);
    }

    /**
     * @param SepaDirectDebitPaymentMethodSpecificInput $oParameters
     * @return void
     */
    public function addSepaDirectDebitParameters(SepaDirectDebitPaymentMethodSpecificInput $oParameters)
    {
        $this->oApiRequest->setSepaDirectDebitPaymentMethodSpecificInput($oParameters);
    }

    /**
     * @param CoreOrder $oCoreOrder
     * @param int $iPaymentProductId
     * @param string $sIban
     * @return SepaDirectDebitPaymentMethodSpecificInput
     */
    public function buildApiSepaDirectDebitSpecificInput(CoreOrder $oCoreOrder, $iPaymentProductId, $sIban)
    {
        $oParameters = new SepaDirectDebitPaymentMethodSpecificInput();
        $oParameters->setPaymentProductId($iPaymentProductId);

        $oPaymentProductSpecificInput = new SepaDirectDebitPaymentProduct771SpecificInput();

        $oMandateRequest = $this->buildApiSepaMandateRequest($oCoreOrder, $this->oApiRequest->getOrder(), $sIban);
        $oPaymentProductSpecificInput->setMandate($oMandateRequest);

        $oParameters->setPaymentProduct771SpecificInput($oPaymentProductSpecificInput);

        return $oParameters;
    }

    /**
     * @param CoreOrder $oCoreOrder
     * @param Order $oApiOrder
     * @param string $sIban
     * @return CreateMandateRequest
     */
    protected function buildApiSepaMandateRequest(CoreOrder $oCoreOrder, Order $oApiOrder, $sIban)
    {
        $oMandateRequest = new CreateMandateRequest();

        $oMandateCustomer = $this->buildMandateCustomer($oCoreOrder, $oApiOrder, $sIban);
        $oMandateRequest->setCustomer($oMandateCustomer);

        $sUserId = $oCoreOrder->oxorder__oxuserid->value;
        $oMandateRequest->setCustomerReference($sUserId);

        $oHelper = FcwlopPaymentHelper::getInstance();
        $oUser = new CoreUser();
        $oUser->load($sUserId);
        $sLocale = $oHelper->fcwlopGetLocale($oUser);
        $aLang = explode('_', $sLocale);
        $oMandateRequest->setLanguage($aLang[0] ?? 'en');

        $oMandateRequest->setRecurrenceType('UNIQUE');
        $oMandateRequest->setSignatureType('UNSIGNED');

        $sUniqueMandateReference = (new \DateTimeImmutable())->format('Ymdhis') .
            '_' . $oUser->oxuser__oxcustnr->value .
            '_' . $oCoreOrder->oxorder_oxordernr->value;
        $oMandateRequest->setUniqueMandateReference($sUniqueMandateReference);

        return $oMandateRequest;
    }

    /**
     * @param CoreOrder $oCoreOrder
     * @param Order $oApiOrder
     * @param string $sIban
     * @return MandateCustomer
     */
    protected function buildMandateCustomer(CoreOrder $oCoreOrder, Order $oApiOrder, $sIban)
    {
        $oCustomer = $oApiOrder->getCustomer();
        $oMandateCustomer = new MandateCustomer();

        $oBankAccountIban = new BankAccountIban();
        $oBankAccountIban->setIban($sIban);
        $oMandateCustomer->setBankAccountIban($oBankAccountIban);

        if (!empty($oCustomer->getCompanyInformation())) {
            $oMandateCustomer->setCompanyName($oCustomer->getCompanyInformation()->getName());
        } elseif(!empty($oCoreOrder->oxorder__oxbillcompany->value)) {
            $oMandateCustomer->setCompanyName($oCoreOrder->oxorder__oxbillcompany->value);
        }

        $oMandateContactDetails = new MandateContactDetails();
        $oMandateContactDetails->setEmailAddress($oCustomer->getContactDetails()->getEmailAddress());
        $oMandateCustomer->setContactDetails($oMandateContactDetails);

        $oMandateAddress = new MandateAddress();
        $oMandateAddress->fromJson($oCustomer->getBillingAddress()->toJson());
        $oMandateCustomer->setMandateAddress($oMandateAddress);

        $oMandatePersonalName = new MandatePersonalName();
        $oMandatePersonalName->fromJson($oCustomer->getPersonalInformation()->getName()->toJson());
        $oMandatePersonalInfo = new MandatePersonalInformation();
        $oMandatePersonalInfo->setName($oMandatePersonalName);
        $oMandatePersonalInfo->setTitle($oCustomer->getPersonalInformation()->getGender());
        $oMandateCustomer->setPersonalInformation($oMandatePersonalInfo);

        return $oMandateCustomer;
    }

    /**
     * @param int $iPaymentProductId
     * @param string $sReturnUrl
     * @param CoreOrder $oCoreOrder
     * @return HostedCheckoutSpecificInput
     */
    public function buildApiHostedParameter($iPaymentProductId, $sReturnUrl, CoreOrder $oCoreOrder)
    {
        $oParams = new HostedCheckoutSpecificInput();
        $oParams->setLocale(FcwlopOrderHelper::getInstance()->fcwlopGetLocale($oCoreOrder));

        $aProductFilter = [$iPaymentProductId];
        if ($iPaymentProductId === 0) {
            $aProductFilter = [];
            foreach (FcwlopPaymentMethodTypes::WORLDLINE_CARDS_PRODUCTS as $sPaymentId => $sName) {
                $oPayment = oxNew(Payment::class);
                $oPayment->load($sPaymentId);
                if ($oPayment->oxpayments__oxactive->value == 1) {
                    $aProductFilter[] = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlinePaymentModel($sPaymentId)->getWorldlinePaymentCode();
                }
            }
        }

        $oProductFilters = new PaymentProductFiltersHostedCheckout();
        $oPaymentRestrictionFilter = new PaymentProductFilter();
        $oPaymentRestrictionFilter->setProducts($aProductFilter);
        $oProductFilters->setRestrictTo($oPaymentRestrictionFilter);
        $oParams->setPaymentProductFilters($oProductFilters);

        $oParams->setReturnUrl($sReturnUrl);
        $oParams->setShowResultPage(false);

        return $oParams;
    }

    /**
     * @return FcwlopGenericResponse
     * @throws \Exception
     */
    public function execute()
    {
        $oRequestLog = oxNew(FcwlopRequestLog::class);

        try {
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetHostedCheckoutApi()->createHostedCheckout($this->oApiRequest);
            $oRequestLog->logRequest($this->toArray(), $oApiResponse->toObject(), $this->oOrder->getId(), $this->sRequestType, 'SUCCESS');

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
        $oApiOrder = $this->oApiRequest->getOrder();
        $aParameters['customer'] = $oApiOrder->getCustomer() ? $oApiOrder->getCustomer()->toJson() : '';
        $aParameters['amountOfMoney'] = $oApiOrder->getAmountOfMoney() ? $oApiOrder->getAmountOfMoney()->toJson() : '';
        $aParameters['references'] = $oApiOrder->getReferences() ? $oApiOrder->getReferences()->toJson() : '';
        $aParameters['discount'] = $oApiOrder->getDiscount() ? $oApiOrder->getDiscount()->toJson() : '';
        $aParameters['additionalInput'] = $oApiOrder->getAdditionalInput() ? $oApiOrder->getAdditionalInput()->toJson() : '';
        $aParameters['shipping'] = $oApiOrder->getShipping() ? $oApiOrder->getShipping()->toJson() : '';
        $aParameters['shoppingCart'] = $oApiOrder->getShoppingCart() ? $oApiOrder->getShoppingCart()->toJson() : '';
        $aParameters['surchargeSpecificInput'] = $oApiOrder->getSurchargeSpecificInput() ? $oApiOrder->getSurchargeSpecificInput()->toJson() : '';
        $aParameters['apiRequest'] = $this->oApiRequest ? $this->oApiRequest->toJson() : '';
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
