-- ============================================================
-- MIGRACION 004 - Tabla `mantenimientos` multi-tenant
-- Fecha: 2026-06-25
-- Motivo: adaptar `mantenimientos` al modelo multi-tenant
--         y agregar borrado logico + auditoria.
--
-- Cambios:
--   1. Agregar `activo` (borrado logico).
--   2. Agregar `created_by` para auditoria.
-- ============================================================

-- 1. Columnas nuevas
ALTER TABLE `mantenimientos`
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `proximo_service_km`,
    ADD COLUMN `created_by` INT(11) NULL AFTER `activo`,
    ADD KEY `idx_mantenimientos_activo` (`activo`),
    ADD KEY `idx_mantenimientos_created_by` (`created_by`);

-- 2. FK de auditoria
ALTER TABLE `mantenimientos`
    ADD CONSTRAINT `mantenimientos_fk_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;