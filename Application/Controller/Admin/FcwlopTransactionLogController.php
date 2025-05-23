<?php
/**
 * PAYONE OXID Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE OXID Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE OXID Connector.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link          http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version       OXID eShop CE
 */

namespace FC\FCWLOP\Application\Controller\Admin;

use FC\FCWLOP\Application\Model\FcwlopTransactionLog;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Core\Registry;

class FcwlopTransactionLogController extends AdminDetailsController
{

    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcwlop/admin/fcwlop_transactionlog';

    /**
     * Array with existing status of order
     *
     * @var array|null
     */
    protected ?array $_aStatus = null;


    /**
     * Loads transaction log entry with given oxid, passes
     * its data to Twig engine and returns path to a template
     * "fcwlop_transactionlog".
     *
     * @return string
     */
    public function render(): string
    {
        parent::render();

        $oLogEntry = oxNew(FcwlopTransactionLog::class);

        $sOxid = Registry::getRequest()->getRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            // load object
            $oLogEntry->load($sOxid);
            $this->_aViewData["edit"] = $oLogEntry;
        }

        return $this->_sThisTemplate;
    }

}
