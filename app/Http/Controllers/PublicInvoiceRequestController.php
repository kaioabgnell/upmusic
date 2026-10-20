<?php

namespace App\Http\Controllers;

use App\Actions\External\ProcessCardInvoiceSubmission;
use App\Http\Requests\StoreCardInvoiceSubmissionRequest;
use App\Models\CardInvoiceRequest;
use App\Support\Br;

/**
 * Página pública da Solicitação de Nota (sem login). Usa a MESMA view do formulário externo
 * (specs/11) — muda só para onde o envio vai: em vez de criar um card, entra no card do link.
 */
class PublicInvoiceRequestController extends Controller
{
    public function show(string $token)
    {
        $request = $this->resolveActive($token);

        if (! $request) {
            return response()->view('external.unavailable', [], 404);
        }

        $card = $request->card;

        return view('external.form', [
            'displayTitle' => $card->event
                ? "Envie a nota fiscal para o evento {$card->event->name}"
                : 'Envio de dados e nota fiscal',
            'submitUrl' => route('invoice-request.submit', $token),
            // Card que já tem empresa chega com CNPJ e razão social preenchidos: quem recebe o
            // link costuma ser essa empresa, e redigitar o próprio CNPJ é onde o erro entra.
            'prefill' => [
                'cnpj' => $card->empresa ? Br::formatCnpj($card->empresa->document) : null,
                'name' => $card->empresa?->corporate_name,
            ],
        ]);
    }

    public function submit(StoreCardInvoiceSubmissionRequest $httpRequest, string $token, ProcessCardInvoiceSubmission $action)
    {
        $request = $this->resolveActive($token);

        if (! $request) {
            return response()->view('external.unavailable', [], 404);
        }

        // Honeypot anti-bot: preenchido, finge sucesso e não toca no card.
        if ($httpRequest->filled('website')) {
            return redirect()->route('invoice-request.success', $token);
        }

        $action->execute($request, $httpRequest->validated(), $httpRequest->file('invoice'), $httpRequest->ip());

        return redirect()->route('invoice-request.success', $token);
    }

    public function success(string $token)
    {
        CardInvoiceRequest::where('token', $token)->firstOrFail();

        return view('external.success', ['againUrl' => route('invoice-request.show', $token)]);
    }

    private function resolveActive(string $token): ?CardInvoiceRequest
    {
        // `whereHas('card')`: o card usa soft delete, então a FK em cascata não roda quando ele é
        // excluído — sem isso o link seguiria "ativo" e quebraria ao tentar anexar num card sumido.
        return CardInvoiceRequest::where('token', $token)
            ->where('active', true)
            ->whereHas('card')
            ->with(['card.event:id,name', 'card.empresa:id,corporate_name,document'])
            ->first();
    }
}
