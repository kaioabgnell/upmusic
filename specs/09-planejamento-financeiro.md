# 09 — Planejamento Financeiro (Previsto x Realizado)

> **Modelo recomendado:** `opus` (Opus 4.8) — módulo de maior complexidade.

## 1. Objetivo

Migrar o controle hoje mantido em Excel para o sistema, registrando valores previstos e realizados e
oferecendo comparativo previsto x realizado para acompanhamento financeiro.

## 2. Estrutura

- **Plano financeiro** (`financial_plans`): agrupador (ex.: "Evento X 2026"), com empresa (opcional) e
  período (ano/mês opcionais).
- **Lançamentos** (`financial_entries`): linhas com descrição, categoria, valor previsto, valor realizado,
  datas prevista/realizada. Podem ter **origem em um card** (`card_id`) e/ou uma empresa.

## 3. Funcionalidades

- **CRUD de planos** e de **lançamentos** (inline, tipo planilha, para facilitar a migração do Excel).
- **Importação do Excel:** upload de CSV/XLSX mapeando colunas (descrição, categoria, previsto, realizado,
  datas). Validação e pré-visualização antes de gravar. (Se XLSX, usar lib como `maatwebsite/excel`.)
- **Vínculo com cards:** ao vincular um card, sincronizar/importar `estimated_value`/`actual_value` do card
  como sugestão de previsto/realizado (edição livre depois).
- **Comparativo previsto x realizado:**
  - Por plano: totais de previsto, realizado, **desvio** (realizado − previsto) e **% de realização**.
  - Por categoria e por empresa (agregações SUM no banco).
  - Visão consolidada (dashboard) com totais e gráfico simples (barras previsto vs realizado). Sem emojis;
    ícones Font Awesome.
- **Filtros:** por empresa, período, categoria, plano.
- **Exportação:** CSV do comparativo (opcional).

## 4. Regras de negócio

- Desvio e % calculados no banco/serviço (`FinancialReportService`), não no Blade.
- Valores em `DECIMAL(15,2)`, moeda BRL, máscara na UI.
- Realizado pode superar previsto (desvio negativo destacado).
- Excluir plano remove seus lançamentos (cascade); confirmação SweetAlert2.

## 5. Rotas (web)

```
Resource /financeiro/planos          plans.*            role:admin,coordenador
Resource embutido lançamentos:
POST   /financeiro/planos/{plan}/lancamentos     entries.store
PUT    /financeiro/lancamentos/{entry}           entries.update
DELETE /financeiro/lancamentos/{entry}           entries.destroy
POST   /financeiro/planos/{plan}/importar        plans.import      (Excel/CSV)
GET    /financeiro/comparativo                   financial.report  (previsto x realizado, com filtros)
```

## 6. Critérios de aceite

- [ ] CRUD de planos e lançamentos com edição rápida (estilo planilha).
- [ ] Importação de Excel/CSV com pré-visualização e validação.
- [ ] Comparativo previsto x realizado com desvio e % por plano, categoria e empresa.
- [ ] Filtros por empresa/período/categoria.
- [ ] Agregações calculadas no banco (sem N+1 nem cálculo em loop no Blade).
- [ ] Vínculo opcional de lançamento a card.
