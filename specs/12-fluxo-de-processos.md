# 12 — Fluxo Completo de Processos

> **Modelo recomendado:** `sonnet` (Sonnet 5).

O sistema contempla o fluxo completo abaixo, organizado por setor. As etapas são **configuráveis**
conforme a operação da Up Music — os valores abaixo são o padrão inicial (usado nos seeders de
[quadros e colunas](03-modelo-de-dados.md#5-seeders)).

## 1. Fluxo por setor (colunas padrão dos quadros)

| Setor (Quadro) | Etapas (colunas, em ordem) | Coluna final¹ |
|----------------|----------------------------|:-------------:|
| **Orçamentos** | Solicitação de Compra/Contratação → Coleta de Orçamentos → Aprovação do Orçamento | Aprovação do Orçamento |
| **Jurídico** | Confecção e Assinatura do Contrato | Confecção e Assinatura do Contrato |
| **Financeiro** | Recebimento da Nota Fiscal → Liberação para Pagamento → Pagamento Realizado | Pagamento Realizado |
| **Conclusão** | Prestação de Contas / Fotos de Comprovação → Finalizado | Finalizado |

¹ `is_final = true` na última coluna → habilita o botão "Enviar para outro departamento" (ver [07](07-kanban-e-cards.md#8-transição-entre-departamentos-envio-para-outro-quadro)).

> Observação: o fluxo do escopo lista Financeiro terminando em "Prestação de Contas / Fotos → Finalizado".
> Aqui essas duas etapas finais são modeladas no quadro **Conclusão** para deixar a transição entre
> departamentos explícita. Como as etapas são configuráveis, a Up Music pode mantê-las no Financeiro se
> preferir — basta ajustar as colunas no cadastro do quadro.

## 2. Transições entre departamentos (caminho típico)

```
Orçamentos ──(aprovado)──▶ Jurídico ──(contrato assinado)──▶ Financeiro ──(pago)──▶ Conclusão ──▶ Finalizado
```

- Cada transição parte da coluna final do quadro de origem, via botão "Enviar para outro departamento",
  levando o card ao quadro de destino (coluna de entrada). Histórico, anexos (NF, comprovantes) e valores
  são preservados.
- A **entrada** do fluxo também pode vir do **formulário externo** ([11](11-formulario-externo.md)),
  criando o card já na coluna de análise do quadro configurado (tipicamente Financeiro/Recebimento de NF
  ou Orçamentos, conforme configuração).

## 3. Mapeamento com o modelo de dados

- Setores → `setores`; quadros → `boards` (1 por setor no seed); etapas → `board_columns`
  (com `is_final`/`is_entry`).
- Movimentações registradas em `card_movements` (coluna e departamento) para auditoria/relatórios.
- Valores previsto/realizado dos cards alimentam o [Planejamento Financeiro](09-planejamento-financeiro.md);
  serviços/preços concluídos alimentam o [Banco de Preços](10-banco-de-precos.md).

## 4. Critérios de aceite

- [ ] Seeders criam os 4 quadros com as colunas padrão e flags corretas.
- [ ] Caminho Orçamentos → Jurídico → Financeiro → Conclusão executável via transições.
- [ ] Etapas configuráveis (adicionar/remover/reordenar colunas) sem quebrar cards existentes.
- [ ] Entrada por formulário externo cai na coluna de análise configurada.
