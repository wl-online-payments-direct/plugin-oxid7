<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Cronjob;

use FC\FCWLOP\Application\Model\FcwlopCronjob;

class FcwlopCronBase
{
    /**
     * Id of current cronjob
     *
     * @var string
     */
    protected $sCronjobId = null;

    /**
     * Default cronjob interval in minutes
     *
     * @var int
     */
    protected $iDefaultMinuteInterval = null;

    /**
     * Logfile name
     *
     * @var string
     */
    protected $sLogFileName = 'WorldlineCronjobErrors.log';

    /**
     * Data from cronjob table
     *
     * @var array
     */
    protected $aDbData = null;

    /**
     * ShopId used for cronjob, false means no shopId restriction
     *
     * @var int|false
     */
    protected $iShopId = false;

    /**
     * Base constructor.
     *
     * @param int|false $iShopId
     * @return void
     */
    public function __construct($iShopId = false)
    {
        $this->iShopId = $iShopId;

        $oCronjob = FcwlopCronjob::getInstance();
        if ($this->getCronjobId() !== null && $oCronjob->isCronjobAlreadyExisting($this->getCronjobId()) === false) {
            $oCronjob->addNewCronjob($this->getCronjobId(), $this->getDefaultMinuteInterval());
        }
        $this->loadDbData();
    }

    /**
     * Adds data of cronjob to property
     *
     * @return void
     */
    protected function loadDbData()
    {
        $this->aDbData = FcwlopCronjob::getInstance()->getCronjobData($this->getCronjobId());
    }

    /**
     * Return cronjob id
     *
     * @return string
     */
    public function getCronjobId()
    {
        return $this->sCronjobId;
    }

    /**
     * Returns shop id set by cronjob call
     *
     * @return int|false
     */
    public function getShopId()
    {
        return $this->iShopId;
    }

    /**
     * Return default interval in minutes
     *
     * @return int
     */
    public function getDefaultMinuteInterval()
    {
        return $this->iDefaultMinuteInterval;
    }

    /**
     * Returns datetime of last run of the cronjob
     *
     * @return string
     */
    public function getLastRunDateTime()
    {
        return $this->aDbData['LAST_RUN'];
    }

    /**
     * Returns configured minute interval for cronjob
     *
     * @return int
     */
    public function getMinuteInterval()
    {
        return $this->aDbData['MINUTE_INTERVAL'];
    }

    /**
     * Checks if cronjob is activated in config
     * Hook to be overloaded by child classes
     * Return true if enabled in config
     * Return false if disabled
     *
     * @return bool
     */
    public function isCronjobActivated()
    {
        return false;
    }

    /**
     * Echoes given information
     *
     * @param  string $sMessage
     * @return void
     */
    public static function outputInfo($sMessage)
    {
        echo date('Y-m-d H:i:s - ').$sMessage."\n";
    }

    /**
     * Main method for cronjobs
     * Hook to be overloaded by child classes
     * Return true if successful
     * Return false if not successful
     *
     * @return bool
     */
    protected function handleCronjob()
    {
        return false;
    }

    /**
     * Finished cronjob
     *
     * @param bool         $blResult
     * @param string|false $sError
     * @return void
     */
    protected function finishCronjob($blResult, $sError = false)
    {
        FcwlopCronjob::getInstance()->markCronjobAsFinished($this->getCronjobId());
        if ($blResult === false) {
            $this->logResult('Cron "' . $this->getCronjobId() . '" failed' . ($sError !== false ? " (Error: ".$sError.")" : ""));
        }
    }

    /**
     * Log cronjob error to log file if enabled
     *
     * @param array $aResult
     * @return void
     */
    protected function logResult($sMessage)
    {
        $sLogFilePath = getShopBasePath().'/log/'.$this->sLogFileName;
        $oLogFile = fopen($sLogFilePath, "a");
        if ($oLogFile) {
            fwrite($oLogFile, $sMessage);
            fclose($oLogFile);
        }
    }

    /**
     * Starts cronjob
     *
     * @return bool
     */
    public function startCronjob()
    {
        self::outputInfo("Start cronjob '".$this->getCronjobId()."'");

        $sError = false;
        $blResult = false;
        try {
            $blResult = $this->handleCronjob();
        } catch (\Exception $exc) {
            $sError = $exc->getMessage();
        }
        $this->finishCronjob($blResult, $sError);

        self::outputInfo("Finished cronjob '".$this->getCronjobId()."' - Status: ".($blResult === false ? 'NOT' : '')." successful");
        if ($sError !== false) {
            self::outputInfo("Error-Message: ".$sError);
        }

        return $blResult;
    }
}