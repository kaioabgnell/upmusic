<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Custo fixo</h2></x-slot>

    <x-page-header :title="$fixedCost->name" :subtitle="($fixedCost->empresa?->trade_name ?: $fixedCost->empresa?->corporate_name)" icon="fa-arrows-rotate">
        <x-slot name="actions">
            <a href="{{ route('fixed-costs.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </x-slot>
    </x-page-header>

    {{-- Ações --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        @include('custos-fixos._actions', ['cost' => $fixedCost, 'reactivationCount' => $reactivationCount])
    </div>

    {{-- Dados do cadastro --}}
    <div class="bg-white border border-hairline rounded-xl p-6 mb-6">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <x-badge :variant="$fixedCost->active ? 'success' : 'danger'">{{ $fixedCost->active ? 'Ativo' : 'Inativo' }}</x-badge>
            <x-badge variant="neutral" icon="fa-clock">{{ $fixedCost->generation_mode === \App\Domain\Enums\FixedCostGenerationMode::LeadDays ? "Gera {$fixedCost->lead_days} dia(s) antes do vencimento" : 'Todas as parcelas geradas de uma vez' }}</x-badge>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-xs text-steel">Valor da parcela</dt>
                <dd class="font-semibold text-brand-ink">{{ \App\Support\Br::formatMoney((float) $fixedCost->installment_value) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-steel">Parcelas</dt>
                <dd class="font-semibold text-brand-ink">
                    {{ $fixedCost->installments_total }}
                    @if ($fixedCost->start_installment > 1)
                        <span class="font-normal text-steel">· começa na {{ $fixedCost->start_installment }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-steel">Primeiro vencimento</dt>
                <dd class="font-semibold text-brand-ink">{{ $fixedCost->first_due_date->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-steel">Próximo vencimento</dt>
                <dd class="font-semibold text-brand-ink">
                    @if ($nextNumber)
                        {{ $nextDueDate->format('d/m/Y') }} <span class="font-normal text-steel">· parcela {{ $nextNumber }}</span>
                    @else
                        <span class="font-normal text-steel">Todas geradas</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-steel">Quadro de destino</dt>
                <dd class="font-semibold text-brand-ink">
                    @if ($fixedCost->board)
                        <a href="{{ route('boards.show', $fixedCost->board) }}" class="hover:underline">{{ $fixedCost->board->name }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-steel">Coluna de destino</dt>
                <dd class="font-semibold text-brand-ink">{{ $fixedCost->column?->name ?? 'Coluna de entrada' }}</dd>
            </div>
            @if ($fixedCost->description)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-steel">Descrição</dt>
                    <dd class="text-brand-ink whitespace-pre-line">{{ $fixedCost->description }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Parcelas (specs/27 §2.6). Excluir renumera as seguintes (§5.5); card excluído pode ser gerado de novo (§3.4). --}}
    <h3 class="font-semibold text-brand-ink mb-3"><i class="fa-solid fa-list-ol text-brand-orange mr-2"></i>Parcelas</h3>
    <x-data-table>
        <x-slot name="head">
            <th class="px-4 py-3 font-medium w-36">Parcela</th>
            <th class="px-4 py-3 font-medium w-36">Vencimento</th>
            <th class="px-4 py-3 font-medium">Situação</th>
            <th class="px-4 py-3 font-medium text-right w-28">Card</th>
            <th class="px-4 py-3 font-medium text-right w-48">Ações</th>
        </x-slot>

        @foreach ($rows as $row)
            @php
                $total = $fixedCost->installments_total;
                $installment = $row['installment'];
                $card = $row['card'];
                $activeCard = $card && ! $card->trashed() ? $card : null;
                $isLast = $row['number'] === $total;
            @endphp
            <tr @class(['opacity-50' => $row['status'] === 'before_start'])>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-black text-white">
                        <i class="fa-solid fa-arrows-rotate text-[10px]"></i>{{ $row['number'] }} de {{ $total }}
                    </span>
                </td>
                <td class="px-4 py-3 text-brand-ink whitespace-nowrap">{{ $row['due_date']?->format('d/m/Y') ?? '—' }}</td>
                <td class="px-4 py-3">
                    @switch($row['status'])
                        @case('generated')
                            @if ($card->concluded_at)
                                <x-badge variant="dark" icon="fa-circle-check">Paga · concluído</x-badge>
                            @elseif ($card->column?->is_final)
                                <x-badge variant="dark" icon="fa-circle-check">Paga</x-badge>
                                <span class="text-xs text-steel ml-1">coluna "{{ $card->column->name }}"</span>
                            @elseif ($card->archived_at)
                                <x-badge variant="neutral" icon="fa-box-archive">Arquivado</x-badge>
                            @else
                                <x-badge variant="success">Gerada</x-badge>
                                <span class="text-xs text-steel ml-1">coluna "{{ $card->column?->name }}"</span>
                            @endif
                            @break
                        @case('card_deleted')
                            <x-badge variant="danger" icon="fa-trash">Card excluído</x-badge>
                            @break
                        @case('before_start')
                            <span class="text-xs text-steel">Paga fora do sistema</span>
                            @break
                        @case('paused')
                            <x-badge variant="neutral" icon="fa-pause">Pausada</x-badge>
                            @break
                        @default
                            @if ($row['generation_date'])
                                <span class="text-xs text-steel">Será gerada em {{ $row['generation_date']->format('d/m/Y') }}</span>
                            @else
                                <span class="text-xs text-steel">Pendente</span>
                            @endif
                    @endswitch
                </td>
                <td class="px-4 py-3 text-right">
                    @if ($activeCard && $activeCard->board)
                        <a href="{{ route('boards.show.card', [$activeCard->board, $activeCard]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-ink hover:underline">
                            #{{ $activeCard->id }} <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                    @else
                        <span class="text-steel">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if ($installment)
                        <div class="flex items-center justify-end gap-1">
                            @if ($row['status'] === 'card_deleted')
                                <form method="POST" action="{{ route('fixed-costs.installments.regenerate', [$fixedCost, $installment]) }}"
                                      data-confirm="Gerar um card novo para a parcela {{ $row['number'] }} de {{ $total }} (vence em {{ $row['due_date']->format('d/m/Y') }})?"
                                      data-confirm-title="Gerar card novamente" data-confirm-button="Gerar card">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-hairline px-2.5 h-8 text-xs font-medium text-brand-ink hover:bg-surface" title="Gerar card novamente">
                                        <i class="fa-solid fa-rotate-left"></i> Gerar card novamente
                                    </button>
                                </form>
                            @endif

                            @if ($row['paid'])
                                {{-- Parcela paga não sai (§5.5): o botão aparece, mas explica o porquê. --}}
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel/40 cursor-not-allowed"
                                      title="Parcela paga não pode ser excluída.">
                                    <i class="fa-solid fa-trash"></i>
                                </span>
                            @elseif ($rows->whereNotNull('installment')->count() <= 1)
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel/40 cursor-not-allowed"
                                      title="Única parcela. Inative ou exclua o custo fixo.">
                                    <i class="fa-solid fa-trash"></i>
                                </span>
                            @else
                                <form method="POST" action="{{ route('fixed-costs.installments.destroy', [$fixedCost, $installment]) }}"
                                      data-confirm="Excluir a parcela {{ $row['number'] }} de {{ $total }} (vence em {{ $row['due_date']->format('d/m/Y') }})?{{ $activeCard ? " O card #{$activeCard->id} vai para a lixeira." : '' }}{{ $isLast ? '' : ' As parcelas seguintes serão renumeradas (a '.($row['number'] + 1).' vira a '.$row['number'].') e mantêm os vencimentos.' }} O total passa para {{ $total - 1 }}."
                                      data-confirm-title="Excluir parcela" data-confirm-button="Excluir parcela">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-red-50 hover:text-red-600" title="Excluir parcela">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-app-layout>
