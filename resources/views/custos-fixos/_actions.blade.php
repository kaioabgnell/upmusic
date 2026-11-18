{{-- Ações de um custo fixo (specs/27 §3.3 e §5): usadas na listagem e no detalhe.
     Espera $cost, $reactivationCount e, opcionalmente, $compact (só ícones). --}}
@php
    $compact = $compact ?? false;
    $nextNumber = $cost->nextPendingNumber();
    $generated = $cost->generatedInstallments()->count();
    $btn = $compact
        ? 'inline-flex items-center justify-center w-8 h-8 rounded-md text-steel hover:bg-surface hover:text-brand-ink'
        : 'inline-flex items-center gap-2 rounded-md border border-hairline px-3 py-2 text-sm font-medium text-brand-ink hover:bg-surface';
@endphp

@if ($compact)
    <a href="{{ route('fixed-costs.show', $cost) }}" class="{{ $btn }}" title="Ver parcelas"><i class="fa-solid fa-eye"></i></a>
@endif
<a href="{{ route('fixed-costs.edit', $cost) }}" class="{{ $btn }}" title="Editar">
    <i class="fa-solid fa-pen"></i>@unless ($compact) Editar @endunless
</a>

@if ($cost->active && $nextNumber)
    <form method="POST" action="{{ route('fixed-costs.generate-next', $cost) }}"
          data-confirm="Gerar agora a parcela {{ $nextNumber }} de {{ $cost->installments_total }} (vence em {{ $cost->dueDateFor($nextNumber)->format('d/m/Y') }})?"
          data-confirm-title="Gerar próxima parcela" data-confirm-button="Gerar">
        @csrf
        <button type="submit" class="{{ $btn }}" title="Gerar próxima parcela agora">
            <i class="fa-solid fa-forward-step"></i>@unless ($compact) Gerar próxima parcela @endunless
        </button>
    </form>
@endif

<button type="button" class="{{ $btn }}" title="Renovar (somar parcelas)"
        onclick="renewFixedCost({{ Illuminate\Support\Js::from(route('fixed-costs.renew', $cost)) }}, {{ Illuminate\Support\Js::from($cost->name) }}, {{ $cost->installments_total }})">
    <i class="fa-solid fa-rotate-right"></i>@unless ($compact) Renovar @endunless
</button>

<form method="POST" action="{{ route('fixed-costs.toggle-active', $cost) }}"
      data-confirm="{{ $cost->active
          ? "Inativar \"{$cost->name}\"? Nenhuma parcela nova será gerada. Os cards já gerados não mudam."
          : "Reativar \"{$cost->name}\"?".($reactivationCount > 0 ? " {$reactivationCount} card(s) serão criados agora." : '') }}"
      data-confirm-title="{{ $cost->active ? 'Inativar custo fixo' : 'Reativar custo fixo' }}"
      data-confirm-button="{{ $cost->active ? 'Inativar' : 'Reativar' }}">
    @csrf @method('PATCH')
    <button type="submit" class="{{ $btn }}" title="{{ $cost->active ? 'Inativar' : 'Reativar' }}">
        <i class="fa-solid {{ $cost->active ? 'fa-pause' : 'fa-play' }}"></i>@unless ($compact) {{ $cost->active ? 'Inativar' : 'Reativar' }} @endunless
    </button>
</form>

@if ($generated > 0)
    {{-- Com parcela gerada não exclui (§5.3): o botão aparece, mas explica o porquê. --}}
    <span class="{{ $btn }} !text-steel/40 cursor-not-allowed hover:!bg-transparent"
          title="Já gerou {{ $generated }} parcela(s). Inative em vez de excluir.">
        <i class="fa-solid fa-trash"></i>@unless ($compact) Excluir @endunless
    </span>
@else
    <form method="POST" action="{{ route('fixed-costs.destroy', $cost) }}" data-confirm="Excluir o custo fixo {{ $cost->name }}?">
        @csrf @method('DELETE')
        <button type="submit" class="{{ $btn }} hover:!bg-red-50 hover:!text-red-600" title="Excluir">
            <i class="fa-solid fa-trash"></i>@unless ($compact) Excluir @endunless
        </button>
    </form>
@endif

@once
    @push('scripts')
    <script>
        // Renovar (specs/27 §5.4): pergunta quantas parcelas a mais e envia por um form comum,
        // para o retorno (toast de sucesso ou erro) seguir o padrão das demais ações.
        async function renewFixedCost(url, name, total) {
            const { value: extra } = await window.Swal.fire({
                title: 'Renovar custo fixo',
                text: `"${name}" tem ${total} parcelas. Quantas parcelas a mais?`,
                input: 'number',
                inputValue: 12,
                inputAttributes: { min: 1, max: 360, step: 1 },
                showCancelButton: true,
                confirmButtonText: 'Renovar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ff8c1e',
                cancelButtonColor: '#000000',
                reverseButtons: true,
                inputValidator: (v) => (!v || Number(v) < 1 || Number(v) > 360) ? 'Informe de 1 a 360 parcelas.' : null,
            });
            if (!extra) return;
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = url;
            f.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="extra" value="${Number(extra)}">`;
            document.body.appendChild(f);
            f.submit();
        }
    </script>
    @endpush
@endonce
