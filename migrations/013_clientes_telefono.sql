-- ============================================================
-- MIGRACIÓN 013 — Agregar columna `telefono` a tabla `clientes`
-- Fecha: 2026-11-07
-- Motivo: La columna telefono faltaba en la tabla clientes pero
--         el código ya la utiliza desde el formulario de registro.
-- ============================================================

ALTER TABLE `clientes`
    ADD COLUMN `telefono` VARCHAR(50) NULL DEFAULT NULL AFTER `direccion`;