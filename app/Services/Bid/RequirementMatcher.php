<?php

namespace App\Services\Bid;

use App\Domain\DTOs\BidMatchResult;
use App\Domain\Enums\BidCompanySize;
use App\Domain\Enums\BidDocumentStatus;
use App\Domain\Enums\BidMatchStatus;
use App\Domain\Enums\BidRequirementKind;
use App\Models\BidCompany;
use App\Models\BidDocument;
use App\Models\BidDocumentType;
use App\Models\BidNoticeRequirement;
use App\Support\BidText;
use App\Support\Br;
use Illuminate\Support\Collection;

/**
 * Cruzamento requisito × empresa — 100% determinístico, sem IA (ver specs/21 §10.3).
 *
 * Regra de ouro: incerteza NUNCA vira aprovação. Tudo que o motor não consegue afirmar volta como
 * `conferir` e aparece para o humano decidir.
 */
class RequirementMatcher
{
    private ?Collection $types = null;

    /**
     * @param  Collection<int,BidDocument>  $documents  acervo VIGENTE da empresa (já carregado, sem N+1)
     */
    public function match(
        BidNoticeRequirement $requirement,
        BidCompany $company,
        Collection $documents,
        ?float $estimatedValue = null
    ): BidMatchResult {
        if ($requirement->ignored) {
            return BidMatchResult::naoAplicavel($requirement->ignored_reason ?: 'Requisito marcado como não aplicável.');
        }

        return match ($requirement->kind) {
            BidRequirementKind::Documento => $this->matchDocument($requirement, $documents),
            BidRequirementKind::RegistroProfissional => $this->matchByTypeSlug(
                $requirement, $documents, BidDocumentType::REGISTRO_PROFISSIONAL_SLUG, 'registro profissional (CREA/CAU)'
            ),
            BidRequirementKind::AtestadoTecnico => $this->matchAtestado($documents),
            BidRequirementKind::Cnae => $this->matchCnae($requirement, $company),
            BidRequirementKind::Porte => $this->matchSize($requirement, $company),
            BidRequirementKind::CapitalSocial => $this->matchAmount(
                $requirement, $estimatedValue, $company->capital_social !== null ? (float) $company->capital_social : null, 'Capital social'
            ),
            BidRequirementKind::PatrimonioLiquido => $this->matchAmount(
                $requirement, $estimatedValue, $company->net_worth !== null ? (float) $company->net_worth : null, 'Patrimônio líquido'
            ),
            // Índices contábeis, visita técnica, garantia e "outro" não são automatizáveis:
            // dependem de leitura de balanço, agenda ou ato do próprio licitante.
            default => BidMatchResult::conferir(
                'Exige conferência manual: '.mb_strtolower($requirement->kind->label()).'.'
            ),
        };
    }

    // Documentos ------------------------------------------------------------

    private function matchDocument(BidNoticeRequirement $requirement, Collection $documents): BidMatchResult
    {
        // 1) Tipo canônico definido na extração — caminho de maior confiança.
        if ($requirement->bid_document_type_id) {
            $found = $documents->where('bid_document_type_id', $requirement->bid_document_type_id);

            if ($found->isNotEmpty()) {
                return $this->fromDocument($this->best($found), 'alta');
            }

            $typeName = $requirement->type?->name ?? $requirement->name;

            return BidMatchResult::ausente("Nenhum documento do tipo \"{$typeName}\" no acervo.");
        }

        // 2) Apelido do catálogo dentro do nome do requisito → procura documentos daquele tipo
        //    (ou cujo nome contenha o mesmo apelido).
        if ($type = $this->typeByAlias($requirement->name)) {
            $found = $documents->filter(
                fn (BidDocument $doc) => $doc->bid_document_type_id === $type->id
                    || $this->nameMatchesType($doc->name, $type)
            );

            if ($found->isNotEmpty()) {
                return $this->fromDocument($this->best($found), 'media');
            }
        }

        // 3) Último recurso: mesma categoria + similaridade de nome. Sempre confiança baixa,
        //    para a UI poder sinalizar que o vínculo precisa de confirmação.
        $threshold = (float) config('licitacoes.fuzzy_threshold', 0.5);
        $best = null;
        $bestScore = 0.0;

        foreach ($documents as $doc) {
            if ($requirement->bid_document_category_id
                && $doc->bid_document_category_id !== $requirement->bid_document_category_id) {
                continue;
            }

            $score = BidText::similarity($doc->name, $requirement->name);

            if ($score >= $threshold && $score > $bestScore) {
                $best = $doc;
                $bestScore = $score;
            }
        }

        if ($best) {
            return $this->fromDocument($best, 'baixa', "semelhança com \"{$best->name}\"");
        }

        return BidMatchResult::ausente("Nenhum documento compatível com \"{$requirement->name}\" no acervo.", 'baixa');
    }

    private function matchByTypeSlug(
        BidNoticeRequirement $requirement,
        Collection $documents,
        string $slug,
        string $label
    ): BidMatchResult {
        $type = $this->types()->firstWhere('slug', $slug);
        $found = $type ? $documents->where('bid_document_type_id', $type->id) : collect();

        if ($found->isNotEmpty()) {
            return $this->fromDocument($this->best($found), 'alta');
        }

        // Sem o tipo canônico no acervo, tenta o caminho genérico antes de declarar ausência.
        $fallback = $this->matchDocument($requirement, $documents);

        return $fallback->status === BidMatchStatus::Ausente
            ? BidMatchResult::ausente("Nenhum {$label} no acervo.")
            : $fallback;
    }

    /**
     * Atestado de capacidade técnica: existir no acervo não significa atender ao que o edital pede
     * (objeto, quantidade, órgão emissor). Existindo, vira `conferir`; não existindo, `ausente`.
     */
    private function matchAtestado(Collection $documents): BidMatchResult
    {
        $type = $this->types()->firstWhere('slug', BidDocumentType::ATESTADO_SLUG);
        $found = $type ? $documents->where('bid_document_type_id', $type->id) : collect();

        if ($found->isEmpty()) {
            $found = $documents->filter(fn (BidDocument $doc) => $type && $this->nameMatchesType($doc->name, $type));
        }

        if ($found->isEmpty()) {
            return BidMatchResult::ausente('Nenhum atestado de capacidade técnica no acervo.');
        }

        $doc = $this->best($found);

        return BidMatchResult::conferir(
            "Atestado localizado (\"{$doc->name}\") — o teor precisa ser conferido contra o exigido.",
            $doc->id
        );
    }

    // Requisitos estruturais da empresa -------------------------------------

    private function matchCnae(BidNoticeRequirement $requirement, BidCompany $company): BidMatchResult
    {
        $expected = $requirement->expected['cnae'] ?? null;
        $label = $requirement->expected['cnae_label'] ?? $expected;

        if (! $expected) {
            return BidMatchResult::conferir('O edital exige atividade compatível, mas o CNAE não foi identificado — conferir manualmente.');
        }

        if ($company->cnaes === null || $company->cnaes === []) {
            return BidMatchResult::conferir('CNAEs da empresa não cadastrados — não é possível conferir automaticamente.');
        }

        if (in_array($expected, $company->cnaeClasses(), true)) {
            return BidMatchResult::atendido("CNAE {$label} consta no cadastro da empresa.");
        }

        return BidMatchResult::ausente("CNAE {$label} não consta no cadastro da empresa.");
    }

    private function matchSize(BidNoticeRequirement $requirement, BidCompany $company): BidMatchResult
    {
        $expected = $requirement->expected['size'] ?? null;

        if (! is_array($expected) || $expected === []) {
            return BidMatchResult::conferir('Exigência de porte não identificada com clareza — conferir manualmente.');
        }

        $labels = implode('/', array_map(
            fn ($size) => BidCompanySize::tryFrom($size)?->shortLabel() ?? mb_strtoupper($size),
            $expected
        ));

        if (in_array($company->size->value, $expected, true)) {
            return BidMatchResult::atendido("Porte {$company->size->shortLabel()} atende à exigência ({$labels}).");
        }

        return BidMatchResult::ausente("Item exclusivo para {$labels}; a empresa é {$company->size->shortLabel()}.");
    }

    private function matchAmount(
        BidNoticeRequirement $requirement,
        ?float $estimatedValue,
        ?float $companyValue,
        string $label
    ): BidMatchResult {
        $required = $requirement->requiredAmount($estimatedValue);

        if ($required === null) {
            return BidMatchResult::conferir("Valor mínimo de {$label} não identificado no edital — conferir manualmente.");
        }

        if ($companyValue === null) {
            return BidMatchResult::conferir(
                mb_strtolower($label).' não cadastrado na empresa — exigido no mínimo '.Br::formatMoney($required).'.'
            );
        }

        $percent = $requirement->expected['percent_of_estimate'] ?? null;
        $origin = $percent ? " ({$percent}% do valor estimado)" : '';

        if ($companyValue >= $required) {
            return BidMatchResult::atendido(
                "{$label} de ".Br::formatMoney($companyValue).' atende ao mínimo de '.Br::formatMoney($required).$origin.'.'
            );
        }

        return BidMatchResult::ausente(
            "{$label} de ".Br::formatMoney($companyValue).' abaixo do mínimo de '.Br::formatMoney($required).$origin.'.'
        );
    }

    // Auxiliares ------------------------------------------------------------

    /** Melhor documento do conjunto: primeiro pelo status, depois pela validade mais distante. */
    private function best(Collection $documents): BidDocument
    {
        // Tupla + comparador explícito: sortBy() com array de callables trata cada um como
        // comparador ($a, $b) no Laravel 10, o que ordenaria por acidente.
        $key = fn (BidDocument $doc): array => [
            match ($doc->status) {
                BidDocumentStatus::Permanente, BidDocumentStatus::Valido => 0,
                BidDocumentStatus::Vencendo => 1,
                BidDocumentStatus::Vencido => 2,
            },
            -($doc->expires_at?->timestamp ?? PHP_INT_MAX),
        ];

        return $documents->sort(fn (BidDocument $a, BidDocument $b) => $key($a) <=> $key($b))->first();
    }

    private function fromDocument(BidDocument $document, string $confidence, ?string $note = null): BidMatchResult
    {
        $status = BidMatchStatus::fromDocumentStatus($document->status);
        $reason = "{$document->name} — {$document->status_label}".($note ? " (casado por {$note})" : '');

        return new BidMatchResult(
            status: $status,
            reason: $reason,
            confidence: $confidence,
            documentId: $document->id,
            critical: $document->is_critical,
            daysToExpire: $document->days_to_expire,
        );
    }

    /** Catálogo carregado uma vez por requisição. */
    private function types(): Collection
    {
        return $this->types ??= BidDocumentType::query()->active()->get();
    }

    /** Tipo cujo nome ou apelido aparece dentro do texto informado. */
    private function typeByAlias(string $text): ?BidDocumentType
    {
        $normalized = BidText::normalize($text);
        $match = null;
        $matchLength = 0;

        foreach ($this->types() as $type) {
            foreach ($type->normalizedNames() as $name) {
                // O apelido mais longo ganha: "cnd estadual" deve vencer "cnd".
                if ($name !== '' && str_contains($normalized, $name) && mb_strlen($name) > $matchLength) {
                    $match = $type;
                    $matchLength = mb_strlen($name);
                }
            }
        }

        return $match;
    }

    private function nameMatchesType(string $documentName, BidDocumentType $type): bool
    {
        $normalized = BidText::normalize($documentName);

        foreach ($type->normalizedNames() as $name) {
            if ($name !== '' && str_contains($normalized, $name)) {
                return true;
            }
        }

        return false;
    }
}
