<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\FCWLOP\Application\Model\Payment;

class FcwlopPaymentMethodTypes
{
    /**
    * @var array|string[]
    */
    public const WORLDLINE_PAYMENT_TYPES = [
        'fcwlopalipay' => 'Alipay+',
        'fcwlopamericanexpress' => 'American Express',
        'fcwlopapplepay' => 'Apple Pay',
        'fcwlopbancontact' => 'Bancontact',
        'fcwlopbanktransfer' => 'Bank transfer by Worldline',
        'fcwlopbimpli' => 'Bimpli CADO',
        'fcwlopbizum' => 'Bizum',
        'fcwlopcadhoc' => 'Cadhoc',
        'fcwlopcartesbancaires' => 'Cartes Bancaires',
        'fcwlopcetelem3x4x' => 'Cetelem 3x/4x',
        'fcwlopchequesvacances' => 'Chèques-Vacances Connect',
        'fcwlopcofidis3x4x' => 'Cofidis 3x/4x',
        'fcwlopcpay' => 'Cpay',
        'fcwlopdinersclub' => 'Diners Club',
        'fcwlopdiscover' => 'Discover',
        'fcwlopeps' => 'EPS',
        'fcwlopfloapay' => 'FloaPay',
        'fcwlopgooglepay' => 'Google Pay',
        'fcwlopideal' => 'iDEAL',
        'fcwlopillicado' => 'Illicado',
        'fcwlopintersolve' => 'Intersolve',
        'fcwlopjcb' => 'JCB',
        'fcwlopklarna' => 'Klarna',
        'fcwlopmaestro' => 'Maestro',
        'fcwlopmastercard' => 'Mastercard',
        'fcwlopmbway' => 'MB Way',
        'fcwlopmealvouchers' => 'Mealvouchers',
        'fcwlopmultibanco' => 'Multibanco',
        'fcwloponey3x4x' => 'Oney 3x-4x',
        'fcwloponeybankcard' => 'Oney Bank Card',
        'fcwloponeyfinancement' => 'Oney Financement Long',
        'fcwloponeygiftcard' => 'OneyBrandedGiftCard',
        'fcwlopp24' => 'P24',
        'fcwloppaypal' => 'paypal',
        'fcwloppostfinance' => 'PostFinance Pay',
        'fcwlopsepadirectdebit' => 'SEPA Direct Debit',
        'fcwlopsofinco3x4x' => 'Sofinco 3x/4x',
        'fcwlopspiritofcadeau' => 'Spirit of Cadeau',
        'fcwloptwint' => 'TWINT',
        'fcwlopupi' => 'UPI - UnionPay International',
        'fcwlopvisa' => 'Visa',
        'fcwlopwechatpay' => 'WeChat Pay',
        'fcwlopgroupedcard' => 'Credit Card',
    ];

    /**
     * @var array|string[]
     */
    public const WORLDLINE_CARDS_PRODUCTS = [
        'fcwlopamericanexpress' => 'American Express',
        'fcwlopbancontact' => 'Bancontact',
        'fcwlopcartesbancaires' => 'Cartes Bancaires',
        'fcwlopdinersclub' => 'Diners Club',
        'fcwlopdiscover' => 'Discover',
        'fcwlopjcb' => 'JCB',
        'fcwlopmaestro' => 'Maestro',
        'fcwlopmastercard' => 'Mastercard',
        'fcwlopupi' => 'UPI - UnionPay International',
        'fcwlopvisa' => 'Visa',
    ];

    public static function fcwlopIsWorldlineMethodType($sPaymentMethodType)
    {
        return in_array($sPaymentMethodType, array_keys(self::WORLDLINE_PAYMENT_TYPES));
    }

    public static function fcwlopIsWorldlineCardsProduct($sPaymentMethodType)
    {
        return in_array($sPaymentMethodType, array_keys(self::WORLDLINE_CARDS_PRODUCTS));
    }
}