-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-06-2026 a las 04:24:41
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `trans_dev_db`
--
CREATE DATABASE IF NOT EXISTS `trans_dev_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `trans_dev_db`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `choferes`
--

CREATE TABLE `choferes` (
  `id` int(11) NOT NULL,
  `transportista_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cuil` varchar(11) NOT NULL,
  `licencia_nro` varchar(50) DEFAULT NULL,
  `vencimiento_licencia` date DEFAULT NULL,
  `porcentaje_ganancia` decimal(5,2) DEFAULT 0.00,
  `telefono` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chofer_pagos`
--

CREATE TABLE `chofer_pagos` (
  `id` int(11) NOT NULL,
  `chofer_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `tipo` enum('adelanto','sueldo','liquidacion','otro') NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `transportista_id` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(11) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `es_comercial` tinyint(1) DEFAULT 0,
  `es_pagador` tinyint(1) DEFAULT 0,
  `es_comisionista` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comisionista_pagos`
--

CREATE TABLE `comisionista_pagos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--

CREATE TABLE `configuraciones` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `costo_total` decimal(15,2) DEFAULT 0.00,
  `proximo_service_km` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_catalogos`
--

CREATE TABLE `proveedores_catalogos` (
  `id` int(11) NOT NULL,
  `cod_prov` varchar(50) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `costo` decimal(15,2) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transportistas`
--

CREATE TABLE `transportistas` (
  `id` int(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `cuit` varchar(11) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','user','developer') DEFAULT 'user',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `transportista_id` int(11) NOT NULL,
  `chofer_id` int(11) DEFAULT NULL,
  `dominio` varchar(10) NOT NULL,
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `acoplado` varchar(50) DEFAULT NULL,
  `anio` int(11) DEFAULT NULL,
  `vtv_vencimiento` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `viajes`
--

CREATE TABLE `viajes` (
  `id` int(11) NOT NULL,
  `transportista_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `chofer_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `acoplado` varchar(50) DEFAULT NULL,
  `origen` varchar(255) NOT NULL,
  `destino` varchar(255) NOT NULL,
  `producto` varchar(100) DEFAULT NULL,
  `fecha_carga` date NOT NULL,
  `peso_bruto` decimal(12,2) DEFAULT 0.00,
  `peso_tara` decimal(12,2) DEFAULT 0.00,
  `peso_neto` decimal(12,2) GENERATED ALWAYS AS (`peso_bruto` - `peso_tara`) STORED,
  `tarifa_tonelada` decimal(15,2) DEFAULT 0.00,
  `total_flete_bruto` decimal(15,2) DEFAULT 0.00,
  `total_flete_neto` decimal(15,2) DEFAULT 0.00,
  `chofer_porcentaje` decimal(5,2) DEFAULT 0.00,
  `acreditado_chofer` tinyint(1) DEFAULT 0,
  `comision_tipo` enum('ninguna','porcentaje','monto_fijo') DEFAULT 'ninguna',
  `comision_valor` decimal(15,2) DEFAULT 0.00,
  `comision_pagada` tinyint(1) DEFAULT 0,
  `comisionista_id` int(11) DEFAULT NULL,
  `comision_receptor` varchar(150) DEFAULT NULL,
  `ctg_nro` varchar(20) DEFAULT NULL,
  `carta_porte_nro` varchar(20) DEFAULT NULL,
  `otros_docs` varchar(100) DEFAULT NULL,
  `pagador_id` int(11) DEFAULT NULL,
  `pagador_flete` varchar(150) DEFAULT NULL,
  `factura_nro` varchar(50) DEFAULT NULL,
  `factura_fecha` date DEFAULT NULL,
  `fecha_cobro` date DEFAULT NULL,
  `estado` enum('en_viaje','descargado','facturado','cobrado','liquidado') DEFAULT 'en_viaje',
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `viajes_adelantos`
--

CREATE TABLE `viajes_adelantos` (
  `id` int(11) NOT NULL,
  `viaje_id` int(11) NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `fecha` date NOT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `viajes_gastos`
--

CREATE TABLE `viajes_gastos` (
  `id` int(11) NOT NULL,
  `viaje_id` int(11) NOT NULL,
  `tipo_gasto` enum('combustible','peaje','playa','reparacion_ruta','otros','nuevo_tipo') DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `pagado_por` enum('empresa','adelanto','descuento_flete') DEFAULT 'empresa',
  `fecha` date NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `choferes`
--
ALTER TABLE `choferes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuil` (`cuil`),
  ADD KEY `transportista_id` (`transportista_id`);

--
-- Indices de la tabla `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chofer_id` (`chofer_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuit` (`cuit`),
  ADD KEY `transportista_id` (`transportista_id`);

--
-- Indices de la tabla `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `configuraciones`
--
ALTER TABLE `configuraciones`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`);

--
-- Indices de la tabla `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cod_prov` (`cod_prov`);

--
-- Indices de la tabla `transportistas`
--
ALTER TABLE `transportistas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cuit` (`cuit`),
  ADD KEY `fk_transportistas_created_by` (`created_by`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_created_by` (`created_by`);

--
-- Indices de la tabla `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`module`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dominio` (`dominio`),
  ADD KEY `transportista_id` (`transportista_id`),
  ADD KEY `chofer_id` (`chofer_id`);

--
-- Indices de la tabla `viajes`
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
-- Indices de la tabla `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- Indices de la tabla `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `viaje_id` (`viaje_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `choferes`
--
ALTER TABLE `choferes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedores_catalogos`
--
ALTER TABLE `proveedores_catalogos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transportistas`
--
ALTER TABLE `transportistas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `viajes`
--
ALTER TABLE `viajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `choferes`
--
ALTER TABLE `choferes`
  ADD CONSTRAINT `choferes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `chofer_pagos`
--
ALTER TABLE `chofer_pagos`
  ADD CONSTRAINT `chofer_pagos_ibfk_1` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `comisionista_pagos`
--
ALTER TABLE `comisionista_pagos`
  ADD CONSTRAINT `comisionista_pagos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `mantenimientos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Filtros para la tabla `transportistas`
--
ALTER TABLE `transportistas`
  ADD CONSTRAINT `fk_transportistas_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehiculos_ibfk_2` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `viajes`
--
ALTER TABLE `viajes`
  ADD CONSTRAINT `viajes_ibfk_1` FOREIGN KEY (`transportista_id`) REFERENCES `transportistas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `viajes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_3` FOREIGN KEY (`chofer_id`) REFERENCES `choferes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_4` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `viajes_ibfk_5` FOREIGN KEY (`comisionista_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `viajes_ibfk_6` FOREIGN KEY (`pagador_id`) REFERENCES `clientes` (`id`);

--
-- Filtros para la tabla `viajes_adelantos`
--
ALTER TABLE `viajes_adelantos`
  ADD CONSTRAINT `viajes_adelantos_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `viajes_gastos`
--
ALTER TABLE `viajes_gastos`
  ADD CONSTRAINT `viajes_gastos_ibfk_1` FOREIGN KEY (`viaje_id`) REFERENCES `viajes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
