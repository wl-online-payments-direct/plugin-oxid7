<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment;

class FcwlopPaymentMethodCodes
{
    /**
    * @var array|int[]
    */
    public const WORLDLINE_PAYMENT_CODES = [
        'fcwlopalipay' => 5405,
        'fcwlopamericanexpress' => 2,
        'fcwlopapplepay' => 302,
        'fcwlopbancontact' => 3012,
        'fcwlopbanktransfer' => 5408,
        'fcwlopbimpli' => 3103,
        'fcwlopbizum' => 5001,
        'fcwlopcadhoc' => 5601,
        'fcwlopcartesbancaires' => 130,
        'fcwlopcetelem3x4x' => 5133,
        'fcwlopchequesvacances' => 5403,
        'fcwlopcofidis3x4x' => 5129,
        'fcwlopcpay' => 5100,
        'fcwlopdinersclub' => 132,
        'fcwlopdiscover' => 128,
        'fcwlopeps' => 5406,
        'fcwlopfloapay' => 5139,
        'fcwlopgooglepay' => 320,
        'fcwlopideal' => 809,
        'fcwlopillicado' => 3112,
        'fcwlopintersolve' => 5700,
        'fcwlopjcb' => 125,
        'fcwlopklarna' => 3301,
        'fcwlopmaestro' => 117,
        'fcwlopmastercard' => 3,
        'fcwlopmbway' => 5908,
        'fcwlopmealvouchers' => 5402,
        'fcwlopmultibanco' => 5500,
        'fcwloponey3x4x' => 5110,
        'fcwloponeybankcard' => 5127,
        'fcwloponeyfinancement' => 5125,
        'fcwloponeygiftcard' => 5600,
        'fcwlopp24' => 3124,
        'fcwloppaypal' => 840,
        'fcwloppostfinance' => 3203,
        'fcwlopsepadirectdebit' => 771,
        'fcwlopsofinco3x4x' => 5131,
        'fcwlopspiritofcadeau' => 3116,
        'fcwloptwint' => 5407,
        'fcwlopupi' => 56,
        'fcwlopvisa' => 1,
        'fcwlopwechatpay' => 5404,
    ];

    /**
     * @param int $iPaymentMethodCode
     * @return bool
     */
    public static function fcwlopIsWorldlineMethodCode($iPaymentMethodCode)
    {
        return in_array($iPaymentMethodCode, self::WORLDLINE_PAYMENT_CODES);
    }
}