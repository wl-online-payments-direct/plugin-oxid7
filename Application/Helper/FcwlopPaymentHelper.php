<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Helper;

use Exception;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericCardMethod;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodCodes;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodModels;
use FC\FCWLOP\Application\Model\Payment\FcwlopPaymentMethodTypes;
use FC\FCWLOP\Application\Model\Request\FcwlopCancelPaymentRequest;
use FC\FCWLOP\Application\Model\Request\FcwlopCapturePaymentRequest;
use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedTokenizationRequest;
use FC\FCWLOP\Application\Model\Request\FcwlopRefundRequest;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\DataObject;
use OnlinePayments\Sdk\DefaultConnection;
use OnlinePayments\Sdk\Domain\CreateHostedTokenizationRequest;
use OnlinePayments\Sdk\Domain\PaymentDetailsResponse;
use OnlinePayments\Sdk\Domain\PaymentProduct;
use OnlinePayments\Sdk\Domain\PaymentProductFilterHostedTokenization;
use OnlinePayments\Sdk\Domain\PaymentProductFiltersHostedTokenization;
use OnlinePayments\Sdk\Domain\TestConnection;
use OnlinePayments\Sdk\Merchant\HostedCheckout\HostedCheckoutClient;
use OnlinePayments\Sdk\Merchant\HostedTokenization\HostedTokenizationClient;
use OnlinePayments\Sdk\Merchant\MerchantClient;
use OnlinePayments\Sdk\Merchant\Payments\PaymentsClient;
use OnlinePayments\Sdk\Merchant\Products\GetPaymentProductsParams;
use OnlinePayments\Sdk\Webhooks\InMemorySecretKeyStore;
use OnlinePayments\Sdk\Webhooks\WebhooksHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use Psr\Container\ContainerInterface;

class FcwlopPaymentHelper
{
    /**
     * @var FcwlopPaymentHelper
     */
    protected static $oInstance = null;

    /**
     * @var ContainerInterface
     */
    protected $oContainer;

    /**
     * @var Client
     */
    protected $oMerchantClient;

    /**
     * Create singleton instance of payment helper
     *
     * @return FcwlopPaymentHelper
     */
    static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Determine if given paymentId is a Worldline payment method
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentMethod($sPaymentId)
    {
        return FcwlopPaymentMethodTypes::fcwlopIsWorldlineMethodType($sPaymentId);
    }

    /**
     * Determine if given paymentCode is a Worldline payment method
     *
     * @param int $iPaymentCode
     * @return bool
     */
    public function fcwlopIsWorldlinePaymentMethodCode($iPaymentCode)
    {
        return FcwlopPaymentMethodCodes::fcwlopIsWorldlineMethodCode($iPaymentCode);
    }

    /**
     * Returns payment model for given paymentId
     *
     * @param string $sPaymentId
     * @return FcwlopWorldlineGenericMethod
     * @throws Exception
     */
    public function fcwlopGetWorldlinePaymentModel($sPaymentId)
    {
        if ($this->fcwlopIsWorldlinePaymentMethod($sPaymentId) === false || !in_array($sPaymentId, array_keys(FcwlopPaymentMethodModels::WORLDLINE_PAYMENT_MODELS))) {
            throw new Exception('Worldline Payment method unknown - '.$sPaymentId);
        }

        if ($sPaymentId == 'fcwlopgroupedcard' &&
            FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineCreditCardMode() == 'embedded'
        ) {
            return oxNew(FcwlopWorldlineGenericCardMethod::class);
        }

        $sModel = FcwlopPaymentMethodModels::fcwlopGetWorldlineMethodModel($sPaymentId);
        if (is_null($sModel)) {
            /** @var FcwlopWorldlineGenericMethod $oGenericModel */
            $oGenericModel = oxNew(FcwlopPaymentMethodModels::fcwlopGetWorldlineMethodModel('fcwlopgeneric'));
            $oGenericModel->setOxidPaymentId($sPaymentId);
            $oGenericModel->setWorldlinePaymentCode(FcwlopPaymentMethodCodes::WORLDLINE_PAYMENT_CODES[$sPaymentId]);

            return $oGenericModel;
        }

        return oxNew($sModel);
    }

    /**
     * Returns configured capture mode of worldline
     *
     * @return string
     */
    public function fcwlopGetWorldlineCaptureMode()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopCaptureMethod');
    }

    /**
     * Returns configured mode of worldline
     *
     * @return string
     */
    public function fcwlopGetWorldlineMode()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopMode');
    }

    /**
     * Returns configured Credit Card Checkout mode
     *
     * @return string
     */
    public function fcwlopGetWorldlineCreditCardMode()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopCcCheckoutType');
    }

    /**
     * Returns configured Credit Card Group display mode
     *
     * @return boolean
     */
    public function fcwlopIsWorldlineCreditCardGrouped()
    {
        return (bool) $this->fcwlopGetShopConfVar('sFcwlopCcGroupDisplay');
    }

    /**
     * Checks if method is a worldline Cards product
     *
     * @return boolean
     */
    public function fcwlopIsWorldlineCardsProduct($sPaymentMethodType)
    {
        return FcwlopPaymentMethodTypes::fcwlopIsWorldlineCardsProduct($sPaymentMethodType);
    }

    /**
     * @return DataObject|TestConnection
     * @throws Exception
     */
    public function fcwlopTestWorldlineConnection()
    {
        /** @var MerchantClient $oApi */
        $oApi = $this->fcwlopLoadWorldlineApi();

        $oResponse = $oApi->services()->testConnection();
        return $oResponse;
    }

    /**
     * @param boolean $blSkipFieldData
     * @return PaymentProduct[]
     * @throws Exception
     */
    public function fcwlopFetchWorldlineEnabledMethods($blSkipFieldData = true)
    {
        /** @var MerchantClient $oApi */
        $oApi = $this->fcwlopLoadWorldlineApi();
        $oParams = new GetPaymentProductsParams();
        $oParams->setCountryCode($this->fcwlopGetShopCountryCode());
        $oParams->setCurrencyCode($this->fcwlopGetShopCurrency()->name);
        if ($blSkipFieldData) {
            $oParams->addHide('fields');
        }

        return $oApi->products()->getPaymentProducts($oParams)->getPaymentProducts();
    }

    /**
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcwlopFetchWorldlineInstalledMethods()
    {
        $aMethods = [];

        $sQuery = " SELECT 
                        OXID, FCWLOPEXTID
                    FROM 
                        oxpayments
                    WHERE 
                        FCWLOPISWORLDLINE = 1";
        $aResult = DatabaseProvider::getDb()->getAll($sQuery);
        foreach ($aResult as $aRow) {
            $aMethods[] = [
                (int) $aRow[1] => $aRow[0],
            ];
        }

        return $aMethods;
    }

    /**
     * @param array $aMethodDetails
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcwlopRegisterWorldlineMethod(array $aMethodDetails)
    {
        $aPaymentMethodMap = array_flip(FcwlopPaymentMethodCodes::WORLDLINE_PAYMENT_CODES);
        if (!isset($aPaymentMethodMap[$aMethodDetails['id']])) {
            throw new Exception('Worldline method (' . $aMethodDetails['id'] . ' - ' . $aMethodDetails['label'] . ') could not be added. Matching OXID not found.');
        }

        $sOxid = $aPaymentMethodMap[$aMethodDetails['id']];

        $sQuery = "
            INSERT INTO oxpayments(OXID,OXACTIVE,OXDESC,OXADDSUM,OXADDSUMTYPE,OXFROMBONI,OXFROMAMOUNT,OXTOAMOUNT,OXVALDESC,OXCHECKED,OXDESC_1,OXVALDESC_1,OXDESC_2,OXVALDESC_2,OXDESC_3,OXVALDESC_3,OXLONGDESC,OXLONGDESC_1,OXLONGDESC_2,OXLONGDESC_3,OXSORT, FCWLOPISWORLDLINE, FCWLOPEXTID, FCWLOPEXTTYPE, FCWLOPEXTGROUPTYPE, FCWLOPEXTLOGO) 
            VALUES ('{$sOxid}', '{$aMethodDetails['active']}', '{$aMethodDetails['label']}', 0, 'abs', 0, 0, 1000000, '', 0, '{$aMethodDetails['label']}', '', '', '', '', '', '', '', '', '', 0, 1, {$aMethodDetails['id']}, '{$aMethodDetails['type']}', '{$aMethodDetails['groupType']}', '{$aMethodDetails['logoLink']}');
        ";

        $blNewlyAdded = FcwlopDatabaseHelper::insertRowIfNotExists(
            'oxpayments',
            array('OXID' => $sOxid),
            $sQuery
        );

        if ($blNewlyAdded === true) {
            //Insert basic payment method configuration
            foreach (FcwlopDatabaseHelper::$aGroupsToAdd as $sGroupId) {
                DatabaseProvider::getDb()->Execute("INSERT INTO oxobject2group(OXID,OXSHOPID,OXOBJECTID,OXGROUPSID) values (REPLACE(UUID(),'-',''), :shopid, :paymentid, :groupid);", [
                    ':shopid' => Registry::getConfig()->getShopId(),
                    ':paymentid' => $sOxid,
                    ':groupid' => $sGroupId,
                ]);
            }
        }
        FcwlopDatabaseHelper::insertRowIfNotExists('oxobject2payment', array('OXPAYMENTID' => $sOxid, 'OXTYPE' => 'oxdelset'), "INSERT INTO oxobject2payment(OXID,OXPAYMENTID,OXOBJECTID,OXTYPE) values (REPLACE(UUID(),'-',''), :paymentid, 'oxidstandard', 'oxdelset');", [':paymentid' => $sOxid]);
    }

    /**
     * @param string $sTransactionId
     * @param int $iStep
     * @return DataObject|PaymentDetailsResponse
     * @throws DatabaseConnectionException
     */
    public function fcwlopGetWorldlinePaymentDetails($sTransactionId, $iStep = -1)
    {
        if ($iStep < 0) {
            $iStep = $this->fcwlopGetLatestTransactionStep($sTransactionId);
        }

        $sPaymentId = $sTransactionId . '_' . $iStep;
        return $this->fcwlopLoadWorldlineApi()->payments()->getPaymentDetails($sPaymentId);
    }

    /**
     * @param string $sTransactionId
     * @param int $iStep
     * @return array
     * @throws DatabaseConnectionException
     */
    public function fcwlopGetWorldlinePaymentCaptures($sTransactionId, $iStep = -1)
    {
        if ($iStep < 0) {
            $iStep = $this->fcwlopGetLatestTransactionStep($sTransactionId);
        }

        $sPaymentId = $sTransactionId . '_' . $iStep;
        $aDbTransaction = $this->fcwlopGetWorldlineTransaction($sTransactionId, -1, 'CAPTURED');

        $aCaptures = [];
        $oPaymentCaptures = $this->fcwlopLoadWorldlineApi()->payments()->getCaptures($sPaymentId);
        foreach ($oPaymentCaptures->getCaptures() as $oCapture) {
            if ($oCapture->getStatus() != 'CAPTURED') {
                continue;
            }

            $aCaptures[$oCapture->getId()] = [
                'date' => $aDbTransaction['FCWLOP_TIME'],
                'amount' => $oCapture->getCaptureOutput()->getAcquiredAmount()->getAmount(),
                'currency' =>  $oCapture->getCaptureOutput()->getAcquiredAmount()->getCurrencyCode()
            ];
        }

        return $aCaptures;
    }

    /**
     * @param string $sTransactionId
     * @param int $iStep
     * @return array
     * @throws DatabaseConnectionException
     */
    public function fcwlopGetWorldlinePaymentRefunds($sTransactionId, $iStep = -1)
    {
        if ($iStep < 0) {
            $iStep = $this->fcwlopGetLatestTransactionStep($sTransactionId);
        }

        $sPaymentId = $sTransactionId . '_' . $iStep;
        $aDbTransaction = $this->fcwlopGetWorldlineTransaction($sTransactionId, -1, 'REFUNDED');

        $aRefunds = [];
        $oPaymentRefunds = $this->fcwlopLoadWorldlineApi()->payments()->getRefunds($sPaymentId);
        foreach ($oPaymentRefunds->getRefunds() as $oRefundResponse) {
            if ($oRefundResponse->getStatus() != 'REFUNDED') {
                continue;
            }
            
            $oRefundOutput = $oRefundResponse->getRefundOutput();
            $aRefunds[$oRefundResponse->getId()] = [
                'date' => $aDbTransaction['FCWLOP_TIME'],
                'amount' => $oRefundOutput->getAmountOfMoney() ? $oRefundOutput->getAmountOfMoney()->getAmount() : '',
                'currency' =>  $oRefundOutput->getAmountOfMoney() ? $oRefundOutput->getAmountOfMoney()->getCurrencyCode() : '',
            ];
        }

        return $aRefunds;
    }

    /**
     * Return the Worldline webhook url
     *
     * @return string
     */
    public function fcwlopGetWebhookUrl()
    {
        return Registry::getConfig()->getShopUrl().'index.php?cl=fcwlopWebhook&fnc=handle';
    }

    /**
     * Return the configured Worldline webhook key
     *
     * @return string
     */
    public function fcwlopGetWebhookEndpointId()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopWebhookKey');
    }

    /**
     * Return the configured Worldline webhook secret
     *
     * @return string
     */
    public function fcwlopGetWebhookEndpointSecret()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopWebhookSecret');
    }

    /**
     * @return HostedCheckoutClient
     */
    public function fcwlopGetHostedCheckoutApi()
    {
        $oApi = $this->fcwlopLoadWorldlineApi();
        
        return $oApi->hostedCheckout();
    }

    /**
     * @return PaymentsClient
     */
    public function fcwlopGetPaymentApi()
    {
        $oApi = $this->fcwlopLoadWorldlineApi();

        return $oApi->payments();
    }

    /**
     * @return HostedTokenizationClient
     */
    public function fcwlopGetHostedTokenizationApi()
    {
        $oApi = $this->fcwlopLoadWorldlineApi();

        return $oApi->hostedTokenization();
    }

    /**
     * @return WebhooksHelper
     */
    public function fcwlopGetWorldlineWebhookHelper()
    {
        $sEndpointKeyId = $this->fcwlopGetWebhookEndpointId();
        $sEndpointSecret = $this->fcwlopGetWebhookEndpointSecret();
        $oKeyStore = new InMemorySecretKeyStore();
        $oKeyStore->storeSecretKey($sEndpointKeyId, $sEndpointSecret);

        return new WebhooksHelper($oKeyStore);
    }

    /**
     * Returns config value
     *
     * @param  string $sVarName
     * @return mixed|false
     */
    public function fcwlopGetShopConfVar($sVarName)
    {
        $moduleConfiguration = $this
            ->fcwlopGetContainer()
            ->get(ModuleConfigurationDaoBridgeInterface::class)
            ->get("fcwlop");
        if (!$moduleConfiguration->hasModuleSetting($sVarName)) {
            return false;
        }
        return $moduleConfiguration->getModuleSetting($sVarName)->getValue();
    }

    /**
     * @return string
     */
    public function fcwlopGetHelpUrl()
    {
        return 'https://support.worldline.com';
    }

    /**
     * @param Order $oOrder
     * @return FcwlopCapturePaymentRequest
     */
    public function fcwlopGetFullCaptureRequest(Order $oOrder)
    {
        $dAmount = $oOrder->oxorder__oxtotalordersum->value;

        return $this->fcwlopGetCaptureRequest($oOrder, true, $dAmount);
    }

    /**
     * @param Order $oOrder
     * @param boolean $blIsFinal
     * @param double $dAmount
     * @return FcwlopCapturePaymentRequest
     */
    public function fcwlopGetCaptureRequest(Order $oOrder, $blIsFinal, $dAmount)
    {
        $oRequest = new FcwlopCapturePaymentRequest($oOrder);
        $oRequest->setIsFinal($blIsFinal);
        $oRequest->addOperationReferencesParameter(
            $oRequest->buildOperationReferencesParameter()
        );
        $oRequest->addAmountParameter(FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($dAmount));

        return $oRequest;
    }

    /**
     * @param Order $oOrder
     * @param array $aPositions
     * @return void
     */
    public function fcwlopProcessCapturePositions(Order $oOrder, array $aPositions)
    {
        foreach ($aPositions as $sOrderArticleOxid => $aOrderArticleDetails) {
            if (in_array($sOrderArticleOxid, ['oxdelcost', 'oxpaycost', 'oxwrapcost', 'oxgiftcardcost', 'oxvoucherdiscount', 'oxdiscount'])) {
                $oCapturedPriceField = new Field(abs($aOrderArticleDetails['price']));

                if ($sOrderArticleOxid == 'oxdelcost') {
                    $oOrder->oxorder__fcwlopdelcostcaptured = $oCapturedPriceField;
                } elseif ($sOrderArticleOxid == 'oxpaycost') {
                    $oOrder->oxorder__fcwloppaycostcaptured = $oCapturedPriceField;
                } elseif ($sOrderArticleOxid == 'oxwrapcost') {
                    $oOrder->oxorder__fcwlopwrapcostcaptured = $oCapturedPriceField;
                } elseif ($sOrderArticleOxid == 'oxgiftcardcost') {
                    $oOrder->oxorder__fcwlopgiftcardcaptured = $oCapturedPriceField;
                } elseif ($sOrderArticleOxid == 'oxvoucherdiscount') {
                    $oOrder->oxorder__fcwlopvoucherdiscountcaptured = $oCapturedPriceField;
                } elseif ($sOrderArticleOxid == 'oxdiscount') {
                    $oOrder->oxorder__fcwlopdiscountcaptured = $oCapturedPriceField;
                }

                $oOrder->save();

            } else {
                $oOrderArticle = oxNew(OrderArticle::class);
                $oOrderArticle->load($sOrderArticleOxid);
                if ($oOrderArticle) {
                    $iNewCapturedAmount = $oOrderArticle->oxorderarticles__fcwlopamountcaptured->value + $aOrderArticleDetails['amount'];
                    $oOrderArticle->oxorderarticles__fcwlopamountcaptured = new Field($iNewCapturedAmount);
                    $oOrderArticle->save();
                }
            }
        }
    }

    /**
     * @param Order $oOrder
     * @param double $dAmount
     * @return FcwlopRefundRequest
     */
    public function fcwlopGetRefundRequest(Order $oOrder, $dAmount)
    {
        $oRequest = new FcwlopRefundRequest($oOrder);
        $oRequest->addOperationReferencesParameter(
            $oRequest->fcwlopBuildOperationReferencesParameter()
        );
        $oRequest->addAmountParameter(
            FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($dAmount),
            $oOrder->oxorder__oxcurrency->value
        );

        return $oRequest;
    }

    /**
     * @param Order $oOrder
     * @return FcwlopCancelPaymentRequest
     */
    public function fcwlopGetCancelPaymentRequest(Order $oOrder)
    {
        $oRequest = new FcwlopCancelPaymentRequest($oOrder);

        $dAmount = $oOrder->oxorder__oxtotalordersum->value;
        $oRequest->addAmountParameter(
            FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($dAmount),
            $oOrder->oxorder__oxcurrency->value
        );
        $oRequest->setIsFinal(true);

        return $oRequest;
    }

    /**
     * @var $oPaymentList
     * @return FcwlopCreateHostedTokenizationRequest
     */
    public function fcwlopGetCreateHostedTokenizationRequest($oPaymentList)
    {
        $oCreateHostedTokenizationRequest = new FcwlopCreateHostedTokenizationRequest();

        $oUser = Registry::getSession()->getBasket()->getBasketUser();
        $sLocale = FcwlopPaymentHelper::getInstance()->fcwlopGetLocale($oUser);
        $oCreateHostedTokenizationRequest->setLocale($sLocale);

        $oCreateHostedTokenizationRequest->setAskConsumerConsent(false);

        $aActiveCardBrands = [];
        foreach ($oPaymentList as $sOxid => $oMethod) {
            if ($this->fcwlopIsWorldlineCardsProduct($sOxid)) {
                if ($oMethod->oxpayments__oxactive == 1) {
                    $aActiveCardBrands[] = $oMethod->oxpayments__fcwlopextid;
                }
            }
        }
        $oPaymentProductFilters = new PaymentProductFiltersHostedTokenization();
        $oRestrictFilter = new PaymentProductFilterHostedTokenization();
        $oRestrictFilter->setProducts($aActiveCardBrands);
        $oPaymentProductFilters->setRestrictTo($oRestrictFilter);
        
        $oCreateHostedTokenizationRequest->setPaymentProductFilters($oPaymentProductFilters);
        
        return $oCreateHostedTokenizationRequest;
    }

    /**
     * @param User $oUser
     * @return string
     */
    public function fcwlopGetLocale(User $oUser)
    {
        $aAvailableLanguages = Registry::getLang()->getActiveShopLanguageIds();
        $iCurrentLangId = Registry::getConfig()->getActiveShop()->getLanguage();
        $sCurrentLang = $aAvailableLanguages[$iCurrentLangId] ?? 'de';
        $sCurrentCountry = FcwlopOrderHelper::getInstance()->fcwlopGetCountryCode($oUser->oxuser__oxcountryid->value) ?? 'DE';

        return $sCurrentLang.'_'.$sCurrentCountry;
    }

    /**
     * @param Order $oOrder
     * @param array $aPositions
     * @return void
     */
    public function fcwlopProcessRefundPositions(Order $oOrder, array $aPositions)
    {
        foreach ($aPositions as $sOrderArticleOxid => $aOrderArticleDetails) {
            $oOrderArticle = oxNew(OrderArticle::class);

            if (in_array($sOrderArticleOxid, ['oxdelcost', 'oxpaycost', 'oxwrapcost', 'oxgiftcardcost', 'oxvoucherdiscount', 'oxdiscount'])) {
                $oRefundedPriceField = new Field(abs($aOrderArticleDetails['price']));

                if ($sOrderArticleOxid == 'oxdelcost') {
                    $oOrder->oxorder__fcwlopdelcostrefunded = $oRefundedPriceField;
                } elseif ($sOrderArticleOxid == 'oxpaycost') {
                    $oOrder->oxorder__fcwloppaycostrefunded = $oRefundedPriceField;
                } elseif ($sOrderArticleOxid == 'oxwrapcost') {
                    $oOrder->oxorder__fcwlopwrapcostrefunded = $oRefundedPriceField;
                } elseif ($sOrderArticleOxid == 'oxgiftcardcost') {
                    $oOrder->oxorder__fcwlopgiftcardrefunded = $oRefundedPriceField;
                } elseif ($sOrderArticleOxid == 'oxvoucherdiscount') {
                    $oOrder->oxorder__fcwlopvoucherdiscountrefunded = $oRefundedPriceField;
                } elseif ($sOrderArticleOxid == 'oxdiscount') {
                    $oOrder->oxorder__fcwlopdiscountrefunded = $oRefundedPriceField;
                }

                $oOrder->save();

            } else {
                $oOrderArticle->load($sOrderArticleOxid);
                if ($oOrderArticle) {
                    $iNewRefundedAmount = $oOrderArticle->oxorderarticles__fcwlopamountrefunded->value + $aOrderArticleDetails['amount'];
                    $oOrderArticle->oxorderarticles__fcwlopamountrefunded = new Field($iNewRefundedAmount);
                    $oOrderArticle->save();
                }
            }
        }
    }

    public function fcwlopGetApiEndpoint($sMode = '')
    {
        $sMode = empty($sMode) ? $this->fcwlopGetWorldlineMode() : $sMode;
        return $sMode == 'live' ?
            $this->fcwlopGetWorldlineApiLiveEndpoint() :
            $this->fcwlopGetWorldlineApiSandboxEndpoint();
    }

    /**
     * @return void
     */
    public function fcwlopCleanWorldlineSession()
    {
        Registry::getSession()->deleteVariable('fcwlop_needs_redirection');
        Registry::getSession()->deleteVariable('fcwlop_redirect_url');
        Registry::getSession()->deleteVariable('fcwlop_is_redirected');
        Registry::getSession()->deleteVariable('fcwlop_hosted_tokenization_id');
    }

    /**
     * @return array
     */
    public function fcwlopGetActivatedCreditCards()
    {
        $aActivatedCards = [];
        
        foreach (array_keys(FcwlopPaymentMethodTypes::WORLDLINE_CARDS_PRODUCTS) as $sOxid) {
            $oMethod = oxNew(Payment::class);
            $oMethod->load($sOxid);
            if ($oMethod && $oMethod->oxpayments__oxactive->value == 1) {
                $aActivatedCards[] = $sOxid;
            }
        }

        return $aActivatedCards;
    }

    /**
     * @param string $sTransactionId
     * @return int
     * @throws DatabaseConnectionException
     */
    protected function fcwlopGetLatestTransactionStep($sTransactionId)
    {
        $iStep = 0;

        $sQuery = "SELECT MAX(FCWLOP_TXSTEP) as last_step
                        FROM fcwloptransactionlog 
                        WHERE FCWLOP_TXID = :sTransactionId";
        $sResult = DatabaseProvider::getDb()->getOne($sQuery,[':sTransactionId' => $sTransactionId]);
        if (!empty($sResult)) {
            $iStep = (int) $sResult;
        }

        return $iStep;
    }

    /**
     * Fetch Database stored Worldline transactions logs
     *
     * @param string $sTransactionId
     * @param int $iStep
     * @param string $sStatus
     * @return array
     * @throws DatabaseConnectionException
     */
    protected function fcwlopGetWorldlineTransaction($sTransactionId, $iStep = -1, $sStatus = '')
    {
        $sQuery = "SELECT *
                    FROM fcwloptransactionlog 
                    WHERE FCWLOP_TXID = :sTransactionId";
        $aParams = [':sTransactionId' => $sTransactionId];

        if ($iStep >= 0) {
            $sQuery .=  " AND FCWLOP_STEP = :iStep";
            $aParams[':iStep'] = $iStep;
        }

        if (!empty($sStatus)) {
            $sQuery .=  " AND FCWLOP_STATUS = :sStatus";
            $aParams[':sStatus'] = $sStatus;
        }

        $oDb = DatabaseProvider::getDb();
        $oDb->setFetchMode(DatabaseProvider::FETCH_MODE_ASSOC);
        $aResult = $oDb->getRow($sQuery, $aParams);
        if (!empty($sResult)) {
            return [];
        }
        return $aResult;
    }

    /**
     * @return MerchantClient
     */
    protected function fcwlopLoadWorldlineApi($sMode = '')
    {
        if (!$this->oMerchantClient) {
            $sApiKey = $this->fcwlopGetWorldlineApiKey();
            $sApiSecret = $this->fcwlopGetWorldlineApiSecret();

            $sMode = empty($sMode) ? $this->fcwlopGetWorldlineMode() : $sMode;
            $sApiEndpoint = $sMode == 'live' ?
                $this->fcwlopGetWorldlineApiLiveEndpoint() :
                $this->fcwlopGetWorldlineApiSandboxEndpoint();

            $oConnection = new DefaultConnection();
            $oCommunicatorConfiguration = new CommunicatorConfiguration(
                $sApiKey,
                $sApiSecret,
                $sApiEndpoint,
                'OnlinePayments'
            );

            $oCommunicator = new Communicator(
                $oConnection,
                $oCommunicatorConfiguration
            );

            $oClient = new Client($oCommunicator);
            $this->oMerchantClient = $oClient->merchant($this->fcwlopGetWorldlinePspId());
        }

        return $this->oMerchantClient;
    }

    /**
     * @return string
     */
    protected function fcwlopGetWorldlinePspId()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopPspId');
    }

    /**
     * @return string
     */
    protected function fcwlopGetWorldlineApiKey()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopApiKey');
    }

    /**
     * @return string
     */
    protected function fcwlopGetWorldlineApiSecret()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopApiSecret');
    }

    /**
     * @return string
     */
    protected function fcwlopGetWorldlineApiSandboxEndpoint()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopSandboxEndpoint');
    }

    /**
     * @return string
     */
    protected function fcwlopGetWorldlineApiLiveEndpoint()
    {
        return $this->fcwlopGetShopConfVar('sFcwlopLiveEndpoint');
    }

    /**
     * @return string
     */
    protected function fcwlopGetShopCountryCode()
    {
        return 'DE'; // FIXME
    }

    /**
     * @return object
     */
    protected function fcwlopGetShopCurrency()
    {
        return Registry::getConfig()->getActShopCurrencyObject();
    }

    /**
     * Returns DependencyInjection container
     *
     * @return ContainerInterface
     */
    protected function fcwlopGetContainer()
    {
        if ($this->oContainer === null) {
            $this->oContainer = ContainerFactory::getInstance()->getContainer();
        }
        return $this->oContainer;
    }
}