<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Registry;

class FcwlopRequestLog extends BaseModel
{
    public $fcwloprequestlog__request;
    public $fcwloprequestlog__response;

    /**
     * Object core table name
     *
     * @var string
     */
    public static $sTableName = "fcwloprequestlog";

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = 'fcwloprequestlog';

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->init('fcwloprequestlog');
    }

    /**
     * @param array $aRequest
     * @param \Exception $oEx
     * @param int $iOrderNr
     * @param string $sRequestType
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function logErrorRequest($aRequest, \Exception $oEx, $iOrderNr, $sRequestType)
    {
        $aResponse = [
            'code' => $oEx->getCode(),
            'message' => $oEx->getMessage()
        ];
        $this->logRequest($aRequest, (object)$aResponse, $iOrderNr, $sRequestType, $oEx->getCode());
    }

    /**
     * Parse data and write the request and response in one DB entry
     *
     * @param array $aRequest
     * @param object $oResponse
     * @param int $iOrderNr
     * @param string $sRequestType
     * @param string $sResponseStatus
     * @return void
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
     */
    public function logRequest($aRequest, $oResponse, $sOrderId, $sRequestType, $sResponseStatus)
    {
        $oDb = DatabaseProvider::getDb();

        $sStoreId = Registry::getConfig()->getActiveShop()->getShopId();
        $iOrderNr = 0;
        $sMode = FcwlopPaymentHelper::getInstance()->fcwlopGetWorldlineMode();
        if ($sOrderId) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            $iOrderNr = $oOrder->oxorder__oxordernr->value ?? '';
            $sStoreId = $oOrder->getShopId();
            $sMode = !empty($oOrder->oxorder__fcwlopmode->value) ? $oOrder->oxorder__fcwlopmode->value : $sMode;
        }

        $sSavedRequest = $this->encodeData($aRequest);
        $sSavedResponse = $this->encodeData($oResponse);

        $sQuery = " INSERT INTO `".self::$sTableName."` (
                        ORDERID, STOREID, ORDERNR, MODE, REQUESTTYPE, RESPONSESTATUS, REQUEST, RESPONSE
                    ) VALUES (
                        :orderid, :storeid, :ordernr, :mode, :requesttype, :responsestatus, :savedrequest, :savedresponse
                    )";
        $oDb->Execute($sQuery, [
            ':orderid' => $sOrderId,
            ':storeid' => $sStoreId,
            ':ordernr' => $iOrderNr,
            ':mode' => $sMode,
            ':requesttype' => $sRequestType,
            ':responsestatus' => $sResponseStatus,
            ':savedrequest' => $sSavedRequest,
            ':savedresponse' => $sSavedResponse,
        ]);
    }

    /**
     * Get request as array
     *
     * @return bool|array
     */
    public function getRequestArray(): bool|array
    {
        $aRequestArray = [
            'mode' => $this->fcwloprequestlog__mode->value
        ];

        $aRequestData = $this->decodeData($this->fcwloprequestlog__request->rawValue);
        $aRequestArray = array_merge($aRequestArray, $aRequestData);

        if(isset($aRequestArray['customer'])) {
            $aCustomer = $this->decodeData($aRequestArray['customer']);
            if(!empty($aCustomer['billingAddress'])) {
                $aAddress = $aCustomer['billingAddress'];
                $aRequestArray['billing_street'] = $aAddress['street'];
                $aRequestArray['billing_house_number'] = $aAddress['houseNumber'];
                $aRequestArray['billing_additional_info'] = $aAddress['additionalInfo'];
                $aRequestArray['billing_zip'] = $aAddress['zip'];
                $aRequestArray['billing_city'] = $aAddress['city'];
                $aRequestArray['billing_country'] = $aAddress['countryCode'];
            }
            if(!empty($aCustomer['contactDetails'])) {
                $aCustomerDetails = $aCustomer['contactDetails'];
                $aRequestArray['email'] = $aCustomerDetails['emailAddress'];
                $aRequestArray['phone'] = $aCustomerDetails['phoneNumber'];
            }
            $aRequestArray['locale'] = $aCustomer['locale'];
            $aRequestArray['merchant_customer_id'] = $aCustomer['merchantCustomerId'];

            unset($aRequestArray['customer']);
        }

        if(!empty($aRequestArray['amountOfMoney'])) {
            $aAmountOfMoney = $this->decodeData($aRequestArray['amountOfMoney']);
            $aRequestArray['amount'] = $aAmountOfMoney['amount'];
            $aRequestArray['currency'] = $aAmountOfMoney['currencyCode'];

            unset($aRequestArray['amountOfMoney']);
        }

        if(!empty($aRequestArray['references'])) {
            $aReferences = $this->decodeData($aRequestArray['references']);
            $aRequestArray['order_nr'] = $aReferences['merchantReference'];

            unset($aRequestArray['references']);
        }

        if(!empty($aRequestArray['shipping'])) {
            $aShipping = $this->decodeData($aRequestArray['shipping']);
            if(!empty($aShipping['address'])) {
                $aAddress = $aShipping['address'];
                $aRequestArray['shipping_street'] = $aAddress['street'];
                $aRequestArray['shipping_house_number'] = $aAddress['houseNumber'];
                $aRequestArray['shipping_additional_info'] = $aAddress['additionalInfo'];
                $aRequestArray['shipping_zip'] = $aAddress['zip'];
                $aRequestArray['shipping_city'] = $aAddress['city'];
                $aRequestArray['shipping_country'] = $aAddress['countryCode'];
            }
            if(!empty($aShipping['method'])) {
                $aRequestArray['shipping_method'] = $aShipping['method']['name'];
            }
            $aRequestArray['shipping_cost'] = $aShipping['shippingCost'];
            $aRequestArray['shipping_cost_tax'] = $aShipping['shippingCostTax'];

            unset($aRequestArray['shipping']);
        }

        if(!empty($aRequestArray['shoppingCart'])) {
            $aShoppingCart = $this->decodeData($aRequestArray['shoppingCart']);
            foreach ($aShoppingCart['items'] as $k => $item) {
                $i = $k+1;
                $aRequestArray['item_'.$i.'_code'] = $item['orderLineDetails']['productCode'];
                $aRequestArray['item_'.$i.'_name'] = $item['orderLineDetails']['productName'];
                $aRequestArray['item_'.$i.'_price'] = $item['orderLineDetails']['productPrice'];
                $aRequestArray['item_'.$i.'_quantity'] = $item['orderLineDetails']['quantity'];
                $aRequestArray['item_'.$i.'_total_price'] = $item['amountOfMoney']['amount'];
                $aRequestArray['item_'.$i.'_currency'] = $item['amountOfMoney']['currencyCode'];
                $aRequestArray['item_'.$i.'_description'] = $item['invoiceData']['description'];
            }

            unset($aRequestArray['shoppingCart']);
        }

        if(!empty($aRequestArray['discount'])) {
            $aDiscount = $this->decodeData($aRequestArray['discount']);
            $aRequestArray['discount_amount'] = $aDiscount['amount'];

            unset($aRequestArray['discount']);
        }

        ksort($aRequestArray);

        return $aRequestArray;
    }

    /**
     * Get response as array
     *
     * @return bool|array
     */
    public function getResponseArray(): bool|array
    {
        $aReponseArray = $this->decodeData($this->fcwloprequestlog__response->rawValue);
        array_walk($aReponseArray, function(&$mEntry) {
            $mEntry =  is_array($mEntry) ? json_encode($mEntry) : $mEntry;
        });

        return $aReponseArray;
    }

    /**
     * Return create query for module installation
     *
     * @return string
     */
    public static function getTableCreateQuery()
    {
        return "CREATE TABLE `".self::$sTableName."` (
            `OXID` INT(32) NOT NULL AUTO_INCREMENT COLLATE 'latin1_general_ci',
            `TIMESTAMP` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ORDERID` VARCHAR(32) NOT NULL,
            `STOREID` VARCHAR(32) NOT NULL,
            `ORDERNR` INT(11) NOT NULL DEFAULT '0',
            `MODE` VARCHAR(32) NOT NULL,
            `REQUESTTYPE` VARCHAR(32) NOT NULL DEFAULT '',
            `RESPONSESTATUS` VARCHAR(32) NOT NULL DEFAULT '',
            `REQUEST` TEXT NOT NULL,
            `RESPONSE` TEXT NOT NULL,
            PRIMARY KEY (OXID)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT COLLATE='utf8_general_ci';";
    }

    /**
     * Encode data object to a saveable string
     *
     * @param $oData
     * @return string
     */
    protected function encodeData($oData)
    {
        return json_encode($oData);
    }

    /**
     * Decode data array from an encoded string
     *
     * @param string $sData
     * @return array
     */
    protected function decodeData($sData)
    {
        return json_decode($sData, true);
    }
}
