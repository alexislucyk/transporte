-- ============================================================
-- MIGRACIÓN 001 — Tabla `clientes` multi-tenant
-- Fecha: 2026-06-25
-- Motivo: adaptar `clientes` al modelo de multi-tenancy 100% aislado
--         entre empresas (ver bitacora.md §3).
--
-- Cambios:
--   1. Agregar `activo` (borrado lógico, consistente con el resto de tablas).
--   2. Agregar `created_by` para auditoría de quién registró al cliente.
--   3. Cambiar UNIQUE(cuit) global a UNIQUE(cuit, transportista_id)
--      para que el mismo CUIT pueda existir en distintos tenants
--      (escenario real: mismo cliente con contratos con 2 transportistas).
-- ============================================================

-- 1. Agregar columnas
ALTER TABLE `clientes`
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `es_comisionista`,
    ADD COLUMN `created_by` INT(11) NULL AFTER `activo`,
    ADD KEY `idx_clientes_activo` (`activo`),
    ADD KEY `idx_clientes_created_by` (`created_by`);

-- 2. Foreign key de auditoría
ALTER TABLE `clientes`
    ADD CONSTRAINT `clientes_fk_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. Reemplazar UNIQUE global por UNIQUE compuesto (cuit + tenant)
ALTER TABLE `clientes` DROP INDEX `cuit`;
ALTER TABLE `clientes` ADD UNIQUE KEY `uk_clientes_cuit_tenant` (`cuit`, `transportista_id`);

-- 4. Backfill: created_by se setea al dueño del tenant para no perder auditoría
UPDATE `clientes` c
INNER JOIN `transportistas` t ON t.id = c.transportista_id
SET c.created_by = t.created_by
WHERE c.created_by IS NULL;
