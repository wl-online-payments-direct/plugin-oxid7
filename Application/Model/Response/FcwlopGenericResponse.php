<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Response;

class FcwlopGenericResponse
{
    /**
     * @var string
     */
    protected string $sStatus = '';

    /**
     * @var int
     */
    protected int $iStatusCode = 0;

    /**
     * @var array
     */
    protected array $aBody = [];

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->sStatus;
    }

    /**
     * @param string $sStatus
     */
    public function setStatus($sStatus)
    {
        $this->sStatus = $sStatus;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->iStatusCode;
    }

    /**
     * @param int $iStatusCode
     */
    public function setStatusCode($iStatusCode)
    {
        $this->iStatusCode = $iStatusCode;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->aBody;
    }

    /**
     * @param array $aBody
     */
    public function setBody($aBody)
    {
        $this->aBody = $aBody;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'status' => $this->sStatus,
            'code' => $this->iStatusCode,
            'body' => $this->aBody
        ];
    }

    /**
     * @return false|string
     */
    public function toJson()
    {
        return json_encode($this->toArray()) ?: '';
    }
}


