<?php

namespace App\Http\Controllers;

use App\Actions\Supplier\ProcessSupplierMinuta;
use App\Http\Requests\StoreSupplierMinutaRequest;
use App\Models\CardSupplierForm;

/**
 * Página pública (sem auth) onde o fornecedor vê o resumo do orçamento aprovado e envia a própria
 * minuta, que cai como anexo no card (ver specs/19). Gated pelo token do link + active.
 */
class SupplierFormController extends Controller
{
    public function show(string $token)
    {
        $form = $this->resolveActiveForm($token);

        if (! $form) {
            return response()->view('external.unavailable', [], 404);
        }

        return view('supplier.form', ['form' => $form, 'card' => $form->card]);
    }

    public function submit(StoreSupplierMinutaRequest $request, string $token, ProcessSupplierMinuta $action)
    {
        $form = $this->resolveActiveForm($token);

        if (! $form) {
            return response()->view('external.unavailable', [], 404);
        }

        $action->execute($form, $request->file('minuta'), $request->input('note'), $request->ip());

        return redirect()->route('supplier.form.success', $token);
    }

    public function success(string $token)
    {
        $form = CardSupplierForm::where('token', $token)->firstOrFail();

        return view('supplier.success', ['form' => $form]);
    }

    /** Carrega o link só se existir e estiver ativo, com o card e relações exibidas na página. */
    private function resolveActiveForm(string $token): ?CardSupplierForm
    {
        return CardSupplierForm::where('token', $token)
            ->where('active', true)
            ->with(['card.empresa:id,corporate_name,trade_name,document', 'card.event:id,name', 'card.fornecedor:id,name,document,type'])
            ->first();
    }
}
