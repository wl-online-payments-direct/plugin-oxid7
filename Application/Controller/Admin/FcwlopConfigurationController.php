<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Controller\Admin;

use FC\FCWLOP\Application\Helper\FcwlopPaymentHelper;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Registry;

class FcwlopConfigurationController extends FrontendController
{
    /**
     * Install new Worldline method from the enabled list of configured account
     *
     * @return void
     */
    public function fcwlopUpdatePaymentMethodsConfig()
    {
        try {
            $oPaymentHelper = FcwlopPaymentHelper::getInstance();
            $iUpdateSuccess = 0;

            $oEnabledWorldlineMethods = $oPaymentHelper->fcwlopFetchWorldlineEnabledMethods();
            $aInstalledWorldlineMethods = $oPaymentHelper->fcwlopFetchWorldlineInstalledMethods();

            foreach ($oEnabledWorldlineMethods as $oPaymentProduct) {
                $iWlopId = $oPaymentProduct->getId();
                if (isset($aInstalledWorldlineMethods[$iWlopId])) {
                    continue;
                }

                $aMethodDetails = [
                    'id' => $iWlopId,
                    'label' => $oPaymentProduct->getDisplayHints()->getLabel(),
                    'logoLink' => $oPaymentProduct->getDisplayHints()->getLogo(),
                    'type' => $oPaymentProduct->getPaymentMethod(),
                    'groupType' => $oPaymentProduct->getPaymentProductGroup(),
                ];

                try {
                    FcwlopPaymentHelper::getInstance()->fcwlopRegisterWorldlineMethod($aMethodDetails);
                } catch (\Exception $oEx) {
                    $iUpdateSuccess = 1;
                    Registry::getLogger()->error($oEx->getMessage());
                }
            }

            if ($iUpdateSuccess === 0) {
                $aResponse = $this->composeJsonResponseElements(
                    200, 'SUCCESS', Registry::getLang()->translateString('FCWLOP_CONFIGURATION_UPDATE_SUCCESS')
                );
            } elseif ($iUpdateSuccess === 1) {
                $aResponse = $this->composeJsonResponseElements(
                    201, 'SUCCESS', Registry::getLang()->translateString('FCWLOP_CONFIGURATION_UPDATE_SUCCESS_WITH_MISSING')
                );
            } else {
                $aResponse = $this->composeJsonResponseElements(
                    400, 'ERROR', Registry::getLang()->translateString('FCWLOP_CONFIGURATION_UPDATE_ERROR')
                );
            }
        } catch (\Exception $oEx) {
            $aResponse = $this->composeJsonResponseElements(
                400, 'ERROR', Registry::getLang()->translateString('FCWLOP_CONFIGURATION_UPDATE_ERROR').':'.$oEx->getMessage(),
            );
        }

        echo json_encode($aResponse);

        exit();
    }

    /**
     * Test the connection to Worldline API based on configured credential
     *
     * @return void
     */
    public function fcwlopTestConnection()
    {
        try {
            $blIsConnected = FcwlopPaymentHelper::getInstance()->fcwlopTestWorldlineConnection();

            $aResponse = $blIsConnected ?
                $this->composeJsonResponseElements(200, 'SUCCESS') :
                $aResponse = $this->composeJsonResponseElements(400, 'ERROR');

        } catch (\Exception $oEx) {
            $aResponse = $this->composeJsonResponseElements(400, 'ERROR', '', $oEx);
        }

        echo json_encode($aResponse);

        exit();
    }

    /**
     * @param int $iCode
     * @param string $sStatus
     * @param string $sMessage
     * @param \Exception|null $oEx
     * @return array
     */
    protected function composeJsonResponseElements($iCode = -1, $sStatus = '', $sMessage = '', \Exception $oEx = null)
    {
        if (!is_null($oEx)) {
            return [
                'code' => $oEx->getCode() > 0 ? $oEx->getCode() : 400,
                'status' => 'ERROR',
                'body' => [
                    'message' => ($sMessage ?? '') . ' (' . $oEx->getMessage() . ') '
                ]
            ];
        } else {
            return [
                'code' => $iCode > 0 ? $iCode : 200,
                'status' => $sStatus ?? 'SUCCESS',
                'body' => [
                    'message' => $sMessage
                ]
            ];
        }
    }
}