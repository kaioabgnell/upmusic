<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardSupplierForm;
use Illuminate\Support\Str;

/**
 * Gestão do link de minuta de um card pela equipe (ver specs/19). A página pública em si fica no
 * SupplierFormController. Autorização reaproveita a policy do card (authorize('update', $card)),
 * mesmo padrão de concluir/transferir/arquivar.
 */
class CardSupplierFormController extends Controller
{
    /** Gera (ou reativa) o link de minuta do card e devolve a URL pública pronta para copiar. */
    public function generate(Card $card)
    {
        $this->authorize('update', $card);

        abort_unless($card->board->allows_supplier_form, 422, 'Este quadro não permite solicitar minuta ao fornecedor.');

        $form = CardSupplierForm::firstOrNew(['card_id' => $card->id]);
        if (! $form->exists) {
            $form->token = $this->newToken();
            $form->created_by = auth()->id();
        }
        $form->active = true;
        $form->save();

        return response()->json($this->formJson($form));
    }

    /** Desativa o link sem apagar nada (URL pública passa a retornar 404). */
    public function disable(Card $card)
    {
        $this->authorize('update', $card);

        $form = $card->supplierForm;
        abort_unless($form, 404);

        $form->update(['active' => false]);

        return response()->json($this->formJson($form));
    }

    private function formJson(CardSupplierForm $form): array
    {
        return [
            'active' => $form->active,
            'url' => $form->active ? route('supplier.form.show', $form->token) : null,
        ];
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(40);
        } while (CardSupplierForm::where('token', $token)->exists());

        return $token;
    }
}
