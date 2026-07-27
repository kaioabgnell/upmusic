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

];
