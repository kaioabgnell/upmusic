@php
    $displayTitle = $form->event ? "Envie os dados para o evento {$form->event->name}" : ($form->title ?? 'Envio de dados e nota fiscal');
@endphp
<x-public-layout>
    <x-slot name="title">{{ $displayTitle }} — UpMusic</x-slot>

    <div class="bg-white border border-hairline rounded-xl p-6 sm:p-8">
        <h1 class="text-xl font-semibold text-brand-ink">{{ $displayTitle }}</h1>
        <p class="text-sm text-steel mt-1">Preencha os dados abaixo e, se já tiver, anexe a nota fiscal. Nossa equipe fará a análise.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('external.form.submit', $form->token) }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf

            {{-- Honeypot anti-bot (oculto) --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

            <div>
                <label for="cnpj" class="text-sm font-medium text-brand-ink">CNPJ da empresa</label>
                <input id="cnpj" name="cnpj" value="{{ old('cnpj') }}" x-data x-mask="99.999.999/9999-99" placeholder="00.000.000/0000-00" required
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>

            <div>
                <label for="name" class="text-sm font-medium text-brand-ink">Empresa</label>
                <input id="name" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
            </div>

            <div>
                <label for="requester_name" class="text-sm font-medium text-brand-ink">Nome do solicitante</label>
                <input id="requester_name" name="requester_name" value="{{ old('requester_name') }}" required
                       class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                <p class="mt-1 text-xs text-steel">Quem solicitou o serviço — usado para saber a quem direcionar a cobrança.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="value" class="text-sm font-medium text-brand-ink">Valor (R$)</label>
                    <input id="value" name="value" value="{{ old('value') }}" inputmode="decimal" placeholder="0,00" required
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
                <div>
                    <label for="service_date" class="text-sm font-medium text-brand-ink">Data</label>
                    <input id="service_date" name="service_date" type="date" value="{{ old('service_date') }}" required
                           class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">
                </div>
            </div>

            <div>
                <label for="service_description" class="text-sm font-medium text-brand-ink">Descrição do serviço</label>
                <textarea id="service_description" name="service_description" rows="3" required
                          class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">{{ old('service_description') }}</textarea>
            </div>

            <div>
                <label for="payment_data" class="text-sm font-medium text-brand-ink">Dados para pagamento</label>
                <textarea id="payment_data" name="payment_data" rows="3" required placeholder="Banco, agência, conta e/ou chave PIX para recebimento"
                          class="mt-1 w-full border-gray-300 focus:border-brand-orange focus:ring-brand-orange rounded-md text-sm">{{ old('payment_data') }}</textarea>
            </div>

            {{-- Nota fiscal opcional: o envio pode chegar sem arquivo (a equipe cobra o documento depois).
                 O input usa `sr-only` (e não `hidden`/`display:none`) porque um campo com display:none não
                 recebe foco — o browser não consegue exibir a validação nativa nele e cancela o submit do
                 formulário inteiro com "An invalid form control ... is not focusable". --}}
            <div x-data="{ fileName: '' }">
                <label for="invoice" class="text-sm font-medium text-brand-ink">Nota fiscal <span class="font-normal text-steel">(opcional)</span></label>
                <label class="mt-1 flex flex-col items-center justify-center w-full px-6 py-6 border-2 border-dashed border-hairline rounded-lg cursor-pointer bg-surface hover:border-brand-orange transition-colors">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-steel"></i>
                    <span class="mt-2 text-sm text-steel"><span class="font-medium text-brand-ink">Clique para anexar</span> (PDF, JPG ou PNG · até 10MB)</span>
                    <span x-show="fileName" x-text="fileName" class="mt-2 text-xs font-medium text-brand-orange-deep"></span>
                    <input id="invoice" type="file" name="invoice" accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only"
                           @change="fileName = $event.target.files[0]?.name ?? ''">
                </label>
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-brand-orange px-4 py-3 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep transition-colors">
                <i class="fa-solid fa-paper-plane"></i> Enviar
            </button>
        </form>
    </div>
</x-public-layout>
