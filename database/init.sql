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
    bacteriologo_id  INT          NULL,
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
    nit        VARCHAR(20)  NULL,
    direccion  VARCHAR(255) NULL,
    email      VARCHAR(150) NULL,
    logo_path  VARCHAR(255) NULL,
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


-- ============================================================
-- Catálogo de exámenes y parámetros (EAV)
-- ============================================================

CREATE TABLE IF NOT EXISTS exam_types (
    cups        VARCHAR(30)  NOT NULL PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descripcion TEXT         NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_parameters (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cups          VARCHAR(30)   NOT NULL,
    codigo        VARCHAR(50)   NOT NULL,
    nombre        VARCHAR(150)  NOT NULL,
    unidad        VARCHAR(50)   NULL,
    valor_min_ref DECIMAL(10,4) NULL,
    valor_max_ref DECIMAL(10,4) NULL,
    sexo          CHAR(1)       NOT NULL DEFAULT '*',
    edad_min      TINYINT       NULL,
    edad_max      TINYINT       NULL,
    obligatorio   TINYINT(1)    NOT NULL DEFAULT 0,
    orden         SMALLINT      NOT NULL DEFAULT 0,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ep_exam_type FOREIGN KEY (cups) REFERENCES exam_types(cups) ON DELETE CASCADE,
    INDEX idx_ep_cups (cups),
    UNIQUE KEY uq_ep_cups_codigo_sexo_edad (cups, codigo, sexo, edad_min, edad_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_result_values (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    lab_result_id  INT           NOT NULL,
    parameter_id   INT           NOT NULL,
    valor_numerico DECIMAL(10,4) NULL,
    valor_texto    VARCHAR(255)  NULL,
    flag           ENUM('normal','alto','bajo','critico','indeterminado') NOT NULL DEFAULT 'indeterminado',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lrv_result    FOREIGN KEY (lab_result_id) REFERENCES lab_results(id) ON DELETE CASCADE,
    CONSTRAINT fk_lrv_parameter FOREIGN KEY (parameter_id)  REFERENCES exam_parameters(id),
    INDEX idx_lrv_result (lab_result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Centros de salud, pacientes y relaciones
-- ============================================================

CREATE TABLE IF NOT EXISTS health_centers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    ciudad      VARCHAR(100) NULL,
    direccion   VARCHAR(255) NULL,
    telefono    VARCHAR(30)  NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aliado_health_center (
    aliado_id        VARCHAR(100) NOT NULL,
    health_center_id INT          NOT NULL,
    PRIMARY KEY (aliado_id, health_center_id),
    CONSTRAINT fk_ahc_aliado  FOREIGN KEY (aliado_id)        REFERENCES aliados(id)        ON DELETE CASCADE,
    CONSTRAINT fk_ahc_center  FOREIGN KEY (health_center_id) REFERENCES health_centers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patients (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento   VARCHAR(20)  NOT NULL,
    identificacion   VARCHAR(50)  NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    sexo             CHAR(1)      NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    email            VARCHAR(150) NULL,
    telefono         VARCHAR(30)  NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patient_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columnas opcionales en lab_orders (patient_id, health_center_id)
DROP PROCEDURE IF EXISTS sp_add_lab_orders_cols;
DELIMITER //
CREATE PROCEDURE sp_add_lab_orders_cols()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'patient_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN patient_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_patient FOREIGN KEY (patient_id) REFERENCES patients(id);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'health_center_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN health_center_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_health_center FOREIGN KEY (health_center_id) REFERENCES health_centers(id);
    END IF;
END //
DELIMITER ;
CALL sp_add_lab_orders_cols();
DROP PROCEDURE IF EXISTS sp_add_lab_orders_cols;

-- ============================================================
-- Tipos de resultado, rangos por reactivo y flags extendidos
-- ============================================================

-- Columnas tipo_resultado y etiqueta_booleano en exam_parameters
DROP PROCEDURE IF EXISTS sp_add_exam_param_cols;
DELIMITER //
CREATE PROCEDURE sp_add_exam_param_cols()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_parameters' AND COLUMN_NAME = 'tipo_resultado'
    ) THEN
        ALTER TABLE exam_parameters
            ADD COLUMN tipo_resultado ENUM('numerico','texto','booleano') NOT NULL DEFAULT 'numerico' AFTER valor_max_ref;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_parameters' AND COLUMN_NAME = 'etiqueta_booleano'
    ) THEN
        ALTER TABLE exam_parameters
            ADD COLUMN etiqueta_booleano ENUM('normal_alto','positivo_negativo','reactivo_no_reactivo') NULL AFTER tipo_resultado;
    END IF;
END //
DELIMITER ;
CALL sp_add_exam_param_cols();
DROP PROCEDURE IF EXISTS sp_add_exam_param_cols;

-- Rangos de referencia por reactivo
CREATE TABLE IF NOT EXISTS exam_parameter_ranges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    parameter_id  INT           NOT NULL,
    reactivo      VARCHAR(150)  NOT NULL,
    valor_min_ref DECIMAL(10,4) NULL,
    valor_max_ref DECIMAL(10,4) NULL,
    sexo          CHAR(1)       NOT NULL DEFAULT '*',
    edad_min      TINYINT       NULL,
    edad_max      TINYINT       NULL,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_epr_parameter FOREIGN KEY (parameter_id) REFERENCES exam_parameters(id) ON DELETE CASCADE,
    INDEX idx_epr_parameter (parameter_id),
    UNIQUE KEY uq_epr_param_reactivo_sexo_edad (parameter_id, reactivo, sexo, edad_min, edad_max)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columnas valor_booleano y reactivo en lab_result_values
DROP PROCEDURE IF EXISTS sp_add_result_value_cols;
DELIMITER //
CREATE PROCEDURE sp_add_result_value_cols()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_result_values' AND COLUMN_NAME = 'valor_booleano'
    ) THEN
        ALTER TABLE lab_result_values ADD COLUMN valor_booleano TINYINT(1) NULL AFTER valor_texto;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_result_values' AND COLUMN_NAME = 'reactivo'
    ) THEN
        ALTER TABLE lab_result_values ADD COLUMN reactivo VARCHAR(150) NULL AFTER valor_booleano;
    END IF;
END //
DELIMITER ;
CALL sp_add_result_value_cols();
DROP PROCEDURE IF EXISTS sp_add_result_value_cols;

ALTER TABLE lab_result_values
    MODIFY COLUMN flag ENUM(
        'normal','alto','bajo','critico','indeterminado',
        'positivo','negativo','reactivo','no_reactivo'
    ) NOT NULL DEFAULT 'indeterminado';

-- ============================================================
-- Bacteriólogos
-- ============================================================

CREATE TABLE IF NOT EXISTS bacteriologos (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    aliado_id           VARCHAR(100)  NOT NULL,
    tipo_documento      VARCHAR(20)   NOT NULL,
    identificacion      VARCHAR(50)   NOT NULL,
    nombre              VARCHAR(200)  NOT NULL,
    tarjeta_profesional VARCHAR(50)   NULL,
    universidad         VARCHAR(200)  NULL,
    firma_path          VARCHAR(255)  NULL,
    activo              TINYINT(1)    NOT NULL DEFAULT 1,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bact_aliado FOREIGN KEY (aliado_id) REFERENCES aliados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_bact_doc (tipo_documento, identificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FK bacteriologo_id en lab_results (tabla bacteriologos ya existe arriba)
DROP PROCEDURE IF EXISTS sp_add_bact_fk;
DELIMITER //
CREATE PROCEDURE sp_add_bact_fk()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_results' AND CONSTRAINT_NAME = 'fk_lr_bacteriologo'
    ) THEN
        ALTER TABLE lab_results ADD CONSTRAINT fk_lr_bacteriologo FOREIGN KEY (bacteriologo_id) REFERENCES bacteriologos(id);
    END IF;
END //
DELIMITER ;
CALL sp_add_bact_fk();
DROP PROCEDURE IF EXISTS sp_add_bact_fk;

-- ============================================================
-- Log de envíos de email y portal de pacientes
-- ============================================================

CREATE TABLE IF NOT EXISTS result_email_log (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_solicitud_key VARCHAR(100) NOT NULL,
    email_destino    VARCHAR(150) NOT NULL,
    estado           ENUM('enviado','error') NOT NULL,
    error_mensaje    TEXT NULL,
    enviado_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rel_order FOREIGN KEY (id_solicitud_key) REFERENCES lab_orders(id_solicitud_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_access_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT          NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expires_at  DATETIME     NOT NULL,
    usado       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pat_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_pat_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Médicos
-- ============================================================

CREATE TABLE IF NOT EXISTS medicos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento   VARCHAR(20)  NOT NULL,
    identificacion   VARCHAR(50)  NOT NULL,
    nombre           VARCHAR(200) NOT NULL,
    especialidad     VARCHAR(150) NULL,
    registro_medico  VARCHAR(50)  NULL,
    user_id          INT          NULL,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_medico_doc (tipo_documento, identificacion),
    UNIQUE KEY uq_medico_user (user_id),
    CONSTRAINT fk_medico_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columna medico_id en lab_orders
DROP PROCEDURE IF EXISTS sp_add_medico_id;
DELIMITER //
CREATE PROCEDURE sp_add_medico_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lab_orders' AND COLUMN_NAME = 'medico_id'
    ) THEN
        ALTER TABLE lab_orders ADD COLUMN medico_id INT NULL;
        ALTER TABLE lab_orders ADD CONSTRAINT fk_lo_medico FOREIGN KEY (medico_id) REFERENCES medicos(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL sp_add_medico_id();
DROP PROCEDURE IF EXISTS sp_add_medico_id;
