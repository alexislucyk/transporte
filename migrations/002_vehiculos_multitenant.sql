-- ============================================================
-- MIGRACION 002 - Tabla `vehiculos` multi-tenant
-- Fecha: 2026-06-25
-- Motivo: adaptar `vehiculos` al modelo multi-tenant 100% aislado
--         y agregar borrado logico (consistente con clientes).
--
-- Cambios:
--   1. Agregar `activo` (borrado logico).
--   2. Agregar `created_by` para auditoria.
--   3. Cambiar UNIQUE(dominio) global a UNIQUE(dominio, transportista_id)
--      para que la misma patente pueda existir en distintos tenants.
-- ============================================================

-- 1. Columnas nuevas
ALTER TABLE `vehiculos`
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `vtv_vencimiento`,
    ADD COLUMN `created_by` INT(11) NULL AFTER `activo`,
    ADD KEY `idx_vehiculos_activo` (`activo`),
    ADD KEY `idx_vehiculos_created_by` (`created_by`);

-- 2. FK de auditoria
ALTER TABLE `vehiculos`
    ADD CONSTRAINT `vehiculos_fk_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. UNIQUE compuesto (dominio por tenant)
ALTER TABLE `vehiculos` DROP INDEX `dominio`;
ALTER TABLE `vehiculos` ADD UNIQUE KEY `uk_vehiculos_dominio_tenant` (`dominio`, `transportista_id`);

-- 4. Backfill: created_by = dueno del tenant
UPDATE `vehiculos` v
INNER JOIN `transportistas` t ON t.id = v.transportista_id
SET v.created_by = t.created_by
WHERE v.created_by IS NULL;
