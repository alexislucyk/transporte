-- Migration 008: Tablas de Cobros de Fletes
-- Registro detallado de cobros: cuenta destino, medio de pago, retenciones, cheques
-- Multi-tenant: transportista_id

-- Tabla principal de cobros
CREATE TABLE IF NOT EXISTS `cobros_fletes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transportista_id` int(11) NOT NULL,
  `viaje_id` int(11) NOT NULL,
  `cuenta_id` int(11) DEFAULT NULL COMMENT 'Cuenta de caja destino (cuentas_empresa.id)',
  `fecha_cobro` date NOT NULL,
  `monto_total_facturado` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de la factura (neto+iva)',
  `monto_neto_cobrado` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total cobrado después de retenciones',
  `total_retenciones` decimal(15,2) NOT NULL DEFAULT 0.00,
  `medio_cobro` enum('efectivo','transferencia','cheque','mercadopago','otro') NOT NULL DEFAULT 'efectivo',
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transportista_id` (`transportista_id`),
  KEY `viaje_id` (`viaje_id`),
  KEY `cuenta_id` (`cuenta_id`),
  CONSTRAINT `cobros_fletes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cobros_fletes_ibfk_2` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cobros_fletes_ibfk_3` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de retenciones aplicadas al cobro
CREATE TABLE IF NOT EXISTS `cobros_fletes_retenciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cobro_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL COMMENT 'Ej: IVA, Ganancias, Ingresos Brutos, SUSS, Otro',
  `concepto` varchar(200) DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `cobro_id` (`cobro_id`),
  CONSTRAINT `cobros_fletes_retenciones_ibfk_1` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_fletes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos del cheque cuando el medio de cobro es cheque
CREATE TABLE IF NOT EXISTS `cobros_fletes_cheques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cobro_id` int(11) NOT NULL,
  `tipo_cheque` enum('comun','diferido') NOT NULL DEFAULT 'comun',
  `banco` varchar(100) DEFAULT NULL,
  `numero_cheque` varchar(50) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL COMMENT 'Fecha de cobro diferido',
  `librador` varchar(150) DEFAULT NULL COMMENT 'Quien emite el cheque',
  `endosante` varchar(150) DEFAULT NULL COMMENT 'A quien se endosa',
  `importe` decimal(15,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `cobro_id` (`cobro_id`),
  CONSTRAINT `cobros_fletes_cheques_ibfk_1` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_fletes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;