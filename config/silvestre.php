<?php

/*
 * Configuración regional y comercial de Silvestre.
 *
 * Hay DOS flujos de dinero y nunca se mezclan:
 *
 *  1. Cliente → negocio (ventas). Silvestre NO procesa ese dinero. El cliente
 *     le paga directo al negocio con el método que el negocio acepte
 *     (efectivo, transferencia, billetera móvil, POS propio). Silvestre solo
 *     registra el pedido y, si aplica, el comprobante que sube el cliente.
 *
 *  2. Negocio → Silvestre (suscripciones). Es el ÚNICO ingreso de la
 *     plataforma. Se cobra en dólares.
 */
return [

    'country' => 'GT',
    'city' => 'Guatemala',
    'timezone' => 'America/Guatemala',

    // Moneda de catálogos, pedidos e ingresos de los negocios.
    'currency' => [
        'code' => 'GTQ',
        'symbol' => 'Q',
    ],

    // Moneda en la que Silvestre cobra la suscripción.
    'subscription_currency' => [
        'code' => 'USD',
        'symbol' => '$',
    ],

    'phone' => [
        'prefix' => '+502',
        'digits' => 8,
    ],

    // Días de prueba de Pro, contados desde que el perfil llega al 100 %.
    'trial_days' => 90,

    // Horas que se aparta el inventario mientras el cliente paga por
    // transferencia o billetera y el negocio confirma el comprobante.
    'payment_hold_hours' => 24,

    // A dónde transfieren los negocios el pago de su suscripción.
    // Por ahora solo transferencia a Banco Industrial. El número y el
    // titular se llenan en el .env para no dejar datos bancarios en el código.
    'subscription_payee' => [
        'bank' => env('SILVESTRE_PAYEE_BANK', 'Banco Industrial'),
        'account_type' => env('SILVESTRE_PAYEE_ACCOUNT_TYPE', 'Monetaria'),
        'account_number' => env('SILVESTRE_PAYEE_ACCOUNT_NUMBER'),
        'holder' => env('SILVESTRE_PAYEE_HOLDER'),
    ],

    // Bancos que operan en Guatemala, para los datos de transferencia.
    'banks' => [
        'Banco Industrial',
        'Banrural',
        'BAM',
        'G&T Continental',
        'BAC Credomatic',
        'Bantrab',
        'Banco Promerica',
        'Interbanco',
        'Ficohsa',
        'Banco Azteca',
        'Crédito Hipotecario Nacional',
        'Otro',
    ],

    'account_types' => ['Monetaria', 'Ahorro'],

    'wallets' => ['Tigo Money', 'Otra'],
];
