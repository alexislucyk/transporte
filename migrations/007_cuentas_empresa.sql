-- Migration 007: Crear tabla cuentas_empresa
-- Reemplaza el módulo tesoreria.php por cuentas.php
-- Cuentas: bancos, billeteras virtuales, caja de efectivo

CREATE TABLE IF NOT EXISTS `cuentas_empresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transportista_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('banco','billetera_virtual','caja_efectivo','otro') NOT NULL DEFAULT 'banco',
  `banco` varchar(100) DEFAULT NULL,
  `numero_cuenta` varchar(50) DEFAULT NULL,
  `cbu` varchar(22) DEFAULT NULL,
  `alias` varchar(50) DEFAULT NULL,
  `titular` varchar(150) DEFAULT NULL,
  `cuit_titular` varchar(11) DEFAULT NULL,
  `saldo_inicial` decimal(15,2) DEFAULT 0.00,
  `saldo_actual` decimal(15,2) DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transportista_id` (`transportista_id`),
  CONSTRAINT `cuentas_empresa_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;