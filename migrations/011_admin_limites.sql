-- Migración: Límites individuales por Administrador
-- Fecha: 2026-10-07
-- 
-- Cada administrador puede tener límites diferentes según su plan contratado.
-- La tabla almacena cuántas empresas, vehículos y choferes puede gestionar cada admin.
-- Valor 0 = sin límite.

CREATE TABLE IF NOT EXISTS `admin_limites` (
  `admin_id` int(11) NOT NULL,
  `limite_empresas` int(11) NOT NULL DEFAULT 0,
  `limite_vehiculos` int(11) NOT NULL DEFAULT 0,
  `limite_choferes` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`admin_id`),
  CONSTRAINT `fk_admin_limites_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar registros para admins existentes (todos con 0 = sin límite)
INSERT INTO `admin_limites` (admin_id, limite_empresas, limite_vehiculos, limite_choferes)
SELECT u.id, 0, 0, 0 FROM users u WHERE u.role = 'admin'
ON DUPLICATE KEY UPDATE admin_id = u.id;