-- =====================================================================================
-- upMusic — Financeiro do Evento (specs/23)
-- Migrations de 2026_09_01_000001 a 2026_09_01_000009
--
-- Gerado a partir do schema real criado pelas migrations do Laravel.
-- Alvo: MySQL / MariaDB (XAMPP). Execute em phpMyAdmin com o banco do sistema selecionado.
--
-- SEGURO PARA RODAR MAIS DE UMA VEZ: todo CREATE usa IF NOT EXISTS, a coluna nova em `boards` é
-- criada só se ainda não existir, e os INSERTs são idempotentes.
--
-- PRÉ-REQUISITO: as tabelas `events`, `users`, `cards`, `card_attachments`, `empresas`,
-- `fornecedores`, `fornecedor_categorias` e `boards` já devem existir (são alvo das foreign keys).
--
-- ORDEM IMPORTA: as tabelas abaixo estão na ordem de dependência das foreign keys. Não reordene.
-- =====================================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -------------------------------------------------------------------------------------
-- 1) 2026_09_01_000001_create_finance_sheets_table
-- A planilha financeira do evento (1:1 com `events`). Criada sob demanda pelo sistema.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_sheets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `uses_second_estimate` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'aberto',
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_sheets_event_id_unique` (`event_id`),
  KEY `finance_sheets_closed_by_foreign` (`closed_by`),
  CONSTRAINT `finance_sheets_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_sheets_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 2) 2026_09_01_000002_create_finance_payment_sources_table
-- Grupos de pagamento — substituem as colunas fixas CAIXA EVENTO / SÓCIO / TICKETEIRA / BAR.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_payment_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `kind` varchar(20) NOT NULL DEFAULT 'caixa',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_payment_sources_user_id_foreign` (`user_id`),
  KEY `finance_payment_sources_active_index` (`active`),
  CONSTRAINT `finance_payment_sources_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 3) 2026_09_01_000003_create_finance_cost_items_table
-- Aba CUSTOS. `total_estimated_1`, `total_estimated_2` e `total_actual` são COLUNAS GERADAS
-- (STORED): reproduzem "TOTAL = VALOR UNIT. x QUANT. x DIÁRIAS" das fórmulas J/L/N do arquivo
-- modelo. O banco recusa INSERT/UPDATE que as mencione — quem grava é só o unitário.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_cost_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `finance_sheet_id` bigint(20) unsigned NOT NULL,
  `fornecedor_categoria_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(180) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'orcamento',
  `status_auto` tinyint(1) NOT NULL DEFAULT 1,
  `art_status` varchar(20) NOT NULL DEFAULT 'nao_tem',
  `fornecedor_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_name` varchar(180) DEFAULT NULL,
  `authorized_by` bigint(20) unsigned DEFAULT NULL,
  `authorized_by_name` varchar(120) DEFAULT NULL,
  `daily_count` decimal(8,2) NOT NULL DEFAULT 1.00,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_estimated_1` decimal(15,2) NOT NULL DEFAULT 0.00,
  `unit_estimated_2` decimal(15,2) DEFAULT NULL,
  `unit_actual` decimal(15,2) DEFAULT NULL,
  `total_estimated_1` decimal(15,2) GENERATED ALWAYS AS (`unit_estimated_1` * `quantity` * `daily_count`) STORED,
  `total_estimated_2` decimal(15,2) GENERATED ALWAYS AS (coalesce(`unit_estimated_2`,0) * `quantity` * `daily_count`) STORED,
  `total_actual` decimal(15,2) GENERATED ALWAYS AS (coalesce(`unit_actual`,0) * `quantity` * `daily_count`) STORED,
  `card_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_cost_items_fornecedor_categoria_id_foreign` (`fornecedor_categoria_id`),
  KEY `finance_cost_items_fornecedor_id_foreign` (`fornecedor_id`),
  KEY `finance_cost_items_authorized_by_foreign` (`authorized_by`),
  KEY `finance_cost_items_sheet_position_idx` (`finance_sheet_id`,`position`),
  KEY `finance_cost_items_sheet_categoria_idx` (`finance_sheet_id`,`fornecedor_categoria_id`),
  KEY `finance_cost_items_sheet_status_idx` (`finance_sheet_id`,`status`),
  KEY `finance_cost_items_card_id_index` (`card_id`),
  CONSTRAINT `finance_cost_items_authorized_by_foreign` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_cost_items_card_id_foreign` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_cost_items_finance_sheet_id_foreign` FOREIGN KEY (`finance_sheet_id`) REFERENCES `finance_sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_cost_items_fornecedor_categoria_id_foreign` FOREIGN KEY (`fornecedor_categoria_id`) REFERENCES `fornecedor_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_cost_items_fornecedor_id_foreign` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 4) 2026_09_01_000004_create_finance_payments_table
-- Pagamentos por linha de custo (permitem pagamento parcial). PAGO = SUM(amount).
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `finance_cost_item_id` bigint(20) unsigned NOT NULL,
  `finance_payment_source_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_at` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_payments_finance_payment_source_id_foreign` (`finance_payment_source_id`),
  KEY `finance_payments_created_by_foreign` (`created_by`),
  KEY `finance_payments_item_source_idx` (`finance_cost_item_id`,`finance_payment_source_id`),
  CONSTRAINT `finance_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_payments_finance_cost_item_id_foreign` FOREIGN KEY (`finance_cost_item_id`) REFERENCES `finance_cost_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_payments_finance_payment_source_id_foreign` FOREIGN KEY (`finance_payment_source_id`) REFERENCES `finance_payment_sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 5) 2026_09_01_000005_create_finance_documents_table
-- Bloco CONTROLE (orçamento, contrato, NF, comprovante, ART, boleto). Documento vindo do
-- Kanban REFERENCIA o anexo do card (`card_attachment_id`) — o arquivo não é copiado. O unique
-- (item, anexo) é o que torna o reenvio do card idempotente.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `finance_cost_item_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `card_attachment_id` bigint(20) unsigned DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `size` int(10) unsigned NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_documents_item_attachment_uq` (`finance_cost_item_id`,`card_attachment_id`),
  KEY `finance_documents_card_attachment_id_foreign` (`card_attachment_id`),
  KEY `finance_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `finance_documents_item_kind_idx` (`finance_cost_item_id`,`kind`),
  CONSTRAINT `finance_documents_card_attachment_id_foreign` FOREIGN KEY (`card_attachment_id`) REFERENCES `card_attachments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_documents_finance_cost_item_id_foreign` FOREIGN KEY (`finance_cost_item_id`) REFERENCES `finance_cost_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `finance_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 6) 2026_09_01_000006_create_finance_revenues_table
-- Aba RECEITAS. `pending_value` (FALTA RECEBER) é coluna gerada: realizado - recebido.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_revenues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `finance_sheet_id` bigint(20) unsigned NOT NULL,
  `category` varchar(40) NOT NULL,
  `description` varchar(180) DEFAULT NULL,
  `empresa_id` bigint(20) unsigned DEFAULT NULL,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actual_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `received_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pending_value` decimal(15,2) GENERATED ALWAYS AS (`actual_value` - `received_value`) STORED,
  `finance_payment_source_id` bigint(20) unsigned DEFAULT NULL,
  `received_at` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_revenues_empresa_id_foreign` (`empresa_id`),
  KEY `finance_revenues_finance_payment_source_id_foreign` (`finance_payment_source_id`),
  KEY `finance_revenues_sheet_position_idx` (`finance_sheet_id`,`position`),
  KEY `finance_revenues_sheet_category_idx` (`finance_sheet_id`,`category`),
  CONSTRAINT `finance_revenues_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_revenues_finance_payment_source_id_foreign` FOREIGN KEY (`finance_payment_source_id`) REFERENCES `finance_payment_sources` (`id`),
  CONSTRAINT `finance_revenues_finance_sheet_id_foreign` FOREIGN KEY (`finance_sheet_id`) REFERENCES `finance_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 7) 2026_09_01_000007_create_finance_partner_settlements_table
-- ACERTO SÓCIOS do Resumo Geral.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_partner_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `finance_sheet_id` bigint(20) unsigned NOT NULL,
  `finance_payment_source_id` bigint(20) unsigned DEFAULT NULL,
  `partner_name` varchar(120) NOT NULL,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `manual_amount` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_partner_settlements_finance_payment_source_id_foreign` (`finance_payment_source_id`),
  KEY `finance_partner_settlements_finance_sheet_id_index` (`finance_sheet_id`),
  CONSTRAINT `finance_partner_settlements_finance_payment_source_id_foreign` FOREIGN KEY (`finance_payment_source_id`) REFERENCES `finance_payment_sources` (`id`),
  CONSTRAINT `finance_partner_settlements_finance_sheet_id_foreign` FOREIGN KEY (`finance_sheet_id`) REFERENCES `finance_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 8) 2026_09_01_000008_create_finance_item_presets_table
-- Catálogo de descrições por categoria — autocomplete da coluna DESCRIÇÃO.
-- -------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_item_presets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fornecedor_categoria_id` bigint(20) unsigned NOT NULL,
  `description` varchar(180) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finance_item_presets_cat_desc_uq` (`fornecedor_categoria_id`,`description`),
  KEY `finance_item_presets_active_index` (`active`),
  CONSTRAINT `finance_item_presets_fornecedor_categoria_id_foreign` FOREIGN KEY (`fornecedor_categoria_id`) REFERENCES `fornecedor_categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------------------
-- 9) 2026_09_01_000009_add_feeds_finance_to_boards_table
-- Quadro que alimenta o Financeiro: card que entra num quadro com esta flag sincroniza sozinho
-- com a planilha do evento.
--
-- Feito com PREPARE/EXECUTE (e não "ADD COLUMN IF NOT EXISTS") porque essa sintaxe curta só existe
-- no MariaDB — assim o script roda igual em MySQL 8.
-- -------------------------------------------------------------------------------------
SET @has_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boards' AND COLUMN_NAME = 'feeds_finance'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE `boards` ADD COLUMN `feeds_finance` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`',
    'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- O quadro "Financeiro" é exatamente o ponto do fluxo em que a planilha começava a ser preenchida
-- à mão; já nasce ligado.
UPDATE `boards` SET `feeds_finance` = 1 WHERE `name` = 'Financeiro';


-- -------------------------------------------------------------------------------------
-- Registro das migrations
--
-- Sem isto, um `php artisan migrate` futuro tentaria criar as tabelas de novo e falharia.
-- O batch é calculado a partir do maior batch já existente.
-- -------------------------------------------------------------------------------------
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`migration`, @batch FROM (
    SELECT '2026_09_01_000001_create_finance_sheets_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000002_create_finance_payment_sources_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000003_create_finance_cost_items_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000004_create_finance_payments_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000005_create_finance_documents_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000006_create_finance_revenues_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000007_create_finance_partner_settlements_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000008_create_finance_item_presets_table' AS `migration`
    UNION ALL
    SELECT '2026_09_01_000009_add_feeds_finance_to_boards_table'
) AS m
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` mi WHERE mi.`migration` = m.`migration`
);
