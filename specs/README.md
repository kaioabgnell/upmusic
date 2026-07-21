# upMusic — Especificações do Projeto

Sistema de Gestão de Processos Internos da Up Music. Este diretório contém a especificação
completa para execução do projeto, organizada por módulo. Leia na ordem abaixo.

| # | Documento | Conteúdo | Modelo |
|---|-----------|----------|--------|
| 00 | [Visão Geral](00-visao-geral.md) | Objetivos, escopo, glossário, personas, decisões e premissas | `sonnet` |
| 01 | [Arquitetura Técnica](01-arquitetura-tecnica.md) | Stack, arquitetura limpa em camadas, estrutura de pastas, performance | `sonnet` |
| 02 | [Design System](02-design-system.md) | Cores, tipografia, Font Awesome, SweetAlert2, logos, layout Pipefy | `sonnet` |
| 03 | [Modelo de Dados](03-modelo-de-dados.md) | Schema MySQL completo, relacionamentos, ERD, migrations | `sonnet` |
| 04 | [Autenticação e Permissões](04-autenticacao-e-permissoes.md) | Login, usuários, 3 níveis de acesso, policies | `sonnet` |
| 05 | [Cadastros Base](05-cadastros-base.md) | Setores, Empresas, Fornecedores (PF/PJ) | `sonnet` |
| 06 | [Quadros e Departamentos](06-quadros-e-departamentos.md) | Menu de quadros/processos, quadro = departamento | `opus` |
| 07 | [Kanban e Cards](07-kanban-e-cards.md) | Colunas configuráveis, cards, detalhe, filtros, transição entre quadros | `opus` |
| 08 | [Templates de Cards](08-templates-de-cards.md) | Conjuntos pré-definidos de cards e importação | `opus` |
| 09 | [Planejamento Financeiro](09-planejamento-financeiro.md) | Previsto x Realizado, migração do Excel | `opus` |
| 10 | [Banco de Preços](10-banco-de-precos.md) | Preços de serviços e evolução histórica por cliente | `sonnet` |
| 11 | [Formulário Externo](11-formulario-externo.md) | Link público, upload de NF, geração de card | `sonnet` |
| 12 | [Fluxo de Processos](12-fluxo-de-processos.md) | Fluxo completo por setor da Up Music | `sonnet` |
| 13 | [Integração WhatsApp](13-integracao-whatsapp.md) | Automação de coleta (fase futura) | `sonnet` |
| 14 | [Kanban Reativo e Carregamento Assíncrono](14-kanban-reatividade-assincrona.md) | Melhoria pós-entrega: sem reload + carregamento desacoplado dos cards | `opus` |
| 15 | [Banco de Preços por Categoria](15-banco-de-precos-por-categoria.md) | Melhoria pós-entrega: histórico de preços por categoria de fornecedor (substitui Serviço+Cliente) | `sonnet` |
| 16 | [Captura Rápida de Orçamentos e NFs](16-captura-rapida-orcamentos-nf.md) | WhatsApp → Card de custo zero via PWA + Web Share Target (alternativa à spec 13) | `opus` |
| 17 | [Fluxo de Aprovação de Etapas](17-fluxo-de-aprovacao-de-etapas.md) | Melhoria pós-entrega: coluna com aprovador(es) dedicado(s) — aprovado avança, reprovado arquiva | `opus` |
| 18 | [Link Direto e Compartilhamento de Card](18-link-direto-e-compartilhamento-de-card.md) | Melhoria pós-entrega: URL própria por card (`/quadros/{board}/card/{card}`), exibição do `#ID` e botão "Compartilhar Card" | `sonnet` |
| — | [CHECKLIST](CHECKLIST.md) | **Checklist de desenvolvimento — atualizar a cada entrega** | `sonnet` |

## Modelo por fase

Convenção de qual modelo do Claude usar ao implementar cada bloco:

- **Specs 00–05 → `sonnet` (Sonnet 5):** fundação e CRUDs bem-especificados (setup, design, banco, auth, cadastros).
- **Specs 06–09 → `opus` (Opus 4.8):** módulos de maior complexidade (quadros/config, Kanban, templates, financeiro).
- **Specs 10–13 + CHECKLIST → `sonnet` (Sonnet 5):** banco de preços, formulário externo, fluxo, WhatsApp e acompanhamento.

Trocar com `/model sonnet` ou `/model opus` no início de cada bloco.

## Como usar estas specs

1. Cada módulo traz: objetivo, requisitos funcionais, regras de negócio, dados envolvidos,
   telas/UX, rotas e critérios de aceite.
2. O [modelo de dados](03-modelo-de-dados.md) é a fonte da verdade das tabelas e relacionamentos.
   Fluxos de criação seguem a ordem de dependência descrita lá.
3. O [CHECKLIST](CHECKLIST.md) organiza a implementação em fases; marque itens conforme entrega.
4. Regras transversais de design estão em [02-design-system.md](02-design-system.md) e em
   [`../DESIGN.md`](../DESIGN.md).
