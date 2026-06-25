-- ============================================================
-- MIGRACION 003 - Tabla `choferes` multi-tenant
-- Fecha: 2026-06-25
-- Motivo: adaptar `choferes` al modelo multi-tenant 100% aislado
--         y agregar borrado logico (consistente con clientes).
--
-- Cambios:
--   1. Agregar `activo` (borrado logico) - ya existe en schema.sql
--   2. Agregar `created_by` para auditoria.
--   3. Cambiar UNIQUE(cuil) global a UNIQUE(cuil, transportista_id)
--      para que el mismo CUIL pueda existir en distintos tenants.
-- ============================================================

-- 1. Columna nueva (activo ya esta en el schema)
ALTER TABLE `choferes`
    ADD COLUMN `created_by` INT(11) NULL AFTER `activo`,
    ADD KEY `idx_choferes_created_by` (`created_by`);

-- 2. FK de auditoria
ALTER TABLE `choferes`
    ADD CONSTRAINT `choferes_fk_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. UNIQUE compuesto (cuil por tenant)
ALTER TABLE `choferes` DROP INDEX `cuil`;
ALTER TABLE `choferes` ADD UNIQUE KEY `uk_choferes_cuil_tenant` (`cuil`, `transportista_id`);

-- 4. Backfill: created_by = dueno del tenant
UPDATE `choferes` ch
INNER JOIN `transportistas` t ON t.id = ch.transportista_id
SET ch.created_by = t.created_by
WHERE ch.created_by IS NULL;