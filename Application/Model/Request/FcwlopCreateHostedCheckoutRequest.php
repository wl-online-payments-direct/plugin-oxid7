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
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutRequest;
use OnlinePayments\Sdk\Domain\HostedCheckoutSpecificInput;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\PaymentProductFilter;
use OnlinePayments\Sdk\Domain\PaymentProductFiltersHostedCheckout;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\Payment;
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
        return $aParameters;
    }
}
