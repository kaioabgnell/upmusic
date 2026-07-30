<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Google Gemini — leitura de certidões e análise de editais do módulo de Licitações
    | (ver specs/21 §5.1). A chave fica SÓ no servidor; nunca é exposta ao front-end.
    */
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 180),
        // TOTAL de tentativas por chamada (não repetições extras): 2 = uma tentativa + um retry
        // em 429/5xx, que são os picos de demanda do Google. Ver GeminiClient.
        'attempts' => (int) env('GEMINI_ATTEMPTS', 2),
    ],

    /*
    | Consulta de CNPJ (preenchimento automático da razão social nos cadastros rápidos de
    | empresa/fornecedor). A API oficial do Conecta gov.br (specs/19) exige credenciamento como
    | órgão público — inacessível para a Up Music — então usamos a BrasilAPI, que espelha os
    | mesmos dados públicos da Receita Federal sem necessidade de chave.
    */
    'cnpj_lookup' => [
        'base_url' => env('CNPJ_LOOKUP_BASE_URL', 'https://brasilapi.com.br/api/cnpj/v1'),
        'timeout' => (int) env('CNPJ_LOOKUP_TIMEOUT', 8),
    ],

];
