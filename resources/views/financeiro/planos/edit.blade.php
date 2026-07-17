@php $money = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Plano: {{ $plan->name }}</h2></x-slot>

    <x-page-header title="Editar plano" :subtitle="$plan->name" icon="fa-chart-line">
        <x-slot name="actions">
            <a href="{{ route('financial.report', ['plan_id' => $plan->id]) }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-scale-balanced"></i> Comparativo</a>
            <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        </x-slot>
    </x-page-header>

    {{-- Dados do plano --}}
    <form method="POST" action="{{ route('plans.update', $plan) }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-2xl mb-6">
        @csrf @method('PUT')
        @include('financeiro.planos._form-fields')
        <div class="flex justify-end border-t border-hairline pt-5">
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep"><i class="fa-solid fa-floppy-disk"></i> Salvar dados</button>
        </div>
    </form>

    {{-- Importar CSV --}}
    <div class="bg-white border border-hairline rounded-xl p-4 mb-6">
        <form method="POST" action="{{ route('plans.import.preview', $plan) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
            @csrf
            <div class="text-sm text-brand-ink"><i class="fa-solid fa-file-csv text-brand-orange mr-1"></i> Importar do Excel/CSV</div>
            <input type="file" name="file" accept=".csv,.txt" required class="text-sm">
            <button type="submit" class="rounded-md border border-hairline px-3 py-1.5 text-sm font-medium text-brand-ink hover:bg-surface">Pré-visualizar</button>
            <span class="text-xs text-steel">Colunas: descrição; categoria; previsto; realizado; data prevista; data realizada (1ª linha = cabeçalho)</span>
        </form>
    </div>

    {{-- Lançamentos (planilha) --}}
    <div class="bg-white border border-hairline rounded-xl overflow-hidden" id="fin-editor" data-plan="{{ $plan->id }}">
        <div class="px-4 py-3 border-b border-hairline flex items-center justify-between">
            <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-table-list text-brand-orange mr-2"></i>Lançamentos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface text-left text-steel">
                    <tr>
                        <th class="px-3 py-2 font-medium">Descrição</th>
                        <th class="px-3 py-2 font-medium">Categoria</th>
                        <th class="px-3 py-2 font-medium text-right">Previsto</th>
                        <th class="px-3 py-2 font-medium text-right">Realizado</th>
                        <th class="px-3 py-2 font-medium">Dt. prev.</th>
                        <th class="px-3 py-2 font-medium">Dt. real.</th>
                        <th class="px-3 py-2 font-medium">Card</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="fin-rows" class="divide-y divide-hairline">
                    @foreach ($plan->entries as $entry)
                        <tr class="fin-row" data-id="{{ $entry->id }}">
                            <td class="px-3 py-2"><input type="text" value="{{ $entry->description }}" class="w-48 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="description" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2"><input type="text" value="{{ $entry->category }}" class="w-32 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="category" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2"><input type="text" value="{{ $money($entry->estimated_value) }}" class="w-28 text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm fin-est" data-field="estimated_value" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2"><input type="text" value="{{ $money($entry->actual_value) }}" class="w-28 text-right border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm fin-act" data-field="actual_value" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2"><input type="date" value="{{ $entry->estimated_date?->format('Y-m-d') }}" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="estimated_date" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2"><input type="date" value="{{ $entry->actual_date?->format('Y-m-d') }}" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="actual_date" onchange="finEntries.save({{ $entry->id }}, this)"></td>
                            <td class="px-3 py-2">
                                <select class="w-40 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" data-field="card_id" onchange="finEntries.save({{ $entry->id }}, this)">
                                    <option value="">—</option>
                                    @foreach ($cards as $c)
                                        <option value="{{ $c->id }}" @selected($entry->card_id == $c->id)>{{ Str::limit($c->title, 30) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2"><button type="button" class="text-steel hover:text-red-600" onclick="finEntries.remove({{ $entry->id }}, this)"><i class="fa-solid fa-trash"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-surface font-semibold text-brand-ink">
                    <tr>
                        <td class="px-3 py-2" colspan="2">Totais</td>
                        <td class="px-3 py-2 text-right" id="tot-est">R$ {{ $money($totals['estimated']) }}</td>
                        <td class="px-3 py-2 text-right" id="tot-act">R$ {{ $money($totals['actual']) }}</td>
                        <td class="px-3 py-2 text-right" colspan="4">Desvio: <span id="tot-dev" class="{{ ($totals['actual']-$totals['estimated']) < 0 ? 'text-red-600' : 'text-green-600' }}">R$ {{ $money($totals['actual'] - $totals['estimated']) }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <form class="p-4 border-t border-hairline flex items-center gap-2" onsubmit="event.preventDefault(); finEntries.add(this);">
            <input type="text" name="description" placeholder="Descrição do novo lançamento" required class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            <button type="submit" class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep"><i class="fa-solid fa-plus"></i> Adicionar lançamento</button>
        </form>
    </div>

    @push('scripts')
    <script>
        window.finEntries = (function () {
            const planId = document.getElementById('fin-editor').dataset.plan;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const parseMoney = (v) => { const n = parseFloat(String(v).replace(/\./g, '').replace(',', '.')); return isNaN(n) ? 0 : n; };
            const fmt = (n) => 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            async function api(url, method, body) {
                const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: body ? JSON.stringify(body) : null });
                if (!res.ok) { const d = await res.json().catch(() => ({})); throw new Error(d.message || 'Erro ao salvar.'); }
                return res.json();
            }
            function payload(row) { const p = {}; row.querySelectorAll('[data-field]').forEach(el => p[el.dataset.field] = el.value); return p; }
            function recalc() {
                let est = 0, act = 0;
                document.querySelectorAll('#fin-rows .fin-row').forEach(r => {
                    est += parseMoney(r.querySelector('.fin-est').value);
                    act += parseMoney(r.querySelector('.fin-act').value);
                });
                document.getElementById('tot-est').textContent = fmt(est);
                document.getElementById('tot-act').textContent = fmt(act);
                const dev = document.getElementById('tot-dev');
                dev.textContent = fmt(act - est);
                dev.className = (act - est) < 0 ? 'text-red-600' : 'text-green-600';
            }
            return {
                async save(id, el) {
                    const row = el.closest('.fin-row');
                    try { await api(`{{ url('financeiro/lancamentos') }}/${id}`, 'PUT', payload(row)); recalc(); window.upAlerts.notifySuccess('Salvo.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async remove(id, btn) {
                    if (!await window.upAlerts.confirmAction({ text: 'Excluir este lançamento?' })) return;
                    try { await api(`{{ url('financeiro/lancamentos') }}/${id}`, 'DELETE'); btn.closest('.fin-row').remove(); recalc(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async add(form) {
                    const description = form.description.value.trim();
                    if (!description) return;
                    try { await api(`{{ url('financeiro/planos') }}/${planId}/lancamentos`, 'POST', { description }); location.reload(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
            };
        })();
    </script>
    @endpush
</x-app-layout>
