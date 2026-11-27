-- =====================================================================
-- Perfis de acesso (specs/28-perfis-de-acesso.md)
-- Script gerado a partir das migrations database/migrations/2026_11_26_000001..000006
-- e da Action app/Actions/AccessProfiles/MigrateRolesToAccessProfiles.php, que
-- converte os papéis antigos (admin/coordenador/usuario) em Administrador +
-- Perfil de acesso sem mudar o acesso de ninguém (specs/28 §9).
--
-- Uso: importar via phpMyAdmin no banco do upMusic, junto com a publicação do
-- código da spec 28 (o código novo não funciona com o banco antigo, e vice-versa).
--
-- ANTES: exporte um backup do banco. O MySQL não desfaz alterações de estrutura
-- (CREATE/ALTER/DROP) se a importação parar no meio.
--
-- Requer o esquema das entregas anteriores: users.role, users.can_access_rentals,
-- user_board, event_user e boards.fixed_costs. O bloco 0 só lê essas colunas: se
-- faltar alguma, o phpMyAdmin para com erro ali, antes de mudar qualquer coisa.
-- Rodar uma vez só: se as tabelas de perfis já existirem, o bloco 1 para com erro.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION group_concat_max_len = 10000;

-- ---------------------------------------------------------------------
-- 0) Pré-requisitos (só leitura)
-- ---------------------------------------------------------------------
SELECT `role`, `can_access_rentals` FROM `users` LIMIT 0;
SELECT `user_id`, `board_id` FROM `user_board` LIMIT 0;
SELECT `user_id`, `event_id` FROM `event_user` LIMIT 0;
SELECT `fixed_costs`, `deleted_at` FROM `boards` LIMIT 0;

-- ---------------------------------------------------------------------
-- 1) access_profiles (2026_11_26_000001)
-- ---------------------------------------------------------------------
CREATE TABLE `access_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(60) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `access_profiles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) access_profile_modules (2026_11_26_000002)
-- ---------------------------------------------------------------------
CREATE TABLE `access_profile_modules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `access_profile_id` BIGINT UNSIGNED NOT NULL,
    `module` VARCHAR(40) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `access_profile_modules_access_profile_id_module_unique` (`access_profile_id`, `module`),
    KEY `access_profile_modules_module_index` (`module`),
    CONSTRAINT `access_profile_modules_access_profile_id_foreign`
        FOREIGN KEY (`access_profile_id`) REFERENCES `access_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) access_profile_board (2026_11_26_000003)
-- ---------------------------------------------------------------------
CREATE TABLE `access_profile_board` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `access_profile_id` BIGINT UNSIGNED NOT NULL,
    `board_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `access_profile_board_access_profile_id_board_id_unique` (`access_profile_id`, `board_id`),
    KEY `access_profile_board_board_id_foreign` (`board_id`),
    CONSTRAINT `access_profile_board_access_profile_id_foreign`
        FOREIGN KEY (`access_profile_id`) REFERENCES `access_profiles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `access_profile_board_board_id_foreign`
        FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) users.is_admin + users.access_profile_id (2026_11_26_000004)
-- ---------------------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`,
    ADD COLUMN `access_profile_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_admin`,
    ADD CONSTRAINT `users_access_profile_id_foreign`
        FOREIGN KEY (`access_profile_id`) REFERENCES `access_profiles` (`id`) ON DELETE RESTRICT,
    ADD KEY `users_is_admin_index` (`is_admin`);

-- ---------------------------------------------------------------------
-- 5) Papéis antigos -> Administrador + Perfil de acesso (2026_11_26_000005)
--    Usuários na lixeira entram também, para que restaurar um usuário funcione.
-- ---------------------------------------------------------------------

-- 5.1) admin -> Administrador, sem perfil.
UPDATE `users` SET `is_admin` = 1, `access_profile_id` = NULL WHERE `role` = 'admin';

-- 5.2) coordenador sem eventos vinculados -> perfil "Coordenador": tudo o que ele
--      acessava, menos Config. Financeiro, Captura rápida e Licitações (só do Admin).
INSERT INTO `access_profiles` (`name`, `created_at`, `updated_at`)
SELECT 'Coordenador', NOW(), NOW() FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM `users` u
    WHERE u.`role` = 'coordenador'
      AND NOT EXISTS (SELECT 1 FROM `event_user` e WHERE e.`user_id` = u.`id`)
);

INSERT INTO `access_profile_modules` (`access_profile_id`, `module`, `created_at`, `updated_at`)
SELECT p.`id`, m.`module`, NOW(), NOW()
FROM `access_profiles` p
CROSS JOIN (
    SELECT 'boards_configure' AS `module` UNION ALL SELECT 'setores' UNION ALL SELECT 'empresas'
    UNION ALL SELECT 'cost_centers' UNION ALL SELECT 'bank_accounts' UNION ALL SELECT 'fixed_costs'
    UNION ALL SELECT 'fornecedores' UNION ALL SELECT 'fornecedor_categorias' UNION ALL SELECT 'events'
    UNION ALL SELECT 'templates' UNION ALL SELECT 'finance' UNION ALL SELECT 'prices'
    UNION ALL SELECT 'rentals'
) m
WHERE p.`name` = 'Coordenador';

UPDATE `users` u
JOIN `access_profiles` p ON p.`name` = 'Coordenador'
SET u.`access_profile_id` = p.`id`
WHERE u.`role` = 'coordenador'
  AND NOT EXISTS (SELECT 1 FROM `event_user` e WHERE e.`user_id` = u.`id`);

-- 5.3) coordenador com eventos vinculados (specs/20) -> perfil "Coordenador restrito
--      por evento": só o que ele via (quadros, Eventos, Financeiro dos Eventos e
--      Banco de Preços). Os vínculos em event_user ficam como estão.
INSERT INTO `access_profiles` (`name`, `created_at`, `updated_at`)
SELECT 'Coordenador restrito por evento', NOW(), NOW() FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM `users` u
    WHERE u.`role` = 'coordenador'
      AND EXISTS (SELECT 1 FROM `event_user` e WHERE e.`user_id` = u.`id`)
);

INSERT INTO `access_profile_modules` (`access_profile_id`, `module`, `created_at`, `updated_at`)
SELECT p.`id`, m.`module`, NOW(), NOW()
FROM `access_profiles` p
CROSS JOIN (
    SELECT 'boards_configure' AS `module` UNION ALL SELECT 'events'
    UNION ALL SELECT 'finance' UNION ALL SELECT 'prices'
) m
WHERE p.`name` = 'Coordenador restrito por evento';

UPDATE `users` u
JOIN `access_profiles` p ON p.`name` = 'Coordenador restrito por evento'
SET u.`access_profile_id` = p.`id`
WHERE u.`role` = 'coordenador'
  AND EXISTS (SELECT 1 FROM `event_user` e WHERE e.`user_id` = u.`id`);

-- 5.4) usuario -> agrupados pela combinação "quadros em user_board + Locações".
--      Cada combinação vira um perfil com os quadros dela, Fornecedores, Banco de
--      Preços e, se tinha o checkbox de Locações, Locações. Nome = quadros em ordem
--      alfabética separados por " + " ("Usuário" sem quadros; " + Locações" no fim).
--      O quadro de custos fixos nunca abria pelo vínculo do usuário: não entra.
--      Tabelas auxiliares comuns (não TEMPORARY): o MySQL não deixa ler uma tabela
--      temporária duas vezes na mesma consulta. Elas são apagadas no fim do bloco.
CREATE TABLE `tmp_spec28_user_access` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `board_ids` VARCHAR(2000) NOT NULL DEFAULT '',
    `board_names` VARCHAR(2000) NOT NULL DEFAULT '',
    `rentals` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_spec28_user_access` (`user_id`, `board_ids`, `board_names`, `rentals`)
SELECT
    u.`id`,
    COALESCE((
        SELECT GROUP_CONCAT(DISTINCT b.`id` ORDER BY b.`id` SEPARATOR ',')
        FROM `user_board` ub JOIN `boards` b ON b.`id` = ub.`board_id`
        WHERE ub.`user_id` = u.`id` AND b.`fixed_costs` = 0
    ), ''),
    COALESCE((
        SELECT GROUP_CONCAT(DISTINCT b.`name` ORDER BY b.`name` SEPARATOR ' + ')
        FROM `user_board` ub JOIN `boards` b ON b.`id` = ub.`board_id`
        WHERE ub.`user_id` = u.`id` AND b.`fixed_costs` = 0 AND b.`deleted_at` IS NULL
    ), ''),
    IF(u.`can_access_rentals` = 1, 1, 0)
FROM `users` u
WHERE u.`role` = 'usuario';

CREATE TABLE `tmp_spec28_groups` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `board_ids` VARCHAR(2000) NOT NULL,
    `rentals` TINYINT(1) NOT NULL,
    `base_name` VARCHAR(60) NOT NULL,
    `final_name` VARCHAR(60) NULL,
    `profile_id` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_spec28_groups` (`board_ids`, `rentals`, `base_name`)
SELECT
    a.`board_ids`,
    a.`rentals`,
    TRIM(LEFT(CONCAT(
        IF(MAX(a.`board_names`) = '', 'Usuário', MAX(a.`board_names`)),
        IF(a.`rentals` = 1, ' + Locações', '')
    ), 60))
FROM `tmp_spec28_user_access` a
GROUP BY a.`board_ids`, a.`rentals`
ORDER BY MIN(a.`user_id`);

-- Nome repetido (entre os grupos ou com um perfil já criado acima) ganha "(2)", "(3)"...
-- Os grupos seguem a ordem do primeiro usuário de cada um, como na Action.
CREATE TABLE `tmp_spec28_names` (
    `id` INT UNSIGNED NOT NULL,
    `dup` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_spec28_names` (`id`, `dup`)
SELECT
    g1.`id`,
    (SELECT COUNT(*) FROM `tmp_spec28_groups` g2 WHERE g2.`base_name` = g1.`base_name` AND g2.`id` < g1.`id`)
    + (SELECT COUNT(*) FROM `access_profiles` p WHERE p.`name` = g1.`base_name`)
FROM `tmp_spec28_groups` g1;

UPDATE `tmp_spec28_groups` g
JOIN `tmp_spec28_names` n ON n.`id` = g.`id`
SET g.`final_name` = IF(
    n.`dup` = 0,
    g.`base_name`,
    CONCAT(RTRIM(LEFT(g.`base_name`, 60 - CHAR_LENGTH(CONCAT(' (', n.`dup` + 1, ')')))), ' (', n.`dup` + 1, ')')
);

INSERT INTO `access_profiles` (`name`, `created_at`, `updated_at`)
SELECT `final_name`, NOW(), NOW() FROM `tmp_spec28_groups` ORDER BY `id`;

UPDATE `tmp_spec28_groups` g
JOIN `access_profiles` p ON p.`name` = g.`final_name`
SET g.`profile_id` = p.`id`;

INSERT INTO `access_profile_modules` (`access_profile_id`, `module`, `created_at`, `updated_at`)
SELECT g.`profile_id`, m.`module`, NOW(), NOW()
FROM `tmp_spec28_groups` g
CROSS JOIN (
    SELECT 'fornecedores' AS `module` UNION ALL SELECT 'prices' UNION ALL SELECT 'rentals'
) m
WHERE m.`module` <> 'rentals' OR g.`rentals` = 1;

INSERT INTO `access_profile_board` (`access_profile_id`, `board_id`, `created_at`, `updated_at`)
SELECT DISTINCT g.`profile_id`, b.`id`, NOW(), NOW()
FROM `tmp_spec28_user_access` a
JOIN `tmp_spec28_groups` g ON g.`board_ids` = a.`board_ids` AND g.`rentals` = a.`rentals`
JOIN `user_board` ub ON ub.`user_id` = a.`user_id`
JOIN `boards` b ON b.`id` = ub.`board_id` AND b.`fixed_costs` = 0;

UPDATE `users` u
JOIN `tmp_spec28_user_access` a ON a.`user_id` = u.`id`
JOIN `tmp_spec28_groups` g ON g.`board_ids` = a.`board_ids` AND g.`rentals` = a.`rentals`
SET u.`access_profile_id` = g.`profile_id`;

DROP TABLE `tmp_spec28_names`;
DROP TABLE `tmp_spec28_groups`;
DROP TABLE `tmp_spec28_user_access`;

-- ---------------------------------------------------------------------
-- 6) Remove o modelo antigo de acesso (2026_11_26_000006)
--    Apagar a coluna `role` apaga junto o índice `users_role_index`.
-- ---------------------------------------------------------------------
DROP TABLE `user_board`;

ALTER TABLE `users`
    DROP COLUMN `role`,
    DROP COLUMN `can_access_rentals`;

-- ---------------------------------------------------------------------
-- Registrar as 6 migrations na tabela `migrations` do Laravel, para que um
-- `php artisan migrate` futuro não tente rodá-las de novo.
-- ---------------------------------------------------------------------
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`) VALUES
    ('2026_11_26_000001_create_access_profiles_table', @batch),
    ('2026_11_26_000002_create_access_profile_modules_table', @batch),
    ('2026_11_26_000003_create_access_profile_board_table', @batch),
    ('2026_11_26_000004_add_access_columns_to_users_table', @batch),
    ('2026_11_26_000005_migrate_roles_to_access_profiles', @batch),
    ('2026_11_26_000006_drop_legacy_role_columns', @batch);

-- =====================================================================
-- Conferência (rodar depois, na aba SQL do phpMyAdmin):
--
-- Quem ficou com qual acesso:
--   SELECT u.id, u.name, u.is_admin, p.name AS perfil, u.deleted_at
--   FROM users u LEFT JOIN access_profiles p ON p.id = u.access_profile_id
--   ORDER BY u.is_admin DESC, p.name, u.name;
--
-- Usuários sem Administrador e sem perfil (não deveria voltar nenhum):
--   SELECT id, name, email FROM users WHERE is_admin = 0 AND access_profile_id IS NULL;
-- =====================================================================
