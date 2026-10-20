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
