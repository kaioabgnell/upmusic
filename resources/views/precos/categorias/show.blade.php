@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Categoria: {{ $categoria->nome }}</h2></x-slot>

    <x-page-header title="Registros de preço" :subtitle="$categoria->nome" icon="fa-tags">
        <x-slot name="actions">
            <a href="{{ route('prices.history', ['categoria_id' => $categoria->id]) }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-chart-line"></i> Ver evolução</a>
            <a href="{{ route('prices.categorias.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        </x-slot>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-steel">
        <span class="inline-flex items-center gap-1.5 rounded-md bg-surface border border-hairline px-3 py-1.5">
            <i class="fa-solid fa-ruler text-brand-orange"></i> Unidade: <span class="font-medium text-brand-ink">{{ $categoria->unidade?->label() ?? 'Não definida' }}</span>
        </span>
        @if ($categoria->preco_interno !== null)
            <span class="inline-flex items-center gap-1.5 rounded-md bg-surface border border-hairline px-3 py-1.5">
                <i class="fa-solid fa-lock text-brand-orange"></i> Preço Interno: <span class="font-medium text-brand-ink">R$ {{ $money($categoria->preco_interno) }}</span>
            </span>
        @endif
        <span class="text-xs">A unidade e o preço interno são editados no <a href="{{ route('fornecedor-categorias.edit', $categoria) }}" class="text-brand-orange-deep hover:underline">cadastro da categoria</a>.</span>
    </div>

    {{-- Registros de preço --}}
    <div class="bg-white border border-hairline rounded-xl overflow-hidden" id="price-editor" data-categoria="{{ $categoria->id }}">
        <div class="px-4 py-3 border-b border-hairline">
            <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-tag text-brand-orange mr-2"></i>Registros de preço</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm table-fixed">
                <colgroup>
                    <col style="width:9rem">
                    <col style="width:7rem">
                    <col style="width:13rem">
                    <col style="width:13rem">
                    <col>
                    <col style="width:2.5rem">
                </colgroup>
                <thead class="bg-surface text-left text-steel">
                    <tr>
                        <th class="px-3 py-2.5 font-medium">Data</th>
                        <th class="px-3 py-2.5 font-medium">Preço</th>
                        <th class="px-3 py-2.5 font-medium">Fornecedor</th>
                        <th class="px-3 py-2.5 font-medium">Evento</th>
                        <th class="px-3 py-2.5 font-medium">Observação</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody id="price-rows" class="divide-y divide-hairline">
                    @foreach ($categoria->priceRecords as $price)
                        <tr class="price-row" data-id="{{ $price->id }}">
                            <td class="px-3 py-2.5"><input type="date" value="{{ $price->reference_date->format('Y-m-d') }}" class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="reference_date" onchange="priceEntries.save({{ $price->id }}, this)"></td>
                            <td class="px-3 py-2.5"><input type="text" value="{{ $money($price->price) }}" class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="price" onchange="priceEntries.save({{ $price->id }}, this)"></td>
                            <td class="px-3 py-2.5">
                                <select class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="fornecedor_id" onchange="priceEntries.save({{ $price->id }}, this)">
                                    <option value="">—</option>
                                    @foreach ($fornecedores as $f)<option value="{{ $f->id }}" @selected($price->fornecedor_id == $f->id)>{{ $f->name }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5">
                                <select class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="event_id" onchange="priceEntries.save({{ $price->id }}, this)">
                                    <option value="">—</option>
                                    @foreach ($events as $e)<option value="{{ $e->id }}" @selected($price->event_id == $e->id)>{{ $e->name }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2.5"><input type="text" value="{{ $price->notes }}" class="w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="notes" onchange="priceEntries.save({{ $price->id }}, this)"></td>
                            <td class="px-3 py-2.5 text-right"><button type="button" class="text-steel hover:text-red-600" onclick="priceEntries.remove({{ $price->id }}, this)"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form class="p-4 border-t border-hairline flex flex-wrap items-end gap-3" onsubmit="event.preventDefault(); priceEntries.add(this);">
            <div class="w-36">
                <label class="text-xs text-steel">Data</label>
                <input type="date" name="reference_date" required class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>
            <div class="w-28">
                <label class="text-xs text-steel">Preço</label>
                <input type="text" name="price" placeholder="0,00" required class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>
            <div class="w-52">
                <label class="text-xs text-steel">Fornecedor</label>
                <select name="fornecedor_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                    <option value="">Opcional</option>
                    @foreach ($fornecedores as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                </select>
            </div>
            <div class="w-52">
                <label class="text-xs text-steel">Evento</label>
                <select name="event_id" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                    <option value="">Opcional</option>
                    @foreach ($events as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs text-steel">Observação</label>
                <input type="text" name="notes" placeholder="Observação (opcional)" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep"><i class="fa-solid fa-plus"></i> Registrar preço</button>
        </form>
    </div>

    @push('scripts')
    <script>
        window.priceEntries = (function () {
            const categoriaId = document.getElementById('price-editor').dataset.categoria;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            async function api(url, method, body) {
                const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: body ? JSON.stringify(body) : null });
                if (!res.ok) { const d = await res.json().catch(() => ({})); throw new Error(d.message || Object.values(d.errors || {}).flat()[0] || 'Erro ao salvar.'); }
                return res.json();
            }
            function payload(row) { const p = {}; row.querySelectorAll('[data-field]').forEach(el => p[el.dataset.field] = el.value); return p; }
            return {
                async save(id, el) {
                    const row = el.closest('.price-row');
                    try { await api(`{{ url('precos/registros') }}/${id}`, 'PUT', payload(row)); window.upAlerts.notifySuccess('Registro salvo.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async remove(id, btn) {
                    if (!await window.upAlerts.confirmAction({ text: 'Excluir este registro de preço?' })) return;
                    try { await api(`{{ url('precos/registros') }}/${id}`, 'DELETE'); btn.closest('.price-row').remove(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async add(form) {
                    const price = form.price.value.trim();
                    const date = form.reference_date.value;
                    if (!price || !date) return;
                    try {
                        await api(`{{ url('precos/categorias') }}/${categoriaId}/registros`, 'POST', {
                            reference_date: date, price,
                            fornecedor_id: form.fornecedor_id.value,
                            event_id: form.event_id.value,
                            notes: form.notes.value,
                        });
                        location.reload();
                    } catch (e) { window.upAlerts.notifyError(e.message); }
                },
            };
        })();
    </script>
    @endpush
</x-app-layout>
