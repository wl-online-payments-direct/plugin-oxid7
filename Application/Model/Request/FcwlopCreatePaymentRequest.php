<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Request;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use FC\FCWLOP\Application\Model\FcwlopRequestLog;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericErrorResponse;
use FC\FCWLOP\Application\Model\Response\FcwlopGenericResponse;
use OnlinePayments\Sdk\DataObject;
use OnlinePayments\Sdk\DeclinedPaymentException;
use OnlinePayments\Sdk\Domain\BrowserData;
use OnlinePayments\Sdk\Domain\CapturePaymentRequest;
use OnlinePayments\Sdk\Domain\CaptureResponse;
use OnlinePayments\Sdk\Domain\CardPaymentMethodSpecificInput;
use OnlinePayments\Sdk\Domain\CreatePaymentRequest;
use OnlinePayments\Sdk\Domain\Customer;
use OnlinePayments\Sdk\Domain\CustomerDevice;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\PaymentReferences;
use OnlinePayments\Sdk\Domain\RedirectionData;
use OnlinePayments\Sdk\Domain\ThreeDSecure;
use OnlinePayments\Sdk\ReferenceException;
use OnlinePayments\Sdk\ValidationException;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Registry;

class FcwlopCreatePaymentRequest extends FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = 'CreatePayment';

    /**
     * @var CreatePaymentRequest
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
        $this->oApiRequest = new CreatePaymentRequest();
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
     * @param string $sHostedTokenizationId
     * @return void
     */
    public function setHostedTokenizationId($sHostedTokenizationId)
    {
        $this->oApiRequest->setHostedTokenizationId($sHostedTokenizationId);
    }

    /**
     * @param string $sReturnUrl
     */
    public function addCardPaymentSpecificInput($sReturnUrl)
    {
        $oRedirectionData = new RedirectionData();
        $oRedirectionData->setReturnUrl($sReturnUrl);
        
        $oThreeDSecure = new ThreeDSecure();
        $oThreeDSecure->setRedirectionData($oRedirectionData);
        $oThreeDSecure->setSkipAuthentication(false);
        
        $oCardPaymentSpecificInput = new CardPaymentMethodSpecificInput();
        $oCardPaymentSpecificInput->setThreeDSecure($oThreeDSecure);
        
        $this->oApiRequest->setCardPaymentMethodSpecificInput($oCardPaymentSpecificInput);
    }

    /**
     * @param CoreOrder $oOrder
     * @return Customer
     * @throws \Exception
     */
    public function buildCustomerData(CoreOrder $oOrder)
    {

        $iColorDepth = 24;
        $blJavascriptEnabled = true;
        $iScreenHeith = 1080;
        $iScreenWidth = 1920;

        $sIpAddr = Registry::getUtilsServer()->getRemoteAddress();
        $sAcceptedHeader = Registry::getUtilsServer()->getServerVar('HTTP_ACCEPT');
        $sUserAgent = Registry::getUtilsServer()->getServerVar('HTTP_USER_AGENT');
        $sLocale = FcwlopOrderHelper::getInstance()->fcwlopGetLocale($oOrder);

        $oDateTimeZoneLocal = new \DateTimeZone('Europe/Berlin');
        $oDateLocal = new \DateTimeImmutable('now', $oDateTimeZoneLocal);
        $oDateTimeZoneUTC = new \DateTimeZone('UTC');
        $iOffset = $oDateTimeZoneUTC->getOffset($oDateLocal);

        $oBrowserData = new BrowserData();
        $oBrowserData->setColorDepth($iColorDepth);
        $oBrowserData->setJavaScriptEnabled($blJavascriptEnabled);
        $oBrowserData->setScreenHeight($iScreenHeith);
        $oBrowserData->setScreenWidth($iScreenWidth);

        $oCustomerDevice = new CustomerDevice();
        $oCustomerDevice->setLocale($sLocale);
        $oCustomerDevice->setAcceptHeader($sAcceptedHeader);
        $oCustomerDevice->setUserAgent($sUserAgent);
        $oCustomerDevice->setTimezoneOffsetUtcMinutes($iOffset);
        $oCustomerDevice->setIpAddress($sIpAddr);
        $oCustomerDevice->setBrowserData($oBrowserData);

        $oCustomer = new Customer();
        $oCustomer->setDevice($oCustomerDevice);
        
        return $oCustomer;
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
            $oApiResponse = FcwlopPaymentHelper::getInstance()->fcwlopGetPaymentApi()->createPayment($this->oApiRequest);
            $oRequestLog->logRequest($this->toArray(), $oApiResponse->toObject(), $this->oOrder->getId(), $this->sRequestType, 'SUCCESS');

            $oResponse = new FcwlopGenericResponse();
            $oResponse->setStatus('SUCCESS');
            $oResponse->setStatusCode(200);
            $oResponse->setBody(json_decode($oApiResponse->toJson(), true));

        } catch (ValidationException | ReferenceException | DeclinedPaymentException $oEx) {
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
            $oResponse->setBody(
                [
                    'exception' => $oEx::class,
                    'message' => $oEx->getMessage(),
                    'trace' => $oEx->getTrace(),
                ]
            );
        }

        return $oResponse;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $aParameters = parent::toArray();
        $aParameters['hostedTokenizationId'] = $this->oApiRequest->getHostedTokenizationId();
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

    /**
     * @return string
     */
    public function toJson()
    {
        return $this->oApiRequest->toJson();
    }
}
