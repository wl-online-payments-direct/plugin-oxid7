<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment\Methods;

use FC\FCWLOP\Application\Model\Request\FcwlopCreateHostedCheckoutRequest;
use FC\FCWLOP\Application\Model\TransactionHandler\FcwlopPaymentTransactionHandler;
use OxidEsales\Eshop\Application\Model\Order;

class FcwlopWorldlineGenericMethod
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = null;

    /**
     * Method code used for API request
     *
     * @var int
     */
    protected $iWorldlinePaymentCode = null;

    /**
     * Determines if the payment methods has to add a redirect url to the request
     *
     * @var bool
     */
    protected $blIsRedirectUrlNeeded = true;

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = false;

    /**
     * Determines if the payment method is hidden at first when payment list is displayed
     *
     * @var bool
     */
    protected $blIsMethodHiddenInitially = false;

    /**
     * Return Oxid payment id
     *
     * @return string
     */
    public function getOxidPaymentId()
    {
        return $this->sOxidPaymentId;
    }

    /**
     * @param string|null $sOxidPaymentId
     */
    public function setOxidPaymentId($sOxidPaymentId)
    {
        $this->sOxidPaymentId = $sOxidPaymentId;
    }

    /**
     * Return Worldline payment code
     *
     * @return int
     */
    public function getWorldlinePaymentCode()
    {
        return $this->iWorldlinePaymentCode;
    }

    /**
     * @param int|null $iWorldlinePaymentCode
     */
    public function setWorldlinePaymentCode($iWorldlinePaymentCode)
    {
        $this->iWorldlinePaymentCode = $iWorldlinePaymentCode;
    }

    /**
     * Returns if the payment methods needs to add the redirect url
     *
     * @return bool
     */
    public function isRedirectUrlNeeded()
    {
        return $this->blIsRedirectUrlNeeded;
    }

    /**
     * @param bool $blIsRedirectUrlNeeded
     */
    public function setIsRedirectUrlNeeded($blIsRedirectUrlNeeded)
    {
        $this->blIsRedirectUrlNeeded = $blIsRedirectUrlNeeded;
    }

    /**
     * Returns custom frontend template or false if not existing
     *
     * @return bool|string
     */
    public function getCustomFrontendTemplate()
    {
        if (!empty($this->sCustomFrontendTemplate)) {
            return "@fcwlop/customFrontendTemplate/".$this->sCustomFrontendTemplate.".html.twig";
        }
        return false;
    }

    /**
     * @param bool|string $sCustomFrontendTemplate
     */
    public function setCustomFrontendTemplate($sCustomFrontendTemplate)
    {
        $this->sCustomFrontendTemplate = $sCustomFrontendTemplate;
    }

    /**
     * Returns if the payment methods has to be hidden initially
     *
     * @return bool
     */
    public function isMethodHiddenInitially()
    {
        return $this->blIsMethodHiddenInitially;
    }

    /**
     * @param bool $blIsMethodHiddenInitially
     */
    public function setIsMethodHiddenInitially($blIsMethodHiddenInitially)
    {
        $this->blIsMethodHiddenInitially = $blIsMethodHiddenInitially;
    }

    /**
     * @return FcwlopCreateHostedCheckoutRequest
     */
    public function getCreateHostedCheckoutRequest(Order $oOrder)
    {
        return new FcwlopCreateHostedCheckoutRequest($oOrder);
    }

    /**
     * Return the transaction status handler
     *
     * @return FcwlopPaymentTransactionHandler
     */
    public function fcwlopGetTransactionHandler()
    {
        return new FcwlopPaymentTransactionHandler();
    }
}
