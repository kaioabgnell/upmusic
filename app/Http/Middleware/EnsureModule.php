<?php

namespace App\Http\Middleware;

use App\Domain\Enums\AccessModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModule
{
    /**
     * Restringe a rota a quem tem o módulo ligado no perfil de acesso (specs/28 §8.3). Admin passa
     * sempre. Com mais de um módulo, qualquer um deles libera.
     * Uso: ->middleware('module:empresas') ou ->middleware('module:fornecedores,fornecedor_categorias')
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();

        $allowed = $user && collect($modules)
            ->contains(fn (string $module) => $user->hasModule(AccessModule::from($module)));

        if (! $allowed) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}
