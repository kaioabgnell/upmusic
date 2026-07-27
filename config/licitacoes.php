<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Módulo de Licitações (ver specs/21)
    |--------------------------------------------------------------------------
    */

    // Janela em que um documento é considerado "vencendo" (specs/21 §10.1).
    'expiring_days' => (int) env('LICITACOES_EXPIRING_DAYS', 30),

    // Dentro desta janela o vencimento é crítico: destaque na UI e crédito menor no score.
    'critical_days' => (int) env('LICITACOES_CRITICAL_DAYS', 7),

    // Análise presa em "processando" por mais que isto é exibida como interrompida (§5.2).
    // Sem worker, é o que transforma um timeout de servidor em um botão "Reprocessar".
    'stale_minutes' => (int) env('LICITACOES_STALE_MINUTES', 10),

    // Teto diário de chamadas de IA por usuário, além do throttle de rota (§12).
    'ai_daily_limit' => (int) env('LICITACOES_AI_DAILY_LIMIT', 50),

    // Limites de upload (KB). O edital é maior porque vai inteiro para o modelo.
    'document_max_kb' => (int) env('LICITACOES_DOCUMENT_MAX_KB', 10240),
    'notice_max_kb' => (int) env('LICITACOES_NOTICE_MAX_KB', 15360),

    // Texto colado do edital.
    'notice_text_min' => 200,
    'notice_text_max' => 50000,

    // Similaridade mínima (Jaccard) para casar documento sem tipo canônico (§10.3).
    'fuzzy_threshold' => 0.5,

    // Cache dos contadores do painel e do badge da sidebar, em segundos (§13).
    'dashboard_cache_ttl' => 300,

];
