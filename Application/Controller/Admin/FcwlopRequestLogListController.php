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

use FC\FCWLOP\Application\Model\FcwlopRequestLog;
use OxidEsales\Eshop\Application\Controller\Admin\AdminListController;
use OxidEsales\Eshop\Core\Registry;

class FcwlopRequestLogListController extends AdminListController
{

    /**
     * Name of chosen object class (default null).
     *
     * @var string
     */
    protected $_sListClass = FcwlopRequestLog::class;

    /**
     * Default SQL sorting parameter (default null).
     *
     * @var string
     */
    protected $_sDefSortField = "timestamp";

    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcwlop/admin/fcwlop_requestlog_list';


    /**
     * Returns sorting fields array
     *
     * @return array
     */
    public function getListSorting(): array
    {
        if ($this->_aCurrSorting === null) {
            $this->_aCurrSorting = Registry::getRequest()->getRequestParameter('sort') ?: [];

            if (empty($this->_aCurrSorting) && $this->_sDefSortField && $baseModel = $this->getItemListBaseObject()) {
                $this->_aCurrSorting[$baseModel->getCoreTableName()] = [$this->_sDefSortField => "desc"];
            }
        }
        return $this->_aCurrSorting;
    }

    /**
     * Return input name for searchfields in list by shop-version
     *
     * @param string $sTable
     * @param string $sField
     * @return string
     */
    public function fcwlopGetInputName(string $sTable, string $sField): string
    {
        return "where[$sTable][$sField]";
    }

    /**
     * Return input form value for searchfields in list by shop-version
     *
     * @param string $sTable
     * @param string $sField
     * @return string
     */
    public function fcwlopGetWhereValue(string $sTable, string $sField): string
    {
        $aWhere = $this->getListFilter();
        if (empty($aWhere)) {
            return '';
        }

        return $aWhere[$sTable][$sField];
    }

    /**
     * Returns list filter array
     *
     * @return array
     */
    public function getListFilter(): array
    {
        if ($this->_aListFilter === null) {
            $this->_aListFilter = Registry::getRequest()->getRequestParameter("where") ?: [];
        }

        return $this->_aListFilter;
    }

    /**
     * Return needed javascript for sorting in list by shop-version
     *
     * @param $sTable
     * @param $sField
     * @return string
     */
    public function fcwlopGetSortingJavascript($sTable, $sField): string
    {
        return "Javascript:top.oxid.admin.setSorting( document.search, '$sTable', '$sField', 'asc');document.search.submit();";
    }
}
