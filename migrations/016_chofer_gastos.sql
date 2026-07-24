-- Tabla de gastos de choferes
CREATE TABLE IF NOT EXISTS `chofer_gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chofer_id` int(11) NOT NULL,
  `tipo_gasto` enum('combustible','peaje','comida','alojamiento','reparacion','otros') DEFAULT 'otros',
  `monto` decimal(15,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `chofer_id` (`chofer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `chofer_gastos`
  ADD CONSTRAINT `chofer_gastos_ibfk_1` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE CASCADE;