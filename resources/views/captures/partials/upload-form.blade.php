{{-- Área de arrastar/soltar + seletor de arquivo (Canal B — funciona em qualquer plataforma, sem PWA/Atalho). --}}
<form method="POST" action="{{ route('captures.upload') }}" enctype="multipart/form-data"
      x-data="{
          dragging: false,
          files: [],
          sending: false,
          addFiles(fileList) {
              this.files = Array.from(fileList);
          },
      }"
      @submit="sending = true">
    @csrf

    <label
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; addFiles($event.dataTransfer.files); $refs.input.files = $event.dataTransfer.files"
        :class="dragging ? 'border-brand-orange bg-brand-orange/5' : 'border-hairline'"
        class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-8 text-center cursor-pointer transition-colors">
        <i class="fa-solid fa-file-arrow-up text-2xl text-steel"></i>
        <p class="text-sm text-brand-ink font-medium">Arraste os arquivos aqui ou clique para selecionar</p>
        <p class="text-xs text-steel">PDF, JPG, PNG ou WEBP &middot; até 10 MB cada</p>
        <input type="file" name="arquivos[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"
               x-ref="input" class="hidden" @change="addFiles($event.target.files)">
    </label>

    <ul x-show="files.length" x-cloak class="mt-3 space-y-1">
        <template x-for="(f, i) in files" :key="i">
            <li class="flex items-center gap-2 text-xs text-steel">
                <i class="fa-solid fa-paperclip"></i>
                <span x-text="f.name"></span>
            </li>
        </template>
    </ul>

    {{-- $errors->get('arquivos.*') devolveria um array agrupado por chave (arquivos.0, arquivos.1...),
         incompatível com x-input-error (que espera uma lista simples de strings) — este form só tem
         esse campo, então listar todos os erros é equivalente e evita o wildcard. --}}
    <x-input-error :messages="$errors->all()" class="mt-2" />

    <button type="submit" :disabled="!files.length || sending"
            class="mt-4 inline-flex items-center gap-2 rounded-md bg-brand-orange px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-orange-deep disabled:opacity-50 transition-colors">
        <i class="fa-solid fa-paper-plane"></i>
        <span x-text="sending ? 'Enviando...' : 'Enviar'"></span>
    </button>
</form>
