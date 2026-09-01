@php $money = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Importar planilha — {{ $evento->name }}</h2></x-slot>

    <x-page-header title="Importar planilha preenchida" icon="fa-file-import"
        :subtitle="$evento->name.' — confira o que será gravado. Nada foi salvo ainda.'" />

    @if ($warnings)
        <div class="mb-6 rounded-xl border border-brand-orange/40 bg-brand-orange/5 p-4">
            <p class="text-sm font-semibold text-brand-ink mb-2">
                <i class="fa-solid fa-triangle-exclamation text-brand-orange-deep mr-1"></i> Avisos da leitura
            </p>
            <ul class="space-y-1 text-sm text-brand-ink list-disc list-inside">
                @foreach (array_slice($warnings, 0, 30) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            @if (count($warnings) > 30)
                <p class="mt-2 text-xs text-steel">… e mais {{ count($warnings) - 30 }} aviso(s).</p>
            @endif
        </div>
    @endif

    <div class="mb-4 rounded-lg border border-hairline bg-white px-4 py-3 text-sm text-steel">
        <i class="fa-solid fa-circle-info text-brand-orange mr-1"></i>
        As marcações do bloco CONTROLE do arquivo (orçamento, contrato, nota…) entram como
        <strong class="text-brand-ink">observação</strong> na linha, nunca como documento — o arquivo não
        carrega os anexos, e documento sem arquivo seria prova falsa. Anexe os comprovantes pelos cards.
    </div>

    <form method="POST" action="{{ route('finance.import', $evento) }}">
        @csrf

        <div class="bg-white border border-hairline rounded-xl overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h3 class="font-semibold text-brand-ink">
                    <i class="fa-solid fa-arrow-trend-down text-brand-orange mr-2"></i>Custos
                </h3>
                <span class="text-xs text-steel">{{ count($costs) }} linha(s)</span>
            </div>
            <div class="overflow-x-auto max-h-[28rem]">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface text-left text-steel text-xs uppercase sticky top-0">
                        <tr>
                            <th class="px-3 py-2 w-10"></th>
                            <th class="px-3 py-2 font-medium">Item</th>
                            <th class="px-3 py-2 font-medium">Descrição</th>
                            <th class="px-3 py-2 font-medium">Empresa</th>
                            <th class="px-3 py-2 font-medium text-right">Previsto</th>
                            <th class="px-3 py-2 font-medium text-right">Realizado</th>
                            <th class="px-3 py-2 font-medium text-right">Pagamentos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($costs as $i => $row)
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="costs[{{ $i }}]" value="{{ json_encode($row, JSON_UNESCAPED_UNICODE) }}" checked
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                </td>
                                <td class="px-3 py-2 text-steel">
                                    {{ $row['item_name'] ?: '—' }}
                                    @unless ($row['fornecedor_categoria_id'])
                                        <x-badge class="ml-1">sem categoria</x-badge>
                                    @endunless
                                </td>
                                <td class="px-3 py-2 text-brand-ink">{{ $row['description'] }}</td>
                                <td class="px-3 py-2 text-steel">{{ $row['supplier_name'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    {{ $money(($row['unit_estimated_1'] ?? 0) * $row['quantity'] * $row['daily_count']) }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    {{ $row['unit_actual'] === null ? '—' : $money($row['unit_actual'] * $row['quantity'] * $row['daily_count']) }}
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-steel">
                                    {{ $row['payments'] ? $money(array_sum($row['payments'])) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                        @if (empty($costs))
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-steel">Nenhuma linha de custo aproveitável.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-hairline rounded-xl overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
                <h3 class="font-semibold text-brand-ink">
                    <i class="fa-solid fa-arrow-trend-up text-brand-orange mr-2"></i>Receitas
                </h3>
                <span class="text-xs text-steel">{{ count($revenues) }} linha(s)</span>
            </div>
            <div class="overflow-x-auto max-h-[20rem]">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface text-left text-steel text-xs uppercase sticky top-0">
                        <tr>
                            <th class="px-3 py-2 w-10"></th>
                            <th class="px-3 py-2 font-medium">Receita</th>
                            <th class="px-3 py-2 font-medium">Descrição</th>
                            <th class="px-3 py-2 font-medium text-right">Previsto</th>
                            <th class="px-3 py-2 font-medium text-right">Realizado</th>
                            <th class="px-3 py-2 font-medium text-right">Recebido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($revenues as $i => $row)
                            <tr>
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="revenues[{{ $i }}]" value="{{ json_encode($row, JSON_UNESCAPED_UNICODE) }}" checked
                                           class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                                </td>
                                <td class="px-3 py-2 text-brand-ink">{{ $row['category_label'] }}</td>
                                <td class="px-3 py-2 text-steel">{{ $row['description'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($row['estimated_value']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($row['actual_value']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($row['received_value']) }}</td>
                            </tr>
                        @endforeach
                        @if (empty($revenues))
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-steel">Nenhuma receita aproveitável.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                <i class="fa-solid fa-check"></i> Importar as linhas marcadas
            </button>
            <a href="{{ route('finance.show', $evento) }}" class="text-sm text-steel hover:text-brand-ink">Cancelar</a>
        </div>
    </form>
</x-app-layout>
