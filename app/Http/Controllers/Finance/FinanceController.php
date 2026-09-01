<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceSheet;

/**
 * Base dos controllers do Financeiro do Evento (specs/23 §11).
 *
 * O bloqueio da planilha fechada NÃO pode morar na policy: `Gate::before` libera o Admin em
 * qualquer policy (specs/04), então um admin passaria direto pelo congelamento da prestação de
 * contas. A trava é um guard explícito, aplicado depois da autorização de acesso — assim quem não
 * enxerga o evento continua recebendo 403, e quem enxerga recebe 422 com o motivo real.
 */
abstract class FinanceController extends Controller
{
    /** Autoriza a escrita: acesso ao evento (403) e planilha aberta (422). */
    protected function authorizeWrite(FinanceSheet $sheet): void
    {
        $this->authorize('update', $sheet);

        abort_if(
            $sheet->isClosed(),
            422,
            'A prestação de contas deste evento está fechada. Reabra o financeiro antes de editar.'
        );
    }
}
