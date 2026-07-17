# 00 — Visão Geral

> **Modelo recomendado:** `sonnet` (Sonnet 5).

## 1. Contexto

A Up Music recebe orçamentos de clientes de forma recorrente e precisa controlar todo o fluxo —
da solicitação até a finalização e prestação de contas. Hoje esse controle é feito em Excel, o que
dificulta colaboração, rastreabilidade e visão consolidada. O upMusic centraliza esse trabalho em
uma plataforma web colaborativa com quadro visual (Kanban) inspirado no Pipefy.

## 2. Objetivos

- Substituir o controle em Excel por uma plataforma centralizada e colaborativa.
- Organizar o fluxo de trabalho entre setores via quadro visual (Kanban), com usabilidade referência no Pipefy.
- Estruturar o planejamento financeiro comparando **previsto x realizado**.
- Manter um **banco de preços** dos serviços prestados, com evolução histórica por cliente.
- Automatizar a coleta de dados e notas fiscais via **formulário externo** e, futuramente, integração com WhatsApp.

## 3. Escopo (módulos)

1. **Autenticação e Controle de Acesso** — login, gestão de usuários, 3 níveis de permissão. → [04](04-autenticacao-e-permissoes.md)
2. **Cadastros Base** — Setores, Empresas, Fornecedores (PF/PJ). → [05](05-cadastros-base.md)
3. **Quadros / Departamentos** — cadastro de quadros (cada quadro = um departamento) + menu de quadros e processos. → [06](06-quadros-e-departamentos.md)
4. **Kanban de Processos** — colunas configuráveis, cards estilo Pipefy, vínculo de empresa, filtros, transição entre departamentos. → [07](07-kanban-e-cards.md)
5. **Templates de Cards** — conjuntos pré-definidos de cards e importação em lote. → [08](08-templates-de-cards.md)
6. **Planejamento Financeiro** — previsto x realizado, migração do Excel. → [09](09-planejamento-financeiro.md)
7. **Banco de Preços** — histórico de preços por cliente. → [10](10-banco-de-precos.md)
8. **Formulário Externo** — link público, upload de NF, geração automática de card. → [11](11-formulario-externo.md)
9. **Fluxo Completo de Processos** — etapas por setor. → [12](12-fluxo-de-processos.md)
10. **Integração WhatsApp** — automação de coleta (fase futura). → [13](13-integracao-whatsapp.md)

## 4. Glossário

| Termo | Definição |
|-------|-----------|
| **Setor / Departamento** | Unidade organizacional da Up Music (Orçamentos, Jurídico/Contratos, Financeiro, Conclusão). |
| **Quadro (Board)** | Painel Kanban que representa um departamento. Contém colunas e cards. |
| **Coluna / Etapa / Fase** | Estágio do processo dentro de um quadro (ex.: Solicitar Orçamento → Analisar → Conclusão). |
| **Card** | Item de trabalho (um orçamento, um contrato, um pagamento). Percorre as colunas e pode ir para outro quadro. |
| **Empresa** | Cliente da Up Music, vinculável a um card. |
| **Fornecedor** | Prestador PF ou PJ contratado no processo. |
| **Template de Card** | Conjunto pré-definido de cards para criação em lote. |
| **Previsto x Realizado** | Comparativo financeiro entre valores planejados e efetivados. |
| **Banco de Preços** | Registro histórico de preços de serviços por cliente. |
| **Formulário Externo** | Link público onde o cliente envia dados e NF, gerando um card. |

## 5. Personas / Níveis de acesso

| Perfil | Descrição |
|--------|-----------|
| **Admin** | Acesso total à configuração e aos dados. Gerencia usuários, quadros, cadastros e integrações. |
| **Coordenador** | Gestão de quadros, cards e equipe. Configura colunas, templates e acompanha o financeiro. |
| **Usuário** | Opera o fluxo conforme as permissões (quadros a que tem acesso): cria e move cards, comenta, anexa. |

Detalhamento das capacidades por perfil em [04-autenticacao-e-permissoes.md](04-autenticacao-e-permissoes.md).

## 6. Requisitos técnicos (resumo)

- Backend PHP 8.1 + Laravel 10; banco MySQL (`upmusic_local`, já criado).
- Arquitetura limpa em camadas, foco em performance (ver [01](01-arquitetura-tecnica.md)).
- Interface responsiva (desktop, tablet, celular) com layout moderno.
- Formulário externo público com upload de arquivos (notas fiscais).
- Font Awesome para ícones; SweetAlert2 para alertas; sem emojis. Cores da marca preto/laranja.

## 7. Decisões e premissas

Escolhas feitas para permitir execução imediata. Podem ser revistas pelo cliente.

- **Laravel 10** (não 11/12) por causa do PHP 8.1 do ambiente.
- **Frontend Blade + Tailwind + Alpine.js + Vite**, drag-and-drop com SortableJS. Escolha alinhada
  ao `DESIGN.md` (tokens Tailwind-friendly) e ao objetivo de performance sem SPA pesada.
  Livewire pode ser adotado depois se a interatividade exigir — não é bloqueante.
- **Quadro = Departamento.** O cadastro de "Setores" (3.2) alimenta a lista de departamentos; cada
  quadro referencia um setor. Ver [06](06-quadros-e-departamentos.md).
- **Cards com campos configuráveis** por quadro (estilo Pipefy), além dos campos fixos. Ver [07](07-kanban-e-cards.md).
- **Autenticação própria** (Laravel Breeze base, adaptada ao design) — sem SSO nesta fase.
- **Storage local** (`storage/app/public`) para anexos e NFs nesta fase; trocável para S3 via config.
- **WhatsApp** documentado como fase futura; não bloqueia o MVP.
- Sem testes de automação de front, carga, Playwright ou e2e (fora de escopo). Testes de backend (feature/unit) são opcionais e recomendados nos serviços críticos.

## 8. Fora de escopo (fase atual)

- Integração WhatsApp em produção (apenas especificada).
- SSO / login social.
- App mobile nativo (a web é responsiva).
- Emissão fiscal / integração contábil externa.
