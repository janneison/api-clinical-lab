-- Tabla de usuarios para autenticación (JWT/Api Key almacenados con hash)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    api_key_hash CHAR(64) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_orders (
    id_solicitud_key VARCHAR(100) PRIMARY KEY,
    id_admision VARCHAR(100) NOT NULL,
    id_atencion VARCHAR(100) NULL,
    tipo_documento VARCHAR(20) NOT NULL,
    identificacion VARCHAR(50) NOT NULL,
    nombre_paciente VARCHAR(200) NOT NULL,
    sexo VARCHAR(10) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    centro_salud VARCHAR(150) NOT NULL,
    fecha_orden DATETIME NOT NULL,
    medico_ordena VARCHAR(150) NOT NULL,
    numero_autorizacion VARCHAR(100) NULL,
    id_aliado VARCHAR(100) NULL,
    fecha_envio DATETIME NULL,
    porc_ejecucion DECIMAL(5,2) DEFAULT 0,
    estado_orden VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lab_orders_estado (estado_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    id_admision VARCHAR(100) NOT NULL,
    cups VARCHAR(30) NOT NULL,
    nombre_laboratorio VARCHAR(150) NOT NULL,
    fecha_toma_muestra DATETIME NULL,
    metodo VARCHAR(150) NULL,
    reactivo VARCHAR(150) NULL,
    invima VARCHAR(100) NULL,
    estado_resultado VARCHAR(100) NULL,
    fecha_resultado DATETIME NULL,
    tipo_id_bacteriologo VARCHAR(10) NULL,
    id_bacteriologo VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_detail_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    cups VARCHAR(30) NOT NULL,
    values_json JSON NOT NULL,
    attachment_path VARCHAR(255) NULL,
    received_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
