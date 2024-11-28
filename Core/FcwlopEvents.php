<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Core;

use FC\FCWLOP\Application\Helper\FcwlopDatabaseHelper;
use FC\FCWLOP\Application\Model\FcwlopCronjob;
use FC\FCWLOP\Application\Model\FcwlopRequestLog;
use FC\FCWLOP\Application\Model\FcwlopTransactionLog;

/**
 * Activation and deactivation handler
 */
class FcwlopEvents
{
    public static string $sQueryAlterOxorderDelCostCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPDELCOSTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderPayCostCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPPAYCOSTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderWrapCostCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPWRAPCOSTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderGiftcardCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPGIFTCARDCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderVoucherDiscountCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPVOUCHERDISCOUNTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderDiscountCaptured = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPDISCOUNTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderDelCostRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPDELCOSTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderPayCostRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPPAYCOSTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderWrapCostRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPWRAPCOSTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderGiftcardRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPGIFTCARDREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderVoucherDiscountRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPVOUCHERDISCOUNTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderDiscountRefunded = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPDISCOUNTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderShipmentHasBeenMarked = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPSHIPMENTHASBEENMARKED` tinyint(1) UNSIGNED NOT NULL DEFAULT  '0';";
    public static string $sQueryAlterOxorderAuthMode = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPAUTHMODE` VARCHAR(32) CHARSET utf8 COLLATE utf8_general_ci DEFAULT '' NOT NULL;";
    public static string $sQueryAlterOxorderMode = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPMODE` VARCHAR(32) CHARSET utf8 COLLATE utf8_general_ci DEFAULT '' NOT NULL;";
    public static string $sQueryAlterOxorderExternalTransId = "ALTER TABLE `oxorder` ADD COLUMN `FCWLOPEXTERNALTRANSID` VARCHAR(64) CHARSET utf8 COLLATE utf8_general_ci DEFAULT '' NOT NULL;";

    public static string $sQueryAlterOxorderArticlesAmountCaptured = "ALTER TABLE `oxorderarticles` ADD COLUMN `FCWLOPAMOUNTCAPTURED` DOUBLE NOT NULL DEFAULT '0';";
    public static string $sQueryAlterOxorderArticlesAmountRefunded = "ALTER TABLE `oxorderarticles` ADD COLUMN `FCWLOPAMOUNTREFUNDED` DOUBLE NOT NULL DEFAULT '0';";

    public static string $sQueryAlterOxpaymentsIsWorldline = "ALTER TABLE oxpayments ADD COLUMN FCWLOPISWORLDLINE TINYINT(1) DEFAULT '0' NOT NULL;";
    public static string $sQueryAlterOxpaymentsExternalId = "ALTER TABLE oxpayments ADD COLUMN FCWLOPEXTID INT(10) NULL;";
    public static string $sQueryAlterOxpaymentsExternalType = "ALTER TABLE oxpayments ADD COLUMN FCWLOPEXTTYPE VARCHAR(50) NULL;";
    public static string $sQueryAlterOxpaymentsExternalGroupType = "ALTER TABLE oxpayments ADD COLUMN FCWLOPEXTGROUPTYPE VARCHAR(50) NULL;";
    public static string $sQueryAlterOxpaymentsExternalLogo = "ALTER TABLE oxpayments ADD COLUMN FCWLOPEXTLOGO VARCHAR(255) NULL;";

    /**
     * Execute action on activate event.
     *
     * @return void
     */
    public static function onActivate()
    {
        self::addDatabaseStructure();
        self::addData();
        self::regenerateViews();
        self::clearTmp();
    }

    /**
     * Execute action on deactivate event.
     *
     * @return void
     */
    public static function onDeactivate()
    {
        FcwlopDatabaseHelper::deactivatePaymentMethods();
    }

    /**
     * Add database data needed for the Worldline module
     *
     * @return void
     */
    protected static function addData()
    {
    }

    /**
     * Add new tables and add columns to existing tables
     *
     * @return void
     */
    protected static function addDatabaseStructure()
    {
        //CREATE NEW TABLES
        FcwlopDatabaseHelper::addTableIfNotExists(FcwlopCronjob::$sTableName, FcwlopCronjob::getTableCreateQuery());
        FcwlopDatabaseHelper::addTableIfNotExists(FcwlopRequestLog::$sTableName, FcwlopRequestLog::getTableCreateQuery());
        FcwlopDatabaseHelper::addTableIfNotExists(FcwlopTransactionLog::$sTableName, FcwlopTransactionLog::getTableCreateQuery());

        //ADD NEW COLUMNS
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPAUTHMODE', self::$sQueryAlterOxorderAuthMode);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPMODE', self::$sQueryAlterOxorderMode);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPDELCOSTCAPTURED', self::$sQueryAlterOxorderDelCostCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPPAYCOSTCAPTURED', self::$sQueryAlterOxorderPayCostCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPWRAPCOSTCAPTURED', self::$sQueryAlterOxorderWrapCostCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPGIFTCARDCAPTURED', self::$sQueryAlterOxorderGiftcardCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPVOUCHERDISCOUNTCAPTURED', self::$sQueryAlterOxorderVoucherDiscountCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPDISCOUNTCAPTURED', self::$sQueryAlterOxorderDiscountCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPDELCOSTREFUNDED', self::$sQueryAlterOxorderDelCostRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPPAYCOSTREFUNDED', self::$sQueryAlterOxorderPayCostRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPWRAPCOSTREFUNDED', self::$sQueryAlterOxorderWrapCostRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPGIFTCARDREFUNDED', self::$sQueryAlterOxorderGiftcardRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPVOUCHERDISCOUNTREFUNDED', self::$sQueryAlterOxorderVoucherDiscountRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPDISCOUNTREFUNDED', self::$sQueryAlterOxorderDiscountRefunded);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPEXTERNALTRANSID', self::$sQueryAlterOxorderExternalTransId);

        FcwlopDatabaseHelper::addColumnIfNotExists('oxorderarticles', 'FCWLOPAMOUNTCAPTURED', self::$sQueryAlterOxorderArticlesAmountCaptured);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorderarticles', 'FCWLOPAMOUNTREFUNDED', self::$sQueryAlterOxorderArticlesAmountRefunded);

        $aShipmentSentQuery = ["UPDATE `oxorder` SET FCWLOPSHIPMENTHASBEENMARKED = 1 WHERE oxpaymenttype LIKE 'fcwlop%' AND oxsenddate > '1970-01-01 00:00:01';"];
        FcwlopDatabaseHelper::addColumnIfNotExists('oxorder', 'FCWLOPSHIPMENTHASBEENMARKED', self::$sQueryAlterOxorderShipmentHasBeenMarked, $aShipmentSentQuery);

        FcwlopDatabaseHelper::addColumnIfNotExists('oxpayments', 'FCWLOPISWORLDLINE', self::$sQueryAlterOxpaymentsIsWorldline);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxpayments', 'FCWLOPEXTID', self::$sQueryAlterOxpaymentsExternalId);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxpayments', 'FCWLOPEXTTYPE', self::$sQueryAlterOxpaymentsExternalType);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxpayments', 'FCWLOPEXTGROUPTYPE', self::$sQueryAlterOxpaymentsExternalGroupType);
        FcwlopDatabaseHelper::addColumnIfNotExists('oxpayments', 'FCWLOPEXTLOGO', self::$sQueryAlterOxpaymentsExternalLogo);
    }

    /**
     * Regenerates database view-tables.
     *
     * @return void
     */
    protected static function regenerateViews()
    {
        $oShop = oxNew('oxShop');
        $oShop->generateViews();
    }

    /**
     * Clear cache.
     *
     * @return void
     */
    protected static function clearTmp()
    {
        $output = shell_exec(VENDOR_PATH . '/bin/oe-console oe:cache:clear');
    }
}
