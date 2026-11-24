<?php

namespace App\Services\Finance;

use App\Domain\Enums\FinanceCostStatus;
use App\Models\BankAccount;
use App\Models\CostCenter;
use App\Models\FinanceCostItem;
use App\Models\FinancePayment;
use App\Models\FinancePaymentSource;
use App\Models\FinanceSheet;
use App\Models\Fornecedor;
use App\Models\FornecedorCategoria;

/**
 * Listas do bloco de filtros da aba CUSTOS — as mesmas na grade e no RELATÓRIO
 * (`financeiro/eventos/_cost_filters.blade.php`).
 */
class CostFilterOptions
{
    /** @return array<string,mixed> */
    public function for(FinanceSheet $sheet): array
    {
        return [
            'categorias' => FornecedorCategoria::active()->orderBy('nome')->get(['id', 'nome']),
            'statuses' => FinanceCostStatus::options(),
            'fornecedores' => Fornecedor::active()->orderBy('name')->get(['id', 'name']),
            // Ativos + os já usados em alguma linha desta planilha (mesmo inativos), para o filtro
            // e a grade nunca esconderem o centro que uma linha já tem (specs/25).
            'costCenters' => CostCenter::options(
                FinanceCostItem::where('finance_sheet_id', $sheet->id)->whereNotNull('cost_center_id')
                    ->distinct()->pluck('cost_center_id')
            ),
            // Ativas + as já usadas em algum pagamento desta planilha (specs/26): o filtro e a lista
            // de pagamentos nunca escondem a conta que um pagamento já tem.
            'bankAccounts' => BankAccount::options(
                FinancePayment::whereNotNull('bank_account_id')
                    ->whereHas('costItem', fn ($q) => $q->where('finance_sheet_id', $sheet->id))
                    ->distinct()->pluck('bank_account_id')
            ),
            'sources' => FinancePaymentSource::active()->ordered()->get(['id', 'name', 'kind']),
        ];
    }
}
