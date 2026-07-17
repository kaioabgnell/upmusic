<?php

namespace App\Actions\External;

use App\Actions\Cards\CreateCard;
use App\Domain\Enums\AttachmentKind;
use App\Domain\Enums\CardOrigin;
use App\Domain\Enums\ExternalSubmissionStatus;
use App\Models\Empresa;
use App\Models\ExternalForm;
use App\Models\ExternalSubmission;
use App\Support\Br;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessExternalSubmission
{
    public function __construct(private CreateCard $createCard) {}

    /**
     * Registra o envio externo, gera um card na coluna de análise, anexa a NF
     * e casa a empresa por CNPJ. Tudo em transação.
     *
     * @param  array{cnpj:string,name:string,value:mixed,service_date:string,service_description:string,payment_data:string}  $data
     */
    public function execute(ExternalForm $form, array $data, UploadedFile $invoice, ?string $ip = null): ExternalSubmission
    {
        return DB::transaction(function () use ($form, $data, $invoice, $ip) {
            $cnpj = Br::digits($data['cnpj']);
            $value = Br::money($data['value']);

            // Casa a empresa por CNPJ (se já cadastrada).
            $empresa = Empresa::where('document', $cnpj)->first();

            // Armazena a NF.
            $path = $invoice->store("external-invoices/{$form->id}", 'local');

            // Cria o card na coluna de análise configurada.
            $board = $form->board;
            $cardData = [
                'title' => Str::limit($data['name'].' — '.$data['service_description'], 160, ''),
                'description' => $data['service_description'],
                'empresa_id' => $empresa?->id,
                'event_id' => $form->event_id,
                'estimated_value' => $value,
                'board_column_id' => $form->target_column_id,
            ];
            $card = $this->createCard->execute($board, $cardData, null, CardOrigin::ExternalForm);

            // Anexa a NF ao card.
            $card->attachments()->create([
                'kind' => AttachmentKind::NotaFiscal->value,
                'original_name' => $invoice->getClientOriginalName(),
                'path' => $path,
                'mime' => $invoice->getClientMimeType(),
                'size' => $invoice->getSize(),
            ]);

            // Registra o submission vinculado ao card.
            return $form->submissions()->create([
                'empresa_id' => $empresa?->id,
                'card_id' => $card->id,
                'cnpj' => $cnpj,
                'name' => $data['name'],
                'value' => $value,
                'service_date' => $data['service_date'],
                'service_description' => $data['service_description'],
                'payment_data' => $data['payment_data'],
                'invoice_path' => $path,
                'status' => ExternalSubmissionStatus::Recebido->value,
                'ip' => $ip,
            ]);
        });
    }
}
