-- Migration 009: Tabla de movimientos de cuentas de empresa
-- Registra todas las transacciones (entradas/salidas) de cada cuenta
-- Se alimenta automáticamente desde cobros_fletes (entradas) y manualmente (salidas/egresos)

CREATE TABLE IF NOT EXISTS `cuentas_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transportista_id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL COMMENT 'cuentas_empresa.id',
  `tipo` enum('entrada','salida') NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL COMMENT 'ej: cobro_flete, retiro_efectivo, transferencia, gasto, ajuste',
  `referencia_id` int(11) DEFAULT NULL COMMENT 'ID del registro origen (ej: cobros_fletes.id)',
  `monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_resultante` decimal(15,2) DEFAULT NULL COMMENT 'Saldo de la cuenta después del movimiento',
  `fecha_movimiento` date NOT NULL,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transportista_id` (`transportista_id`),
  KEY `cuenta_id` (`cuenta_id`),
  KEY `referencia` (`referencia_tipo`,`referencia_id`),
  KEY `fecha_movimiento` (`fecha_movimiento`),
  CONSTRAINT `cuentas_movimientos_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuentas_movimientos_ibfk_2` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;