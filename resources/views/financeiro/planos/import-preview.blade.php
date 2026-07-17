<x-app-layout>
    <x-slot name="header"><h2 class="text-lg font-semibold text-brand-ink">Importar — {{ $plan->name }}</h2></x-slot>
    <x-page-header title="Pré-visualização da importação" :subtitle="count($rows).' linha(s) detectada(s)'" icon="fa-file-csv" />

    <form method="POST" action="{{ route('plans.import', $plan) }}" class="bg-white border border-hairline rounded-xl overflow-hidden">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-surface text-left text-steel">
                    <tr>
                        <th class="px-3 py-2 font-medium">Descrição</th>
                        <th class="px-3 py-2 font-medium">Categoria</th>
                        <th class="px-3 py-2 font-medium">Previsto</th>
                        <th class="px-3 py-2 font-medium">Realizado</th>
                        <th class="px-3 py-2 font-medium">Dt. prevista</th>
                        <th class="px-3 py-2 font-medium">Dt. realizada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td class="px-3 py-2"><input name="rows[{{ $i }}][description]" value="{{ $row['description'] }}" class="w-48 border-gray-300 rounded-md text-sm"></td>
                            <td class="px-3 py-2"><input name="rows[{{ $i }}][category]" value="{{ $row['category'] }}" class="w-32 border-gray-300 rounded-md text-sm"></td>
                            <td class="px-3 py-2"><input name="rows[{{ $i }}][estimated_value]" value="{{ $row['estimated_value'] }}" class="w-24 border-gray-300 rounded-md text-sm"></td>
                            <td class="px-3 py-2"><input name="rows[{{ $i }}][actual_value]" value="{{ $row['actual_value'] }}" class="w-24 border-gray-300 rounded-md text-sm"></td>
                            <td class="px-3 py-2"><input type="date" name="rows[{{ $i }}][estimated_date]" value="{{ $row['estimated_date'] }}" class="border-gray-300 rounded-md text-sm"></td>
                            <td class="px-3 py-2"><input type="date" name="rows[{{ $i }}][actual_date]" value="{{ $row['actual_date'] }}" class="border-gray-300 rounded-md text-sm"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-end gap-2 p-4 border-t border-hairline">
            <a href="{{ route('plans.edit', $plan) }}" class="rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep"><i class="fa-solid fa-check"></i> Confirmar importação</button>
        </div>
    </form>
</x-app-layout>
