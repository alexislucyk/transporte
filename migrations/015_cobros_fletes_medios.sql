-- Tabla para múltiples medios de cobro por cobro de flete
CREATE TABLE IF NOT EXISTS cobros_fletes_medios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cobro_id INT NOT NULL,
    medio_cobro VARCHAR(50) NOT NULL,
    importe DECIMAL(12,2) NOT NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cobro_id) REFERENCES cobros_fletes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;