-- Migración: Límites de gestión para Administradores
-- Fecha: 2026-10-07
-- 
-- Inserta los valores por defecto en la tabla configuraciones
-- Estos límites controlan cuántas empresas, vehículos y choferes
-- puede gestionar un administrador.

INSERT INTO configuraciones (clave, valor) VALUES ('limite_empresas', '0') ON DUPLICATE KEY UPDATE valor = valor;
INSERT INTO configuraciones (clave, valor) VALUES ('limite_vehiculos', '0') ON DUPLICATE KEY UPDATE valor = valor;
INSERT INTO configuraciones (clave, valor) VALUES ('limite_choferes', '0') ON DUPLICATE KEY UPDATE valor = valor;