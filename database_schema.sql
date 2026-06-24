-- Estructura de Base de Datos para Gestión de Transporte (Argentina)

CREATE DATABASE IF NOT EXISTS trans_dev_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trans_dev_db;

-- 0. Seguridad y Usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'user', 'developer') DEFAULT 'user',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Usuario por defecto: admin / admin123
INSERT INTO users (username, password, full_name, role) VALUES ('admin', '$2y$10$Xm3q9S1H9t9/6H5I6D/YGe8q.jYtG8fU0/3H7G6zXU9O5j1g5o5W.', 'Administrador', 'admin');

CREATE TABLE user_permissions (
    user_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id, module),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 1. Entidades Principales
CREATE TABLE transportistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(11) UNIQUE NOT NULL, -- Sin guiones
    direccion VARCHAR(255),
    telefono VARCHAR(50),
    email VARCHAR(100),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE choferes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    cuil VARCHAR(11) UNIQUE NOT NULL,
    licencia_nro VARCHAR(50),
    vencimiento_licencia DATE,
    porcentaje_ganancia DECIMAL(5,2) DEFAULT 0.00, -- Ej: 15.50
    telefono VARCHAR(50),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT NOT NULL,
    chofer_id INT DEFAULT NULL,
    dominio VARCHAR(10) UNIQUE NOT NULL, -- Patente (Argentina: ABC123 o AB123CD)
    marca VARCHAR(50),
    modelo VARCHAR(50),
    acoplado VARCHAR(50), -- Dato de texto como la marca
    anio INT,
    vtv_vencimiento DATE,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    FOREIGN KEY (chofer_id) REFERENCES choferes(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT NOT NULL,
    razon_social VARCHAR(150) NOT NULL,
    cuit VARCHAR(11) UNIQUE NOT NULL,
    direccion VARCHAR(255),
    es_comercial BOOLEAN DEFAULT FALSE,
    es_pagador BOOLEAN DEFAULT FALSE,
    es_comisionista BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Módulo de Operaciones (Viajes)
CREATE TABLE viajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transportista_id INT NOT NULL,
    cliente_id INT NOT NULL,
    chofer_id INT NOT NULL,
    vehiculo_id INT NOT NULL, -- Camión
    acoplado VARCHAR(50), -- Dato de texto
    origen VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    producto VARCHAR(100),
    fecha_carga DATE NOT NULL,
    
    -- Pesaje
    peso_bruto DECIMAL(12,2) DEFAULT 0,
    peso_tara DECIMAL(12,2) DEFAULT 0,
    peso_neto DECIMAL(12,2) AS (peso_bruto - peso_tara) STORED,
    
    -- Valores y Documentación
    tarifa_tonelada DECIMAL(15,2) DEFAULT 0,
    total_flete_bruto DECIMAL(15,2) DEFAULT 0,
    total_flete_neto DECIMAL(15,2) DEFAULT 0,
    chofer_porcentaje DECIMAL(5,2) DEFAULT 0, -- Se captura al momento de crear el viaje
    acreditado_chofer BOOLEAN DEFAULT FALSE, -- Indica si ya impactó en Cta Cte
    comision_tipo ENUM('ninguna', 'porcentaje', 'monto_fijo') DEFAULT 'ninguna',
    comision_valor DECIMAL(15,2) DEFAULT 0,
    comision_pagada BOOLEAN DEFAULT FALSE,
    comisionista_id INT DEFAULT NULL, -- Relación con clientes (comisionistas)
    comision_receptor VARCHAR(150) DEFAULT NULL, -- Backup de texto
    ctg_nro VARCHAR(20), -- Argentina: Código de Trazabilidad de Granos
    carta_porte_nro VARCHAR(20),
    otros_docs VARCHAR(100) DEFAULT NULL,
    pagador_id INT DEFAULT NULL, -- Relación con clientes
    pagador_flete VARCHAR(150) DEFAULT NULL, -- Backup de texto
    factura_nro VARCHAR(50) DEFAULT NULL,
    factura_fecha DATE DEFAULT NULL,
    fecha_cobro DATE DEFAULT NULL,
    
    estado ENUM('en_viaje', 'descargado', 'facturado', 'cobrado', 'liquidado') DEFAULT 'en_viaje',
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (chofer_id) REFERENCES choferes(id),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    FOREIGN KEY (comisionista_id) REFERENCES clientes(id),
    FOREIGN KEY (pagador_id) REFERENCES clientes(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Finanzas del Viaje
CREATE TABLE viajes_gastos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viaje_id INT NOT NULL,
    tipo_gasto ENUM('combustible', 'peaje', 'viaticos', 'reparacion_ruta', 'otros') NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    descripcion VARCHAR(255),
    pagado_por ENUM('empresa', 'adelanto', 'descuento_flete') DEFAULT 'empresa',
    fecha DATE NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (viaje_id) REFERENCES viajes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE viajes_adelantos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viaje_id INT NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    fecha DATE NOT NULL,
    metodo_pago VARCHAR(50), -- Efectivo, Transferencia
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (viaje_id) REFERENCES viajes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Mantenimiento de Vehículos
CREATE TABLE mantenimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    fecha DATE NOT NULL,
    kilometraje INT,
    descripcion TEXT NOT NULL,
    costo_total DECIMAL(15,2) DEFAULT 0,
    proximo_service_km INT,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Configuración del Sistema
CREATE TABLE IF NOT EXISTS configuraciones (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO configuraciones (clave, valor) VALUES ('tema', 'corporativo');

-- 6. Pagos y Adelantos Generales a Choferes (Cuenta Corriente)
CREATE TABLE IF NOT EXISTS chofer_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chofer_id INT NOT NULL,
    fecha DATE NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    tipo ENUM('adelanto', 'sueldo', 'liquidacion', 'otro') NOT NULL,
    detalle VARCHAR(255),
    FOREIGN KEY (chofer_id) REFERENCES choferes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Pagos a Comisionistas (Terceros)
CREATE TABLE IF NOT EXISTS comisionista_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL, -- El comisionista es un cliente con flag es_comisionista
    fecha DATE NOT NULL,
    monto DECIMAL(15,2) NOT NULL,
    detalle VARCHAR(255),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. Facturación de Fletes (modelo: una factura puede contener varios viajes)
CREATE TABLE IF NOT EXISTS facturas_fletes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    transportista_id INT NOT NULL,
    cliente_id INT NOT NULL, -- cliente que emite/recibe (en este sistema se usa el pagador o cliente del viaje según corresponda)

    factura_nro VARCHAR(50) NOT NULL,
    factura_fecha DATE NOT NULL,

    moneda VARCHAR(10) DEFAULT 'ARS',

    flete_neto_total DECIMAL(15,2) DEFAULT 0,
    iva_monto DECIMAL(15,2) DEFAULT 0,
    retenciones_total DECIMAL(15,2) DEFAULT 0,
    otros_descuentos_total DECIMAL(15,2) DEFAULT 0,

    total_factura DECIMAL(15,2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_facturas_fletes (transportista_id, factura_nro, factura_fecha),

    FOREIGN KEY (transportista_id) REFERENCES transportistas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facturas_fletes_viajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    viaje_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_factura_viaje (factura_id, viaje_id),

    FOREIGN KEY (factura_id) REFERENCES facturas_fletes(id) ON DELETE CASCADE,
    FOREIGN KEY (viaje_id) REFERENCES viajes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS facturas_fletes_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,

    tipo ENUM('IVA','RET_GANANCIAS','RET_IIBB','DESCUENTO','OTRA_RETENCION') NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,

    base_monto DECIMAL(15,2) DEFAULT 0,
    porcentaje DECIMAL(7,4) DEFAULT NULL, -- opcional
    monto DECIMAL(15,2) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (factura_id) REFERENCES facturas_fletes(id) ON DELETE CASCADE
) ENGINE=InnoDB;