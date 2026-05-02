-- ============================================================
-- Init script — se ejecuta automáticamente al levantar MySQL
-- Orden: tablas de negocio → auth → roles seed
-- ============================================================

-- Tablas de negocio (lab_orders, lab_order_details, lab_results)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS lab_orders (
    id_solicitud_key    VARCHAR(100) PRIMARY KEY,
    id_admision         VARCHAR(100) NOT NULL,
    id_atencion         VARCHAR(100) NULL,
    tipo_documento      VARCHAR(20)  NOT NULL,
    identificacion      VARCHAR(50)  NOT NULL,
    nombre_paciente     VARCHAR(200) NOT NULL,
    sexo                VARCHAR(10)  NOT NULL,
    fecha_nacimiento    DATE         NOT NULL,
    centro_salud        VARCHAR(150) NOT NULL,
    fecha_orden         DATETIME     NOT NULL,
    medico_ordena       VARCHAR(150) NOT NULL,
    numero_autorizacion VARCHAR(100) NULL,
    id_aliado           VARCHAR(100) NULL,
    fecha_envio         DATETIME     NULL,
    porc_ejecucion      DECIMAL(5,2) DEFAULT 0,
    estado_orden        VARCHAR(50)  NOT NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lab_orders_estado (estado_orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_order_details (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key     VARCHAR(100) NOT NULL,
    id_admision          VARCHAR(100) NOT NULL,
    cups                 VARCHAR(30)  NOT NULL,
    nombre_laboratorio   VARCHAR(150) NOT NULL,
    fecha_toma_muestra   DATETIME     NULL,
    metodo               VARCHAR(150) NULL,
    reactivo             VARCHAR(150) NULL,
    invima               VARCHAR(100) NULL,
    estado_resultado     VARCHAR(100) NULL,
    fecha_resultado      DATETIME     NULL,
    tipo_id_bacteriologo VARCHAR(10)  NULL,
    id_bacteriologo      VARCHAR(50)  NULL,
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_detail_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_results (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    cups             VARCHAR(30)  NOT NULL,
    values_json      JSON         NOT NULL,
    attachment_path  VARCHAR(255) NULL,
    received_at      DATETIME     NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auth: roles, aliados, users, user_aliado
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (name) VALUES
    ('admin'),
    ('lab_operator'),
    ('aliado_operator'),
    ('viewer');

CREATE TABLE IF NOT EXISTS aliados (
    id         VARCHAR(100) PRIMARY KEY,
    nombre     VARCHAR(200) NOT NULL,
    activo     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id       INT          NOT NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_aliado (
    user_id   INT          NOT NULL,
    aliado_id VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, aliado_id),
    CONSTRAINT fk_ua_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ua_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

