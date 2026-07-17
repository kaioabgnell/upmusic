@php use App\Domain\Enums\FieldType; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Configurar: {{ $board->name }}</h2></x-slot>

    <x-page-header title="Configurar quadro" :subtitle="$board->name" icon="fa-gear">
        <x-slot name="actions">
            <a href="{{ route('boards.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" id="board-config" data-board="{{ $board->id }}">
        {{-- Colunas --}}
        <section class="lg:col-span-2 bg-white border border-hairline rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-list-check text-brand-orange mr-2"></i>Etapas (colunas)</h3>
                <span class="text-xs text-steel">Arraste para reordenar</span>
            </div>

            <div id="columns-list" class="space-y-2">
                @foreach ($board->columns as $column)
                    <div class="column-row flex items-center gap-3 rounded-lg border border-hairline p-3 bg-surface" data-id="{{ $column->id }}">
                        <i class="fa-solid fa-grip-vertical text-steel cursor-move handle"></i>
                        <input type="color" value="{{ $column->color ?? '#e5e5e5' }}" class="h-8 w-10 rounded border border-hairline cursor-pointer" onchange="boardConfig.saveColumn({{ $column->id }}, this)" data-field="color">
                        <input type="text" value="{{ $column->name }}" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="boardConfig.saveColumn({{ $column->id }}, this)" data-field="name">
                        <label class="flex items-center gap-1 text-xs text-steel whitespace-nowrap">
                            <input type="checkbox" @checked($column->is_entry) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange" onchange="boardConfig.saveColumn({{ $column->id }}, this)" data-field="is_entry"> entrada
                        </label>
                        <label class="flex items-center gap-1 text-xs text-steel whitespace-nowrap">
                            <input type="checkbox" @checked($column->is_final) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange" onchange="boardConfig.saveColumn({{ $column->id }}, this)" data-field="is_final"> final
                        </label>
                        <button type="button" class="text-steel hover:text-red-600" title="Excluir" onclick="boardConfig.deleteColumn({{ $column->id }}, this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                @endforeach
            </div>

            {{-- Adicionar nova coluna (sempre ao final, estilo Pipefy) --}}
            <form class="mt-4 flex items-center gap-2" onsubmit="event.preventDefault(); boardConfig.addColumn(this);">
                <input type="text" name="name" placeholder="Nome da nova etapa" required class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                <button type="submit" class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                    <i class="fa-solid fa-plus"></i> Adicionar nova coluna
                </button>
            </form>
        </section>

        {{-- Acesso --}}
        <section class="bg-white border border-hairline rounded-xl p-6">
            <h3 class="font-semibold text-brand-ink mb-4"><i class="fa-solid fa-user-lock text-brand-orange mr-2"></i>Acesso ao quadro</h3>
            <p class="text-xs text-steel mb-3">Usuários com perfil "Usuário" só veem os quadros marcados. Admin e Coordenador veem todos.</p>
            <form method="POST" action="{{ route('boards.access', $board) }}">
                @csrf @method('PUT')
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @foreach ($users as $u)
                        <label class="flex items-center gap-2 rounded-md border border-hairline px-3 py-2 cursor-pointer hover:bg-surface">
                            <input type="checkbox" name="users[]" value="{{ $u->id }}" @checked(in_array($u->id, $accessIds)) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange">
                            <span class="text-sm text-brand-ink">{{ $u->name }}</span>
                            <span class="ml-auto text-[11px] text-steel uppercase">{{ $u->role->value }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="mt-4 w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar acesso
                </button>
            </form>
        </section>

        {{-- Campos do card --}}
        <section class="lg:col-span-3 bg-white border border-hairline rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-rectangle-list text-brand-orange mr-2"></i>Campos do card</h3>
                <span class="text-xs text-steel">Arraste para reordenar</span>
            </div>

            <div id="fields-list" class="space-y-2">
                @foreach ($board->fields as $field)
                    <div class="field-row flex flex-wrap items-center gap-3 rounded-lg border border-hairline p-3 bg-surface" data-id="{{ $field->id }}">
                        <i class="fa-solid fa-grip-vertical text-steel cursor-move handle"></i>
                        <input type="text" value="{{ $field->label }}" class="flex-1 min-w-[160px] border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="boardConfig.saveField({{ $field->id }}, this)" data-field="label">
                        <select class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="boardConfig.saveField({{ $field->id }}, this)" data-field="type">
                            @foreach (FieldType::options() as $value => $label)
                                <option value="{{ $value }}" @selected($field->type->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" value="{{ $field->options ? implode(', ', $field->options) : '' }}" placeholder="Opções (vírgula) — só para Seleção" class="flex-1 min-w-[160px] border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="boardConfig.saveField({{ $field->id }}, this)" data-field="options">
                        <label class="flex items-center gap-1 text-xs text-steel whitespace-nowrap">
                            <input type="checkbox" @checked($field->required) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange" onchange="boardConfig.saveField({{ $field->id }}, this)" data-field="required"> obrigatório
                        </label>
                        <button type="button" class="text-steel hover:text-red-600" title="Excluir" onclick="boardConfig.deleteField({{ $field->id }}, this)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                @endforeach
            </div>

            <form class="mt-4 flex flex-wrap items-center gap-2" onsubmit="event.preventDefault(); boardConfig.addField(this);">
                <input type="text" name="label" placeholder="Rótulo do novo campo" required class="flex-1 min-w-[200px] border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                <select name="type" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                    @foreach (FieldType::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                    <i class="fa-solid fa-plus"></i> Adicionar campo
                </button>
            </form>
        </section>
    </div>

    @push('scripts')
    <script>
        window.boardConfig = (function () {
            const boardId = document.getElementById('board-config').dataset.board;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            async function api(url, method, body) {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body ? JSON.stringify(body) : null,
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.message || 'Erro ao salvar.');
                }
                return res.status === 204 ? null : res.json();
            }

            function rowPayload(row, selector) {
                const p = {};
                row.querySelectorAll('[data-field]').forEach(el => {
                    const f = el.dataset.field;
                    if (el.type === 'checkbox') p[f] = el.checked;
                    else if (f === 'options') p[f] = el.value.split(',').map(s => s.trim()).filter(Boolean);
                    else p[f] = el.value;
                });
                return p;
            }

            return {
                async saveColumn(id, el) {
                    const row = el.closest('.column-row');
                    try { await api(`{{ url('colunas') }}/${id}`, 'PUT', rowPayload(row)); window.upAlerts.notifySuccess('Etapa salva.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async deleteColumn(id, btn) {
                    if (!await window.upAlerts.confirmAction({ text: 'Excluir esta etapa?' })) return;
                    try { await api(`{{ url('colunas') }}/${id}`, 'DELETE'); btn.closest('.column-row').remove(); window.upAlerts.notifySuccess('Etapa excluída.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async addColumn(form) {
                    const name = form.name.value.trim();
                    if (!name) return;
                    try { await api(`{{ url('quadros') }}/${boardId}/colunas`, 'POST', { name }); location.reload(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async saveField(id, el) {
                    const row = el.closest('.field-row');
                    try { await api(`{{ url('campos') }}/${id}`, 'PUT', rowPayload(row)); window.upAlerts.notifySuccess('Campo salvo.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async deleteField(id, btn) {
                    if (!await window.upAlerts.confirmAction({ text: 'Excluir este campo?' })) return;
                    try { await api(`{{ url('campos') }}/${id}`, 'DELETE'); btn.closest('.field-row').remove(); window.upAlerts.notifySuccess('Campo excluído.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async addField(form) {
                    const label = form.label.value.trim();
                    if (!label) return;
                    try { await api(`{{ url('quadros') }}/${boardId}/campos`, 'POST', { label, type: form.type.value }); location.reload(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                reorder(listEl, url) {
                    const order = [...listEl.children].map(el => el.dataset.id).filter(Boolean);
                    api(url, 'POST', { order }).catch(e => window.upAlerts.notifyError(e.message));
                },
            };
        })();

        document.addEventListener('DOMContentLoaded', () => {
            const cols = document.getElementById('columns-list');
            const fields = document.getElementById('fields-list');
            if (cols) new window.Sortable(cols, { handle: '.handle', animation: 150, onEnd: () => boardConfig.reorder(cols, `{{ url('quadros') }}/${cols.closest('#board-config').dataset.board}/colunas/reordenar`) });
            if (fields) new window.Sortable(fields, { handle: '.handle', animation: 150, onEnd: () => boardConfig.reorder(fields, `{{ url('quadros') }}/${document.getElementById('board-config').dataset.board}/campos/reordenar`) });
        });
    </script>
    @endpush
</x-app-layout>
