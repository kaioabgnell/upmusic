/**
 * Máscara de dinheiro compartilhada pelos componentes Alpine (painel do card e Financeiro).
 *
 * "Dígito digitado é centavo, da direita pra esquerda", como num caixa eletrônico. É o contrário
 * do `$money` do @alpinejs/mask, que espera a pessoa digitar a própria vírgula: lá, dígitos puros
 * viram milhar ("1500" → "1.500" em vez de "15,00") e o número chega 100x maior ao salvar.
 *
 * Aqui não existe essa ambiguidade — digitar, colar "1.500,00" pronto ou apagar tudo dão sempre o
 * mesmo resultado previsível. Campo vazio continua vazio: "ainda não informado" e "R$ 0,00" são
 * estados diferentes no Financeiro.
 */
export function maskMoneyDigits(raw) {
    const digits = String(raw ?? '').replace(/\D/g, '');

    return digits === ''
        ? ''
        : (Number(digits) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Decimal cru do backend (ex.: `1234.5` ou `null`) -> string BR para exibição ("1.234,50").
 *
 * É o complemento de `maskMoneyDigits`: todo valor monetário que chega de uma resposta do servidor
 * é um número puro (JSON não tem formato BR), mas o campo mascarado só aceita e só produz strings
 * BR — sem essa conversão no ponto de entrada, o campo mostraria "1234.5" (ponto, sem milhar) até a
 * primeira edição. Vazio/null vira string vazia, não "0,00": são estados diferentes (ver
 * `maskMoneyDigits`).
 */
export function moneyFromDecimal(value) {
    if (value === null || value === undefined || value === '') return '';
    const num = Number(value);

    return Number.isNaN(num) ? '' : num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Caminho inverso de `moneyFromDecimal`: "1.234,50" (BR) -> 1234.5. Contas no cliente antes do round-trip ao servidor. */
export function parseMoneyBR(value) {
    if (value === null || value === undefined || value === '') return null;
    const num = Number(String(value).replace(/\./g, '').replace(',', '.'));

    return Number.isNaN(num) ? null : num;
}
