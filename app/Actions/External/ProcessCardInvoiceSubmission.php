<?php

namespace App\Actions\External;

use App\Domain\Enums\AttachmentKind;
use App\Models\CardInvoiceRequest;
use App\Models\CardInvoiceSubmission;
use App\Models\Empresa;
use App\Support\Br;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Recebe o envio do link de Solicitação de Nota e o coloca NO CARD de origem — nunca cria card.
 *
 * Os dados digitados viram um comentário no histórico, e não sobrescrevem título, descrição ou
 * valores do card: esses campos foram negociados por quem toca o processo, e alguns espelham a
 * linha do Financeiro (CardObserver). Um terceiro de fora não pode reescrevê-los preenchendo um
 * formulário. O que entra de fato é a NF, como anexo do tipo "Nota fiscal" — e, se o card já está
 * no Financeiro, o CardAttachmentObserver a transforma em documento de controle sozinho.
 */
class ProcessCardInvoiceSubmission
{
    public function execute(CardInvoiceRequest $request, array $data, ?UploadedFile $invoice = null, ?string $ip = null): CardInvoiceSubmission
    {
        return DB::transaction(function () use ($request, $data, $invoice, $ip) {
            $card = $request->card;
            $cnpj = Br::digits($data['cnpj']);
            $value = Br::money($data['value']);

            $attachment = $invoice ? $card->attachments()->create([
                'uploaded_by' => null,
                'kind' => AttachmentKind::NotaFiscal->value,
                'original_name' => $invoice->getClientOriginalName(),
                'path' => $invoice->store("card-invoices/{$card->id}", 'local'),
                'mime' => $invoice->getClientMimeType(),
                'size' => $invoice->getSize(),
            ]) : null;

            // Só PREENCHE a empresa: card sem empresa ganha a que bate com o CNPJ enviado; card que
            // já tem uma fica como está, mesmo que o CNPJ divirja — a divergência aparece no
            // comentário para quem conferir, em vez de trocar o vínculo em silêncio.
            if (! $card->empresa_id && ($empresa = Empresa::where('document', $cnpj)->first())) {
                $card->update(['empresa_id' => $empresa->id]);
            }

            $card->comments()->create([
                'user_id' => null,
                'body' => implode("\n", array_filter([
                    'Solicitação de nota recebida pelo link externo.',
                    'Solicitante - '.$data['requester_name'],
                    'Empresa - '.$data['name'].' ('.Br::formatCnpj($cnpj).')',
                    'Valor (R$) - '.number_format((float) $value, 2, ',', '.'),
                    'Data - '.date('d/m/Y', strtotime($data['service_date'])),
                    'Descrição do serviço - '.$data['service_description'],
                    'Dados para pagamento - '.$data['payment_data'],
                    $attachment ? 'Nota fiscal anexada - '.$attachment->original_name : 'Enviado sem nota fiscal.',
                ])),
            ]);

            return $request->submissions()->create([
                'card_id' => $card->id,
                'card_attachment_id' => $attachment?->id,
                'cnpj' => $cnpj,
                'name' => $data['name'],
                'requester_name' => $data['requester_name'],
                'value' => $value,
                'service_date' => $data['service_date'],
                'service_description' => $data['service_description'],
                'payment_data' => $data['payment_data'],
                'ip' => $ip,
            ]);
        });
    }
}
