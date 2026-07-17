# 10 — Banco de Preços dos Serviços

> **Modelo recomendado:** `sonnet` (Sonnet 5).

## 1. Objetivo

Registrar os preços dos serviços prestados e visualizar a evolução histórica dos preços por cliente.

## 2. Estrutura

- **Serviço** (`services`): nome, descrição, categoria, unidade (diária, unidade, hora), ativo.
- **Registro de preço** (`service_prices`): histórico de preços de um serviço, com preço, data de
  referência, empresa (cliente), fornecedor (opcional), origem em card (opcional), observação e autor.

## 3. Funcionalidades

- **CRUD de serviços.**
- **Registrar preço:** para um serviço, informar preço, data de referência, empresa (cliente) e,
  opcionalmente, fornecedor/observação. Cada registro é um ponto na série histórica.
- **Evolução histórica por cliente:**
  - Ao selecionar serviço + empresa, listar os preços por data (mais recente ao mais antigo) e mostrar a
    **variação** entre registros (absoluta e %).
  - Gráfico de linha da evolução do preço ao longo do tempo (por cliente). Sem emojis; ícones Font Awesome.
- **Comparação entre clientes:** para um serviço, tabela do último preço por cliente.
- **Origem automática (opcional):** ao concluir um card com serviço/valor definido, oferecer registrar o
  preço no banco de preços (vinculando `card_id`).
- **Filtros:** por serviço, empresa, fornecedor, período.

## 4. Regras de negócio

- `reference_date` obrigatória; ordenar séries por ela.
- Variação calculada no serviço (`PriceHistoryService`) a partir da série ordenada.
- Preço em `DECIMAL(15,2)`, BRL.
- Índice `(service_id, empresa_id, reference_date)` garante consulta eficiente da evolução por cliente.

## 5. Rotas (web)

```
Resource /precos/servicos             services.*         role:admin,coordenador
POST     /precos/servicos/{service}/registros   prices.store
PUT      /precos/registros/{price}              prices.update
DELETE   /precos/registros/{price}              prices.destroy
GET      /precos/evolucao                       prices.history   (serviço+empresa → série + variação + gráfico)
```

## 6. Critérios de aceite

- [ ] CRUD de serviços.
- [ ] Registro de múltiplos preços por serviço, com data, empresa e fornecedor opcional.
- [ ] Evolução histórica por cliente com variação absoluta e %.
- [ ] Gráfico de evolução por cliente e comparação do último preço entre clientes.
- [ ] Consulta usando o índice composto (sem varredura ineficiente).
- [ ] Registro opcional de preço a partir de card concluído.
