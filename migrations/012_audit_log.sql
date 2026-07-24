-- ========================================
-- SISTEMA DE AUDITORÍA
-- Registro de todas las acciones de admins y usuarios
-- Solo visible para el rol 'developer'
-- ========================================

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `user_role` enum('admin','user','developer') DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `datos_anteriores` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `accion` (`accion`),
  KEY `modulo` (`modulo`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para búsquedas por usuario y fecha
ALTER TABLE `audit_log`
  ADD KEY `idx_user_fecha` (`user_id`, `created_at`);