<x-public-layout>
    <x-slot name="title">Minuta recebida — upMusic</x-slot>

    <div class="bg-white border border-hairline rounded-xl p-8 text-center">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-600 mb-4">
            <i class="fa-solid fa-check text-2xl"></i>
        </span>
        <h1 class="text-xl font-semibold text-brand-ink">Minuta recebida com sucesso!</h1>
        <p class="text-sm text-steel mt-2 max-w-sm mx-auto">
            Recebemos a minuta do contrato. Nossa equipe jurídica fará a análise. Obrigado!
        </p>
        <a href="{{ route('supplier.form.show', $form->token) }}"
           class="mt-6 inline-flex items-center gap-2 rounded-md border border-hairline px-4 py-2 text-sm font-medium text-brand-ink hover:bg-surface">
            <i class="fa-solid fa-plus"></i> Enviar outra minuta
        </a>
    </div>
</x-public-layout>
