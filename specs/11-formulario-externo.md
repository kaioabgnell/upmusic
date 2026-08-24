# 11 — Formulário Externo para Clientes (link público)

> **Modelo recomendado:** `sonnet` (Sonnet 5).

## 1. Objetivo

Link enviado aos clientes para preenchimento de dados e envio de notas fiscais. O cliente informa os
dados e anexa a NF; ao enviar, é gerado automaticamente um card no quadro, na etapa de análise da equipe.

## 2. Link público

- Configurável por quadro: **external_forms** guarda `board_id`, `target_column_id` (coluna de análise),
  `token` (usado na URL), título e status.
- URL pública sem autenticação: `GET /f/{token}` (rotas em `routes/public.php`, layout `x-public-layout`
  com logo Up Music e identidade da marca).
- No Kanban, botão **"Compartilhar formulário"** (topbar) exibe/gera o link e permite copiar (SweetAlert2).

## 3. Campos do formulário (conforme escopo)

- **CNPJ da empresa** (obrigatório, validado/máscara).
- **Nome** (obrigatório).
- **Nome do solicitante** (obrigatório): quem pediu o serviço — é por ele que a equipe sabe a quem
  direcionar a cobrança. Gravado em `external_submissions.requester_name` e exibido no modal do card.
- **Valor** (obrigatório, BRL).
- **Data** (obrigatório).
- **Descrição do serviço** (obrigatório).
- **Anexo da nota fiscal** (**opcional**): PDF/imagem, tamanho/MIME validados. O envio sem arquivo cria o
  card normalmente, só sem o anexo (`invoice_path` nulo) — a equipe cobra o documento depois. O input **não**
  pode ser `required` com `display:none`: campo escondido não recebe foco e o browser aborta o submit inteiro
  ("An invalid form control with name='invoice' is not focusable").

Formulário responsivo, acessível, com feedback SweetAlert2 e tela de sucesso após envio. Ícones Font Awesome.

## 4. Processamento do envio

Ao enviar (`POST /f/{token}`), numa transação:

1. Registrar `external_submissions` (dados + `invoice_path` do upload + `ip`, status `recebido`).
2. **Casar empresa por CNPJ** com `empresas.cnpj`; se existir, `empresa_id` é preenchido; senão fica nulo
   (equipe cadastra/associa depois).
3. **Criar card** no `board_id` do formulário, na `target_column_id` (coluna de análise / `is_entry`),
   com `origin = external_form`, `estimated_value = valor` e `empresa_id` (se casado). Título e descrição
   seguem o formato acordado com a equipe:
   - **Título:** `CNPJ formatado - Empresa` (ex.: `11.222.333/0001-81 - Som & Luz Ltda`).
   - **Descrição** (uma linha por dado):
     ```
     Valor (R$) - 1.500,00
     Descrição do serviço - [descrição]
     Dados para pagamento - [dados]
     ```
4. Anexar a **nota fiscal** ao card (`card_attachments.kind = nota_fiscal`, mesmo arquivo do submission),
   quando o envio trouxe arquivo.
5. Vincular `external_submissions.card_id` ao card criado.
6. Exibir tela de sucesso ao cliente.

## 5. Segurança e limites

- Rate limiting na rota pública (`throttle`) para evitar spam.
- Validação estrita de upload (MIME, tamanho máx.), armazenamento em `storage/app/public`.
- Proteção CSRF (formulário Blade), honeypot/anti-bot simples opcional.
- Token do formulário não sequencial (aleatório); desativar formulário (`active=false`) invalida o link.

## 6. Rotas (public — sem auth)

```
GET  /f/{token}      external.form.show
POST /f/{token}      external.form.submit
```

Config (web, autenticado):
```
Resource /quadros/{board}/formularios   external.forms.*   role:admin,coordenador
```

## 7. Critérios de aceite

- [ ] Link público por quadro com token e ativação/desativação.
- [ ] Formulário com todos os campos e upload de NF, responsivo e com identidade Up Music.
- [ ] Envio cria card na coluna de análise com `origin = external_form` e NF anexada.
- [ ] Empresa casada por CNPJ quando existir; senão card sem vínculo.
- [ ] Rate limiting e validação de upload aplicados.
- [ ] Submission registrada e vinculada ao card.
- [ ] Nome do solicitante obrigatório no envio e visível no card quando preenchido.
