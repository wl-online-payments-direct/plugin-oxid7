<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model;

use FC\FCWLOP\Application\Helper\FcwlopDatabaseHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\EshopCommunity\Core\Database\Adapter\DatabaseInterface;

class FcwlopCronjob
{
    /**
     * @var FcwlopCronjob
     */
    protected static $oInstance = null;

    /**
     * Table name
     *
     * @var string
     */
    public static $sTableName = "fcwlopcronjob";

    /**
     * Create singleton instance of cronjob resource model
     *
     * @return FcwlopCronjob
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Return create query for module installation
     *
     * @return string
     */
    public static function getTableCreateQuery()
    {
        return "CREATE TABLE `".self::$sTableName."` (
            `OXID` CHAR(32) NOT NULL COLLATE 'latin1_general_ci',
            `MINUTE_INTERVAL` INT(11) NOT NULL,
            `LAST_RUN` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`OXID`) USING BTREE
        ) COLLATE='utf8_general_ci' ENGINE=InnoDB";
    }

    /**
     * Adds new cronjob to the table
     *
     * @param  string $sCronjobId
     * @param  int    $iDefaultMinuteInterval
     * @return void
     */
    public function addNewCronjob($sCronjobId, $iDefaultMinuteInterval)
    {
        $oDb = FcwlopDatabaseHelper::getPdoDb();

        $sQuery = "INSERT INTO `".self::$sTableName."` (OXID, MINUTE_INTERVAL, LAST_RUN) VALUES(:sOxid, :iMinuteinterval, '0000-00-00 00:00:00');";

        $oDb->executeStatement($sQuery, [
            'sOxid' => $sCronjobId,
            'iMinuteinterval' => $iDefaultMinuteInterval,
        ]);
    }

    /**
     * Check if cronjob already exists
     *
     * @param string $sCronjobId
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function isCronjobAlreadyExisting($sCronjobId)
    {
        $oDb = FcwlopDatabaseHelper::getPdoDb();

        $sQuery = "SELECT OXID FROM `".self::$sTableName."` WHERE OXID = :sOxid;";

        $sOxid = $oDb->fetchOne($sQuery, [
            'sOxid' => $sCronjobId
        ]);

        return $sOxid !== false;
    }

    /**
     * Marks given cronjob id as finished
     *
     * @param  string $sCronjobId
     * @return void
     */
    public function markCronjobAsFinished($sCronjobId)
    {
        $oDb = FcwlopDatabaseHelper::getPdoDb();
        $oDb->executeStatement("UPDATE `".self::$sTableName."` SET LAST_RUN = NOW() WHERE OXID = :sOxid;", [
            'sOxid' => $sCronjobId
        ]);
    }

    /**
     * Return cronjob data for given cronjobId
     *
     * @param  string $sCronjobId
     * @return array
     */
    public function getCronjobData($sCronjobId)
    {
        $oDb = FcwlopDatabaseHelper::getPdoDb();

        $sQuery = "SELECT * FROM `".self::$sTableName."` WHERE OXID = :sOxid;";

        $aRow = $oDb->fetchAssoc($sQuery, [
            'sOxid' => $sCronjobId
        ]);

        return $aRow;
    }
}