<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Request;

use Exception;
use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use OnlinePayments\Sdk\Domain\Address;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CompanyInformation;
use OnlinePayments\Sdk\Domain\ContactDetails;
use OnlinePayments\Sdk\Domain\Customer;
use OnlinePayments\Sdk\Domain\Discount;
use OnlinePayments\Sdk\Domain\LineItem;
use OnlinePayments\Sdk\Domain\LineItemInvoiceData;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\OrderLineDetails;
use OnlinePayments\Sdk\Domain\OrderReferences;
use OnlinePayments\Sdk\Domain\Shipping;
use OnlinePayments\Sdk\Domain\ShippingMethod;
use OnlinePayments\Sdk\Domain\ShoppingCart;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Application\Model\User as CoreUser;
use OxidEsales\Eshop\Core\Registry;

abstract class FcwlopBaseRequest
{
    /**
     * @var string
     */
    protected $sRequestType = '';

    /**
     * Array or request parameters
     *
     * @var array
     */
    protected $aParameters = [];

    /**
     * Execute Request to Worldline API and return Response
     *
     * @return mixed
     * @throws Exception
     */
    public abstract function execute();

    /**
     * Add parameter to request
     *
     * @param string $sKey
     * @param string|array $mValue
     * @return void
     */
    public function addParameter($sKey, $mValue)
    {
        $this->aParameters[$sKey] = $mValue;
    }

    /**
     * Returns filled API Order parameter object
     *
     * @param CoreOrder $oCoreOrder
     * @return Order
     */
    public function buildApiOrderParameter(CoreOrder $oCoreOrder)
    {
        $oOrder = new Order();
        $oCoreUser = $oCoreOrder->getUser();

        $oAmount = $this->buildApiAmount($oCoreOrder);
        $oOrder->setAmountOfMoney($oAmount);

        $oCustomer = $this->buildApiCustomer($oCoreOrder, $oCoreUser);
        $oOrder->setCustomer($oCustomer);

        $oShipping = new Shipping();

        $oShippingAddress = $this->buildApiShippingAddress($oCoreOrder);
        if(empty($oShippingAddress->getCity())) {
            $oShippingAddress = $this->buildApiBillingAddress($oCoreOrder);
        }
        $oShipping->setAddress($oShippingAddress);
        $aDeliveryCostDetails = $this->buildApiDeliveryCostDetails($oCoreOrder);
        $oShipping->setShippingCost($aDeliveryCostDetails['cost']);
        $oShipping->setShippingCostTax($aDeliveryCostDetails['costVat']);
        $oShipping->setMethod($aDeliveryCostDetails['method']);
        $oOrder->setShipping($oShipping);

        if ($oCoreOrder->oxorder__oxdiscount->value > 0) {
            $oDiscount = $oOrder->getDiscount() ?? new Discount();
            $oDiscount->setAmount(FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oCoreOrder->oxorder__oxdiscount->value));
            $oOrder->setDiscount($oDiscount);
        }


        $oBasket = $oCoreOrder->getBasket() ?? Registry::getSession()->getBasket();
        $oVoucherDiscount = $oBasket->getVoucherDiscount();
        if ($oVoucherDiscount->getBruttoPrice() > 0) {
            $oDiscount = $oOrder->getDiscount() ?? new Discount();
            $oDiscount->setAmount(FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oVoucherDiscount->getBruttoPrice()));
            $oOrder->setDiscount($oDiscount);
        }

        $oReferences = new OrderReferences();
        $oReferences->setMerchantReference($oCoreOrder->oxorder__oxordernr->value);
        $oOrder->setReferences($oReferences);

        $oOrder->setShoppingCart($this->buildApiShoppingCart($oCoreOrder));

        return $oOrder;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->aParameters;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $sRet = json_encode($this->toArray());
        return $sRet ? $sRet : '';
    }


    /**
     * Returns filled API CustomerObject
     *
     * @param CoreOrder $oCoreOrder
     * @param CoreUser $oCoreUser
     * @return Customer
     */
    protected function buildApiCustomer(CoreOrder $oCoreOrder, CoreUser $oCoreUser)
    {
        $oCustomer = new Customer();

        $oCustomer->setContactDetails($this->buildApiContactDetails($oCoreUser));
        $oCustomer->setBillingAddress($this->buildApiBillingAddress($oCoreOrder));
        if (!empty($oCoreOrder->oxorder__oxbillcompany->value)) {
            $oCompanyInfo = new CompanyInformation();
            $oCompanyInfo->setName(trim($oCoreOrder->oxorder__oxbillcompany->value));
            $oCustomer->setCompanyInformation($oCompanyInfo);
        }
        $oCustomer->setMerchantCustomerId($oCoreUser->oxuser__oxid->value);
        if (!empty($oCoreUser->oxuser__oxustid->value)) {
            $oCustomer->setFiscalNumber(trim($oCoreUser->oxuser__oxustid->value));
        }

        $oCustomer->setLocale(FcwlopOrderHelper::getInstance()->fcwlopGetLocale($oCoreOrder));

        return $oCustomer;
    }

    /**
     * Returns filled API ContactDetails object for customer
     *
     * @param CoreUser $oCoreUser
     * @return ContactDetails
     */
    protected function buildApiContactDetails(CoreUser $oCoreUser)
    {
        $oContactDetails = new ContactDetails();
        $oContactDetails->setEmailAddress($this->getCustomerEmail($oCoreUser));
        $oContactDetails->setPhoneNumber($this->getCustomerPhone($oCoreUser));
        return $oContactDetails;
    }

    /**
     * Returns filled API Address object for Billing
     *
     * @param CoreOrder $oOrder
     * @return Address
     */
    protected function buildApiBillingAddress(CoreOrder $oOrder)
    {
        $aBillingAddressDetails = [
            'street' => trim($oOrder->oxorder__oxbillstreet->value),
            'housenr' => trim($oOrder->oxorder__oxbillstreetnr->value),
            'additional' => trim($oOrder->oxorder__oxbilladdinfo->value),
            'zip' => $oOrder->oxorder__oxbillzip->value,
            'city' => $oOrder->oxorder__oxbillcity->value,
            'countryCode' => FcwlopOrderHelper::getInstance()->fcwlopGetCountryCode($oOrder->oxorder__oxbillcountryid->value)
        ];
        return $this->fillApiAddress($aBillingAddressDetails);
    }

    /**
     * Returns filled API Address object for Shipping
     *
     * @param CoreOrder $oOrder
     * @return Address
     */
    protected function buildApiShippingAddress(CoreOrder $oOrder)
    {
        $aShippingAddressDetails = [
            'street' => trim($oOrder->oxorder__oxdelstreet->value),
            'housenr' => trim($oOrder->oxorder__oxdelstreetnr->value),
            'additional' => trim($oOrder->oxorder__oxdeladdinfo->value),
            'zip' => $oOrder->oxorder__oxdelzip->value,
            'city' => $oOrder->oxorder__oxdelcity->value,
            'countryCode' => FcwlopOrderHelper::getInstance()->fcwlopGetCountryCode($oOrder->oxorder__oxdelcountryid->value)
        ];
        return $this->fillApiAddress($aShippingAddressDetails);
    }

    /**
     * Returns filled order delivery costs details
     *
     * @param CoreOrder $oCoreOrder
     * @return array
     */
    protected function buildApiDeliveryCostDetails(CoreOrder $oCoreOrder)
    {
        $oShippingMethod = new ShippingMethod();
        $oShippingMethod->setName($oCoreOrder->getDelSet()->oxdeliveryset__oxtitle->value);

        return [
            'cost' => FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oCoreOrder->oxorder__oxdelcost->value),
            'costVat' => 0,
            'method' => $oShippingMethod
        ];
    }

    /**
     * Returns filled API ShoppingCart object
     *
     * @param CoreOrder $oCoreOrder
     * @return ShoppingCart
     */
    protected function buildApiShoppingCart(CoreOrder $oCoreOrder)
    {
        $oShoppingCart = new ShoppingCart();
        $oShoppingCart->setItems($this->buildApiItems($oCoreOrder));

        return $oShoppingCart;
    }

    /**
     * Returns filled array of API LineItem objects
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function buildApiItems(CoreOrder $oOrder)
    {
        $sOrderCurrency = $oOrder->oxorder__oxcurrency->value;

        $aItems = [];
        $oOrderArticles = $oOrder->getOrderArticles();

        /** @var OrderArticle $oOrderArticle */
        foreach ($oOrderArticles as $oOrderArticle) {
            $oLineItem = new LineItem();

            $oAmountOfMoney = new AmountOfMoney();
            $iUnitPrice = FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oOrderArticle->oxorderarticles__oxbprice->value);
            $iQuantity = $oOrderArticle->oxorderarticles__oxamount->value;
            $oAmountOfMoney->setAmount($iUnitPrice * $iQuantity);
            $oAmountOfMoney->setCurrencyCode($sOrderCurrency);
            $oLineItem->setAmountOfMoney($oAmountOfMoney);

            $oInvoiceData = new LineItemInvoiceData();
            $oInvoiceData->setDescription($oOrderArticle->oxorderarticles__oxshortdesc->value);
            $oLineItem->setInvoiceData($oInvoiceData);

            $oOrderLineDetails = new OrderLineDetails();
            $oOrderLineDetails->setProductCode($oOrderArticle->oxorderarticles__oxartnum->value);
            $oOrderLineDetails->setProductName($oOrderArticle->oxorderarticles__oxtitle->value);
            $oOrderLineDetails->setProductPrice($iUnitPrice);
            $oOrderLineDetails->setQuantity($iQuantity);
            $oOrderLineDetails->setTaxAmount($oOrderArticle->oxorderarticles__vatprice->value);
            $oLineItem->setOrderLineDetails($oOrderLineDetails);

            $aItems[] = $oLineItem;
        }

        $oLang = Registry::getLang();
        if ($oOrder->oxorder__oxwrapcost->value > 0) {
            $oWrapLineItem = new LineItem();

            $oAmountOfMoney = new AmountOfMoney();
            $iUnitPrice = FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oOrder->oxorder__oxwrapcost->value);
            $iQuantity = 1;
            $oAmountOfMoney->setAmount($iUnitPrice * $iQuantity);
            $oAmountOfMoney->setCurrencyCode($sOrderCurrency);
            $oWrapLineItem->setAmountOfMoney($oAmountOfMoney);

            $oInvoiceData = new LineItemInvoiceData();
            $oInvoiceData->setDescription($oLang->translateString('FCWLOP_WRAPPING'));
            $oWrapLineItem->setInvoiceData($oInvoiceData);

            $oOrderLineDetails = new OrderLineDetails();
            $oOrderLineDetails->setProductCode('wrapping');
            $oOrderLineDetails->setProductName($oLang->translateString('FCWLOP_WRAPPING'));
            $oOrderLineDetails->setProductPrice($iUnitPrice);
            $oOrderLineDetails->setQuantity($iQuantity);
            $oOrderLineDetails->setTaxAmount($oOrderArticle->oxorder__oxwrapvat->value);
            $oWrapLineItem->setOrderLineDetails($oOrderLineDetails);

            $aItems[] = $oWrapLineItem;
        }

        return $aItems;
    }

    /**
     * Fills an API Address object with parameter address details
     *
     * @param array $aAdressData
     * @return Address
     */
    protected function fillApiAddress($aAdressData)
    {
        $oAddress = new Address();
        $oAddress->setStreet($aAdressData['street'] ?? '');
        $oAddress->setHouseNumber($aAdressData['housenr'] ?? '');
        $oAddress->setAdditionalInfo($aAdressData['additional'] ?? '');
        $oAddress->setZip($aAdressData['zip'] ?? '');
        $oAddress->setCity($aAdressData['city'] ?? '');
        $oAddress->setCountryCode($aAdressData['countryCode'] ?? '');

        return $oAddress;
    }

    /**
     * @param CoreUser $oCoreUser
     * @return string
     */
    protected function getCustomerEmail(CoreUser $oCoreUser)
    {
        return $oCoreUser->oxuser__oxusername->value;
    }

    /**
     * @param CoreUser $oCoreUser
     * @return string
     */
    protected function getCustomerPhone(CoreUser $oCoreUser)
    {
        return $oCoreUser->oxuser__oxfon->value;
    }

    /**
     * Return metadata parameters
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function buildMetadataParameters(CoreOrder $oOrder)
    {
        return [
            'order_id' => $oOrder->getId(),
            'store_id' => $oOrder->getShopId(),
        ];
    }

    /**
     * @param CoreOrder $oCoreOrder
     * @return AmountOfMoney
     */
    protected function buildApiAmount(CoreOrder $oCoreOrder)
    {
        $oAmount = new AmountOfMoney();
        $iAmountInCent = FcwlopOrderHelper::getInstance()->fcwlopGetPriceInCent($oCoreOrder->oxorder__oxtotalordersum->value);
        $oAmount->setAmount($iAmountInCent);
        $oAmount->setCurrencyCode($oCoreOrder->oxorder__oxcurrency->value);

        return $oAmount;
    }
}
