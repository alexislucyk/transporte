-- Tabla de items adicionales en facturas de viajes
-- Permite agregar conceptos extras (escritos manualmente) que suman o restan al total del flete
CREATE TABLE IF NOT EXISTS `viaje_factura_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viaje_id` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `operacion` enum('suma','resta') DEFAULT 'suma',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `viaje_id` (`viaje_id`),
  CONSTRAINT `viaje_factura_items_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;