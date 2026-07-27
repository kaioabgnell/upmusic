<?php

namespace App\Actions\Bid;

use App\Domain\Enums\BidNoticeStatus;
use App\Models\BidNotice;
use App\Services\Bid\GeminiException;
use App\Services\Bid\NoticeExtractor;
use Illuminate\Support\Facades\DB;

/**
 * Análise completa de um edital (ver specs/21 §5.2 e §9.5): extrai com IA, persiste requisitos e
 * dispara a avaliação determinística das empresas.
 *
 * Roda SÍNCRONO, na própria requisição — sem fila e sem worker, por decisão de operação. O registro
 * é criado antes com `status = processando`, então uma falha a meio caminho fica visível e
 * reprocessável em vez de silenciosa.
 */
class AnalyzeNotice
{
    public function __construct(
        private readonly NoticeExtractor $extractor,
        private readonly EvaluateNotice $evaluate,
    ) {}

    /**
     * @param  \Illuminate\Support\Collection<int,\App\Models\BidCompany>|null  $companies
     *                                                                                      empresas a avaliar; null = todas as ativas (ou as já avaliadas, ao reprocessar)
     */
    public function execute(BidNotice $notice, ?\Illuminate\Support\Collection $companies = null): BidNotice
    {
        // Sem worker, a análise pode passar do limite padrão de execução do PHP (§5.2).
        set_time_limit(0);

        $notice->update([
            'status' => BidNoticeStatus::Processando,
            'error_message' => null,
        ]);

        try {
            $extracted = $this->extractor->extract($notice);
        } catch (GeminiException $e) {
            $notice->update([
                'status' => BidNoticeStatus::Erro,
                'error_message' => $e->userMessage(),
                // Preserva a resposta bruta quando houver: é o que permite diagnosticar depois.
                'raw_response' => $e->rawResponse(),
            ]);

            throw $e;
        }

        DB::transaction(function () use ($notice, $extracted) {
            $notice->fill(array_filter(
                $extracted['notice'],
                fn ($value) => $value !== null
            ));

            // Título é obrigatório: se a IA não sugeriu um, mantém o que já existia.
            if (blank($notice->title)) {
                $notice->title = $extracted['notice']['title'] ?? $notice->original_name ?? 'Edital sem título';
            }

            $notice->fill([
                'status' => BidNoticeStatus::Analisado,
                'ai_confidence' => $extracted['confidence'],
                'ai_warnings' => $extracted['warnings'],
                'raw_response' => $extracted['raw'],
                'prompt_version' => NoticeExtractor::PROMPT_VERSION,
                'analyzed_at' => now(),
                'error_message' => null,
            ])->save();

            // Reprocessar substitui os requisitos anteriores (as conferências caem em cascata) —
            // é o comportamento esperado: o edital é o mesmo, a leitura é nova.
            $notice->requirements()->delete();

            foreach ($extracted['requirements'] as $requirement) {
                $notice->requirements()->create($requirement);
            }
        });

        return $this->evaluate->execute($notice->fresh(), $companies);
    }
}
