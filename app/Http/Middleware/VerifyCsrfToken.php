<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // POST do Web Share Target da PWA (Android) — disparado pelo SO, sem token CSRF. Ainda exige
        // sessão autenticada + validação estrita de upload; só estaciona arquivo (nada destrutivo).
        // Ver specs/16, Fase 2.
        'captura/receber',
    ];
}
