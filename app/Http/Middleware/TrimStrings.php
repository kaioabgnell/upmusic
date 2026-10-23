<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
        // Grades com autosave por campo (Custos, Receitas — specs/23 §8.4) salvam a cada tecla e
        // devolvem o valor salvo pro input que ainda está sendo editado (Object.assign(row, saved)
        // em finance.js). Sem esta exceção, o espaço no fim some sozinho sempre que o autosave
        // dispara ANTES de a pessoa continuar digitando: ela vê o próprio espaço que acabou de
        // teclar ser apagado.
        'notes',
    ];
}
