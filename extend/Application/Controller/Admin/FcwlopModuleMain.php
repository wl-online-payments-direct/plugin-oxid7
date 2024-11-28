<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\extend\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Registry;

class FcwlopModuleMain extends ModuleMain_parent
{
    /**
     * @var null
     */
    protected $sFcwlopNewestVersion = null;

    /**
     * Collects the current newest release version number from github
     *
     * @return string|false
     */
    public function fcwlopGetNewestReleaseVersion()
    {
        if ($this->sFcwlopNewestVersion === null) {
            $this->sFcwlopNewestVersion = false;

            $sComposerJson = file_get_contents("https://raw.githubusercontent.com/wl-online-payments-direct/plugin-oxid7/master/composer.json");
            if (!empty($sComposerJson)) {
                $aComposerJson = json_decode($sComposerJson, true);
                if (!empty($aComposerJson['version'])) {
                    $this->sFcwlopNewestVersion = $aComposerJson['version'];
                }
            }
        }
        return $this->sFcwlopNewestVersion;
    }

    /**
     * Returns current version of Worldline module
     *
     * @return string|false
     */
    public function fcwlopGetUsedVersionNumber()
    {
        $sModuleId = $this->fcwlopGetCurrentModuleId();
        if ($sModuleId) {
            $oModule = oxNew(Module::class);
            if ($oModule->load($sModuleId)) {
                return $oModule->getInfo('version');
            }
        }
        return false;
    }

    /**
     * Check if Worldline module is active
     *
     * @return bool
     */
    public function fcwlopIsModuleActive()
    {
        $sModuleId = $this->fcwlopGetCurrentModuleId();
        if ($sModuleId) {
            $oModule = oxNew(Module::class);
            if ($oModule->load($sModuleId)) {
                return $oModule->isActive();
            }
        }
        return false;
    }

    /**
     * Checks if old version warning has to be shown
     *
     * @return bool
     */
    public function fcwlopShowOldVersionWarning()
    {
        $sNewestVersion = $this->fcwlopGetNewestReleaseVersion();
        if ($sNewestVersion !== false && version_compare($sNewestVersion, $this->fcwlopGetUsedVersionNumber(), '>')) {
            return true;
        }
        return false;
    }

    /**
     * Returns currently loaded module id
     *
     * @return string
     */
    protected function fcwlopGetCurrentModuleId()
    {
        if (Registry::getRequest()->getRequestParameter("moduleId")) {
            $sModuleId = Registry::getRequest()->getRequestParameter("moduleId");
        } else {
            $sModuleId = $this->getEditObjectId();
        }
        return $sModuleId;
    }

    /**
     * Executes parent method parent::render(),
     * passes data to Twig engine and returns name of template file "module_main.html.twig".
     *
     * Extension: Return Worldline template if Worldline module was detected
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();

        if ($this->fcwlopGetCurrentModuleId() == "fcwlop" && $this->fcwlopIsModuleActive()) {
            // Return Worldline template
            return "@fcwlop/fcwlop_module_main";
        }

        return $sReturn;
    }

}