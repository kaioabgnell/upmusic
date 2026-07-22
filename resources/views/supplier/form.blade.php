@php
    use App\Support\Br;
    $empresa = $card->empresa;
    $displayTitle = 'Envio de minuta — '.($card->fornecedor?->name ?? 'Fornecedor');
@endphp
<x-public-layout>
    <x-slot name="title">{{ $displayTitle }} — UpMusic</x-slot>

    {{-- Resumo do orçamento aprovado (somente leitura) --}}
    <div class="bg-white border border-hairline rounded-xl p-6 sm:p-8">
        <h1 class="text-xl font-semibold text-brand-ink">Envio da minuta do contrato</h1>
        <p class="text-sm text-steel mt-1">Confira os dados do orçamento aprovado e anexe a minuta do contrato. Nossa equipe jurídica fará a análise.</p>

        <div class="mt-6 rounded-lg bg-surface border border-hairline p-4 space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-steel">Orçamento aprovado</p>

            @if ($empresa)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                    <span class="text-sm text-steel">Empresa</span>
                    <span class="text-sm font-medium text-brand-ink">{{ $empresa->corporate_name }}</span>
                </div>
                @if ($empresa->document)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                        <span class="text-sm text-steel">CNPJ</span>
                        <span class="text-sm font-medium text-brand-ink">{{ Br::formatCnpj($empresa->document) }}</span>
                    </div>
                @endif
            @endif

            @if ($fornecedor = $card->fornecedor)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 pt-2 border-t border-hairline">
                    <span class="text-sm text-steel">Fornecedor</span>
                    <span class="text-sm font-medium text-brand-ink">{{ $fornecedor->name }}</span>
                </div>
                @if ($fornecedor->document)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                        <span class="text-sm text-steel">CNPJ</span>
                        <span class="text-sm font-medium text-brand-ink">
                            {{ $fornecedor->type->value === 'PF' ? Br::formatCpf($fornecedor->document) : Br::formatCnpj($fornecedor->document) }}
                        </span>
                    </div>
                @endif
            @endif

            @if ($card->event)
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 pt-2 border-t border-hairline">
                    <span class="text-sm text-steel">Evento</span>
                    <span class="text-sm font-medium text-brand-ink">{{ $card->event->name }}</span>
                </div>
            @endif

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                <span class="text-sm text-steel">Valor aprovado</span>
                <span class="text-sm font-semibold text-brand-ink">
                    @if ($card->actual_value !== null)
                        R$ {{ number_format((float) $card->actual_value, 2, ',', '.') }}
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('supplier.form.submit', $form->token) }}" enctype="multipart/form-data" class="mt-6 space-y-4"
              x-data="{ fileName: '' }"
              @submit.prevent="if (!fileName) { window.upAlerts.notifyError('É obrigatório o envio da minuta.'); return; } $el.submit();">
            @csrf

            <div>
                <label class="text-sm font-medium text-brand-ink">Minuta do contrato</label>
                <label class="mt-1 flex flex-col items-center justify-center w-full px-6 py-6 border-2 border-dashed border-hairline rounded-lg cursor-pointer bg-surface hover:border-brand-orange transition-colors">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-steel"></i>
                    <span class="mt-2 text-sm text-steel"><span class="font-medium text-brand-ink">Clique para anexar</span> (PDF, DOC ou DOCX · até 10MB)</span>
                    <span x-show="fileName" x-text="fileName" class="mt-2 text-xs font-medium text-brand-orange-deep"></span>
                    <input type="file" name="minuta" accept=".pdf,.doc,.docx" class="hidden"
                           @change="fileName = $event.target.files[0]?.name ?? ''">
                </label>
            </div>

            <div>
                <label for="note" class="text-sm font-medium text-brand-ink">Observação (opcional)</label>
                <textarea id="note" name="note" rows="3" placeholder="Alguma observação sobre a minuta enviada?"
                          class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">{{ old('note') }}</textarea>
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-3 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-paper-plane"></i> Enviar minuta
            </button>
        </form>
    </div>
</x-public-layout>
