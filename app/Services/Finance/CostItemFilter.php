<?php

namespace App\Services\Finance;

use App\Domain\Enums\FinanceCostStatus;
use App\Models\BankAccount;
use App\Models\CostCenter;
use App\Models\FinancePaymentSource;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;
use App\Support\Br;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filtros da aba CUSTOS (specs/23 §8.4), num lugar só: a grade, a exportação e o RELATÓRIO aplicam
 * exatamente a mesma regra, então as três telas nunca discordam sobre "quais linhas".
 *
 * Os filtros escolhem LINHAS de custo; os números de cada linha (inclusive todos os pagamentos dela)
 * vêm inteiros. Nas RECEITAS só existem dois deles — centro de custo e conta bancária —, e só esses
 * dois valem para elas (`applyToRevenues`).
 */
final class CostItemFilter
{
    /** Parâmetros da query string que pertencem a este filtro. */
    public const KEYS = [
        'search', 'categoria_id', 'status', 'fornecedor_id', 'cost_center_id', 'bank_account_id',
        'source_id', 'total_actual', 'sem_comprovante', 'falta_pagar', 'pago_a_maior',
    ];

    /** Valor de "sem centro" / "pagamento sem conta" nas seleções múltiplas. */
    public const NONE = 'none';

    private function __construct(
        private ?string $search,
        private ?int $categoriaId,
        private ?FinanceCostStatus $status,
        /** @var array<int> */
        private array $fornecedorIds,
        /** @var array<int> */
        private array $costCenterIds,
        private bool $withoutCostCenter,
        /** @var array<int> */
        private array $bankAccountIds,
        private bool $withoutBankAccount,
        private ?int $sourceId,
        private ?float $totalActual,
        private bool $semComprovante,
        private bool $faltaPagar,
        private bool $pagoAMaior,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(self::KEYS));
    }

    /** @param array<string,mixed> $input */
    public static function fromArray(array $input): self
    {
        $ids = fn (string $key) => collect((array) ($input[$key] ?? []))
            ->reject(fn ($v) => $v === self::NONE || $v === null || $v === '')
            ->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $hasNone = fn (string $key) => in_array(self::NONE, (array) ($input[$key] ?? []), true);
        $flag = fn (string $key) => filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

        $search = trim((string) ($input['search'] ?? ''));

        return new self(
            search: $search !== '' ? $search : null,
            categoriaId: ((int) ($input['categoria_id'] ?? 0)) ?: null,
            status: FinanceCostStatus::tryFrom((string) ($input['status'] ?? '')),
            fornecedorIds: $ids('fornecedor_id'),
            costCenterIds: $ids('cost_center_id'),
            withoutCostCenter: $hasNone('cost_center_id'),
            bankAccountIds: $ids('bank_account_id'),
            withoutBankAccount: $hasNone('bank_account_id'),
            sourceId: ((int) ($input['source_id'] ?? 0)) ?: null,
            // Br::money() devolve null para entrada vazia ou inválida — aí o filtro simplesmente não vale.
            totalActual: filled($input['total_actual'] ?? null) ? Br::money((string) $input['total_actual']) : null,
            semComprovante: $flag('sem_comprovante'),
            faltaPagar: $flag('falta_pagar'),
            pagoAMaior: $flag('pago_a_maior'),
        );
    }

    public function isEmpty(): bool
    {
        return $this->search === null && $this->categoriaId === null && $this->status === null
            && ! $this->fornecedorIds && ! $this->hasCostCenter() && ! $this->hasBankAccount()
            && $this->sourceId === null && $this->totalActual === null
            && ! $this->semComprovante && ! $this->faltaPagar && ! $this->pagoAMaior;
    }

    /** Algum filtro que também vale para as receitas (centro de custo ou conta bancária)? */
    public function affectsRevenues(): bool
    {
        return $this->hasCostCenter() || $this->hasBankAccount();
    }

    /** Filtros que só existem nos custos estão ativos (as receitas ignoram)? */
    public function hasCostOnlyFilters(): bool
    {
        return $this->search !== null || $this->categoriaId !== null || $this->status !== null
            || $this->fornecedorIds || $this->sourceId !== null || $this->totalActual !== null
            || $this->semComprovante || $this->faltaPagar || $this->pagoAMaior;
    }

    /** Aplica os filtros numa consulta de `finance_cost_items`. */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->categoriaId, fn ($q, $v) => $q->where('fornecedor_categoria_id', $v))
            ->when($this->status, fn ($q, $v) => $q->where('status', $v->value))
            ->when($this->fornecedorIds, fn ($q, $v) => $q->whereIn('fornecedor_id', $v))
            // Centro de custo (specs/25): seleção múltipla; "none" = linhas ainda sem centro.
            ->when($this->hasCostCenter(), fn ($q) => $q->where(fn ($w) => $w
                ->when($this->costCenterIds, fn ($i) => $i->whereIn('cost_center_id', $this->costCenterIds))
                ->when($this->withoutCostCenter, fn ($n) => $n->orWhereNull('cost_center_id'))))
            // Conta bancária (specs/26): a conta mora no PAGAMENTO (de onde saiu o dinheiro), então a
            // linha entra se tiver ao menos um pagamento da(s) conta(s); "none" = pagamento sem conta.
            ->when($this->hasBankAccount(), fn ($q) => $q->whereHas('payments', fn ($p) => $p->where(fn ($w) => $w
                ->when($this->bankAccountIds, fn ($i) => $i->whereIn('bank_account_id', $this->bankAccountIds))
                ->when($this->withoutBankAccount, fn ($n) => $n->orWhereNull('bank_account_id')))))
            ->when($this->search, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            // Igualdade com a coluna gerada (DECIMAL exato, sem o arredondamento de float binário).
            ->when($this->totalActual !== null, fn ($q) => $q->where('total_actual', $this->totalActual))
            ->when($this->semComprovante, fn ($q) => $q
                ->whereNotNull('unit_actual')
                ->whereDoesntHave('documents', fn ($d) => $d->where('kind', 'comprovante')))
            ->when($this->faltaPagar, fn ($q) => $q
                ->whereRaw('total_actual > (select COALESCE(SUM(amount),0) from finance_payments
                            where finance_payments.finance_cost_item_id = finance_cost_items.id)'))
            ->when($this->pagoAMaior, fn ($q) => $q
                ->whereRaw('total_actual < (select COALESCE(SUM(amount),0) from finance_payments
                            where finance_payments.finance_cost_item_id = finance_cost_items.id)'))
            ->when($this->sourceId, fn ($q, $v) => $q
                ->whereHas('payments', fn ($p) => $p->where('finance_payment_source_id', $v)));
    }

    /**
     * Aplica numa consulta de `finance_revenues` só o que existe nas receitas: centro de custo e
     * conta bancária (para onde o dinheiro entrou). Os demais filtros são de custos e não valem aqui.
     */
    public function applyToRevenues(Builder $query): Builder
    {
        return $query
            ->when($this->hasCostCenter(), fn ($q) => $q->where(fn ($w) => $w
                ->when($this->costCenterIds, fn ($i) => $i->whereIn('cost_center_id', $this->costCenterIds))
                ->when($this->withoutCostCenter, fn ($n) => $n->orWhereNull('cost_center_id'))))
            ->when($this->hasBankAccount(), fn ($q) => $q->where(fn ($w) => $w
                ->when($this->bankAccountIds, fn ($i) => $i->whereIn('bank_account_id', $this->bankAccountIds))
                ->when($this->withoutBankAccount, fn ($n) => $n->orWhereNull('bank_account_id'))));
    }

    /**
     * Filtros ativos em texto ("Centro de custo: MKT-01 Marketing"), para a exportação e o
     * relatório dizerem o recorte que estão mostrando. `$revenuesOnly`: só os que valem para as
     * receitas (centro de custo e conta bancária).
     *
     * @return array<int,string>
     */
    public function describe(bool $revenuesOnly = false): array
    {
        $names = fn (Collection $c) => $c->join(', ');
        $lines = [];

        if ($revenuesOnly) {
            return array_values(array_filter(
                $this->describe(),
                fn (string $line) => str_starts_with($line, 'Centro de custo:') || str_starts_with($line, 'Conta bancária:'),
            ));
        }

        if ($this->search !== null) {
            $lines[] = "Descrição contém: {$this->search}";
        }
        if ($this->categoriaId !== null) {
            $lines[] = 'Item: '.(FornecedorCategoria::whereKey($this->categoriaId)->value('nome') ?? "#{$this->categoriaId}");
        }
        if ($this->status !== null) {
            $lines[] = 'Status: '.(FinanceCostStatus::options()[$this->status->value] ?? $this->status->value);
        }
        if ($this->fornecedorIds) {
            $lines[] = 'Empresa: '.$names(Fornecedor::whereIn('id', $this->fornecedorIds)->orderBy('name')->pluck('name'));
        }
        if ($this->hasCostCenter()) {
            $labels = CostCenter::withTrashed()->whereIn('id', $this->costCenterIds)->ordered()->get()->map->label;
            if ($this->withoutCostCenter) {
                $labels->prepend('Sem centro de custo');
            }
            $lines[] = 'Centro de custo: '.$names($labels);
        }
        if ($this->hasBankAccount()) {
            $labels = BankAccount::withTrashed()->whereIn('id', $this->bankAccountIds)->ordered()->get()->map->label;
            if ($this->withoutBankAccount) {
                $labels->prepend('Pagamento sem conta');
            }
            $lines[] = 'Conta bancária: '.$names($labels);
        }
        if ($this->sourceId !== null) {
            $lines[] = 'Grupo de pagamento: '.(FinancePaymentSource::whereKey($this->sourceId)->value('name') ?? "#{$this->sourceId}");
        }
        if ($this->totalActual !== null) {
            $lines[] = 'Total realizado: '.Br::formatMoney($this->totalActual);
        }
        if ($this->semComprovante) {
            $lines[] = 'Sem comprovante';
        }
        if ($this->faltaPagar) {
            $lines[] = 'Falta pagar';
        }
        if ($this->pagoAMaior) {
            $lines[] = 'Pago a maior';
        }

        return $lines;
    }

    private function hasCostCenter(): bool
    {
        return $this->costCenterIds !== [] || $this->withoutCostCenter;
    }

    private function hasBankAccount(): bool
    {
        return $this->bankAccountIds !== [] || $this->withoutBankAccount;
    }
}
