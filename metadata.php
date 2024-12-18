<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id'            => 'fcwlop',
    'title'         => [
        'de' => 'Worldline Online Payment direct',
        'en' => 'Worldline Online Payment direct'
    ],
    'description'   => [
        'de' => 'Beschreibung',
        'en' => 'Description',
    ],
    'thumbnail'     => 'img/wordline_logo.svg',
    'version'       => '1.0.0',
    'author'        => 'FATCHIP GmbH',
    'url'           => 'https://www.fatchip.de/',
    'email'         => 'kontakt@fatchip.de',
    'extend'        => [
        \OxidEsales\Eshop\Application\Model\Order::class                            => FC\FCWLOP\extend\Application\Model\FcwlopOrder::class,
        \OxidEsales\Eshop\Application\Model\OrderArticle::class                     => FC\FCWLOP\extend\Application\Model\FcwlopOrderArticle::class,
        \OxidEsales\Eshop\Application\Model\Payment::class                          => FC\FCWLOP\extend\Application\Model\FcwlopPayment::class,
        \OxidEsales\Eshop\Application\Model\PaymentGateway::class                   => FC\FCWLOP\extend\Application\Model\FcwlopPaymentGateway::class,
        \OxidEsales\Eshop\Application\Controller\Admin\ModuleConfiguration::class   => FC\FCWLOP\extend\Application\Controller\Admin\FcwlopModuleConfiguration::class,
        \OxidEsales\Eshop\Application\Controller\Admin\ModuleMain::class            => FC\FCWLOP\extend\Application\Controller\Admin\FcwlopModuleMain::class,
        \OxidEsales\Eshop\Application\Controller\OrderController::class             => FC\FCWLOP\extend\Application\Controller\FcwlopOrderController::class,
        \OxidEsales\Eshop\Application\Controller\PaymentController::class           => FC\FCWLOP\extend\Application\Controller\FcwlopPaymentController::class,
        \OxidEsales\Eshop\Application\Controller\ThankYouController::class          => FC\FCWLOP\extend\Application\Controller\FcwlopThankYouController::class,
    ],
    'controllers'   => [
        // Admin
        'FcwlopConfiguration'       => \FC\FCWLOP\Application\Controller\Admin\FcwlopConfigurationController::class,
        'FcwlopOrderDetails'        => \FC\FCWLOP\Application\Controller\Admin\FcwlopOrderDetailsController::class,
        'FcwlopRequestLog'          => \FC\FCWLOP\Application\Controller\Admin\FcwlopRequestLogController::class,
        'FcwlopRequestLogList'      => \FC\FCWLOP\Application\Controller\Admin\FcwlopRequestLogListController::class,
        'FcwlopRequestLogMain'      => \FC\FCWLOP\Application\Controller\Admin\FcwlopRequestLogMainController::class,
        'FcwlopTransactionLog'      => \FC\FCWLOP\Application\Controller\Admin\FcwlopTransactionLogController::class,
        'FcwlopTransactionLogList'  => \FC\FCWLOP\Application\Controller\Admin\FcwlopTransactionLogListController::class,
        'FcwlopTransactionLogMain'  => \FC\FCWLOP\Application\Controller\Admin\FcwlopTransactionLogMainController::class,

        // Frontend
        'FcwlopWebhook'             => \FC\FCWLOP\Application\Controller\FcwlopWebhookController::class,
    ],
    'events'        => [
        'onActivate'    => \FC\FCWLOP\Core\FcwlopEvents::class.'::onActivate',
        'onDeactivate'  => \FC\FCWLOP\Core\FcwlopEvents::class.'::onDeactivate',
    ],
    'settings'      => [
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopMode',                        'type' => 'select',     'value' => 'test',      'position' => 10, 'constraints' => 'live|test'],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopPspId',                       'type' => 'str',        'value' => '',          'position' => 15],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopApiKey',                      'type' => 'str',        'value' => '',          'position' => 20],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopApiSecret',                   'type' => 'str',        'value' => '',          'position' => 21],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopWebhookKey',                  'type' => 'str',        'value' => '',          'position' => 30],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopWebhookSecret',               'type' => 'str',        'value' => '',          'position' => 31],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopLiveEndpoint',                'type' => 'str',        'value' => '',          'position' => 40],
        ['group' => 'FCWLOP_GENERAL',           'name' => 'sFcwlopSandboxEndpoint',             'type' => 'str',        'value' => '',          'position' => 41],

        ['group' => 'FCWLOP_PAYMENT_METHODS',   'name' => 'sFcwlopCaptureMethod',               'type' => 'select',     'value' => 'direct-sales',  'position' => 10, 'constraints' => 'direct-sales|manual'],
        ['group' => 'FCWLOP_PAYMENT_METHODS',   'name' => 'sFcwlopAutoCancellation',            'type' => 'select',     'value' => '',              'position' => 20, 'constraints' => '0|1|2|4|6|12|24'],
        ['group' => 'FCWLOP_PAYMENT_METHODS',   'name' => 'sFcwlopCcCheckoutType',              'type' => 'select',     'value' => '',              'position' => 30, 'constraints' => 'embedded|iframe'],
        ['group' => 'FCWLOP_PAYMENT_METHODS',   'name' => 'sFcwlopCcGroupDisplay',              'type' => 'bool',       'value' => '0',             'position' => 40],
    ]
];