-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 24, 2026 at 10:37 PM
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
-- Database: `app_base`
--
CREATE DATABASE IF NOT EXISTS `app_base` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `app_base`;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','usuario') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'usuario',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Database: `app_engine_db`
--
CREATE DATABASE IF NOT EXISTS `app_engine_db` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_slovenian_ci;
USE `app_engine_db`;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb3_slovenian_ci NOT NULL,
  `contacto` varchar(100) COLLATE utf8mb3_slovenian_ci DEFAULT NULL,
  `estado` enum('activo','inactivo') COLLATE utf8mb3_slovenian_ci DEFAULT 'activo',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_slovenian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(50) COLLATE utf8mb3_slovenian_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb3_slovenian_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_slovenian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `licencias`
--

CREATE TABLE `licencias` (
  `id` int NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `software_nombre` varchar(100) COLLATE utf8mb3_slovenian_ci NOT NULL,
  `hwid` varchar(255) COLLATE utf8mb3_slovenian_ci NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` enum('valida','vencida','suspendida') COLLATE utf8mb3_slovenian_ci DEFAULT 'valida'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_slovenian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modulos`
--

CREATE TABLE `modulos` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb3_slovenian_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb3_slovenian_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb3_slovenian_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_slovenian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `validaciones_log`
--

CREATE TABLE `validaciones_log` (
  `id` int NOT NULL,
  `hwid` varchar(255) COLLATE utf8mb3_slovenian_ci DEFAULT NULL,
  `software` varchar(100) COLLATE utf8mb3_slovenian_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_slovenian_ci DEFAULT NULL,
  `fecha_hora` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resultado` enum('autorizado','denegado','pendiente') COLLATE utf8mb3_slovenian_ci DEFAULT 'autorizado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_slovenian_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indexes for table `licencias`
--
ALTER TABLE `licencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hwid` (`hwid`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indexes for table `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `validaciones_log`
--
ALTER TABLE `validaciones_log`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `licencias`
--
ALTER TABLE `licencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `validaciones_log`
--
ALTER TABLE `validaciones_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `licencias`
--
ALTER TABLE `licencias`
  ADD CONSTRAINT `licencias_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;
--
-- Database: `calculadora_3d`
--
CREATE DATABASE IF NOT EXISTS `calculadora_3d` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `calculadora_3d`;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion_global`
--

CREATE TABLE `configuracion_global` (
  `id` int NOT NULL,
  `precio_kwh` decimal(10,2) NOT NULL,
  `costo_hora_mano_obra` decimal(10,2) NOT NULL,
  `porcentaje_error_defecto` int NOT NULL DEFAULT '10'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `filamentos`
--

CREATE TABLE `filamentos` (
  `id` int NOT NULL,
  `marca` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `precio_rollo` decimal(10,2) NOT NULL,
  `peso_rollo_g` int NOT NULL DEFAULT '1000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impresiones`
--

CREATE TABLE `impresiones` (
  `id` int NOT NULL,
  `nombre_proyecto` varchar(150) NOT NULL,
  `impresora_id` int DEFAULT NULL,
  `filamento_id` int DEFAULT NULL,
  `peso_modelo_g` decimal(10,2) NOT NULL,
  `peso_desperdicio_g` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tiempo_impresion_minutos` int NOT NULL,
  `tiempo_mano_obra_minutos` int NOT NULL DEFAULT '0',
  `multiplicador` decimal(5,2) NOT NULL DEFAULT '3.00',
  `precio_final` decimal(10,2) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impresoras`
--

CREATE TABLE `impresoras` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `consumo_watts` int NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `horas_vida_util` int NOT NULL DEFAULT '2000'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `configuracion_global`
--
ALTER TABLE `configuracion_global`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `filamentos`
--
ALTER TABLE `filamentos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `impresiones`
--
ALTER TABLE `impresiones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `impresora_id` (`impresora_id`),
  ADD KEY `filamento_id` (`filamento_id`);

--
-- Indexes for table `impresoras`
--
ALTER TABLE `impresoras`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `configuracion_global`
--
ALTER TABLE `configuracion_global`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `filamentos`
--
ALTER TABLE `filamentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impresiones`
--
ALTER TABLE `impresiones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impresoras`
--
ALTER TABLE `impresoras`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `impresiones`
--
ALTER TABLE `impresiones`
  ADD CONSTRAINT `impresiones_ibfk_1` FOREIGN KEY (`impresora_id`) REFERENCES `impresoras` (`id`),
  ADD CONSTRAINT `impresiones_ibfk_2` FOREIGN KEY (`filamento_id`) REFERENCES `filamentos` (`id`);
--
-- Database: `comunagpdd`
--
CREATE DATABASE IF NOT EXISTS `comunagpdd` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `comunagpdd`;

-- --------------------------------------------------------

--
-- Table structure for table `agenda`
--

CREATE TABLE `agenda` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup`
--

CREATE TABLE `backup` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bancos`
--

CREATE TABLE `bancos` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cabaprov`
--

CREATE TABLE `cabaprov` (
  `BANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NUMERO1` int DEFAULT NULL,
  `NUMERO2` int DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SALDO` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cajaahor`
--

CREATE TABLE `cajaahor` (
  `CODBANCO` int DEFAULT NULL,
  `NUMERO1` int DEFAULT NULL,
  `NUMERO2` int DEFAULT NULL,
  `NOMTITULAR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccruprov`
--

CREATE TABLE `ccruprov` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccurprov`
--

CREATE TABLE `ccurprov` (
  `CODIGO` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cheques`
--

CREATE TABLE `cheques` (
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `CODCHEQUE` int DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `TITULAR` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobcuoru`
--

CREATE TABLE `cobcuoru` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobcuour`
--

CREATE TABLE `cobcuour` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cocoprov`
--

CREATE TABLE `cocoprov` (
  `CODIGO` int DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `codrubro`
--

CREATE TABLE `codrubro` (
  `CODRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compcont`
--

CREATE TABLE `compcont` (
  `CODIGO` int DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compoinv`
--

CREATE TABLE `compoinv` (
  `CODORDEN` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAALTA` datetime DEFAULT NULL,
  `FECHABAJA` datetime DEFAULT NULL,
  `UBICACION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `UNIDAD` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EXISTENCIA` float DEFAULT NULL,
  `VALORORIGEN` float DEFAULT NULL,
  `VALORACTUAL` float DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comuna`
--

CREATE TABLE `comuna` (
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DEPARTAMENTO` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CDEPARTAMENTO` smallint DEFAULT NULL,
  `CDISTRITO` smallint DEFAULT NULL,
  `CSUBDISTRITO` smallint DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contribu`
--

CREATE TABLE `contribu` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `control`
--

CREATE TABLE `control` (
  `CODIGO` smallint DEFAULT NULL,
  `NRORECIBO` int DEFAULT NULL,
  `NROORDEN` int DEFAULT NULL,
  `NROCOMPRA` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cubaprov`
--

CREATE TABLE `cubaprov` (
  `BANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SALDO` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuenbanc`
--

CREATE TABLE `cuenbanc` (
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `NOMTITULAR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuentas`
--

CREATE TABLE `cuentas` (
  `RUBRO` int DEFAULT NULL,
  `SRUBRO` int DEFAULT NULL,
  `SSRUBRO` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deucuoru`
--

CREATE TABLE `deucuoru` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `SALDO` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deucuour`
--

CREATE TABLE `deucuour` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `SALDO` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gencuoru`
--

CREATE TABLE `gencuoru` (
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gencuour`
--

CREATE TABLE `gencuour` (
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impoimpu`
--

CREATE TABLE `impoimpu` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `IMPUNICO` float DEFAULT NULL,
  `IMPEDNORMAL` float DEFAULT NULL,
  `IMPEDESQUINA` float DEFAULT NULL,
  `IMPBANORMAL` float DEFAULT NULL,
  `IMPBAESQUINA` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impuesto`
--

CREATE TABLE `impuesto` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `AFECTA` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ALICUOTA` varchar(12) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CALCULO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIVISION` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTAS` smallint DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `irurales`
--

CREATE TABLE `irurales` (
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `METROSF` float DEFAULT NULL,
  `HECTAREAS` float DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iurbanos`
--

CREATE TABLE `iurbanos` (
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PARTIDA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `METROSF` int DEFAULT NULL,
  `METROSC` float DEFAULT NULL,
  `TIPOTERRENO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `UBICACION` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lludatos`
--

CREATE TABLE `lludatos` (
  `CODIGO` int DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `FECHA` datetime DEFAULT NULL,
  `MILIME` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lluzonas`
--

CREATE TABLE `lluzonas` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manzanas`
--

CREATE TABLE `manzanas` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NORTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SUR` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OESTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mocaprov`
--

CREATE TABLE `mocaprov` (
  `CODIGO` int DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `EFECTIVO` float DEFAULT NULL,
  `BONO` float DEFAULT NULL,
  `CHEQUE` float DEFAULT NULL,
  `INGRESO` float DEFAULT NULL,
  `EGRESO` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movcaj`
--

CREATE TABLE `movcaj` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PANTALLA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `ORDENCOMPRA` int DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `AHNUMERO1` int DEFAULT NULL,
  `AHNUMERO2` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MOVEFECTIVO` float DEFAULT NULL,
  `MOVBONO` float DEFAULT NULL,
  `MOVCHEQUE` float DEFAULT NULL,
  `CAEFECTIVO` float DEFAULT NULL,
  `CABONO` float DEFAULT NULL,
  `CACHEQUE` float DEFAULT NULL,
  `CAINGRESO` float DEFAULT NULL,
  `CAEGRESO` float DEFAULT NULL,
  `CTEFECTIVO` float DEFAULT NULL,
  `CTCHEQUE` float DEFAULT NULL,
  `CTINGRESO` float DEFAULT NULL,
  `CTEGRESO` float DEFAULT NULL,
  `AHEFECTIVO` float DEFAULT NULL,
  `AHCHEQUE` float DEFAULT NULL,
  `AHINGRESO` float DEFAULT NULL,
  `AHEGRESO` float DEFAULT NULL,
  `CH1CODBANCO` int DEFAULT NULL,
  `CH1NROCUENTA1` int DEFAULT NULL,
  `CH1NROCUENTA2` int DEFAULT NULL,
  `CH1CODCHEQUE` int DEFAULT NULL,
  `CH2CODBANCO` int DEFAULT NULL,
  `CH2NROCUENTA1` int DEFAULT NULL,
  `CH2NROCUENTA2` int DEFAULT NULL,
  `CH2CODCHEQUE` int DEFAULT NULL,
  `CH3CODBANCO` int DEFAULT NULL,
  `CH3NROCUENTA1` int DEFAULT NULL,
  `CH3NROCUENTA2` int DEFAULT NULL,
  `CH3CODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OPERACION` int DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movicaja`
--

CREATE TABLE `movicaja` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PANTALLA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `ORDENCOMPRA` int DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `AHNUMERO1` int DEFAULT NULL,
  `AHNUMERO2` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MOVEFECTIVO` double DEFAULT NULL,
  `MOVBONO` float DEFAULT NULL,
  `MOVCHEQUE` double DEFAULT NULL,
  `CAEFECTIVO` double DEFAULT NULL,
  `CABONO` float DEFAULT NULL,
  `CACHEQUE` double DEFAULT NULL,
  `CAINGRESO` double DEFAULT NULL,
  `CAEGRESO` double DEFAULT NULL,
  `CTEFECTIVO` double DEFAULT NULL,
  `CTCHEQUE` float DEFAULT NULL,
  `CTINGRESO` float DEFAULT NULL,
  `CTEGRESO` float DEFAULT NULL,
  `AHEFECTIVO` double DEFAULT NULL,
  `AHCHEQUE` float DEFAULT NULL,
  `AHINGRESO` float DEFAULT NULL,
  `AHEGRESO` float DEFAULT NULL,
  `CH1CODBANCO` int DEFAULT NULL,
  `CH1NROCUENTA1` int DEFAULT NULL,
  `CH1NROCUENTA2` int DEFAULT NULL,
  `CH1CODCHEQUE` int DEFAULT NULL,
  `CH2CODBANCO` int DEFAULT NULL,
  `CH2NROCUENTA1` int DEFAULT NULL,
  `CH2NROCUENTA2` int DEFAULT NULL,
  `CH2CODCHEQUE` int DEFAULT NULL,
  `CH3CODBANCO` int DEFAULT NULL,
  `CH3NROCUENTA1` int DEFAULT NULL,
  `CH3NROCUENTA2` int DEFAULT NULL,
  `CH3CODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OPERACION` int DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movictac`
--

CREATE TABLE `movictac` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `EFECTIVO` float DEFAULT NULL,
  `CHEQUE` float DEFAULT NULL,
  `DEPOSITO` float DEFAULT NULL,
  `EXTRACCION` float DEFAULT NULL,
  `CHCODBANCO` int DEFAULT NULL,
  `CHCODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCAJA` int DEFAULT NULL,
  `ITEMCAJA` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordencom`
--

CREATE TABLE `ordencom` (
  `NROORDEN` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(7) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordenpag`
--

CREATE TABLE `ordenpag` (
  `NROORDEN` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPEFECTIVO` double DEFAULT NULL,
  `IMPBONO` float DEFAULT NULL,
  `IMPCHEQUE` double DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `papel`
--

CREATE TABLE `papel` (
  `CODIGO` smallint DEFAULT NULL,
  `PAPEL` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos`
--

CREATE TABLE `permisos` (
  `id` int NOT NULL,
  `CLAVE` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USERNAME` varchar(50) COLLATE utf8mb3_spanish_ci NOT NULL,
  `PASSWORD` varchar(255) COLLATE utf8mb3_spanish_ci NOT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `APELLIDO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CATEGORIA` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedo`
--

CREATE TABLE `proveedo` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recibos`
--

CREATE TABLE `recibos` (
  `NRORECIBO` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPEFECTIVO` double DEFAULT NULL,
  `IMPBONO` float DEFAULT NULL,
  `IMPCHEQUE` double DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rubroinv`
--

CREATE TABLE `rubroinv` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zonasurb`
--

CREATE TABLE `zonasurb` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `USERNAME` (`USERNAME`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
--
-- Database: `comuna_dev`
--
CREATE DATABASE IF NOT EXISTS `comuna_dev` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;
USE `comuna_dev`;

-- --------------------------------------------------------

--
-- Table structure for table `agenda`
--

CREATE TABLE `agenda` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup`
--

CREATE TABLE `backup` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bancos`
--

CREATE TABLE `bancos` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cabaprov`
--

CREATE TABLE `cabaprov` (
  `BANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NUMERO1` int DEFAULT NULL,
  `NUMERO2` int DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SALDO` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cajaahor`
--

CREATE TABLE `cajaahor` (
  `CODBANCO` int DEFAULT NULL,
  `NUMERO1` int DEFAULT NULL,
  `NUMERO2` int DEFAULT NULL,
  `NOMTITULAR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccruprov`
--

CREATE TABLE `ccruprov` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccurprov`
--

CREATE TABLE `ccurprov` (
  `CODIGO` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cheques`
--

CREATE TABLE `cheques` (
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `CODCHEQUE` int DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `TITULAR` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobcuoru`
--

CREATE TABLE `cobcuoru` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cobcuour`
--

CREATE TABLE `cobcuour` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `ENTREGA` float DEFAULT NULL,
  `FECHAENT` datetime DEFAULT NULL,
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cocoprov`
--

CREATE TABLE `cocoprov` (
  `CODIGO` int DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `codrubro`
--

CREATE TABLE `codrubro` (
  `CODRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compcont`
--

CREATE TABLE `compcont` (
  `CODIGO` int DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compoinv`
--

CREATE TABLE `compoinv` (
  `CODORDEN` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAALTA` datetime DEFAULT NULL,
  `FECHABAJA` datetime DEFAULT NULL,
  `UBICACION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `UNIDAD` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EXISTENCIA` float DEFAULT NULL,
  `VALORORIGEN` float DEFAULT NULL,
  `VALORACTUAL` float DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comuna`
--

CREATE TABLE `comuna` (
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DEPARTAMENTO` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CDEPARTAMENTO` smallint DEFAULT NULL,
  `CDISTRITO` smallint DEFAULT NULL,
  `CSUBDISTRITO` smallint DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contribu`
--

CREATE TABLE `contribu` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `control`
--

CREATE TABLE `control` (
  `CODIGO` smallint DEFAULT NULL,
  `NRORECIBO` int DEFAULT NULL,
  `NROORDEN` int DEFAULT NULL,
  `NROCOMPRA` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cubaprov`
--

CREATE TABLE `cubaprov` (
  `BANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SALDO` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuenbanc`
--

CREATE TABLE `cuenbanc` (
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `NOMTITULAR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `MONEDA` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuentas`
--

CREATE TABLE `cuentas` (
  `id` int NOT NULL,
  `RUBRO` int DEFAULT NULL,
  `SRUBRO` int DEFAULT NULL,
  `SSRUBRO` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deucuoru`
--

CREATE TABLE `deucuoru` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `SALDO` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deucuour`
--

CREATE TABLE `deucuour` (
  `CODIGO` int DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `SALDO` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `id` int NOT NULL,
  `LEGAJO` int NOT NULL,
  `APELLIDO` varchar(40) NOT NULL,
  `NOMBRE` varchar(40) NOT NULL,
  `CUIT` varchar(13) DEFAULT NULL,
  `FECHA_INGRESO` date DEFAULT NULL,
  `CARGO` varchar(50) DEFAULT NULL,
  `SUELDO_BASE` decimal(15,2) DEFAULT '0.00',
  `ESTADO` varchar(10) DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gencuoru`
--

CREATE TABLE `gencuoru` (
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gencuour`
--

CREATE TABLE `gencuour` (
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTA` smallint DEFAULT NULL,
  `ANO` smallint DEFAULT NULL,
  `MES` smallint DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impoimpu`
--

CREATE TABLE `impoimpu` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `IMPUNICO` float DEFAULT NULL,
  `IMPEDNORMAL` float DEFAULT NULL,
  `IMPEDESQUINA` float DEFAULT NULL,
  `IMPBANORMAL` float DEFAULT NULL,
  `IMPBAESQUINA` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impuesto`
--

CREATE TABLE `impuesto` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `AFECTA` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ALICUOTA` varchar(12) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CALCULO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIVISION` varchar(5) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUOTAS` smallint DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `irurales`
--

CREATE TABLE `irurales` (
  `CODIGO1` int DEFAULT NULL,
  `CODIGO2` int DEFAULT NULL,
  `CODIGO3` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `METROSF` float DEFAULT NULL,
  `HECTAREAS` float DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iurbanos`
--

CREATE TABLE `iurbanos` (
  `MANZANA` int DEFAULT NULL,
  `LOTE` int DEFAULT NULL,
  `LETRA` varchar(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PARTIDA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `METROSF` int DEFAULT NULL,
  `METROSC` float DEFAULT NULL,
  `TIPOTERRENO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `UBICACION` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCONTRIBUYENTE` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `liquidaciones`
--

CREATE TABLE `liquidaciones` (
  `id` int NOT NULL,
  `empleado_id` int NOT NULL,
  `periodo` varchar(7) NOT NULL,
  `fecha_emision` datetime DEFAULT CURRENT_TIMESTAMP,
  `sueldo_bruto` decimal(15,2) NOT NULL,
  `total_descuentos` decimal(15,2) NOT NULL,
  `sueldo_neto` decimal(15,2) NOT NULL,
  `detalles_json` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lludatos`
--

CREATE TABLE `lludatos` (
  `CODIGO` int DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `FECHA` datetime DEFAULT NULL,
  `MILIME` int DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lluzonas`
--

CREATE TABLE `lluzonas` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manzanas`
--

CREATE TABLE `manzanas` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NORTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `SUR` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OESTE` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODZONA` int DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mocaprov`
--

CREATE TABLE `mocaprov` (
  `CODIGO` int DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `EFECTIVO` float DEFAULT NULL,
  `BONO` float DEFAULT NULL,
  `CHEQUE` float DEFAULT NULL,
  `INGRESO` float DEFAULT NULL,
  `EGRESO` float DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modulos`
--

CREATE TABLE `modulos` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `clave` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modulos_sistema`
--

CREATE TABLE `modulos_sistema` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `clave` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movcaj`
--

CREATE TABLE `movcaj` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PANTALLA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `ORDENCOMPRA` int DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `AHNUMERO1` int DEFAULT NULL,
  `AHNUMERO2` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MOVEFECTIVO` float DEFAULT NULL,
  `MOVBONO` float DEFAULT NULL,
  `MOVCHEQUE` float DEFAULT NULL,
  `CAEFECTIVO` float DEFAULT NULL,
  `CABONO` float DEFAULT NULL,
  `CACHEQUE` float DEFAULT NULL,
  `CAINGRESO` float DEFAULT NULL,
  `CAEGRESO` float DEFAULT NULL,
  `CTEFECTIVO` float DEFAULT NULL,
  `CTCHEQUE` float DEFAULT NULL,
  `CTINGRESO` float DEFAULT NULL,
  `CTEGRESO` float DEFAULT NULL,
  `AHEFECTIVO` float DEFAULT NULL,
  `AHCHEQUE` float DEFAULT NULL,
  `AHINGRESO` float DEFAULT NULL,
  `AHEGRESO` float DEFAULT NULL,
  `CH1CODBANCO` int DEFAULT NULL,
  `CH1NROCUENTA1` int DEFAULT NULL,
  `CH1NROCUENTA2` int DEFAULT NULL,
  `CH1CODCHEQUE` int DEFAULT NULL,
  `CH2CODBANCO` int DEFAULT NULL,
  `CH2NROCUENTA1` int DEFAULT NULL,
  `CH2NROCUENTA2` int DEFAULT NULL,
  `CH2CODCHEQUE` int DEFAULT NULL,
  `CH3CODBANCO` int DEFAULT NULL,
  `CH3NROCUENTA1` int DEFAULT NULL,
  `CH3NROCUENTA2` int DEFAULT NULL,
  `CH3CODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OPERACION` int DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movicaja`
--

CREATE TABLE `movicaja` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PANTALLA` varchar(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `ORDENCOMPRA` int DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `AHNUMERO1` int DEFAULT NULL,
  `AHNUMERO2` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `MOVEFECTIVO` double DEFAULT NULL,
  `MOVBONO` float DEFAULT NULL,
  `MOVCHEQUE` double DEFAULT NULL,
  `CAEFECTIVO` double DEFAULT NULL,
  `CABONO` float DEFAULT NULL,
  `CACHEQUE` double DEFAULT NULL,
  `CAINGRESO` double DEFAULT NULL,
  `CAEGRESO` double DEFAULT NULL,
  `CTEFECTIVO` double DEFAULT NULL,
  `CTCHEQUE` float DEFAULT NULL,
  `CTINGRESO` float DEFAULT NULL,
  `CTEGRESO` float DEFAULT NULL,
  `AHEFECTIVO` double DEFAULT NULL,
  `AHCHEQUE` float DEFAULT NULL,
  `AHINGRESO` float DEFAULT NULL,
  `AHEGRESO` float DEFAULT NULL,
  `CH1CODBANCO` int DEFAULT NULL,
  `CH1NROCUENTA1` int DEFAULT NULL,
  `CH1NROCUENTA2` int DEFAULT NULL,
  `CH1CODCHEQUE` int DEFAULT NULL,
  `CH2CODBANCO` int DEFAULT NULL,
  `CH2NROCUENTA1` int DEFAULT NULL,
  `CH2NROCUENTA2` int DEFAULT NULL,
  `CH2CODCHEQUE` int DEFAULT NULL,
  `CH3CODBANCO` int DEFAULT NULL,
  `CH3NROCUENTA1` int DEFAULT NULL,
  `CH3NROCUENTA2` int DEFAULT NULL,
  `CH3CODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `OPERACION` int DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movictac`
--

CREATE TABLE `movictac` (
  `CODDIARIO` int DEFAULT NULL,
  `ITEM` int DEFAULT NULL,
  `TIPOMOV` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODBANCO` int DEFAULT NULL,
  `NROCUENTA1` int DEFAULT NULL,
  `NROCUENTA2` int DEFAULT NULL,
  `FECHACARGA` datetime DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `CODPROVEE` int DEFAULT NULL,
  `CODCONTRI` int DEFAULT NULL,
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `EFECTIVO` float DEFAULT NULL,
  `CHEQUE` float DEFAULT NULL,
  `DEPOSITO` float DEFAULT NULL,
  `EXTRACCION` float DEFAULT NULL,
  `CHCODBANCO` int DEFAULT NULL,
  `CHCODCHEQUE` int DEFAULT NULL,
  `NROCOMPROBANTE` int DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCAJA` int DEFAULT NULL,
  `ITEMCAJA` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordencom`
--

CREATE TABLE `ordencom` (
  `NROORDEN` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `NROCOMPROBANTE` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPORTE` float DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(7) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordenpag`
--

CREATE TABLE `ordenpag` (
  `NROORDEN` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPEFECTIVO` double DEFAULT NULL,
  `IMPBONO` float DEFAULT NULL,
  `IMPCHEQUE` double DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NROCUENTA1` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NROCUENTA2` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `papel`
--

CREATE TABLE `papel` (
  `CODIGO` smallint DEFAULT NULL,
  `PAPEL` varchar(11) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos`
--

CREATE TABLE `permisos` (
  `id` int NOT NULL,
  `USERNAME` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `PASSWORD` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `APELLIDO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CATEGORIA` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedo`
--

CREATE TABLE `proveedo` (
  `CODIGO` int DEFAULT NULL,
  `RAZON` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TIPODOC` varchar(3) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `NRODOC` int DEFAULT NULL,
  `NACIMIENTO` datetime DEFAULT NULL,
  `CUIT` varchar(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `DIRECCION` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `LOCALIDAD` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CPOSTAL` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `PROVINCIA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `TELEFONO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `EMAIL` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `USO` varchar(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recibos`
--

CREATE TABLE `recibos` (
  `NRORECIBO` int DEFAULT NULL,
  `FECHACARGA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `FECHAMOV` datetime DEFAULT NULL,
  `PROVEEDOR` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CONCEPTO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CUENTA` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CODCUENTA` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `IMPEFECTIVO` double DEFAULT NULL,
  `IMPBONO` float DEFAULT NULL,
  `IMPCHEQUE` double DEFAULT NULL,
  `IMPORTE` double DEFAULT NULL,
  `IMPLETRA` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH1CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH2CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3NOMBANCO` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `CH3CODCHEQUE` varchar(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO1` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO2` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `ESTADO` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rubroinv`
--

CREATE TABLE `rubroinv` (
  `CODRUBRO` int DEFAULT NULL,
  `CODSUBRUBRO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuario_permisos`
--

CREATE TABLE `usuario_permisos` (
  `usuario_id` int NOT NULL,
  `modulo_clave` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zonasurb`
--

CREATE TABLE `zonasurb` (
  `CODIGO` int DEFAULT NULL,
  `NOMBRE` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL,
  `COMENTARIO` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cuentas`
--
ALTER TABLE `cuentas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `LEGAJO` (`LEGAJO`);

--
-- Indexes for table `liquidaciones`
--
ALTER TABLE `liquidaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_empleado_periodo` (`empleado_id`,`periodo`);

--
-- Indexes for table `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `modulos_sistema`
--
ALTER TABLE `modulos_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `USERNAME` (`USERNAME`);

--
-- Indexes for table `usuario_permisos`
--
ALTER TABLE `usuario_permisos`
  ADD PRIMARY KEY (`usuario_id`,`modulo_clave`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cuentas`
--
ALTER TABLE `cuentas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liquidaciones`
--
ALTER TABLE `liquidaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modulos_sistema`
--
ALTER TABLE `modulos_sistema`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
--
-- Database: `distribuidora`
--
CREATE DATABASE IF NOT EXISTS `distribuidora` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci;
USE `distribuidora`;

-- --------------------------------------------------------

--
-- Table structure for table `ajustes`
--

CREATE TABLE `ajustes` (
  `id` int NOT NULL,
  `clave` varchar(50) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb3_spanish2_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `cuit` varchar(20) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `condicion_iva` varchar(50) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `ganancia` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos_guardados`
--

CREATE TABLE `presupuestos_guardados` (
  `id` int NOT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `cliente_nombre` varchar(255) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `cliente_cuit` varchar(20) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `ver_iva` tinyint(1) DEFAULT NULL,
  `cotizacion_dolar` decimal(10,2) DEFAULT NULL,
  `ganancia_aplicada` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `presupuesto_items_guardados`
--

CREATE TABLE `presupuesto_items_guardados` (
  `id` int NOT NULL,
  `id_presupuesto` int DEFAULT NULL,
  `sku` varchar(50) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `cantidad` int DEFAULT NULL,
  `precio_unitario` decimal(15,2) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int NOT NULL,
  `sku` varchar(50) COLLATE utf8mb3_spanish2_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb3_spanish2_ci NOT NULL,
  `familia` varchar(100) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `macrofamilia` varchar(100) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `marca` varchar(100) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `stock_estado` varchar(50) COLLATE utf8mb3_spanish2_ci DEFAULT NULL,
  `dolar_sin_iva` decimal(15,4) DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) DEFAULT NULL,
  `dolar_con_iva` decimal(15,4) DEFAULT NULL,
  `moneda` varchar(5) COLLATE utf8mb3_spanish2_ci DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ajustes`
--
ALTER TABLE `ajustes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `presupuestos_guardados`
--
ALTER TABLE `presupuestos_guardados`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `presupuesto_items_guardados`
--
ALTER TABLE `presupuesto_items_guardados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_presupuesto` (`id_presupuesto`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ajustes`
--
ALTER TABLE `ajustes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuestos_guardados`
--
ALTER TABLE `presupuestos_guardados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuesto_items_guardados`
--
ALTER TABLE `presupuesto_items_guardados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `presupuesto_items_guardados`
--
ALTER TABLE `presupuesto_items_guardados`
  ADD CONSTRAINT `presupuesto_items_guardados_ibfk_1` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuestos_guardados` (`id`) ON DELETE CASCADE;
--
-- Database: `gpd_comuna`
--
CREATE DATABASE IF NOT EXISTS `gpd_comuna` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `gpd_comuna`;

-- --------------------------------------------------------

--
-- Table structure for table `cobros_tasas`
--

CREATE TABLE `cobros_tasas` (
  `id` int NOT NULL,
  `contribuyente_id` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `metodo_pago` enum('Efectivo','Transferencia','Cheque','Debito','Credito') DEFAULT 'Efectivo',
  `fecha_cobro` date NOT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contribuyentes`
--

CREATE TABLE `contribuyentes` (
  `id` int NOT NULL,
  `dni_cuit` varchar(20) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `tipo_contribuyente` enum('Persona Fisica','Persona Juridica','Monotributista') DEFAULT 'Persona Fisica',
  `direccion_fiscal` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado_cuenta` decimal(15,2) DEFAULT '0.00',
  `notas` text,
  `fecha_registro` date DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emisiones_tasas`
--

CREATE TABLE `emisiones_tasas` (
  `id` int NOT NULL,
  `contribuyente_id` int NOT NULL,
  `tasa_id` int NOT NULL,
  `periodo_mes` tinyint NOT NULL,
  `periodo_anio` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `fecha_emision` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `id` int NOT NULL,
  `dni` varchar(15) NOT NULL,
  `cuil` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `sueldo_base` decimal(15,2) DEFAULT '0.00',
  `estado` enum('activo','licencia','baja') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `liquidaciones`
--

CREATE TABLE `liquidaciones` (
  `id` int NOT NULL,
  `empleado_id` int NOT NULL,
  `periodo_mes` tinyint NOT NULL,
  `periodo_anio` int NOT NULL,
  `monto_neto` decimal(15,2) NOT NULL,
  `fecha_emision` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` int NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int DEFAULT NULL,
  `detalles` text,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pagos`
--

CREATE TABLE `pagos` (
  `id` int NOT NULL,
  `proveedor_id` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `metodo_pago` enum('Efectivo','Transferencia','Cheque') DEFAULT 'Transferencia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_roles`
--

CREATE TABLE `permisos_roles` (
  `id` int NOT NULL,
  `rol` varchar(50) NOT NULL,
  `modulo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_usuarios`
--

CREATE TABLE `permisos_usuarios` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `modulo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int NOT NULL,
  `cuit` varchar(20) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `rubro` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado` enum('activo','suspendido','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tipos_tasas`
--

CREATE TABLE `tipos_tasas` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `monto_sugerido` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `rol` enum('admin','operador') DEFAULT 'operador',
  `estado` enum('activo','inactivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cobros_tasas`
--
ALTER TABLE `cobros_tasas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contribuyente_id` (`contribuyente_id`);

--
-- Indexes for table `contribuyentes`
--
ALTER TABLE `contribuyentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni_cuit` (`dni_cuit`),
  ADD KEY `dni_cuit_2` (`dni_cuit`),
  ADD KEY `razon_social` (`razon_social`);

--
-- Indexes for table `emisiones_tasas`
--
ALTER TABLE `emisiones_tasas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contribuyente_id` (`contribuyente_id`),
  ADD KEY `tasa_id` (`tasa_id`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `cuil` (`cuil`),
  ADD KEY `apellido` (`apellido`,`nombre`),
  ADD KEY `dni_2` (`dni`);

--
-- Indexes for table `liquidaciones`
--
ALTER TABLE `liquidaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empleado_id` (`empleado_id`),
  ADD KEY `periodo_anio` (`periodo_anio`,`periodo_mes`);

--
-- Indexes for table `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `fecha_pago` (`fecha_pago`);

--
-- Indexes for table `permisos_roles`
--
ALTER TABLE `permisos_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rol` (`rol`,`modulo`);

--
-- Indexes for table `permisos_usuarios`
--
ALTER TABLE `permisos_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`,`modulo`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuit` (`cuit`),
  ADD KEY `razon_social` (`razon_social`),
  ADD KEY `cuit_2` (`cuit`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `tipos_tasas`
--
ALTER TABLE `tipos_tasas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cobros_tasas`
--
ALTER TABLE `cobros_tasas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contribuyentes`
--
ALTER TABLE `contribuyentes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emisiones_tasas`
--
ALTER TABLE `emisiones_tasas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liquidaciones`
--
ALTER TABLE `liquidaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_roles`
--
ALTER TABLE `permisos_roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_usuarios`
--
ALTER TABLE `permisos_usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tipos_tasas`
--
ALTER TABLE `tipos_tasas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cobros_tasas`
--
ALTER TABLE `cobros_tasas`
  ADD CONSTRAINT `cobros_tasas_ibfk_1` FOREIGN KEY (`contribuyente_id`) REFERENCES `contribuyentes` (`id`);

--
-- Constraints for table `emisiones_tasas`
--
ALTER TABLE `emisiones_tasas`
  ADD CONSTRAINT `emisiones_tasas_ibfk_1` FOREIGN KEY (`contribuyente_id`) REFERENCES `contribuyentes` (`id`),
  ADD CONSTRAINT `emisiones_tasas_ibfk_2` FOREIGN KEY (`tasa_id`) REFERENCES `tipos_tasas` (`id`);

--
-- Constraints for table `liquidaciones`
--
ALTER TABLE `liquidaciones`
  ADD CONSTRAINT `liquidaciones_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`);

--
-- Constraints for table `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`);

--
-- Constraints for table `permisos_usuarios`
--
ALTER TABLE `permisos_usuarios`
  ADD CONSTRAINT `permisos_usuarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
--
-- Database: `pos_dev`
--
CREATE DATABASE IF NOT EXISTS `pos_dev` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `pos_dev`;

-- --------------------------------------------------------

--
-- Table structure for table `cierres_caja`
--

CREATE TABLE `cierres_caja` (
  `id` int NOT NULL,
  `fecha_cierre` datetime DEFAULT CURRENT_TIMESTAMP,
  `saldo_inicial` decimal(10,2) DEFAULT NULL,
  `ingresos_efectivo` decimal(10,2) DEFAULT NULL,
  `ingresos_transf` decimal(10,2) DEFAULT NULL,
  `egresos` decimal(10,2) DEFAULT NULL,
  `saldo_esperado_efectivo` decimal(10,2) DEFAULT NULL,
  `saldo_real_efectivo` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `observaciones` text,
  `usuario` varchar(50) DEFAULT NULL,
  `fondo_reservado_vuelto` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `apellido` text COLLATE utf8mb4_general_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_tipo_iva` int DEFAULT '99',
  `nombre` text COLLATE utf8mb4_general_ci NOT NULL,
  `direccion` text COLLATE utf8mb4_general_ci NOT NULL,
  `cuit` text COLLATE utf8mb4_general_ci NOT NULL,
  `telefono` text COLLATE utf8mb4_general_ci NOT NULL,
  `estado` text COLLATE utf8mb4_general_ci NOT NULL,
  `habilita_cta` text COLLATE utf8mb4_general_ci NOT NULL,
  `relacion` text COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compras`
--

CREATE TABLE `compras` (
  `id` int NOT NULL,
  `cod_proveedor` int NOT NULL,
  `cond_pago` text NOT NULL,
  `documento` text NOT NULL,
  `n_documento` varchar(100) NOT NULL,
  `total_compra` double NOT NULL,
  `observaciones` text,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_operacion` datetime NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `es_sin_detalle` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compras_detalle`
--

CREATE TABLE `compras_detalle` (
  `id` int NOT NULL,
  `cod_prod` text NOT NULL,
  `descripcion` text NOT NULL,
  `cant` double NOT NULL,
  `p_unit` double NOT NULL,
  `total` double NOT NULL,
  `n_documento` varchar(100) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int NOT NULL,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ctacte`
--

CREATE TABLE `ctacte` (
  `id` int NOT NULL,
  `id_cliente` int NOT NULL,
  `movimiento` text NOT NULL,
  `n_documento` int NOT NULL,
  `debe` double NOT NULL,
  `haber` double NOT NULL,
  `fecha` date NOT NULL,
  `usuario` varchar(100) DEFAULT 'Sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ctacte_proveedores`
--

CREATE TABLE `ctacte_proveedores` (
  `id` int NOT NULL,
  `id_proveedor` int NOT NULL,
  `movimiento` varchar(100) NOT NULL COMMENT 'FACTURA COMPRA, PAGO, NOTA CREDITO',
  `haber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `debe` decimal(10,2) NOT NULL DEFAULT '0.00',
  `n_documento` varchar(50) NOT NULL COMMENT 'Referencia a la factura o recibo',
  `fecha` datetime NOT NULL COMMENT 'Fecha y hora de registro del movimiento',
  `usuario_id` int DEFAULT NULL,
  `compra_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `cuotas_pagos`
--

CREATE TABLE `cuotas_pagos` (
  `id` int NOT NULL,
  `id_cuota` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT '0.00',
  `metodo_pago` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuotas_seguimiento`
--

CREATE TABLE `cuotas_seguimiento` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `nro_cuota` int NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_original` decimal(15,2) NOT NULL,
  `monto_pagado` decimal(15,2) DEFAULT '0.00',
  `estado` varchar(20) NOT NULL DEFAULT 'Pendiente',
  `ultima_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `datos_empresa`
--

CREATE TABLE `datos_empresa` (
  `id` int NOT NULL,
  `nombre_fantasia` varchar(100) DEFAULT NULL,
  `razon_social` varchar(100) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `condicion_iva` varchar(50) DEFAULT NULL,
  `ingresos_brutos` varchar(50) DEFAULT NULL,
  `inicio_actividades` date DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `localidad` varchar(255) NOT NULL,
  `telefono` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `devoluciones`
--

CREATE TABLE `devoluciones` (
  `id` int NOT NULL,
  `op_n` int NOT NULL,
  `n_documento_venta` int NOT NULL,
  `id_cliente` int DEFAULT '0',
  `total_reintegrado` decimal(15,2) NOT NULL,
  `motivo` text COLLATE utf8mb4_general_ci,
  `fecha` datetime NOT NULL,
  `usuario` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cond_pago` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devoluciones_detalle`
--

CREATE TABLE `devoluciones_detalle` (
  `id` int NOT NULL,
  `id_devolucion` int NOT NULL,
  `cod_prod` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `p_unit` decimal(15,2) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modulos`
--

CREATE TABLE `modulos` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `archivo` varchar(100) NOT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `seccion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `detalle` text NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `fecha` date NOT NULL,
  `metodo_pago` text NOT NULL,
  `cerrado` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_rol`
--

CREATE TABLE `permisos_rol` (
  `id` int NOT NULL,
  `rol` varchar(50) NOT NULL,
  `modulo_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_usuario`
--

CREATE TABLE `permisos_usuario` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `modulo_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos`
--

CREATE TABLE `presupuestos` (
  `id` int NOT NULL,
  `id_cliente` int DEFAULT NULL,
  `fecha_presupuesto` datetime DEFAULT CURRENT_TIMESTAMP,
  `total_presupuesto` decimal(10,2) DEFAULT NULL,
  `estado` enum('Pendiente','Convertido','Vencido') DEFAULT 'Pendiente',
  `observaciones` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos_detalle`
--

CREATE TABLE `presupuestos_detalle` (
  `id` int NOT NULL,
  `id_presupuesto` int DEFAULT NULL,
  `cod_prod` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int NOT NULL,
  `cod_prod` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `p_compra` double NOT NULL,
  `p_venta` double NOT NULL,
  `stock` double NOT NULL,
  `fecha_ult_compra` date NOT NULL,
  `rubro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `proveedor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `cod_prov` int NOT NULL,
  `razon` text NOT NULL,
  `cuit` text NOT NULL,
  `telefono` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores_catalogos`
--

CREATE TABLE `proveedores_catalogos` (
  `id` int NOT NULL,
  `cod_prov` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `codigo` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `precio` decimal(15,2) NOT NULL DEFAULT '0.00',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rubros`
--

CREATE TABLE `rubros` (
  `id` int NOT NULL,
  `nombre` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int NOT NULL,
  `nombre_sucursal` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `web` varchar(100) DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ventas`
--

CREATE TABLE `ventas` (
  `id` int NOT NULL,
  `id_cliente` int NOT NULL,
  `cond_pago` enum('CONTADO','CUENTA CORRIENTE','FINANCIADO') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `n_documento` int NOT NULL,
  `total_venta` double NOT NULL,
  `descuento_global` decimal(15,2) DEFAULT '0.00',
  `tipo_descuento_global` enum('fijo','porcentaje') COLLATE utf8mb4_general_ci DEFAULT 'fijo',
  `pago_efectivo` double NOT NULL,
  `pago_transf` double NOT NULL,
  `fecha_venta` datetime NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendiente',
  `usuario` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_afip`
--

CREATE TABLE `ventas_afip` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `cae` varchar(20) NOT NULL,
  `cae_vto` date NOT NULL,
  `punto_venta` int NOT NULL,
  `n_comprobante` int NOT NULL,
  `tipo_comprobante` int NOT NULL,
  `fecha_proceso` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_detalle`
--

CREATE TABLE `ventas_detalle` (
  `id` int NOT NULL,
  `cod_prod` text COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci NOT NULL,
  `cant` double NOT NULL,
  `p_unit` double NOT NULL,
  `descuento_unitario` decimal(15,2) DEFAULT '0.00',
  `p_costo_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` double NOT NULL,
  `n_documento` int NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_financiacion`
--

CREATE TABLE `ventas_financiacion` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `cant_cuotas` int NOT NULL,
  `intervalo_dias` int NOT NULL,
  `interes_porcentaje` decimal(5,2) DEFAULT '0.00',
  `monto_interes` decimal(15,2) DEFAULT '0.00',
  `entrega_inicial` decimal(15,2) DEFAULT '0.00',
  `monto_cuota_sugerida` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cierres_caja`
--
ALTER TABLE `cierres_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_compras_usuario` (`usuario_id`);

--
-- Indexes for table `compras_detalle`
--
ALTER TABLE `compras_detalle`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `ctacte`
--
ALTER TABLE `ctacte`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proveedor` (`id_proveedor`),
  ADD KEY `fk_ctacte_compra` (`compra_id`);

--
-- Indexes for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cuota` (`id_cuota`);

--
-- Indexes for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indexes for table `datos_empresa`
--
ALTER TABLE `datos_empresa`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devoluciones_detalle`
--
ALTER TABLE `devoluciones_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_devolucion` (`id_devolucion`);

--
-- Indexes for table `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indexes for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indexes for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indexes for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_presupuesto` (`id_presupuesto`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`cod_prov`),
  ADD KEY `cod_prov` (`cod_prov`);

--
-- Indexes for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cod_prov` (`cod_prov`),
  ADD KEY `idx_codigo` (`codigo`);

--
-- Indexes for table `rubros`
--
ALTER TABLE `rubros`
  ADD UNIQUE KEY `id_3` (`id`),
  ADD KEY `id` (`id`),
  ADD KEY `id_2` (`id`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_venta` (`id_venta`);

--
-- Indexes for table `ventas_detalle`
--
ALTER TABLE `ventas_detalle`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cierres_caja`
--
ALTER TABLE `cierres_caja`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compras_detalle`
--
ALTER TABLE `compras_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ctacte`
--
ALTER TABLE `ctacte`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `datos_empresa`
--
ALTER TABLE `datos_empresa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devoluciones`
--
ALTER TABLE `devoluciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devoluciones_detalle`
--
ALTER TABLE `devoluciones_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuestos`
--
ALTER TABLE `presupuestos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_detalle`
--
ALTER TABLE `ventas_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  ADD CONSTRAINT `ctacte_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`cod_prov`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ctacte_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  ADD CONSTRAINT `cuotas_pagos_ibfk_1` FOREIGN KEY (`id_cuota`) REFERENCES `cuotas_seguimiento` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  ADD CONSTRAINT `cuotas_seguimiento_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  ADD CONSTRAINT `permisos_rol_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD CONSTRAINT `permisos_usuario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permisos_usuario_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD CONSTRAINT `presupuestos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);

--
-- Constraints for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  ADD CONSTRAINT `presupuestos_detalle_ibfk_1` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  ADD CONSTRAINT `ventas_afip_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`);

--
-- Constraints for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  ADD CONSTRAINT `ventas_financiacion_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;
--
-- Database: `pos_prod`
--
CREATE DATABASE IF NOT EXISTS `pos_prod` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `pos_prod`;

-- --------------------------------------------------------

--
-- Table structure for table `cierres_caja`
--

CREATE TABLE `cierres_caja` (
  `id` int NOT NULL,
  `fecha_cierre` datetime DEFAULT CURRENT_TIMESTAMP,
  `saldo_inicial` decimal(10,2) DEFAULT NULL,
  `ingresos_efectivo` decimal(10,2) DEFAULT NULL,
  `ingresos_transf` decimal(10,2) DEFAULT NULL,
  `egresos` decimal(10,2) DEFAULT NULL,
  `saldo_esperado_efectivo` decimal(10,2) DEFAULT NULL,
  `saldo_real_efectivo` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `observaciones` text,
  `usuario` varchar(50) DEFAULT NULL,
  `fondo_reservado_vuelto` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `apellido` text NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `id_tipo_iva` int DEFAULT '99',
  `nombre` text NOT NULL,
  `direccion` text NOT NULL,
  `cuit` text NOT NULL,
  `telefono` text NOT NULL,
  `estado` text NOT NULL,
  `habilita_cta` text NOT NULL,
  `relacion` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compras`
--

CREATE TABLE `compras` (
  `id` int NOT NULL,
  `cod_proveedor` int NOT NULL,
  `cond_pago` text NOT NULL,
  `documento` text NOT NULL,
  `n_documento` varchar(100) NOT NULL,
  `total_compra` double NOT NULL,
  `observaciones` text,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_operacion` datetime NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `es_sin_detalle` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compras_detalle`
--

CREATE TABLE `compras_detalle` (
  `id` int NOT NULL,
  `cod_prod` text NOT NULL,
  `descripcion` text NOT NULL,
  `cant` double NOT NULL,
  `p_unit` double NOT NULL,
  `total` double NOT NULL,
  `n_documento` varchar(100) NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int NOT NULL,
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ctacte`
--

CREATE TABLE `ctacte` (
  `id` int NOT NULL,
  `id_cliente` int NOT NULL,
  `movimiento` text NOT NULL,
  `n_documento` int NOT NULL,
  `debe` double NOT NULL,
  `haber` double NOT NULL,
  `fecha` date NOT NULL,
  `usuario` varchar(100) DEFAULT 'Sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ctacte_proveedores`
--

CREATE TABLE `ctacte_proveedores` (
  `id` int NOT NULL,
  `id_proveedor` int NOT NULL,
  `movimiento` varchar(100) NOT NULL COMMENT 'FACTURA COMPRA, PAGO, NOTA CREDITO',
  `haber` decimal(10,2) NOT NULL DEFAULT '0.00',
  `debe` decimal(10,2) NOT NULL DEFAULT '0.00',
  `n_documento` varchar(50) NOT NULL COMMENT 'Referencia a la factura o recibo',
  `fecha` datetime NOT NULL COMMENT 'Fecha y hora de registro del movimiento',
  `usuario_id` int DEFAULT NULL,
  `compra_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `cuotas_pagos`
--

CREATE TABLE `cuotas_pagos` (
  `id` int NOT NULL,
  `id_cuota` int NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT '0.00',
  `metodo_pago` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cuotas_seguimiento`
--

CREATE TABLE `cuotas_seguimiento` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `nro_cuota` int NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_original` decimal(15,2) NOT NULL,
  `monto_pagado` decimal(15,2) DEFAULT '0.00',
  `estado` varchar(20) NOT NULL DEFAULT 'Pendiente',
  `ultima_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `datos_empresa`
--

CREATE TABLE `datos_empresa` (
  `id` int NOT NULL,
  `nombre_fantasia` varchar(100) DEFAULT NULL,
  `razon_social` varchar(100) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `condicion_iva` varchar(50) DEFAULT NULL,
  `ingresos_brutos` varchar(50) DEFAULT NULL,
  `inicio_actividades` date DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `localidad` varchar(255) NOT NULL,
  `telefono` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `devoluciones`
--

CREATE TABLE `devoluciones` (
  `id` int NOT NULL,
  `op_n` int NOT NULL,
  `n_documento_venta` int NOT NULL,
  `id_cliente` int DEFAULT '0',
  `total_reintegrado` decimal(15,2) NOT NULL,
  `motivo` text,
  `fecha` datetime NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `cond_pago` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devoluciones_detalle`
--

CREATE TABLE `devoluciones_detalle` (
  `id` int NOT NULL,
  `id_devolucion` int NOT NULL,
  `cod_prod` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `p_unit` decimal(15,2) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modulos`
--

CREATE TABLE `modulos` (
  `id` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `archivo` varchar(100) NOT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `seccion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `monto` decimal(10,0) NOT NULL,
  `detalle` text NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `fecha` date NOT NULL,
  `metodo_pago` text NOT NULL,
  `cerrado` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_rol`
--

CREATE TABLE `permisos_rol` (
  `id` int NOT NULL,
  `rol` varchar(50) NOT NULL,
  `modulo_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `permisos_usuario`
--

CREATE TABLE `permisos_usuario` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `modulo_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos`
--

CREATE TABLE `presupuestos` (
  `id` int NOT NULL,
  `id_cliente` int DEFAULT NULL,
  `fecha_presupuesto` datetime DEFAULT CURRENT_TIMESTAMP,
  `total_presupuesto` decimal(10,2) DEFAULT NULL,
  `estado` enum('Pendiente','Convertido','Vencido') DEFAULT 'Pendiente',
  `observaciones` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `presupuestos_detalle`
--

CREATE TABLE `presupuestos_detalle` (
  `id` int NOT NULL,
  `id_presupuesto` int DEFAULT NULL,
  `cod_prod` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int NOT NULL,
  `cod_prod` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `p_compra` double NOT NULL,
  `p_venta` double NOT NULL,
  `stock` double NOT NULL,
  `fecha_ult_compra` date NOT NULL,
  `rubro` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `proveedor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores`
--

CREATE TABLE `proveedores` (
  `cod_prov` int NOT NULL,
  `razon` text NOT NULL,
  `cuit` text NOT NULL,
  `telefono` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proveedores_catalogos`
--

CREATE TABLE `proveedores_catalogos` (
  `id` int NOT NULL,
  `cod_prov` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `codigo` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `precio` decimal(15,2) NOT NULL DEFAULT '0.00',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rubros`
--

CREATE TABLE `rubros` (
  `id` int NOT NULL,
  `nombre` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int NOT NULL,
  `nombre_sucursal` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `web` varchar(100) DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `ventas`
--

CREATE TABLE `ventas` (
  `id` int NOT NULL,
  `id_cliente` int NOT NULL,
  `cond_pago` enum('CONTADO','CUENTA CORRIENTE','FINANCIADO') COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `n_documento` int NOT NULL,
  `total_venta` double NOT NULL,
  `descuento_global` decimal(15,2) DEFAULT '0.00',
  `tipo_descuento_global` enum('fijo','porcentaje') COLLATE utf8mb4_spanish2_ci DEFAULT 'fijo',
  `pago_efectivo` double NOT NULL,
  `pago_transf` double NOT NULL,
  `fecha_venta` datetime NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'Pendiente',
  `usuario` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_afip`
--

CREATE TABLE `ventas_afip` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `cae` varchar(20) NOT NULL,
  `cae_vto` date NOT NULL,
  `punto_venta` int NOT NULL,
  `n_comprobante` int NOT NULL,
  `tipo_comprobante` int NOT NULL,
  `fecha_proceso` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_detalle`
--

CREATE TABLE `ventas_detalle` (
  `id` int NOT NULL,
  `cod_prod` text COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci NOT NULL,
  `cant` double NOT NULL,
  `p_unit` double NOT NULL,
  `descuento_unitario` decimal(15,2) DEFAULT '0.00',
  `p_costo_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` double NOT NULL,
  `n_documento` int NOT NULL,
  `fecha` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventas_financiacion`
--

CREATE TABLE `ventas_financiacion` (
  `id` int NOT NULL,
  `id_venta` int NOT NULL,
  `cant_cuotas` int NOT NULL,
  `intervalo_dias` int NOT NULL,
  `interes_porcentaje` decimal(5,2) DEFAULT '0.00',
  `monto_interes` decimal(15,2) DEFAULT '0.00',
  `entrega_inicial` decimal(15,2) DEFAULT '0.00',
  `monto_cuota_sugerida` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cierres_caja`
--
ALTER TABLE `cierres_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_compras_usuario` (`usuario_id`);

--
-- Indexes for table `compras_detalle`
--
ALTER TABLE `compras_detalle`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indexes for table `ctacte`
--
ALTER TABLE `ctacte`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proveedor` (`id_proveedor`),
  ADD KEY `fk_ctacte_compra` (`compra_id`);

--
-- Indexes for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cuota` (`id_cuota`);

--
-- Indexes for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- Indexes for table `datos_empresa`
--
ALTER TABLE `datos_empresa`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `devoluciones_detalle`
--
ALTER TABLE `devoluciones_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_devolucion` (`id_devolucion`);

--
-- Indexes for table `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indexes for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indexes for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indexes for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_presupuesto` (`id_presupuesto`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`cod_prov`),
  ADD KEY `cod_prov` (`cod_prov`);

--
-- Indexes for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cod_prov` (`cod_prov`),
  ADD KEY `idx_codigo` (`codigo`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_venta` (`id_venta`);

--
-- Indexes for table `ventas_detalle`
--
ALTER TABLE `ventas_detalle`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_venta` (`id_venta`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cierres_caja`
--
ALTER TABLE `cierres_caja`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compras_detalle`
--
ALTER TABLE `compras_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ctacte`
--
ALTER TABLE `ctacte`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `datos_empresa`
--
ALTER TABLE `datos_empresa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devoluciones`
--
ALTER TABLE `devoluciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devoluciones_detalle`
--
ALTER TABLE `devoluciones_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuestos`
--
ALTER TABLE `presupuestos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_detalle`
--
ALTER TABLE `ventas_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ctacte_proveedores`
--
ALTER TABLE `ctacte_proveedores`
  ADD CONSTRAINT `ctacte_proveedores_ibfk_1` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedores` (`cod_prov`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ctacte_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cuotas_pagos`
--
ALTER TABLE `cuotas_pagos`
  ADD CONSTRAINT `cuotas_pagos_ibfk_1` FOREIGN KEY (`id_cuota`) REFERENCES `cuotas_seguimiento` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cuotas_seguimiento`
--
ALTER TABLE `cuotas_seguimiento`
  ADD CONSTRAINT `cuotas_seguimiento_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permisos_rol`
--
ALTER TABLE `permisos_rol`
  ADD CONSTRAINT `permisos_rol_ibfk_1` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permisos_usuario`
--
ALTER TABLE `permisos_usuario`
  ADD CONSTRAINT `permisos_usuario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `permisos_usuario_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD CONSTRAINT `presupuestos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);

--
-- Constraints for table `presupuestos_detalle`
--
ALTER TABLE `presupuestos_detalle`
  ADD CONSTRAINT `presupuestos_detalle_ibfk_1` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ventas_afip`
--
ALTER TABLE `ventas_afip`
  ADD CONSTRAINT `ventas_afip_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`);

--
-- Constraints for table `ventas_financiacion`
--
ALTER TABLE `ventas_financiacion`
  ADD CONSTRAINT `ventas_financiacion_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE CASCADE;
--
-- Database: `sgl`
--
CREATE DATABASE IF NOT EXISTS `sgl` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;
USE `sgl`;

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` int NOT NULL,
  `license_key` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hwid_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','active','revoked','paused') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `expires_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD KEY `license_key_2` (`license_key`),
  ADD KEY `hwid_hash` (`hwid_hash`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
--
-- Database: `trans_dev_db`
--
CREATE DATABASE IF NOT EXISTS `trans_dev_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `trans_dev_db`;

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chofer_pagos`
--

CREATE TABLE `chofer_pagos` (
  `id` int NOT NULL,
  `chofer_id` int NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `tipo` enum('adelanto','sueldo','liquidacion','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `es_comercial` tinyint(1) DEFAULT '0',
  `es_pagador` tinyint(1) DEFAULT '0',
  `es_comisionista` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
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
  `detalle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuraciones`
--

CREATE TABLE `configuraciones` (
  `clave` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
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

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `peso_bruto` decimal(12,2) DEFAULT '0.00',
  `peso_tara` decimal(12,2) DEFAULT '0.00',
  `peso_neto` decimal(12,2) GENERATED ALWAYS AS ((`peso_bruto` - `peso_tara`)) STORED,
  `tarifa_tonelada` decimal(15,2) DEFAULT '0.00',
  `total_flete_bruto` decimal(15,2) DEFAULT '0.00',
  `total_flete_neto` decimal(15,2) DEFAULT '0.00',
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
-- Indexes for dumped tables
--

--
-- Indexes for table `choferes`
--
ALTER TABLE `choferes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuil` (`cuil`),
  ADD KEY `transportista_id` (`transportista_id`);

--
-- Indexes for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chofer_id` (`chofer_id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuit` (`cuit`),
  ADD KEY `transportista_id` (`transportista_id`);

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
-- Indexes for table `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`);

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
  ADD UNIQUE KEY `dominio` (`dominio`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `chofer_id` (`chofer_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `choferes`
--
ALTER TABLE `choferes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `viajes`
--
ALTER TABLE `viajes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `choferes`
--
ALTER TABLE `choferes`
  ADD CONSTRAINT `choferes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD CONSTRAINT `chofer_pagos_ibfk_1` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  ADD CONSTRAINT `comisionista_pagos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mantenimientos`
--
ALTER TABLE `mantenimientos`
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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
