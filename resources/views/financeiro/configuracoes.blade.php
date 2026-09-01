<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Configurações do Financeiro</h2></x-slot>

    <x-page-header title="Configurações do Financeiro" icon="fa-sliders"
        subtitle="Grupos de pagamento e catálogo de descrições usados na planilha de cada evento." />

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ session('error') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Grupos de pagamento (as colunas "PAGO POR" da planilha) --}}
        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-wallet text-brand-orange mr-2"></i>Grupos de pagamento</h3>
                <p class="text-xs text-steel mt-0.5">Substituem as colunas fixas "Caixa Evento / Sócio / Ticketeira / Bar".</p>
            </div>

            <table class="min-w-full text-sm divide-y divide-hairline">
                <tbody class="divide-y divide-hairline">
                    @foreach ($sources as $source)
                        <tr>
                            <td class="px-4 py-2.5" colspan="2">
                                <form method="POST" action="{{ route('finance.sources.update', $source) }}"
                                      class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $source->name }}" required maxlength="80"
                                           class="flex-1 min-w-[8rem] border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                    <select name="kind" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        @foreach ($kinds as $value => $label)
                                            <option value="{{ $value }}" @selected($source->kind->value === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="user_id" class="border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                                        <option value="">Sem usuário</option>
                                        @foreach ($usuarios as $usuario)
                                            <option value="{{ $usuario->id }}" @selected($source->user_id === $usuario->id)>{{ $usuario->name }}</option>
                                        @endforeach
                                    </select>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-steel">
                                        <input type="checkbox" name="active" value="1" @checked($source->active)
                                               class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Ativo
                                    </label>
                                    <button type="submit" class="rounded-md border border-hairline px-2.5 py-1.5 text-xs font-medium text-brand-ink hover:bg-surface">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="px-2 py-2.5 text-right">
                                <form method="POST" action="{{ route('finance.sources.destroy', $source) }}"
                                      data-confirm="Excluir o grupo {{ $source->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('finance.sources.store') }}" class="p-4 border-t border-hairline flex flex-wrap items-end gap-2">
                @csrf
                <div class="flex-1 min-w-[10rem]">
                    <label class="text-xs text-steel">Novo grupo</label>
                    <input type="text" name="name" required maxlength="80" placeholder="Ex.: Sócio 3"
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
                <div>
                    <label class="text-xs text-steel">Natureza</label>
                    <select name="kind" class="mt-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                        @foreach ($kinds as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="active" value="1">
                <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
                    <i class="fa-solid fa-plus"></i> Adicionar
                </button>
            </form>
        </div>

        {{-- Catálogo de descrições (os 168 itens do arquivo modelo) --}}
        <div class="bg-white border border-hairline rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-hairline">
                <h3 class="font-semibold text-brand-ink"><i class="fa-solid fa-list-check text-brand-orange mr-2"></i>Catálogo de itens</h3>
                <p class="text-xs text-steel mt-0.5">Alimenta o autocomplete da coluna Descrição, por categoria.</p>
            </div>

            <form method="GET" class="p-4 border-b border-hairline">
                <label class="text-xs text-steel">Categoria</label>
                <x-form.select name="categoria_id" class="mt-1" onchange="this.form.submit()">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected($categoriaId == $categoria->id)>
                            {{ $categoria->nome }} ({{ $categoria->item_presets_count }})
                        </option>
                    @endforeach
                </x-form.select>
            </form>

            <div class="max-h-[24rem] overflow-y-auto divide-y divide-hairline">
                @forelse ($presets as $preset)
                    <div class="flex items-center gap-2 px-4 py-2">
                        <form method="POST" action="{{ route('finance.presets.update', $preset) }}" class="flex flex-1 items-center gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="description" value="{{ $preset->description }}" required maxlength="180"
                                   class="flex-1 border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                            <label class="inline-flex items-center gap-1.5 text-xs text-steel">
                                <input type="checkbox" name="active" value="1" @checked($preset->active)
                                       class="rounded border-gray-300 text-brand-orange focus:ring-brand-orange"> Ativo
                            </label>
                            <button type="submit" class="rounded-md border border-hairline px-2.5 py-1.5 text-xs text-brand-ink hover:bg-surface">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('finance.presets.destroy', $preset) }}"
                              data-confirm="Remover “{{ $preset->description }}” do catálogo?">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-steel hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-steel">Nenhum item nesta categoria.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('finance.presets.store') }}" class="p-4 border-t border-hairline flex items-end gap-2">
                @csrf
                <input type="hidden" name="fornecedor_categoria_id" value="{{ $categoriaId }}">
                <div class="flex-1">
                    <label class="text-xs text-steel">Novo item</label>
                    <input type="text" name="description" required maxlength="180" placeholder="Ex.: TENDA 12X12"
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:bg-black">
                    <i class="fa-solid fa-plus"></i> Adicionar
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
