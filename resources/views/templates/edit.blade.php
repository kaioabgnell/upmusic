@php $board = $template->board; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Template: {{ $template->name }}</h2></x-slot>

    <x-page-header title="Editar template" :subtitle="$template->name" icon="fa-clone">
        <x-slot name="actions">
            <a href="{{ route('templates.index') }}" class="inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        </x-slot>
    </x-page-header>

    {{-- Dados do template --}}
    <form method="POST" action="{{ route('templates.update', $template) }}" class="bg-white border border-hairline rounded-xl p-6 space-y-5 max-w-3xl mb-6">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <x-input-label for="name" value="Nome" />
                <x-text-input id="name" name="name" :value="old('name', $template->name)" class="mt-1" required />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="board_id" value="Quadro alvo" />
                <x-form.select id="board_id" name="board_id" class="mt-1" required>
                    @foreach ($boards as $b)
                        <option value="{{ $b->id }}" @selected($template->board_id == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </x-form.select>
                <p class="text-xs text-steel mt-1">Alterar o quadro atualiza colunas e campos disponíveis.</p>
            </div>
        </div>
        <div>
            <x-input-label for="description" value="Descrição" />
            <textarea id="description" name="description" rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md shadow-sm text-sm">{{ old('description', $template->description) }}</textarea>
        </div>
        <div class="flex items-center justify-between border-t border-hairline pt-5">
            <label class="flex items-center gap-2 text-sm text-brand-ink">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked($template->active) class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Template ativo
            </label>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep"><i class="fa-solid fa-floppy-disk"></i> Salvar dados</button>
        </div>
    </form>

    {{-- Itens (cards do template) --}}
    <div class="bg-white border border-hairline rounded-xl p-6" id="tpl-editor" data-template="{{ $template->id }}">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-layer-group text-brand-orange mr-2"></i>Cards do template</h3>
            <span class="text-xs text-steel">Arraste para reordenar · alterações salvam automaticamente</span>
        </div>

        <div id="tpl-items" class="space-y-3">
            @foreach ($template->items as $item)
                <div class="tpl-item rounded-lg border border-hairline p-4 bg-surface" data-id="{{ $item->id }}" x-data="{ open: false }">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-grip-vertical text-steel cursor-move handle"></i>
                        <input type="text" value="{{ $item->title }}" placeholder="Título do card" class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="title">
                        <button type="button" @click="open = !open" class="text-steel hover:text-brand-ink text-sm" title="Detalhes"><i class="fa-solid fa-sliders"></i></button>
                        <button type="button" class="text-steel hover:text-red-600" onclick="tplItems.remove({{ $item->id }}, this)" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                    </div>

                    <div class="mt-3 pl-7 grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-steel">Coluna</label>
                            <select class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="default_column_id">
                                <option value="">Entrada padrão</option>
                                @foreach ($board->columns as $col)
                                    <option value="{{ $col->id }}" @selected($item->default_column_id == $col->id)>{{ $col->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-steel">Prazo</label>
                            <input type="date" value="{{ $item->due_date?->format('Y-m-d') }}" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="due_date">
                        </div>
                        <div>
                            <label class="text-xs text-steel">Prioridade</label>
                            <select class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="priority">
                                <option value="">—</option>
                                <option value="baixa" @selected($item->priority?->value === 'baixa')>Baixa</option>
                                <option value="media" @selected($item->priority?->value === 'media')>Média</option>
                                <option value="alta" @selected($item->priority?->value === 'alta')>Alta</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-steel">Responsável</label>
                            <select class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="default_assignee_id">
                                <option value="">—</option>
                                @foreach ($assignees as $u)
                                    <option value="{{ $u->id }}" @selected($item->default_assignee_id == $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div x-show="open" x-cloak class="mt-3 pl-7 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-steel">Descrição</label>
                            <textarea rows="2" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm" onchange="tplItems.save({{ $item->id }}, this)" data-field="description">{{ $item->description }}</textarea>
                        </div>
                        @if ($board->fields->count())
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-steel mb-2">Valores padrão dos campos</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($board->fields as $field)
                                        @php $val = $item->default_fields[$field->id] ?? ''; @endphp
                                        <div>
                                            <label class="text-xs text-steel">{{ $field->label }}</label>
                                            @switch($field->type->value)
                                                @case('textarea')
                                                    <textarea rows="1" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm tpl-field" data-fieldid="{{ $field->id }}" onchange="tplItems.save({{ $item->id }}, this)">{{ $val }}</textarea>
                                                    @break
                                                @case('select')
                                                    <select class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm tpl-field" data-fieldid="{{ $field->id }}" onchange="tplItems.save({{ $item->id }}, this)">
                                                        <option value="">—</option>
                                                        @foreach ($field->options ?? [] as $opt)
                                                            <option value="{{ $opt }}" @selected($val === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                    @break
                                                @case('checkbox')
                                                    <div class="mt-1"><input type="checkbox" class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange tpl-field" data-fieldid="{{ $field->id }}" @checked($val) onchange="tplItems.save({{ $item->id }}, this)"></div>
                                                    @break
                                                @case('date')
                                                    <input type="date" value="{{ $val }}" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm tpl-field" data-fieldid="{{ $field->id }}" onchange="tplItems.save({{ $item->id }}, this)">
                                                    @break
                                                @default
                                                    <input type="text" value="{{ $val }}" class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm tpl-field" data-fieldid="{{ $field->id }}" onchange="tplItems.save({{ $item->id }}, this)">
                                            @endswitch
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <form class="mt-4 flex items-center gap-2" onsubmit="event.preventDefault(); tplItems.add(this);">
            <input type="text" name="title" placeholder="Título do novo card" required class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            <button type="submit" class="inline-flex items-center gap-2 rounded-md border-2 border-dashed border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:border-brand-orange hover:text-brand-orange-deep">
                <i class="fa-solid fa-plus"></i> Adicionar card
            </button>
        </form>
    </div>

    @push('scripts')
    <script>
        window.tplItems = (function () {
            const tplId = document.getElementById('tpl-editor').dataset.template;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            async function api(url, method, body) {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body ? JSON.stringify(body) : null,
                });
                if (!res.ok) { const d = await res.json().catch(() => ({})); throw new Error(d.message || 'Erro ao salvar.'); }
                return res.json();
            }

            function payload(row) {
                const p = { default_fields: {} };
                row.querySelectorAll('[data-field]').forEach(el => { p[el.dataset.field] = el.value; });
                row.querySelectorAll('.tpl-field').forEach(el => {
                    p.default_fields[el.dataset.fieldid] = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
                });
                return p;
            }

            return {
                async save(id, el) {
                    const row = el.closest('.tpl-item');
                    try { await api(`{{ url('template-itens') }}/${id}`, 'PUT', payload(row)); window.upAlerts.notifySuccess('Card salvo.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async remove(id, btn) {
                    if (!await window.upAlerts.confirmAction({ text: 'Excluir este card do template?' })) return;
                    try { await api(`{{ url('template-itens') }}/${id}`, 'DELETE'); btn.closest('.tpl-item').remove(); window.upAlerts.notifySuccess('Card removido.'); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
                async add(form) {
                    const title = form.title.value.trim();
                    if (!title) return;
                    try { await api(`{{ url('templates') }}/${tplId}/itens`, 'POST', { title }); location.reload(); }
                    catch (e) { window.upAlerts.notifyError(e.message); }
                },
            };
        })();

        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('tpl-items');
            if (list) new window.Sortable(list, {
                handle: '.handle', animation: 150,
                onEnd: () => {
                    const order = [...list.children].map(el => el.dataset.id).filter(Boolean);
                    fetch(`{{ url('templates') }}/${document.getElementById('tpl-editor').dataset.template}/itens/reordenar`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                        body: JSON.stringify({ order }),
                    }).catch(() => window.upAlerts.notifyError('Erro ao reordenar.'));
                },
            });
        });
    </script>
    @endpush
</x-app-layout>
