<?php

namespace App\Actions\Bid;

use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload de documento do acervo, com versionamento (ver specs/21 §10.2).
 *
 * Renovar cria um registro novo apontando para o anterior e marca o anterior como substituído,
 * dentro de uma transação: o acervo vigente nunca fica com duas versões do mesmo documento.
 */
class StoreBidDocument
{
    /**
     * @param  array  $data  campos já validados pelo Form Request
     * @param  BidDocument|null  $supersedes  documento que está sendo renovado
     */
    public function execute(BidCompany $company, array $data, UploadedFile $file, ?BidDocument $supersedes = null): BidDocument
    {
        $path = $this->store($company, $file);

        try {
            return DB::transaction(function () use ($company, $data, $file, $path, $supersedes) {
                $categoryId = $data['bid_document_category_id'] ?? null;

                // O tipo canônico manda na categoria — evita documento classificado fora do lugar.
                if (! empty($data['bid_document_type_id'])) {
                    $categoryId = BidDocumentType::find($data['bid_document_type_id'])?->bid_document_category_id ?? $categoryId;
                }

                $document = $company->documents()->create([
                    'bid_document_category_id' => $categoryId,
                    'bid_document_type_id' => $data['bid_document_type_id'] ?? null,
                    'name' => $data['name'],
                    'control_code' => $data['control_code'] ?? null,
                    'issuer' => $data['issuer'] ?? null,
                    'issued_at' => $data['issued_at'] ?? null,
                    'expires_at' => ($data['no_expiry'] ?? false) ? null : ($data['expires_at'] ?? null),
                    'no_expiry' => (bool) ($data['no_expiry'] ?? false),
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'ai_extracted' => $data['ai_extracted'] ?? null,
                    'ai_confidence' => $data['ai_confidence'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'supersedes_id' => $supersedes?->id,
                    'uploaded_by' => auth()->id(),
                ]);

                $supersedes?->update(['superseded_at' => now()]);

                return $document;
            });
        } catch (\Throwable $e) {
            // Rollback do banco não apaga arquivo: limpa aqui para não deixar órfão no disco.
            Storage::disk('local')->delete($path);

            throw $e;
        }
    }

    /**
     * Guarda o arquivo fora de `public/`, em pasta por empresa, com nome sanitizado
     * (sem acentos e sem caracteres fora de [a-zA-Z0-9._-]) — ver specs/21 §12.
     */
    private function store(BidCompany $company, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe = Str::of($base)->ascii()->replaceMatches('/[^a-zA-Z0-9._-]/', '_')->limit(60, '')->trim('_');

        $name = Str::random(8).'_'.($safe->isEmpty() ? 'documento' : $safe).'.'.$extension;

        return $file->storeAs("licitacoes/{$company->id}", $name, 'local');
    }
}
