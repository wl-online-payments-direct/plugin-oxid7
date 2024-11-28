<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Helper;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;

class FcwlopDatabaseHelper
{
    /**
     * @var FcwlopDatabaseHelper
     */
    protected static $oInstance = null;

    /**
     * Lists of all custom-groups to add the payment-methods to
     *
     * @var array
     */
    public static $aGroupsToAdd = array(
        'oxidadmin',
        'oxidcustomer',
        'oxiddealer',
        'oxidforeigncustomer',
        'oxidgoodcust',
        'oxidmiddlecust',
        'oxidnewcustomer',
        'oxidnewsletter',
        'oxidnotyetordered',
        'oxidpowershopper',
        'oxidpricea',
        'oxidpriceb',
        'oxidpricec',
        'oxidsmallcust',
    );

    /**
     * Create singleton instance of database helper
     *
     * @return FcwlopDatabaseHelper
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Add a database table.
     *
     * @param string $sTableName table to add
     * @param string $sQuery     sql-query to add table
     *
     * @return boolean true or false
     */
    public static function addTableIfNotExists($sTableName, $sQuery)
    {
        $aTables = DatabaseProvider::getDb()->getAll("SHOW TABLES LIKE ?", array($sTableName));
        if (!$aTables || count($aTables) == 0) {
            DatabaseProvider::getDb()->Execute($sQuery);
            return true;
        }
        return false;
    }

    /**
     * Add a column to a database table.
     *
     * @param string $sTableName            table name
     * @param string $sColumnName           column name
     * @param string $sQuery                sql-query to add column to table
     * @param array  $aNewColumnDataQueries  array of queries to execute when column was added
     *
     * @return boolean true or false
     */
    public static function addColumnIfNotExists($sTableName, $sColumnName, $sQuery, $aNewColumnDataQueries = array())
    {
        $aColumns = DatabaseProvider::getDb()->getAll("SHOW COLUMNS FROM {$sTableName} LIKE ?", array($sColumnName));
        if (empty($aColumns)) {
            try {
                DatabaseProvider::getDb()->Execute($sQuery);
                foreach ($aNewColumnDataQueries as $sQuery) {
                    DatabaseProvider::getDb()->Execute($sQuery);
                }
                return true;
            } catch (\Exception $e) {
                // do nothing as of yet
            }
        }
        return false;
    }

    /**
     * Insert a database row to an existing table.
     *
     * @param string $sTableName database table name
     * @param array  $aKeyValue  keys of rows to add for existance check
     * @param string $sQuery     sql-query to insert data
     * @param array  $aParams    sql-query insert parameters
     *
     * @return boolean true or false
     */
    public static function insertRowIfNotExists($sTableName, $aKeyValue, $sQuery, $aParams = [])
    {
        $sCheckQuery = "SELECT * FROM {$sTableName} WHERE 1";
        foreach ($aKeyValue as $key => $value) {
            $sCheckQuery .= " AND $key = '$value'";
        }

        if (!DatabaseProvider::getDb()->getOne($sCheckQuery)) { // row not existing yet?
            DatabaseProvider::getDb()->Execute($sQuery, $aParams);
            return true;
        }
        return false;
    }

    /**
     * Deactivates Worldline payment methods on module deactivation.
     *
     * @return void
     */
    public static function deactivatePaymentMethods()
    {
        $oRequest = Registry::getRequest();
        if ($oRequest->getRequestParameter('cl') == 'module_config' && $oRequest->getRequestParameter('fnc') == 'save') {
            return; // Don't deactivate payment methods when changing config in admin ( this triggers module deactivation )
        }

        DatabaseProvider::getDb()->Execute("UPDATE oxpayments SET oxactive = 0 WHERE FCWLOPISWORLDLINE = 1");
    }

    /**
     * Returns parameter-string for prepared mysql statement
     *
     * @param array $aValues
     * @return string
     */
    public static function getPreparedInStatement($aValues)
    {
        $sReturn = '';
        foreach ($aValues as $sValue) {
            $sReturn .= '?,';
        }
        return '('.rtrim($sReturn, ',').')';
    }
}
