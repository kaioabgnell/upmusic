-- =====================================================================================
-- upMusic — Financeiro do Evento (specs/23) — CATÁLOGO
--
-- Equivale ao `php artisan db:seed --class=FinanceCatalogSeeder`. RODE DEPOIS de
-- `financeiro-do-evento.sql` (que cria as tabelas).
--
-- As migrations criam as tabelas VAZIAS. Sem este arquivo o módulo abre, mas não há nenhum grupo
-- de pagamento para lançar um pagamento nem descrição no autocomplete da coluna DESCRIÇÃO.
--
-- Conteúdo (extraído do arquivo `FINANCEIRO - MODELO.xlsx`):
--   1. 18 categorias de custo em `fornecedor_categorias` (a lista suspensa da coluna ITEM);
--   2.  5 grupos de pagamento em `finance_payment_sources` (as colunas O-S da planilha);
--   3. 168 descrições em `finance_item_presets`.
--
-- IDEMPOTENTE: rodar de novo não duplica nada.
--
-- IMPORTANTE — as categorias que JÁ EXISTEM são reaproveitadas, nunca renomeadas nem duplicadas:
-- cada INSERT só acontece se o nome ainda não estiver na tabela. Isso preserva os vínculos de
-- `fornecedores` e `price_records`.
-- =====================================================================================

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------------------
-- 1. Categorias de custo (coluna ITEM da aba CUSTOS)
-- -------------------------------------------------------------------------------------
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Licenças e Taxas', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Licenças e Taxas');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Projeto', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Projeto');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Logística', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Logística');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Divulgação', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Divulgação');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Mídia', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Mídia');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Estrutura Geral', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Estrutura Geral');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Cenografia', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Cenografia');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'RH', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'RH');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Serviços', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Serviços');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Camarim', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Camarim');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Bar', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Bar');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Outros', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Outros');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Artístico', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Artístico');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Estrutura Palco', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Estrutura Palco');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Estrutura Cam Empresarial', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Estrutura Cam Empresarial');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Rodeio', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Rodeio');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Estrutura Camarote', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Estrutura Camarote');
INSERT INTO `fornecedor_categorias` (`nome`, `active`, `created_at`, `updated_at`)
SELECT 'Prefeitura', 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `fornecedor_categorias` WHERE `nome` = 'Prefeitura');

-- -------------------------------------------------------------------------------------
-- 2. Grupos de pagamento (colunas CAIXA EVENTO / SÓCIO 1 / SÓCIO 2 / TICKETEIRA / BAR)
--
-- São editáveis depois em Financeiro > Configurações (Admin): renomeie "Sócio 1"/"Sócio 2" para os
-- nomes reais e acrescente outros grupos conforme o evento.
-- -------------------------------------------------------------------------------------
INSERT INTO `finance_payment_sources` (`name`, `kind`, `active`, `position`, `created_at`, `updated_at`)
SELECT 'Caixa do Evento', 'caixa', 1, 0, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `finance_payment_sources` WHERE `name` = 'Caixa do Evento');
INSERT INTO `finance_payment_sources` (`name`, `kind`, `active`, `position`, `created_at`, `updated_at`)
SELECT 'Sócio 1', 'socio', 1, 1, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `finance_payment_sources` WHERE `name` = 'Sócio 1');
INSERT INTO `finance_payment_sources` (`name`, `kind`, `active`, `position`, `created_at`, `updated_at`)
SELECT 'Sócio 2', 'socio', 1, 2, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `finance_payment_sources` WHERE `name` = 'Sócio 2');
INSERT INTO `finance_payment_sources` (`name`, `kind`, `active`, `position`, `created_at`, `updated_at`)
SELECT 'Ticketeira', 'ticketeira', 1, 3, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `finance_payment_sources` WHERE `name` = 'Ticketeira');
INSERT INTO `finance_payment_sources` (`name`, `kind`, `active`, `position`, `created_at`, `updated_at`)
SELECT 'Bar', 'bar', 1, 4, NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `finance_payment_sources` WHERE `name` = 'Bar');

-- -------------------------------------------------------------------------------------
-- 3. Catálogo de descrições (168 itens do arquivo modelo)
--
-- O id da categoria é resolvido pelo NOME, não fixado: funciona em qualquer banco, com os ids que
-- ele já tiver. INSERT IGNORE + o índice único (categoria, descrição) evitam duplicata.
-- -------------------------------------------------------------------------------------

-- Camarim
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ALIMENTOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Camarim';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BEBIDAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Camarim';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CAMARIM | ESTRUTURA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Camarim';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EXTRAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Camarim';

-- Cenografia
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ADESIVOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ATIVAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'DECORAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'DEMAIS ITENS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FORRAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LETREIROS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LONAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'MOBILIÁRIO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PAISAGISMO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Cenografia';

-- Divulgação
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ADESIVOS LOUNGE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ANÚNCIOS / TRÁFEGO PAGO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ASSESSORIA DE IMPRENSA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CAMISETAS / ABADÁS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COBERTURA LOCAL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COMUNICADOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ENTREGA | PRESS KIT', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'INFLUENCER', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LETREIROS LED', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'OUTDOOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRESS KIT', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'RÁDIO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'SOCIAL MEDIA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TV', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'VJ', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Divulgação';

-- Estrutura Geral
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BALCAO | PORTICO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BANHEIRO QUÍMICO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BANHEIRO VIP', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BAR ATIVADO + INTAGRAMÁVEL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BARRICADA / ANTIAVALANCHE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ESPAÇO | LOCAL DO EVENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EXTINTORES', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FECHAMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GERADOR DE ENERGIA 260KVA (MODO RESERVA)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GERADOR DE ENERGIA 500KVA (PARALELO)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GERADOR DE ENERGIA 500KVA (STAND BY)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GERADORES DE ENERGIA 260KVA (USO CONTINUO)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GERADORES DE ENERGIA 500KVA (USO CONTINUO)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GRADIL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'HOUSE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ILUMINAÇÃO | CÊNICA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ILUMINAÇÃO | SERVIÇO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LONA LATERAL 10X3', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LONA LATERAL 5X3', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PASSA CABO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'POSTO MÉDICO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRAÇA DE ALIMENTAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRATICÁVEL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TENDA 10X10', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TENDA 8X8', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TENDA 6X6', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TENDA 5X5', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TENDA 4X4', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Geral';

-- Estrutura Palco
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PALCO | CARPETE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PALCO | ESTRUTURA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PALCO | FORRAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PALCO | LED', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PALCO | TESTEIRA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'SONORIZAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ILUMINAÇÃO CÊNICA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Estrutura Palco';

-- Licenças e Taxas
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ALVARÁ | FUNCIONAMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ALVARÁ | SANITÁRIO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'IMPOSTO | FEDERAL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'IMPOSTO | GUIA ISS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | AMBIENTAL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | AMMA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | ART', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | ASSESSORIA BOMBEIRO + PROJETO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | CÓPIAS E ENCADERNAÇÕES', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | DARE PROJETO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | DUAM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | ECAD', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | LAUDO ANTI CHAMAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | RRT PROJETO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | RUÍDO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | SEGURO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | TAXA DE INSPEÇÃO E FUNCIONAMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | TICKETEIRA - CARTÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | TICKETEIRA - PIX', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | USO DO SOLO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TAXA | VIGILÂNCIA SANITÁRIA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Licenças e Taxas';

-- Logística
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COORD. LOGÍSTICA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Logística';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LOGISTICA TERRESTRE (VANS)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Logística';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PASSAGEM 1', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Logística';

-- Mídia
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'AFTER MOVIE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'AGÊNCIA DE MKT', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'DESIGNER', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EQUIPE | FOTOGRAFIA + FILMAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GESTÃO DE GRUPOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GESTÃO DE INFLUENCERS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'MIDIA - GRAVAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'SAC | ATENDIMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TRANSMISSÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Mídia';

-- Outros
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ÁGUA - MONTAGEM/DESMONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COMBUSTÍVEL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COPOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EXTRAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GRÁFICA (CRACHÁS)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GRÁFICA (PULSEIRAS)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'OUTROS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BRINQUEDOS INFLÁVEIS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TICKETEIRA - TROCA DE INGRESSOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Outros';

-- Projeto
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | 3D', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | BOMBEIROS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | CENOGRAFIA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | EVENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | LED', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PROJETO | PALCO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Projeto';

-- RH
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'APRESENTADOR / LOCUTOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BRIGADISTAS | DESMONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BRIGADISTAS | EVENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'BRIGADISTAS | MONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CARREGADORES | DESMONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CARREGADORES | EVENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CARREGADORES | MONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CREDENCIAMENTO | COORDENADOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CREDENCIAMENTO | EFETIVO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FINANCEIRO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FISCAL DE ACESSOS | COMBUSTÍVEL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FISCAL DE ACESSOS | COORDENAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FISCAL DE ACESSOS | EFETIVO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LIMPEZA | CESTOS DE LIXO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LIMPEZA | EFETIVO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LIMPEZA | EFETIVO | MONTAGEM E DESMONTAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LIMPEZA | INSUMOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PATRIMONIAL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'POLICIAIS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PORTARIA | COORDENADOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PORTARIA | EFETIVO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | CAMARIM (AUXILIAR)]', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | CAMARIM (COORD)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | GERAL', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | GERAL (AUXILIAR)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | PALCO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | AUXILIAR (PALCO)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | RODIE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRODUTOR | SINALIZAÇÃO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'SEGURANÇA | COORDENADOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'SEGURANÇA | EFETIVO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'RH';

-- Serviços
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ADVOGADO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'AMBULÂNCIA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CAÇAMBA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CAMINHÃO PIPA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CLIPAGEM', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COLETA DE RESÍDUOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'CONTABILIDADE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'DESPACHANTE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'DESPESAS LOGISTICA EQUIPE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EFEITOS E FOGOS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ELETRICISTA + ATERRAMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EMPILHADEIRA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EMPILHADEIRA | OPERADOR', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ENGENHEIRO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'ESTACIONAMENTO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FIOS + CABOS + INTERRUPTORES (ESTRUTURA LOCAL - ELÉTRICO)', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'FRETE', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'GUINCHO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'INTERNET', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LANCHE POLICIAIS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'LIMPA FOSSA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'RADIOS COMUNICADORES', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'COORDENADOR DE PESQUISA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'EFETIVO DE PESQUISA', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'PRESTAÇÃO DE CONTAS', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';
INSERT IGNORE INTO `finance_item_presets` (`fornecedor_categoria_id`, `description`, `active`, `created_at`, `updated_at`)
SELECT c.`id`, 'TÉCNICO DE SEGURANÇA DO TRABALHO', 1, NOW(), NOW() FROM `fornecedor_categorias` c WHERE c.`nome` = 'Serviços';

-- =====================================================================================
-- Fim. 168 descrições inseridas.
-- Confira com:
--   SELECT COUNT(*) FROM finance_item_presets;      -- esperado: 168
--   SELECT COUNT(*) FROM finance_payment_sources;   -- esperado: 5 (ou mais, se já havia outros)
-- =====================================================================================
