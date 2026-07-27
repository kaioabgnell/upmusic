<?php

namespace App\Http\Requests\Bid;

use App\Models\BidDocument;
use App\Models\BidDocumentType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de documento do acervo (ver specs/21 §9.4 e §10.5).
 *
 * Anexo é obrigatório — todo documento de habilitação é comprovado pelo arquivo. Validação por
 * extensão E mimetype real: nome de arquivo não é prova de nada.
 */
class StoreBidDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BidDocument::class);
    }

    public function rules(): array
    {
        $max = (int) config('licitacoes.document_max_kb', 10240);

        return [
            'arquivo' => [
                'required', 'file', "max:{$max}",
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
            'name' => ['required', 'string', 'max:180'],
            'bid_document_category_id' => ['required', 'exists:bid_document_categories,id'],
            'bid_document_type_id' => ['nullable', 'exists:bid_document_types,id'],
            'no_expiry' => ['boolean'],
            // `exclude_if` tira o campo dos dados validados quando o documento não tem validade.
            // A comparação com a emissão só entra quando há emissão informada.
            'expires_at' => array_merge(
                ['exclude_if:no_expiry,true', 'required', 'date'],
                $this->filled('issued_at') ? ['after_or_equal:issued_at'] : []
            ),
            'issued_at' => ['nullable', 'date'],
            'control_code' => array_merge(
                [$this->controlCodeRequired() ? 'required' : 'nullable'],
                ['string', 'max:120']
            ),
            'issuer' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Rastro do auto-preenchimento: o que a IA sugeriu, para auditoria (§9.4).
            'ai_extracted' => ['nullable', 'array'],
            'ai_confidence' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }

    /** Certidões com código de autenticação exigem o código (marcado no catálogo de tipos). */
    protected function controlCodeRequired(): bool
    {
        $typeId = $this->input('bid_document_type_id');

        return $typeId
            ? (bool) BidDocumentType::whereKey($typeId)->value('requires_control_code')
            : false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'no_expiry' => $this->boolean('no_expiry'),
            // `ai_extracted` chega do modal como JSON serializado num input hidden (§9.4), não como
            // campos aninhados — decodifica antes da regra `array`, senão toda leitura assistida
            // falha a validação (a string nunca passa em `array`).
            'ai_extracted' => $this->decodedAiExtracted(),
        ]);
    }

    private function decodedAiExtracted(): ?array
    {
        $raw = $this->input('ai_extracted');

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function attributes(): array
    {
        return [
            'arquivo' => 'arquivo',
            'name' => 'nome do documento',
            'bid_document_category_id' => 'categoria',
            'bid_document_type_id' => 'tipo',
            'expires_at' => 'data de validade',
            'issued_at' => 'data de emissão',
            'control_code' => 'código de controle',
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'Anexe o arquivo do documento (PDF, JPG ou PNG).',
            'arquivo.mimes' => 'O arquivo precisa ser PDF, JPG ou PNG.',
            'arquivo.mimetypes' => 'O arquivo precisa ser PDF, JPG ou PNG.',
            'expires_at.required' => 'Informe a data de validade ou marque "sem validade".',
            'control_code.required' => 'Este tipo de certidão exige o código de controle.',
        ];
    }
}
