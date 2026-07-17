# 13 — Integração com WhatsApp (fase futura)

> **Modelo recomendado:** `sonnet` (Sonnet 5).

> **Status:** especificação preliminar. Não faz parte do MVP; documentada para planejamento. Não bloqueia
> as demais entregas.

## 1. Objetivo

Automatizar a coleta de dados e notas fiscais de clientes e fornecedores via WhatsApp, complementando o
[formulário externo](11-formulario-externo.md). O contato recebe um fluxo que coleta os mesmos dados
(CNPJ, nome, valor, data, descrição do serviço) e a NF, gerando um card automaticamente.

## 2. Abordagem

- Provedor via **WhatsApp Business Cloud API** (Meta) ou agregador (ex.: Twilio, 360dialog, Z-API).
  Definir com o cliente conforme custo/facilidade.
- **Webhook** recebe mensagens; um serviço orquestra o fluxo conversacional (perguntas sequenciais) e o
  upload de mídia (NF como imagem/PDF).
- Reaproveitar a lógica de criação de card do formulário externo (mesma Action), variando a origem.

## 3. Considerações técnicas (quando implementar)

- Tabelas adicionais: `whatsapp_conversations`, `whatsapp_messages` (estado do fluxo por contato).
- Fila (`queue`) para processar mensagens/mídia de forma assíncrona.
- Reuso de `CreateCardFromExternal` para gerar o card (origem `whatsapp`).
- Casamento de empresa/fornecedor por CNPJ/CPF; validação dos dados coletados.
- Credenciais em `.env`; verificação de assinatura do webhook; rate limiting.
- Sem emojis nas mensagens automáticas do sistema.

## 4. Critérios de aceite (futuro)

- [ ] Contato consegue enviar dados + NF pelo WhatsApp e gerar um card automaticamente.
- [ ] Card criado com a NF anexada e empresa casada por CNPJ quando existir.
- [ ] Processamento assíncrono e resiliente (fila + retries).
