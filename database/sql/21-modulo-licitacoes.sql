-- =====================================================================
-- Módulo de Licitações (specs/21-modulo-licitacoes.md)
-- Script gerado a partir das migrations reais em database/migrations/2026_07_26_*
-- e do seeder database/seeders/BidCatalogSeeder.php.
--
-- Uso: executar via phpMyAdmin no banco `upmusic_local` (o banco já existe,
-- este script só cria as 11 tabelas do módulo e popula o catálogo base).
-- Requer que a tabela `users` já exista (Breeze/auth do upMusic).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) bid_document_categories
-- ---------------------------------------------------------------------
CREATE TABLE `bid_document_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(30) NOT NULL,
    `name` VARCHAR(60) NOT NULL,
    `color` VARCHAR(7) NOT NULL DEFAULT '#5a5a5c',
    `icon` VARCHAR(40) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `system` TINYINT(1) NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_document_categories_slug_unique` (`slug`),
    KEY `bid_document_categories_active_sort_order_index` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) bid_document_types
-- ---------------------------------------------------------------------
CREATE TABLE `bid_document_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_document_category_id` BIGINT UNSIGNED NOT NULL,
    `slug` VARCHAR(60) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `aliases` JSON NULL,
    `issuer` VARCHAR(120) NULL,
    `default_validity_days` INT UNSIGNED NULL,
    `requires_control_code` TINYINT(1) NOT NULL DEFAULT 0,
    `essential` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_document_types_slug_unique` (`slug`),
    KEY `bid_document_types_active_sort_order_index` (`active`, `sort_order`),
    CONSTRAINT `bid_document_types_bid_document_category_id_foreign`
        FOREIGN KEY (`bid_document_category_id`) REFERENCES `bid_document_categories` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) bid_business_lines
-- ---------------------------------------------------------------------
CREATE TABLE `bid_business_lines` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `keywords` JSON NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_business_lines_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) bid_companies
-- ---------------------------------------------------------------------
CREATE TABLE `bid_companies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `corporate_name` VARCHAR(180) NOT NULL,
    `trade_name` VARCHAR(180) NULL,
    `cnpj` VARCHAR(18) NOT NULL,
    `size` ENUM('me', 'epp', 'demais') NOT NULL DEFAULT 'demais',
    `capital_social` DECIMAL(15, 2) NULL,
    `net_worth` DECIMAL(15, 2) NULL,
    `tax_regime` VARCHAR(40) NULL,
    `cnaes` JSON NULL,
    `responsible_name` VARCHAR(180) NULL,
    `email` VARCHAR(150) NULL,
    `phone` VARCHAR(20) NULL,
    `zipcode` VARCHAR(9) NULL,
    `address` VARCHAR(180) NULL,
    `number` VARCHAR(20) NULL,
    `complement` VARCHAR(120) NULL,
    `district` VARCHAR(120) NULL,
    `city` VARCHAR(120) NULL,
    `state` VARCHAR(2) NULL,
    `color` VARCHAR(7) NOT NULL DEFAULT '#0a0a0a',
    `notes` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `bid_companies_cnpj_index` (`cnpj`),
    KEY `bid_companies_corporate_name_index` (`corporate_name`),
    KEY `bid_companies_active_index` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) bid_company_business_line
-- ---------------------------------------------------------------------
CREATE TABLE `bid_company_business_line` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_company_id` BIGINT UNSIGNED NOT NULL,
    `bid_business_line_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_company_line_unique` (`bid_company_id`, `bid_business_line_id`),
    CONSTRAINT `bid_company_business_line_bid_company_id_foreign`
        FOREIGN KEY (`bid_company_id`) REFERENCES `bid_companies` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_company_business_line_bid_business_line_id_foreign`
        FOREIGN KEY (`bid_business_line_id`) REFERENCES `bid_business_lines` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) bid_documents
-- ---------------------------------------------------------------------
CREATE TABLE `bid_documents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_company_id` BIGINT UNSIGNED NOT NULL,
    `bid_document_category_id` BIGINT UNSIGNED NOT NULL,
    `bid_document_type_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(180) NOT NULL,
    `control_code` VARCHAR(120) NULL,
    `issuer` VARCHAR(120) NULL,
    `issued_at` DATE NULL,
    `expires_at` DATE NULL,
    `no_expiry` TINYINT(1) NOT NULL DEFAULT 0,
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
    `ai_extracted` JSON NULL,
    `ai_confidence` DECIMAL(4, 3) NULL,
    `notes` TEXT NULL,
    `supersedes_id` BIGINT UNSIGNED NULL,
    `superseded_at` DATETIME NULL,
    `uploaded_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `bid_documents_company_current_index` (`bid_company_id`, `superseded_at`),
    KEY `bid_documents_company_type_index` (`bid_company_id`, `bid_document_type_id`),
    KEY `bid_documents_expires_at_index` (`expires_at`),
    CONSTRAINT `bid_documents_bid_company_id_foreign`
        FOREIGN KEY (`bid_company_id`) REFERENCES `bid_companies` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_documents_bid_document_category_id_foreign`
        FOREIGN KEY (`bid_document_category_id`) REFERENCES `bid_document_categories` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `bid_documents_bid_document_type_id_foreign`
        FOREIGN KEY (`bid_document_type_id`) REFERENCES `bid_document_types` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `bid_documents_supersedes_id_foreign`
        FOREIGN KEY (`supersedes_id`) REFERENCES `bid_documents` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `bid_documents_uploaded_by_foreign`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) bid_notices
-- ---------------------------------------------------------------------
CREATE TABLE `bid_notices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `status` ENUM('rascunho', 'processando', 'analisado', 'erro') NOT NULL DEFAULT 'rascunho',
    `source` ENUM('pdf', 'imagem', 'texto') NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `original_name` VARCHAR(255) NULL,
    `mime_type` VARCHAR(100) NULL,
    `file_size` INT UNSIGNED NULL,
    `raw_text` LONGTEXT NULL,
    `agency` VARCHAR(180) NULL,
    `number` VARCHAR(60) NULL,
    `process_number` VARCHAR(60) NULL,
    `modality` VARCHAR(60) NULL,
    `portal` VARCHAR(120) NULL,
    `uf` VARCHAR(2) NULL,
    `city` VARCHAR(120) NULL,
    `object_summary` TEXT NULL,
    `estimated_value` DECIMAL(15, 2) NULL,
    `session_at` DATETIME NULL,
    `proposal_deadline_at` DATETIME NULL,
    `me_epp_exclusive` TINYINT(1) NULL,
    `requires_site_visit` TINYINT(1) NULL,
    `requires_bid_bond` TINYINT(1) NULL,
    `ai_confidence` DECIMAL(4, 3) NULL,
    `ai_warnings` JSON NULL,
    `raw_response` LONGTEXT NULL,
    `prompt_version` VARCHAR(20) NULL,
    `error_message` VARCHAR(255) NULL,
    `analyzed_at` DATETIME NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `bid_notices_status_index` (`status`),
    KEY `bid_notices_session_at_index` (`session_at`),
    CONSTRAINT `bid_notices_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) bid_notice_requirements
-- ---------------------------------------------------------------------
CREATE TABLE `bid_notice_requirements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_notice_id` BIGINT UNSIGNED NOT NULL,
    `kind` ENUM(
        'documento', 'cnae', 'porte', 'capital_social', 'patrimonio_liquido',
        'atestado_tecnico', 'registro_profissional', 'indice_contabil',
        'visita_tecnica', 'garantia_proposta', 'outro'
    ) NOT NULL DEFAULT 'documento',
    `bid_document_category_id` BIGINT UNSIGNED NULL,
    `bid_document_type_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(200) NOT NULL,
    `description` VARCHAR(500) NULL,
    `mandatory` TINYINT(1) NOT NULL DEFAULT 1,
    `expected` JSON NULL,
    `source_excerpt` VARCHAR(1000) NOT NULL,
    `source_page` INT UNSIGNED NULL,
    `ignored` TINYINT(1) NOT NULL DEFAULT 0,
    `ignored_reason` VARCHAR(255) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `bid_requirements_notice_order_index` (`bid_notice_id`, `sort_order`),
    CONSTRAINT `bid_notice_requirements_bid_notice_id_foreign`
        FOREIGN KEY (`bid_notice_id`) REFERENCES `bid_notices` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_notice_requirements_bid_document_category_id_foreign`
        FOREIGN KEY (`bid_document_category_id`) REFERENCES `bid_document_categories` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `bid_notice_requirements_bid_document_type_id_foreign`
        FOREIGN KEY (`bid_document_type_id`) REFERENCES `bid_document_types` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9) bid_notice_evaluations
-- ---------------------------------------------------------------------
CREATE TABLE `bid_notice_evaluations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_notice_id` BIGINT UNSIGNED NOT NULL,
    `bid_company_id` BIGINT UNSIGNED NOT NULL,
    `verdict` ENUM('apta', 'apta_com_pendencias', 'inapta') NOT NULL DEFAULT 'inapta',
    `score` DECIMAL(5, 2) NOT NULL DEFAULT 0,
    `rank` INT UNSIGNED NOT NULL DEFAULT 0,
    `met_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `expiring_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `missing_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `blockers` JSON NULL,
    `highlights` JSON NULL,
    `verdict_at_analysis` ENUM('apta', 'apta_com_pendencias', 'inapta') NULL,
    `score_at_analysis` DECIMAL(5, 2) NULL,
    `evaluated_at` DATETIME NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_evaluation_notice_company_unique` (`bid_notice_id`, `bid_company_id`),
    CONSTRAINT `bid_notice_evaluations_bid_notice_id_foreign`
        FOREIGN KEY (`bid_notice_id`) REFERENCES `bid_notices` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_notice_evaluations_bid_company_id_foreign`
        FOREIGN KEY (`bid_company_id`) REFERENCES `bid_companies` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10) bid_requirement_matches
-- ---------------------------------------------------------------------
CREATE TABLE `bid_requirement_matches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bid_notice_requirement_id` BIGINT UNSIGNED NOT NULL,
    `bid_company_id` BIGINT UNSIGNED NOT NULL,
    `bid_document_id` BIGINT UNSIGNED NULL,
    `status` ENUM('atendido', 'vencendo', 'vencido', 'ausente', 'conferir', 'nao_aplicavel') NOT NULL DEFAULT 'ausente',
    `confidence` ENUM('alta', 'media', 'baixa') NOT NULL DEFAULT 'alta',
    `reason` VARCHAR(255) NULL,
    `manual_override` TINYINT(1) NOT NULL DEFAULT 0,
    `overridden_by` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bid_match_requirement_company_unique` (`bid_notice_requirement_id`, `bid_company_id`),
    KEY `bid_match_company_status_index` (`bid_company_id`, `status`),
    CONSTRAINT `bid_requirement_matches_bid_notice_requirement_id_foreign`
        FOREIGN KEY (`bid_notice_requirement_id`) REFERENCES `bid_notice_requirements` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_requirement_matches_bid_company_id_foreign`
        FOREIGN KEY (`bid_company_id`) REFERENCES `bid_companies` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `bid_requirement_matches_bid_document_id_foreign`
        FOREIGN KEY (`bid_document_id`) REFERENCES `bid_documents` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `bid_requirement_matches_overridden_by_foreign`
        FOREIGN KEY (`overridden_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11) bid_ai_calls
-- ---------------------------------------------------------------------
CREATE TABLE `bid_ai_calls` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('documento', 'edital') NOT NULL,
    `related_type` VARCHAR(60) NULL,
    `related_id` BIGINT UNSIGNED NULL,
    `model` VARCHAR(60) NOT NULL,
    `prompt_version` VARCHAR(20) NULL,
    `prompt_tokens` INT UNSIGNED NULL,
    `output_tokens` INT UNSIGNED NULL,
    `total_tokens` INT UNSIGNED NULL,
    `latency_ms` INT UNSIGNED NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `http_status` SMALLINT UNSIGNED NULL,
    `error_message` VARCHAR(255) NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `bid_ai_calls_type_created_at_index` (`type`, `created_at`),
    KEY `bid_ai_calls_related_index` (`related_type`, `related_id`),
    CONSTRAINT `bid_ai_calls_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Registrar as 11 migrations na tabela `migrations` do Laravel, para que
-- `php artisan migrate` não tente recriar estas tabelas depois.
-- Ajuste o valor de `batch` se necessário (próximo número livre no seu banco).
-- =====================================================================
INSERT INTO `migrations` (`migration`, `batch`) VALUES
    ('2026_07_26_000001_create_bid_document_categories_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000002_create_bid_document_types_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000003_create_bid_business_lines_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000004_create_bid_companies_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000005_create_bid_company_business_line_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000006_create_bid_documents_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000007_create_bid_notices_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000008_create_bid_notice_requirements_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000009_create_bid_notice_evaluations_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000010_create_bid_requirement_matches_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t)),
    ('2026_07_26_000011_create_bid_ai_calls_table', (SELECT t.next_batch FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations) AS t));

-- =====================================================================
-- SEEDER: database/seeders/BidCatalogSeeder.php
-- (categorias, tipos de documento e ramos de atuação)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Categorias de documento (6)
-- ---------------------------------------------------------------------
INSERT INTO `bid_document_categories` (`slug`, `name`, `color`, `icon`, `sort_order`, `system`, `active`, `created_at`, `updated_at`) VALUES
    ('fiscal', 'Fiscal', '#1d4ed8', 'fa-file-invoice-dollar', 1, 1, 1, NOW(), NOW()),
    ('trabalhista', 'Trabalhista', '#7c3aed', 'fa-helmet-safety', 2, 1, 1, NOW(), NOW()),
    ('juridica', 'Jurídica', '#0f766e', 'fa-scale-balanced', 3, 1, 1, NOW(), NOW()),
    ('tecnica', 'Técnica', '#b45309', 'fa-screwdriver-wrench', 4, 1, 1, NOW(), NOW()),
    ('financeira', 'Financeira', '#be123c', 'fa-chart-pie', 5, 1, 1, NOW(), NOW()),
    ('outros', 'Outros', '#5a5a5c', 'fa-folder-open', 6, 1, 1, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Tipos de documento (22) — categoria resolvida via subselect pelo slug,
-- igual o seeder faz em memória com o array `$categories`.
-- ---------------------------------------------------------------------

-- Fiscal
INSERT INTO `bid_document_types`
    (`bid_document_category_id`, `slug`, `name`, `aliases`, `issuer`, `default_validity_days`, `requires_control_code`, `essential`, `sort_order`, `active`, `created_at`, `updated_at`) VALUES
((SELECT id FROM bid_document_categories WHERE slug = 'fiscal'), 'cnd_federal',
    'Certidão Negativa de Débitos Relativos a Créditos Tributários Federais e à Dívida Ativa da União',
    JSON_ARRAY('cnd federal', 'certidao conjunta pgfn', 'certidao negativa federal', 'certidao conjunta receita federal', 'cnd receita federal', 'certidao negativa de debitos federais'),
    'Receita Federal / PGFN', 180, 1, 1, 1, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'fiscal'), 'cnd_estadual',
    'Certidão Negativa de Débitos Estaduais',
    JSON_ARRAY('cnd estadual', 'certidao negativa estadual', 'certidao de regularidade fiscal estadual', 'certidao negativa de tributos estaduais'),
    'Secretaria da Fazenda Estadual', 180, 1, 1, 2, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'fiscal'), 'cnd_municipal',
    'Certidão Negativa de Débitos Municipais',
    JSON_ARRAY('cnd municipal', 'certidao negativa municipal', 'certidao de tributos municipais', 'certidao negativa mobiliaria'),
    'Prefeitura Municipal', 180, 1, 1, 3, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'fiscal'), 'crf_fgts',
    'Certificado de Regularidade do FGTS (CRF)',
    JSON_ARRAY('crf', 'fgts', 'certificado de regularidade do fgts', 'crf fgts', 'regularidade fgts', 'certidao fgts'),
    'Caixa Econômica Federal', 30, 1, 1, 4, 1, NOW(), NOW()),

-- Trabalhista
((SELECT id FROM bid_document_categories WHERE slug = 'trabalhista'), 'cndt',
    'Certidão Negativa de Débitos Trabalhistas (CNDT)',
    JSON_ARRAY('cndt', 'certidao negativa de debitos trabalhistas', 'certidao trabalhista', 'debitos trabalhistas'),
    'Tribunal Superior do Trabalho', 180, 1, 1, 5, 1, NOW(), NOW()),

-- Jurídica
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'contrato_social',
    'Contrato social consolidado e alterações',
    JSON_ARRAY('contrato social', 'contrato social consolidado', 'estatuto social', 'ultima alteracao contratual'),
    'Junta Comercial', NULL, 0, 1, 6, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'ato_constitutivo',
    'Ato constitutivo / registro comercial',
    JSON_ARRAY('ato constitutivo', 'registro comercial', 'requerimento de empresario', 'declaracao de firma individual'),
    'Junta Comercial', NULL, 0, 0, 7, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'comprovante_cnpj',
    'Comprovante de Inscrição e Situação Cadastral (CNPJ)',
    JSON_ARRAY('cartao cnpj', 'comprovante de inscricao e situacao cadastral', 'inscricao no cnpj', 'cnpj'),
    'Receita Federal', NULL, 0, 1, 8, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'procuracao',
    'Procuração / instrumento de mandato',
    JSON_ARRAY('procuracao', 'instrumento de mandato', 'instrumento procuratorio'),
    NULL, NULL, 0, 0, 9, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'certidao_simplificada_junta',
    'Certidão simplificada da Junta Comercial',
    JSON_ARRAY('certidao simplificada', 'certidao simplificada junta comercial', 'certidao da junta comercial'),
    'Junta Comercial', 90, 0, 0, 10, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'juridica'), 'alvara_funcionamento',
    'Alvará de funcionamento',
    JSON_ARRAY('alvara', 'alvara de funcionamento', 'licenca de funcionamento'),
    'Prefeitura Municipal', 365, 0, 0, 11, 1, NOW(), NOW()),

-- Técnica
((SELECT id FROM bid_document_categories WHERE slug = 'tecnica'), 'atestado_capacidade_tecnica',
    'Atestado de Capacidade Técnica',
    JSON_ARRAY('atestado de capacidade tecnica', 'atestado tecnico', 'atestado de capacidade', 'comprovacao de aptidao tecnica'),
    NULL, NULL, 0, 0, 12, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'tecnica'), 'registro_crea_cau',
    'Registro/certidão no CREA ou CAU',
    JSON_ARRAY('crea', 'cau', 'registro no crea', 'certidao de registro e quitacao crea', 'registro profissional'),
    'CREA / CAU', 365, 0, 0, 13, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'tecnica'), 'cat_crea',
    'Certidão de Acervo Técnico (CAT)',
    JSON_ARRAY('cat', 'certidao de acervo tecnico', 'acervo tecnico'),
    'CREA', NULL, 0, 0, 14, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'tecnica'), 'licenca_ambiental',
    'Licença ambiental',
    JSON_ARRAY('licenca ambiental', 'licenca de operacao ambiental'),
    NULL, 365, 0, 0, 15, 1, NOW(), NOW()),

-- Financeira
((SELECT id FROM bid_document_categories WHERE slug = 'financeira'), 'balanco_patrimonial',
    'Balanço patrimonial e demonstrações contábeis',
    JSON_ARRAY('balanco patrimonial', 'demonstracoes contabeis', 'balanco', 'dre', 'demonstracao do resultado'),
    NULL, NULL, 0, 1, 16, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'financeira'), 'certidao_falencia_recuperacao',
    'Certidão negativa de falência, concordata e recuperação judicial',
    JSON_ARRAY('certidao de falencia', 'falencia e concordata', 'recuperacao judicial', 'certidao civel', 'certidao negativa de falencia'),
    'Tribunal de Justiça', 90, 1, 1, 17, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'financeira'), 'comprovante_capital_social',
    'Comprovante de capital social integralizado',
    JSON_ARRAY('capital social', 'comprovacao de capital social', 'capital social integralizado'),
    NULL, NULL, 0, 0, 18, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'financeira'), 'demonstracao_indices',
    'Demonstração dos índices contábeis',
    JSON_ARRAY('indices contabeis', 'demonstracao de indices', 'liquidez corrente', 'grau de endividamento', 'liquidez geral'),
    NULL, NULL, 0, 0, 19, 1, NOW(), NOW()),

-- Outros
((SELECT id FROM bid_document_categories WHERE slug = 'outros'), 'sicaf',
    'Consulta consolidada / SICAF',
    JSON_ARRAY('sicaf', 'consulta consolidada', 'certificado de registro cadastral sicaf'),
    'Compras.gov.br', 30, 0, 0, 20, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'outros'), 'crc',
    'Certificado de Registro Cadastral (CRC)',
    JSON_ARRAY('crc', 'certificado de registro cadastral', 'registro cadastral'),
    NULL, 365, 0, 0, 21, 1, NOW(), NOW()),
((SELECT id FROM bid_document_categories WHERE slug = 'outros'), 'declaracoes_diversas',
    'Declarações exigidas em edital',
    JSON_ARRAY('declaracao', 'declaracoes', 'declaracao de menor', 'declaracao de idoneidade', 'declaracao de elaboracao independente'),
    NULL, NULL, 0, 0, 22, 1, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Ramos de atuação (7)
-- ---------------------------------------------------------------------
INSERT INTO `bid_business_lines` (`name`, `keywords`, `active`, `created_at`, `updated_at`) VALUES
    ('Eventos', JSON_ARRAY('evento', 'eventos', 'show', 'shows', 'palco', 'sonorizacao', 'iluminacao', 'festa', 'festival', 'estrutura para evento'), 1, NOW(), NOW()),
    ('Portaria', JSON_ARRAY('portaria', 'porteiro', 'controle de acesso', 'recepcao', 'zeladoria'), 1, NOW(), NOW()),
    ('Segurança', JSON_ARRAY('seguranca', 'vigilancia', 'vigilante', 'seguranca desarmada', 'seguranca patrimonial'), 1, NOW(), NOW()),
    ('Limpeza e conservação', JSON_ARRAY('limpeza', 'conservacao', 'higienizacao', 'asseio', 'copeiragem'), 1, NOW(), NOW()),
    ('Locação de equipamentos', JSON_ARRAY('locacao', 'aluguel de equipamentos', 'cessao de equipamentos', 'locacao de estruturas'), 1, NOW(), NOW()),
    ('Serviços gerais', JSON_ARRAY('servicos gerais', 'apoio operacional', 'mao de obra', 'terceirizacao'), 1, NOW(), NOW()),
    ('Produção audiovisual', JSON_ARRAY('audiovisual', 'filmagem', 'transmissao', 'gravacao', 'painel de led'), 1, NOW(), NOW());
