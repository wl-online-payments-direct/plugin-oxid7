<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopOrderHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;

class FcwlopTransactionLog extends BaseModel
{
    /**
     * Object core table name
     *
     * @var string
     */
    public static $sTableName = "fcwloptransactionlog";

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = 'fcwloptransactionlog';

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->init('fcwloptransactionlog');
    }

    /**
     * @param array $aTransaction
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function logTransaction($aTransaction)
    {
        $oDb = DatabaseProvider::getDb();
        $sSavedResponse = $this->encodeData($aTransaction);

        $sTxTime = isset($aTransaction['created'])
            ? (new \DateTimeImmutable($aTransaction['created']))->format('Y-m-d H:i:s')
            : '';
        $sMerchantId = $aTransaction['merchantId'] ?? '';
        $sType = $aTransaction['type'] ?? '';

        if ($aTransaction['refund']) {
            $dPrice = $aTransaction['refund']['refundOutput']['amountOfMoney']['amount'] ?? 0;
            $sCurrency = $aTransaction['refund']['refundOutput']['amountOfMoney']['currencyCode'] ?? '';
            $iOrderNr = $aTransaction['refund']['refundOutput']['operationReferences']['merchantReference'] ?? 0;
            $sMethod = $aTransaction['refund']['refundOutput']['paymentMethod'] ?? '';
            $sStatus = $aTransaction['refund']['status'] ?? '';
            $iStatusCode = $aTransaction['refund']['statusOutput']['statusCode'] ?? 0;
            $aTxId = isset($aTransaction['refund']['id']) ? explode('_', $aTransaction['refund']['id']) : [];
        } else {
            $dPrice = $aTransaction['payment']['paymentOutput']['amountOfMoney']['amount'] ?? 0;
            $sCurrency = $aTransaction['payment']['paymentOutput']['amountOfMoney']['currencyCode'] ?? '';
            $iOrderNr = $aTransaction['payment']['paymentOutput']['references']['merchantReference'] ?? 0;
            $sMethod = $aTransaction['payment']['paymentOutput']['paymentMethod'] ?? '';
            $sStatus = $aTransaction['payment']['status'] ?? '';
            $iStatusCode = $aTransaction['payment']['statusOutput']['statusCode'] ?? 0;
            $aTxId = isset($aTransaction['payment']['id']) ? explode('_', $aTransaction['payment']['id']) : [];
        }
        $iTxId = $aTxId[0] ?? 0;
        $iTxStep = $aTxId[1] ?? 0;

        $oOrder = null;
        if($iOrderNr > 0) {
            $oOrder = FcwlopOrderHelper::getInstance()->fcwlopLoadOrderByOrderNumber($iOrderNr);
        }
        $sMode = $oOrder ? $oOrder->oxorder__fcwlopmode->value : '';

        $sQuery = " INSERT INTO `".self::$sTableName."` (
                        FCWLOP_TIME, 
                        FCWLOP_ORDERNR, 
                        FCWLOP_TXID, 
                        FCWLOP_TXSTEP, 
                        FCWLOP_TYPE, 
                        FCWLOP_MODE, 
                        FCWLOP_METHOD, 
                        FCWLOP_PRICE, 
                        FCWLOP_CURRENCY,
                        FCWLOP_STATUS, 
                        FCWLOP_STATUS_CODE,
                        FCWLOP_MERCHANTID,
                        FCWLOP_RESPONSEBODY
                    ) VALUES (
                        :txtime, :ordernr, :txid, :txstep, :type, :mode, :method, :price, :currency, :status, :statuscode, :merchantid, :response 
                    )";
        $oDb->Execute($sQuery, [
            ':txtime' => $sTxTime,
            ':ordernr' => $iOrderNr,
            ':txid' => $iTxId,
            ':txstep' => $iTxStep,
            ':type' => $sType,
            ':mode' => $sMode,
            ':method' => $sMethod,
            ':price' => $dPrice,
            ':currency' => $sCurrency,
            ':status' => $sStatus,
            ':statuscode' => $iStatusCode,
            ':merchantid' => $sMerchantId,
            ':response' => $sSavedResponse
        ]);
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
            `FCWLOP_TIME` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
            `FCWLOP_ORDERNR` INT(11) DEFAULT '0',
            `FCWLOP_TXID` VARCHAR(32) NOT NULL DEFAULT '0',
            `FCWLOP_TXSTEP` INT(11) NOT NULL DEFAULT '0',
            `FCWLOP_TYPE` VARCHAR(32) NOT NULL DEFAULT '',
            `FCWLOP_MODE` VARCHAR(8) NOT NULL DEFAULT '',
            `FCWLOP_METHOD` VARCHAR(32) NOT NULL DEFAULT '',
            `FCWLOP_PRICE` DOUBLE NOT NULL DEFAULT '0',
            `FCWLOP_CURRENCY` VARCHAR(32) NOT NULL DEFAULT '',
            `FCWLOP_STATUS` VARCHAR(32) NOT NULL DEFAULT '',
            `FCWLOP_STATUS_CODE` INT(11) NOT NULL DEFAULT 0,
            `FCWLOP_MERCHANTID` VARCHAR(32) NOT NULL DEFAULT '',
            `FCWLOP_RESPONSEBODY` TEXT NOT NULL,
            `TIMESTAMP` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (OXID)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT COLLATE='utf8_general_ci';";
    }

    public function getTransactionArray()
    {
        $aResponse = $this->fcwloptransactionlog__fcwlop_responsebody->value ?
            json_decode($this->fcwloptransactionlog__fcwlop_responsebody->value, true) :
            [];

        array_walk($aResponse, function(&$sItem) {
            if(is_array($sItem)) {
                $sItem = json_encode($sItem);
            }
        });

        return [
            'request' => [
                'oxid' => $this->fcwloptransactionlog__oxid->value,
                'txtime' => $this->fcwloptransactionlog__fcwlop_time->value,
                'order_Nr' => $this->fcwloptransactionlog__fcwlop_ordernr->value,
                'txid' => $this->fcwloptransactionlog__fcwlop_txid->value,
                'sequence_nr' => $this->fcwloptransactionlog__fcwlop_txstep->value,
                'type' => $this->fcwloptransactionlog__fcwlop_type->value,
                'mode' => $this->fcwloptransactionlog__fcwlop_mode->value,
                'method' => $this->fcwloptransactionlog__fcwlop_method->value,
                'price' => $this->fcwloptransactionlog__fcwlop_price->value,
                'currency' => $this->fcwloptransactionlog__fcwlop_currency->value,
                'status' => $this->fcwloptransactionlog__fcwlop_status->value,
                'status_code' => $this->fcwloptransactionlog__fcwlop_status_code->value,
                'merchant_id' => $this->fcwloptransactionlog__fcwlop_merchantid->value,
                'timestamp' => $this->fcwloptransactionlog__timestamp->value,
            ],
            'response' => $aResponse
        ];
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
