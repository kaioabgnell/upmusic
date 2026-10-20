<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardInvoiceRequest;
use Illuminate\Support\Str;

/**
 * "Compartilhar Solicitação de Nota" no menu do card: gera (ou reativa) o link público que coloca
 * os dados e a NF do envio dentro deste card.
 *
 * Um link por card, sempre o mesmo token: reenviar o link para a mesma pessoa não pode quebrar o
 * que ela já recebeu antes.
 */
class CardInvoiceRequestController extends Controller
{
    public function generate(Card $card)
    {
        $this->authorize('update', $card);

        $request = CardInvoiceRequest::firstOrNew(['card_id' => $card->id]);

        if (! $request->exists) {
            $request->token = $this->newToken();
            $request->created_by = auth()->id();
        }

        $request->active = true;
        $request->save();

        return response()->json([
            'active' => true,
            'url' => route('invoice-request.show', $request->token),
        ]);
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(40);
        } while (CardInvoiceRequest::where('token', $token)->exists());

        return $token;
    }
}
