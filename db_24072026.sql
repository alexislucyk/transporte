-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 24, 2026 at 11:57 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `trans_dev_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_limites`
--

CREATE TABLE `admin_limites` (
  `admin_id` int NOT NULL,
  `limite_empresas` int NOT NULL DEFAULT '0',
  `limite_vehiculos` int NOT NULL DEFAULT '0',
  `limite_choferes` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_limites`
--

INSERT INTO `admin_limites` (`admin_id`, `limite_empresas`, `limite_vehiculos`, `limite_choferes`, `updated_at`) VALUES
(8, 1, 2, 2, '2026-07-10 20:34:50'),
(9, 1, 2, 2, '2026-07-11 12:57:07');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_role` enum('admin','user','developer') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `datos_anteriores` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `username`, `user_role`, `accion`, `modulo`, `descripcion`, `datos_anteriores`, `datos_nuevos`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:03:14'),
(2, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:03:52'),
(3, 8, 'admin1', 'admin', 'crear', 'vehiculos', 'Nuevo vehículo registrado: AA001AA (SCANIA 16FG)', NULL, '{\"id\": 3, \"anio\": 2026, \"marca\": \"SCANIA\", \"modelo\": \"16FG\", \"dominio\": \"AA001AA\", \"acoplado\": \"AA001AB\", \"chofer_id\": 0}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:04:43'),
(4, 8, 'admin1', 'admin', 'crear', 'choferes', 'Nuevo chofer registrado: Pepito, Juan (CUIL: 20302003009)', NULL, '{\"id\": 1, \"cuil\": \"20302003009\", \"nombre\": \"Juan\", \"apellido\": \"Pepito\", \"telefono\": \"3491456456\", \"porcentaje_ganancia\": 16}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:05:32'),
(5, 8, 'admin1', 'admin', 'editar', 'vehiculos', 'Vehículo actualizado: AA001AA (ID: 3)', '{\"anio\": 2026, \"marca\": \"SCANIA\", \"modelo\": \"16FG\", \"dominio\": \"AA001AA\", \"acoplado\": \"AA001AB\", \"chofer_id\": null}', '{\"anio\": 2026, \"marca\": \"SCANIA\", \"modelo\": \"16FG\", \"dominio\": \"AA001AA\", \"acoplado\": \"AA001AB\", \"chofer_id\": 1, \"vtv_vencimiento\": \"2030-01-01\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:05:40'),
(6, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 22:05:44'),
(7, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 11:57:38'),
(8, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 12:13:19'),
(9, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 12:37:24'),
(10, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:16:02'),
(11, 1, 'alucyk', 'developer', 'cambiar_empresa', 'empresas', 'Cambio de empresa activa de \'TRANSPORTES SRL\' a \'TRANSPORTES SRL\'', '{\"empresa_anterior_id\": null, \"empresa_anterior_nombre\": \"TRANSPORTES SRL\"}', '{\"empresa_nueva_id\": \"2\", \"empresa_nueva_nombre\": \"TRANSPORTES SRL\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:16:08'),
(12, 1, 'alucyk', 'developer', 'cambiar_empresa', 'empresas', 'Cambio de empresa activa de \'TRANSPORTES SRL\' a \'TRANSPORTES SRL\'', '{\"empresa_anterior_id\": null, \"empresa_anterior_nombre\": \"TRANSPORTES SRL\"}', '{\"empresa_nueva_id\": \"5\", \"empresa_nueva_nombre\": \"TRANSPORTES SRL\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:16:10'),
(13, 1, 'alucyk', 'developer', 'cambiar_empresa', 'empresas', 'Cambio de empresa activa de \'TRANSPORTES SRL\' a \'TRANSPORTES SRL\'', '{\"empresa_anterior_id\": null, \"empresa_anterior_nombre\": \"TRANSPORTES SRL\"}', '{\"empresa_nueva_id\": \"2\", \"empresa_nueva_nombre\": \"TRANSPORTES SRL\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:16:12'),
(14, 1, 'alucyk', 'developer', 'crear', 'choferes', 'Nuevo chofer registrado: LUCYK, ALEXIS (CUIL: 20320743339)', NULL, '{\"id\": 2, \"cuil\": \"20320743339\", \"nombre\": \"ALEXIS\", \"apellido\": \"LUCYK\", \"telefono\": \"3491438555\", \"porcentaje_ganancia\": 16}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:19:58'),
(15, 1, 'alucyk', 'developer', 'crear', 'vehiculos', 'Nuevo vehículo registrado: AB495TR (PEUGEOT PARTNER PATAGONICA)', NULL, '{\"id\": 4, \"anio\": 2017, \"marca\": \"PEUGEOT\", \"modelo\": \"PARTNER PATAGONICA\", \"dominio\": \"AB495TR\", \"acoplado\": \"AB495TS\", \"chofer_id\": 2}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:20:39'),
(16, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 13:57:16'),
(17, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 14:29:16'),
(18, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 15:01:39'),
(19, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 18:38:39'),
(20, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-11 19:42:32'),
(21, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-21 21:14:40'),
(22, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-21 22:58:43'),
(23, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-22 11:08:21'),
(24, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-22 14:35:37'),
(25, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-22 14:59:54'),
(26, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-22 22:56:29'),
(27, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 00:07:53'),
(28, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 11:02:47'),
(29, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 12:59:06'),
(30, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 13:04:16'),
(31, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 14:21:04'),
(32, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 15:01:44'),
(33, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 15:35:23'),
(34, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 19:04:39'),
(35, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 19:35:07'),
(36, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 22:49:23'),
(37, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-23 23:21:58'),
(38, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 00:10:02'),
(39, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 00:41:18'),
(40, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 11:00:32'),
(41, 1, 'alucyk', 'developer', 'crear', 'choferes', 'Nuevo chofer registrado: DUARTE, LUCAS GERARDO (CUIL: 20308796648)', NULL, '{\"id\": 3, \"cuil\": \"20308796648\", \"nombre\": \"LUCAS GERARDO\", \"apellido\": \"DUARTE\", \"telefono\": \"3491\", \"porcentaje_ganancia\": 15}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 11:04:11'),
(42, 1, 'alucyk', 'developer', 'crear', 'vehiculos', 'Nuevo vehículo registrado: JNA863 (CAMION SIN MODELO)', NULL, '{\"id\": 5, \"anio\": 2026, \"marca\": \"CAMION\", \"modelo\": \"SIN MODELO\", \"dominio\": \"JNA863\", \"acoplado\": \"AE924ZR\", \"chofer_id\": 3}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 11:05:14'),
(43, 1, 'alucyk', 'developer', 'editar', 'vehiculos', 'Vehículo actualizado: JNA863 (ID: 5)', '{\"anio\": 2026, \"marca\": \"CAMION\", \"modelo\": \"SIN MODELO\", \"dominio\": \"JNA863\", \"acoplado\": \"AE924ZR\", \"chofer_id\": 3}', '{\"anio\": 2026, \"marca\": \"CAMION\", \"modelo\": \"SIN MODELO\", \"dominio\": \"JNA863\", \"acoplado\": \"AE924ZR\", \"chofer_id\": 3, \"vtv_vencimiento\": \"2027-07-24\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 11:05:32'),
(44, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 11:32:49'),
(45, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 12:22:02'),
(46, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 13:57:27'),
(47, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 13:59:09'),
(48, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 14:14:26'),
(49, 1, 'alucyk', 'developer', 'logout', 'auth', 'Cierre de sesión del usuario', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 15:39:41'),
(50, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 19:12:00'),
(51, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 20:50:48'),
(52, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 20:51:08'),
(53, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 20:51:48'),
(54, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 20:52:27'),
(55, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:18:35'),
(56, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:20:06'),
(57, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:21:23'),
(58, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:21:42'),
(59, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:21:58'),
(60, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:22:29'),
(61, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:23:04'),
(62, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:34:53'),
(63, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 21:35:04'),
(64, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 22:20:02'),
(65, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 22:53:17'),
(66, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 23:19:34'),
(67, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 23:19:42'),
(68, 8, 'admin1', 'admin', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"admin\", \"username\": \"admin1\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 23:19:57'),
(69, 1, 'alucyk', 'developer', 'login', 'auth', 'Inicio de sesión exitoso', NULL, '{\"role\": \"developer\", \"username\": \"alucyk\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-24 23:29:28');

-- --------------------------------------------------------

--
-- Table structure for table `choferes`
--

CREATE TABLE `choferes` (
  `id` int NOT NULL,
  `transportista_id` int DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuil` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `licencia_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vencimiento_licencia` date DEFAULT NULL,
  `porcentaje_ganancia` decimal(5,2) DEFAULT '0.00',
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `choferes`
--

INSERT INTO `choferes` (`id`, `transportista_id`, `nombre`, `apellido`, `cuil`, `licencia_nro`, `vencimiento_licencia`, `porcentaje_ganancia`, `telefono`, `activo`, `created_by`, `created_at`) VALUES
(1, NULL, 'Juan', 'Pepito', '20302003009', '30200300', '2027-01-01', 16.00, '3491456456', 1, 8, '2026-07-10 22:05:32'),
(2, 2, 'ALEXIS', 'LUCYK', '20320743339', '2032074333', '2029-01-01', 16.00, '3491438555', 1, 1, '2026-07-11 13:19:58'),
(3, 2, 'LUCAS GERARDO', 'DUARTE', '20308796648', '2030879664', '2027-07-24', 15.00, '3491', 1, 1, '2026-07-24 11:04:11');

-- --------------------------------------------------------

--
-- Table structure for table `chofer_gastos`
--

CREATE TABLE `chofer_gastos` (
  `id` int NOT NULL,
  `chofer_id` int NOT NULL,
  `tipo_gasto` enum('combustible','peaje','comida','alojamiento','reparacion','otros') COLLATE utf8mb4_unicode_ci DEFAULT 'otros',
  `monto` decimal(15,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chofer_pagos`
--

CREATE TABLE `chofer_pagos` (
  `id` int NOT NULL,
  `chofer_id` int NOT NULL,
  `viaje_id` int DEFAULT NULL,
  `ctg_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adelanto_total` decimal(15,2) DEFAULT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `tipo` enum('adelanto','sueldo','liquidacion','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chofer_pagos`
--

INSERT INTO `chofer_pagos` (`id`, `chofer_id`, `viaje_id`, `ctg_nro`, `adelanto_total`, `fecha`, `monto`, `tipo`, `detalle`) VALUES
(1, 2, NULL, NULL, NULL, '2026-07-11', 160256.00, 'liquidacion', 'Liquidación de CTG 123456'),
(2, 2, NULL, NULL, NULL, '2026-07-11', 75000.00, 'adelanto', 'Sobrante Adelanto CTG 123456'),
(3, 2, NULL, NULL, NULL, '2026-07-11', 157696.00, 'liquidacion', 'Liquidación de CTG 5555555'),
(4, 2, NULL, NULL, NULL, '2026-07-11', 65000.00, 'adelanto', 'Sobrante Adelanto CTG 5555555'),
(5, 2, 3, '999999', NULL, '2026-07-23', 165376.00, 'liquidacion', 'Liquidación de CTG 999999'),
(6, 2, 3, '999999', NULL, '2026-07-23', 50000.00, 'adelanto', 'Sobrante Adelanto CTG 999999');

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `razon_social` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuit` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_comercial` tinyint(1) DEFAULT '0',
  `es_pagador` tinyint(1) DEFAULT '0',
  `es_comisionista` tinyint(1) DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`id`, `transportista_id`, `razon_social`, `cuit`, `direccion`, `telefono`, `es_comercial`, `es_pagador`, `es_comisionista`, `activo`, `created_by`, `created_at`) VALUES
(7, 2, 'CAMPOS DE NOCHERO SRL', '30999999990', 'Zona Urbana - El Nochero', '123123', 1, 1, 0, 1, 1, '2026-07-11 13:58:27'),
(8, 2, 'COMISIONES S.A.', '88123456457', 'Zona Urbana - El Nochero', '345345345', 0, 0, 1, 1, 1, '2026-07-11 13:58:59'),
(9, 2, 'AGROPRODUCCIONES TORRESI S.R.L.', '30711522952', 'SGO.DEL ESTERO', '3491', 1, 1, 0, 1, 1, '2026-07-24 11:07:37');

-- --------------------------------------------------------

--
-- Table structure for table `cobros_fletes`
--

CREATE TABLE `cobros_fletes` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `viaje_id` int NOT NULL,
  `cuenta_id` int DEFAULT NULL COMMENT 'Cuenta de caja destino (cuentas_empresa.id)',
  `fecha_cobro` date NOT NULL,
  `monto_total_facturado` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total de la factura (neto+iva)',
  `monto_neto_cobrado` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Total cobrado despu├®s de retenciones',
  `total_retenciones` decimal(15,2) NOT NULL DEFAULT '0.00',
  `medio_cobro` enum('efectivo','transferencia','cheque','mercadopago','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'efectivo',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobros_fletes_cheques`
--

CREATE TABLE `cobros_fletes_cheques` (
  `id` int NOT NULL,
  `cobro_id` int NOT NULL,
  `tipo_cheque` enum('comun','diferido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'comun',
  `banco` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_cheque` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL COMMENT 'Fecha de cobro diferido',
  `librador` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Quien emite el cheque',
  `endosante` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'A quien se endosa',
  `importe` decimal(15,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobros_fletes_medios`
--

CREATE TABLE `cobros_fletes_medios` (
  `id` int NOT NULL,
  `cobro_id` int NOT NULL,
  `medio_cobro` varchar(50) NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `observaciones` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobros_fletes_retenciones`
--

CREATE TABLE `cobros_fletes_retenciones` (
  `id` int NOT NULL,
  `cobro_id` int NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: IVA, Ganancias, Ingresos Brutos, SUSS, Otro',
  `concepto` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comisionista_pagos`
--

CREATE TABLE `comisionista_pagos` (
  `id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `tipo` enum('comision','pago') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pago',
  `detalle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comisionista_pagos`
--

INSERT INTO `comisionista_pagos` (`id`, `cliente_id`, `fecha`, `monto`, `tipo`, `detalle`) VALUES
(1, 8, '2026-07-23', 70000.00, 'comision', 'Comisión CTG 999999');

-- --------------------------------------------------------

--
-- Table structure for table `configuraciones`
--

CREATE TABLE `configuraciones` (
  `clave` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `configuraciones`
--

INSERT INTO `configuraciones` (`clave`, `valor`) VALUES
('limite_choferes', '0'),
('limite_empresas', '0'),
('limite_vehiculos', '0'),
('tema', 'corporativo');

-- --------------------------------------------------------

--
-- Table structure for table `cuentas_empresa`
--

CREATE TABLE `cuentas_empresa` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('banco','billetera_virtual','caja_efectivo','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'banco',
  `banco` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_cuenta` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cbu` varchar(22) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titular` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuit_titular` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saldo_inicial` decimal(15,2) DEFAULT '0.00',
  `saldo_actual` decimal(15,2) DEFAULT '0.00',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cuentas_empresa`
--

INSERT INTO `cuentas_empresa` (`id`, `transportista_id`, `nombre`, `tipo`, `banco`, `numero_cuenta`, `cbu`, `alias`, `titular`, `cuit_titular`, `saldo_inicial`, `saldo_actual`, `activo`, `created_at`) VALUES
(1, 2, 'Caja Ahorro', 'banco', 'Banco Nacion', '45454646456465454656', '7492850361849205837154', 'transporte.camion', 'Usuario Titular', '30125465858', 0.00, 0.00, 1, '2026-07-11 13:59:32'),
(2, 2, 'Mercado Pago', 'billetera_virtual', 'Mercado Pago', '12369874', '7492850361849205837165', 'transporte.mp', 'Usuario Titular', '30125465858', 0.00, 0.00, 1, '2026-07-11 13:59:57');

-- --------------------------------------------------------

--
-- Table structure for table `cuentas_movimientos`
--

CREATE TABLE `cuentas_movimientos` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `cuenta_id` int NOT NULL COMMENT 'cuentas_empresa.id',
  `tipo` enum('entrada','salida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ej: cobro_flete, retiro_efectivo, transferencia, gasto, ajuste',
  `referencia_id` int DEFAULT NULL COMMENT 'ID del registro origen (ej: cobros_fletes.id)',
  `monto` decimal(15,2) NOT NULL DEFAULT '0.00',
  `saldo_resultante` decimal(15,2) DEFAULT NULL COMMENT 'Saldo de la cuenta después del movimiento',
  `fecha_movimiento` date NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `fecha` date NOT NULL,
  `kilometraje` int DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo_total` decimal(15,2) DEFAULT '0.00',
  `proximo_service_km` int DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores_catalogos`
--

CREATE TABLE `proveedores_catalogos` (
  `id` int NOT NULL,
  `cod_prov` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `costo` decimal(15,2) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transportistas`
--

CREATE TABLE `transportistas` (
  `id` int NOT NULL,
  `razon_social` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuit` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transportistas`
--

INSERT INTO `transportistas` (`id`, `razon_social`, `cuit`, `direccion`, `telefono`, `email`, `created_at`, `created_by`, `activo`) VALUES
(2, 'TRANSPORTES SRL', '30915283217', 'Zona Urbana - El Nochero', '1234567890', 'mail@mail.com', '2026-07-10 21:12:29', 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','user','developer') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `created_by`, `created_at`) VALUES
(1, 'alucyk', '$2y$10$stSH8Kbz8sgMF2M9gHSDquKW/e/kU34E2iSwsFf2dldmUe961Tjw6', 'Developer', 'developer', NULL, '2026-05-12 22:40:56'),
(8, 'admin1', '$2y$10$Tj6DYgyVhU2viM1gSH42qOEN4ECGZlbD8QBB.L12bz/xk0TKJ5ZoG', 'Administrador 1', 'admin', 1, '2026-07-10 20:03:37'),
(9, 'admin2', '$2y$10$6W74gQsJhKgnqShHHJqZ3et8sSJ1voyW9iV6xO67/aFyciS0Lu3PK', 'Administrador 2', 'admin', 1, '2026-07-11 11:58:05');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `module`) VALUES
(8, 'auditoria'),
(8, 'choferes'),
(8, 'choferes_ctacte'),
(8, 'choferes_liquidar'),
(8, 'cobranzas'),
(8, 'comisionistas'),
(8, 'comisionistas_ctacte'),
(8, 'config_permisos_usuarios'),
(8, 'configuracion'),
(8, 'empresas'),
(8, 'importar_carta_porte'),
(8, 'tesoreria'),
(8, 'viajes');

-- --------------------------------------------------------

--
-- Table structure for table `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `chofer_id` int DEFAULT NULL,
  `dominio` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acoplado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anio` int DEFAULT NULL,
  `vtv_vencimiento` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `transportista_id`, `chofer_id`, `dominio`, `marca`, `modelo`, `acoplado`, `anio`, `vtv_vencimiento`, `activo`, `created_by`, `created_at`) VALUES
(4, 2, 2, 'AB495TR', 'PEUGEOT', 'PARTNER PATAGONICA', 'AB495TS', 2017, '2028-01-01', 1, 1, '2026-07-11 13:20:39'),
(5, 2, 3, 'JNA863', 'CAMION', 'SIN MODELO', 'AE924ZR', 2026, '2027-07-24', 1, 1, '2026-07-24 11:05:12');

-- --------------------------------------------------------

--
-- Table structure for table `viajes`
--

CREATE TABLE `viajes` (
  `id` int NOT NULL,
  `transportista_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `chofer_id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `acoplado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destino` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_carga` date NOT NULL,
  `peso_estimado` decimal(12,2) DEFAULT '0.00',
  `peso_bruto` decimal(12,2) DEFAULT '0.00',
  `peso_tara` decimal(12,2) DEFAULT '0.00',
  `peso_neto` decimal(12,2) DEFAULT NULL,
  `tarifa_tonelada` decimal(15,2) DEFAULT '0.00',
  `total_flete_bruto` decimal(15,2) DEFAULT '0.00',
  `total_flete_neto` decimal(15,2) DEFAULT '0.00',
  `factura_importe_neto` decimal(15,2) DEFAULT '0.00',
  `factura_iva_porcentaje` decimal(5,2) DEFAULT '21.00',
  `factura_importe_iva` decimal(15,2) DEFAULT '0.00',
  `factura_importe_total` decimal(15,2) DEFAULT '0.00',
  `chofer_porcentaje` decimal(5,2) DEFAULT '0.00',
  `acreditado_chofer` tinyint(1) DEFAULT '0',
  `comision_tipo` enum('ninguna','porcentaje','monto_fijo') COLLATE utf8mb4_unicode_ci DEFAULT 'ninguna',
  `comision_valor` decimal(15,2) DEFAULT '0.00',
  `comision_pagada` tinyint(1) DEFAULT '0',
  `comisionista_id` int DEFAULT NULL,
  `comision_receptor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ctg_nro` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carta_porte_nro` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otros_docs` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagador_id` int DEFAULT NULL,
  `pagador_flete` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factura_nro` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factura_fecha` date DEFAULT NULL,
  `fecha_cobro` date DEFAULT NULL,
  `estado` enum('en_viaje','descargado','facturado','cobrado','liquidado') COLLATE utf8mb4_unicode_ci DEFAULT 'en_viaje',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `viajes`
--

INSERT INTO `viajes` (`id`, `transportista_id`, `cliente_id`, `chofer_id`, `vehiculo_id`, `acoplado`, `origen`, `destino`, `producto`, `fecha_carga`, `peso_estimado`, `peso_bruto`, `peso_tara`, `peso_neto`, `tarifa_tonelada`, `total_flete_bruto`, `total_flete_neto`, `factura_importe_neto`, `factura_iva_porcentaje`, `factura_importe_iva`, `factura_importe_total`, `chofer_porcentaje`, `acreditado_chofer`, `comision_tipo`, `comision_valor`, `comision_pagada`, `comisionista_id`, `comision_receptor`, `ctg_nro`, `carta_porte_nro`, `otros_docs`, `pagador_id`, `pagador_flete`, `factura_nro`, `factura_fecha`, `fecha_cobro`, `estado`, `observaciones`, `activo`, `created_at`) VALUES
(1, 2, 7, 2, 4, 'AB495TS', 'NOCHERO', 'SAN LORENZO', 'TRIGO', '2026-07-11', 30.00, 30.00, 0.00, 31.30, 32000.00, 960000.00, 1001600.00, 0.00, 21.00, 0.00, 0.00, 16.00, 0, 'monto_fijo', 50000.00, 0, 8, NULL, '123456', NULL, NULL, 7, NULL, '1-1234', '2026-07-11', '2026-07-12', 'facturado', NULL, 1, '2026-07-11 14:01:02'),
(2, 2, 7, 2, 4, 'AB495TS', 'NOCHERO', 'SAN LORENZO', 'TRIGO', '2026-07-11', 30.00, 30.00, 0.00, 30.80, 32000.00, 960000.00, 985600.00, 0.00, 21.00, 0.00, 0.00, 16.00, 0, 'monto_fijo', 50000.00, 0, 8, NULL, '5555555', NULL, NULL, 7, NULL, NULL, NULL, NULL, 'descargado', NULL, 1, '2026-07-11 14:17:47'),
(3, 2, 7, 2, 4, 'AB495TS', 'NOCHERO', 'SANTA FE', 'MAIZ', '2026-07-23', 30.00, 30.00, 0.00, 32.30, 32000.00, 960000.00, 1033600.00, 0.00, 21.00, 0.00, 0.00, 16.00, 0, 'monto_fijo', 70000.00, 1, 8, NULL, '999999', NULL, NULL, 7, NULL, NULL, NULL, NULL, 'descargado', NULL, 1, '2026-07-23 11:31:45'),
(4, 2, 9, 3, 5, 'AE924ZR', 'POZO HERRERA (SGO.DEL ESTERO)', 'SAN LORENZO (SANTA FE)', 'SOJA', '2026-07-24', 36.00, 36.00, 0.00, NULL, 65000.00, 2340000.00, 2340000.00, 0.00, 21.00, 0.00, 0.00, 15.00, 0, 'ninguna', 0.00, 0, NULL, NULL, '10132795390', '30711522952', NULL, 9, NULL, NULL, NULL, NULL, 'en_viaje', NULL, 1, '2026-07-24 19:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `viajes_adelantos`
--

CREATE TABLE `viajes_adelantos` (
  `id` int NOT NULL,
  `viaje_id` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `fecha` date NOT NULL,
  `metodo_pago` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `viajes_adelantos`
--

INSERT INTO `viajes_adelantos` (`id`, `viaje_id`, `monto`, `fecha`, `metodo_pago`, `activo`) VALUES
(1, 1, 100000.00, '2026-07-11', 'efectivo', 1),
(2, 2, 100000.00, '2026-07-11', 'efectivo', 1),
(3, 3, 100000.00, '2026-07-23', 'efectivo', 1);

-- --------------------------------------------------------

--
-- Table structure for table `viajes_gastos`
--

CREATE TABLE `viajes_gastos` (
  `id` int NOT NULL,
  `viaje_id` int NOT NULL,
  `tipo_gasto` enum('combustible','peaje','playa','reparacion_ruta','otros','nuevo_tipo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagado_por` enum('empresa','adelanto','descuento_flete') COLLATE utf8mb4_unicode_ci DEFAULT 'empresa',
  `fecha` date NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `viajes_gastos`
--

INSERT INTO `viajes_gastos` (`id`, `viaje_id`, `tipo_gasto`, `monto`, `descripcion`, `pagado_por`, `fecha`, `activo`) VALUES
(1, 1, 'playa', 25000.00, NULL, 'adelanto', '2026-07-11', 1),
(2, 2, 'playa', 35000.00, NULL, 'adelanto', '2026-07-11', 1),
(3, 3, 'peaje', 15000.00, NULL, 'adelanto', '2026-07-23', 1),
(4, 3, 'playa', 35000.00, NULL, 'adelanto', '2026-07-23', 1);

-- --------------------------------------------------------

--
-- Table structure for table `viaje_factura_items`
--

CREATE TABLE `viaje_factura_items` (
  `id` int NOT NULL,
  `viaje_id` int NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `operacion` enum('suma','resta') COLLATE utf8mb4_unicode_ci DEFAULT 'suma',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_limites`
--
ALTER TABLE `admin_limites`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `accion` (`accion`),
  ADD KEY `modulo` (`modulo`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_user_fecha` (`user_id`,`created_at`);

--
-- Indexes for table `choferes`
--
ALTER TABLE `choferes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_choferes_cuil_tenant` (`cuil`,`transportista_id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `idx_choferes_created_by` (`created_by`);

--
-- Indexes for table `chofer_gastos`
--
ALTER TABLE `chofer_gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chofer_id` (`chofer_id`);

--
-- Indexes for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chofer_id` (`chofer_id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_clientes_cuit_tenant` (`cuit`,`transportista_id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `idx_clientes_activo` (`activo`),
  ADD KEY `idx_clientes_created_by` (`created_by`);

--
-- Indexes for table `cobros_fletes`
--
ALTER TABLE `cobros_fletes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `viaje_id` (`viaje_id`),
  ADD KEY `cuenta_id` (`cuenta_id`);

--
-- Indexes for table `cobros_fletes_cheques`
--
ALTER TABLE `cobros_fletes_cheques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cobro_id` (`cobro_id`);

--
-- Indexes for table `cobros_fletes_medios`
--
ALTER TABLE `cobros_fletes_medios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cobro_id` (`cobro_id`);

--
-- Indexes for table `cobros_fletes_retenciones`
--
ALTER TABLE `cobros_fletes_retenciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cobro_id` (`cobro_id`);

--
-- Indexes for table `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indexes for table `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `cuentas_empresa`
--
ALTER TABLE `cuentas_empresa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transportista_id` (`transportista_id`);

--
-- Indexes for table `cuentas_movimientos`
--
ALTER TABLE `cuentas_movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `cuenta_id` (`cuenta_id`),
  ADD KEY `referencia` (`referencia_tipo`,`referencia_id`),
  ADD KEY `fecha_movimiento` (`fecha_movimiento`);

--
-- Indexes for table `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `idx_mantenimientos_activo` (`activo`),
  ADD KEY `idx_mantenimientos_created_by` (`created_by`);

--
-- Indexes for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cod_prov` (`cod_prov`);

--
-- Indexes for table `transportistas`
--
ALTER TABLE `transportistas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuit` (`cuit`),
  ADD KEY `fk_transportistas_created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_created_by` (`created_by`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`module`);

--
-- Indexes for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_vehiculos_dominio_tenant` (`dominio`,`transportista_id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `chofer_id` (`chofer_id`),
  ADD KEY `idx_vehiculos_activo` (`activo`),
  ADD KEY `idx_vehiculos_created_by` (`created_by`);

--
-- Indexes for table `viajes`
--
ALTER TABLE `viajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `chofer_id` (`chofer_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `comisionista_id` (`comisionista_id`),
  ADD KEY `pagador_id` (`pagador_id`);

--
-- Indexes for table `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- Indexes for table `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- Indexes for table `viaje_factura_items`
--
ALTER TABLE `viaje_factura_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `choferes`
--
ALTER TABLE `choferes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chofer_gastos`
--
ALTER TABLE `chofer_gastos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cobros_fletes`
--
ALTER TABLE `cobros_fletes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cobros_fletes_cheques`
--
ALTER TABLE `cobros_fletes_cheques`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cobros_fletes_medios`
--
ALTER TABLE `cobros_fletes_medios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cobros_fletes_retenciones`
--
ALTER TABLE `cobros_fletes_retenciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cuentas_empresa`
--
ALTER TABLE `cuentas_empresa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cuentas_movimientos`
--
ALTER TABLE `cuentas_movimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transportistas`
--
ALTER TABLE `transportistas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `viajes`
--
ALTER TABLE `viajes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `viaje_factura_items`
--
ALTER TABLE `viaje_factura_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_limites`
--
ALTER TABLE `admin_limites`
  ADD CONSTRAINT `fk_admin_limites_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `choferes`
--
ALTER TABLE `choferes`
  ADD CONSTRAINT `choferes_fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `choferes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chofer_gastos`
--
ALTER TABLE `chofer_gastos`
  ADD CONSTRAINT `chofer_gastos_ibfk_1` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD CONSTRAINT `chofer_pagos_ibfk_1` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chofer_pagos_ibfk_2` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cobros_fletes`
--
ALTER TABLE `cobros_fletes`
  ADD CONSTRAINT `cobros_fletes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cobros_fletes_ibfk_2` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cobros_fletes_ibfk_3` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_empresa` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cobros_fletes_cheques`
--
ALTER TABLE `cobros_fletes_cheques`
  ADD CONSTRAINT `cobros_fletes_cheques_ibfk_1` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_fletes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cobros_fletes_medios`
--
ALTER TABLE `cobros_fletes_medios`
  ADD CONSTRAINT `cobros_fletes_medios_ibfk_1` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_fletes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cobros_fletes_retenciones`
--
ALTER TABLE `cobros_fletes_retenciones`
  ADD CONSTRAINT `cobros_fletes_retenciones_ibfk_1` FOREIGN KEY (`cobro_id`) REFERENCES `cobros_fletes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  ADD CONSTRAINT `comisionista_pagos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cuentas_empresa`
--
ALTER TABLE `cuentas_empresa`
  ADD CONSTRAINT `cuentas_empresa_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cuentas_movimientos`
--
ALTER TABLE `cuentas_movimientos`
  ADD CONSTRAINT `cuentas_movimientos_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cuentas_movimientos_ibfk_2` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_empresa` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `mantenimientos_fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mantenimientos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Constraints for table `transportistas`
--
ALTER TABLE `transportistas`
  ADD CONSTRAINT `fk_transportistas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehiculos_ibfk_2` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `viajes`
--
ALTER TABLE `viajes`
  ADD CONSTRAINT `viajes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `viajes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_3` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_4` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `viajes_ibfk_5` FOREIGN KEY (`comisionista_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_6` FOREIGN KEY (`pagador_id`) REFERENCES `clientes` (`id`);

--
-- Constraints for table `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  ADD CONSTRAINT `viajes_adelantos_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  ADD CONSTRAINT `viajes_gastos_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `viaje_factura_items`
--
ALTER TABLE `viaje_factura_items`
  ADD CONSTRAINT `viaje_factura_items_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
