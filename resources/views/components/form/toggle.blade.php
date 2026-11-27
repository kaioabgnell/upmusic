{{--
    Chave liga/desliga (specs/28 §7.1). Ligado em brand-orange, que o DESIGN.md reserva para estado
    ativo. Os atributos extras vão para o <input> — use a forma longa do Alpine (`x-bind:checked`,
    `x-on:change`, `x-bind:disabled`), porque `:attr` em componente Blade vira expressão PHP.
    Quem usa envolve o componente num <label> com o texto, para o clique no texto alternar a chave.
--}}
<span class="relative inline-flex shrink-0 items-center">
    <input type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span aria-hidden="true"
          class="h-6 w-11 rounded-full bg-gray-300 transition-colors peer-checked:bg-brand-orange peer-focus-visible:ring-2 peer-focus-visible:ring-brand-orange peer-focus-visible:ring-offset-2 peer-disabled:opacity-50"></span>
    <span aria-hidden="true"
          class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
</span>
