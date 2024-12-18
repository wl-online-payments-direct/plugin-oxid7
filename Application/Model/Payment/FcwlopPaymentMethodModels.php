<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment;

use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGenericMethod;
use FC\FCWLOP\Application\Model\Payment\Methods\FcwlopWorldlineGroupedCardMethod;

class FcwlopPaymentMethodModels
{
    /**
     * List of specific models per method.
     * If null, the generic model is used and filled with oxPayId and wlProductId
     *
    * @var array|string[]
    */
    public const WORLDLINE_PAYMENT_MODELS = [
        'fcwlopalipay' => null,
        'fcwlopamericanexpress' => null,
        'fcwlopapplepay' => null,
        'fcwlopbancontact' => null,
        'fcwlopbanktransfer' => null,
        'fcwlopbimpli' => null,
        'fcwlopbizum' => null,
        'fcwlopcadhoc' => null,
        'fcwlopcartesbancaires' => null,
        'fcwlopcetelem3x4x' => null,
        'fcwlopchequesvacances' => null,
        'fcwlopcofidis3x4x' => null,
        'fcwlopcpay' => null,
        'fcwlopdinersclub' => null,
        'fcwlopdiscover' => null,
        'fcwlopeps' => null,
        'fcwlopfloapay' => null,
        'fcwlopgooglepay' => null,
        'fcwlopideal' => null,
        'fcwlopillicado' => null,
        'fcwlopintersolve' => null,
        'fcwlopjcb' => null,
        'fcwlopklarna' => null,
        'fcwlopmaestro' => null,
        'fcwlopmastercard' => null,
        'fcwlopmbway' => null,
        'fcwlopmealvouchers' => null,
        'fcwlopmultibanco' => null,
        'fcwloponey3x4x' => null,
        'fcwloponeybankcard' => null,
        'fcwloponeyfinancement' => null,
        'fcwloponeygiftcard' => null,
        'fcwlopp24' => null,
        'fcwloppaypal' => null,
        'fcwloppostfinance' => null,
        'fcwlopsepadirectdebit' => null,
        'fcwlopsofinco3x4x' => null,
        'fcwlopspiritofcadeau' => null,
        'fcwloptwint' => null,
        'fcwlopupi' => null,
        'fcwlopvisa' => null,
        'fcwlopwechatpay' => null,
        'fcwlopgeneric' => FcwlopWorldlineGenericMethod::class,
        'fcwlopgroupedcard' => FcwlopWorldlineGroupedCardMethod::class
    ];

    /**
     * @param string $sPaymentMethodType
     * @return mixed|string|null
     */
    public static function fcwlopGetWorldlineMethodModel($sPaymentMethodType)
    {
        return self::WORLDLINE_PAYMENT_MODELS[$sPaymentMethodType] ?? null;
    }
}